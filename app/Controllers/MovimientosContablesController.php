<?php
namespace App\Controllers;

use Core\Database;
use Core\TenantContext;
use Core\View;
use App\Services\MenuService;
use App\Services\SifenClientService;

class MovimientosContablesController {

    private static function requireAuth(bool $json = true): bool {
        if (!TenantContext::check()) {
            if ($json) {
                View::json(['status' => 'error', 'message' => 'No autenticado', 'error' => 'No autenticado'], 401);
            } else {
                header('Location: /login');
            }
            return false;
        }
        return true;
    }

    private static function renderFormPage(string $template, array $extra = []): void {
        if (!self::requireAuth(false)) return;
        $menu = MenuService::obtenerMenuPorUsuario(TenantContext::usuario(), TenantContext::grupo());
        View::renderTemplate($template, array_merge([
            'usuario'    => TenantContext::usuario(),
            'empresa'    => TenantContext::empresa(),
            'sucursal'   => TenantContext::sucursal(),
            'nomEmpresa' => TenantContext::nomEmpresa(),
            'ejercicio'  => TenantContext::ejercicio(),
            'modulos'    => $menu,
        ], $extra));
    }

    private static function getInput(): array {
        $raw = file_get_contents('php://input');
        $json = $raw ? json_decode($raw, true) : null;
        if (is_array($json)) {
            return array_merge($_GET, $_POST, $json);
        }
        return array_merge($_GET, $_POST);
    }

    private static function parseSqlDate(?string $fec): ?string {
        if (!$fec || trim($fec) === '') return null;
        $f = trim($fec);
        if (str_contains($f, 'T')) $f = explode('T', $f)[0];
        if (str_contains($f, ' ')) $f = explode(' ', $f)[0];
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $f, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) {
            return $f;
        }
        return null;
    }

    public static function formatNroFactura(?string $nroFactura): string {
        if (!$nroFactura || trim($nroFactura) === '') return '';
        $s = trim($nroFactura);
        if (str_contains($s, '-')) {
            $parts = explode('-', $s);
            if (count($parts) === 3) {
                return str_pad(trim($parts[0]), 3, '0', STR_PAD_LEFT) . '-'
                     . str_pad(trim($parts[1]), 3, '0', STR_PAD_LEFT) . '-'
                     . str_pad(trim($parts[2]), 7, '0', STR_PAD_LEFT);
            }
        } elseif (ctype_digit($s)) {
            if (strlen($s) === 13) {
                return substr($s, 0, 3) . '-' . substr($s, 3, 3) . '-' . str_pad(substr($s, 6), 7, '0', STR_PAD_LEFT);
            } elseif (strlen($s) <= 7) {
                return '001-001-' . str_pad($s, 7, '0', STR_PAD_LEFT);
            }
        }
        return $s;
    }

    private static function calcularDvModulo11(string $numero): int {
        $baseMax = 11;
        $k = 2;
        $total = 0;
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $total += ((int)$numero[$i]) * $k;
            $k++;
            if ($k > $baseMax) $k = 2;
        }
        $resto = $total % 11;
        $digit = 11 - $resto;
        return ($digit >= 10) ? 0 : $digit;
    }

    // =========================================================================
    // VISTAS HTML (Egresos, Ingresos, Asientos)
    // =========================================================================
    public static function pageEgresos(): void {
        if (!self::requireAuth(false)) return;
        $idProp = TenantContext::idPropietario();
        $emp = TenantContext::empresa();
        $contribuyenteNombre = trim((string)(Database::queryForValue(
            "SELECT descripcion FROM empresas WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
            [$idProp, $emp]
        ) ?? ''));
        if ($contribuyenteNombre === '') {
            $contribuyenteNombre = TenantContext::nomEmpresa();
        }
        if ($contribuyenteNombre !== '') {
            try {
                Database::update(
                    "UPDATE co_egresos SET comprador = ? WHERE id_propietario = ? AND cod_empresa = ? AND (comprador IS NULL OR TRIM(comprador) = '' OR comprador LIKE '%[[$%')",
                    [$contribuyenteNombre, $idProp, $emp]
                );
            } catch (\Throwable $ignored) {}
        }
        $ej = TenantContext::ejercicio();
        $anioEj = (int)preg_replace('/\D/', '', (string)$ej);
        if ($anioEj < 1990 || $anioEj > 2100) {
            $anioEj = (int)date('Y');
        }
        $mesActual = (int)date('n');
        $defFechaDesde = sprintf('%04d-%02d-01', $anioEj, $mesActual);
        $defFechaHasta = date('Y-m-t', strtotime($defFechaDesde));

        self::renderFormPage('egresos_registro', [
            'contribuyenteNombre' => $contribuyenteNombre,
            'empresaDesc'         => $contribuyenteNombre,
            'defFechaDesde'       => $defFechaDesde,
            'defFechaHasta'       => $defFechaHasta,
        ]);
    }

    public static function pageIngresos(): void {
        self::renderFormPage('ingresos_registro');
    }

    public static function pageAsientos(): void {
        self::renderFormPage('asientos', [
            'nomForma'    => 'COASIENT',
            'tituloForma' => 'Registro de Asientos Contables',
            'currentYear' => (int)date('Y'),
        ]);
    }

    // =========================================================================
    // API SIFEN (Facturación Electrónica DNIT v150 - Microservicio Java / Nativo)
    // =========================================================================
    private static function getEmpresaSifenConfig(int $idProp, string $codEmpresa): array {
        $emp = Database::queryForMap(
            "SELECT cod_empresa, descripcion, ruc_empresa, direccion, actividad, localidad, departamento,
                    ind_facturacion_electronica,
                    COALESCE(sifen_ambiente, 'TEST') AS sifen_ambiente,
                    COALESCE(sifen_id_csc, '0001') AS sifen_id_csc,
                    COALESCE(sifen_csc, '') AS sifen_csc,
                    sifen_certificado_b64, sifen_certificado_nombre, sifen_certificado_pass, sifen_api_url
             FROM empresas
             WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
            [$idProp, $codEmpresa]
        );
        return $emp ?: [];
    }

    public static function apiSifenHealth(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $in = self::getInput();
        $customUrl = trim((string)($in['apiUrl'] ?? $_GET['apiUrl'] ?? ''));
        if ($customUrl === '' && $empresa !== '') {
            $empCfg = self::getEmpresaSifenConfig($idProp, $empresa);
            $customUrl = trim((string)($empCfg['sifen_api_url'] ?? ''));
        }
        View::json(SifenClientService::healthCheck($customUrl ?: null));
    }

    public static function apiSifenConsultarCdc(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $in = self::getInput();
        $cdc = trim((string)($in['cdc'] ?? $_GET['cdc'] ?? ''));
        $empCfg = $empresa !== '' ? self::getEmpresaSifenConfig($idProp, $empresa) : null;
        View::json(SifenClientService::consultarCdc($cdc, $empCfg));
    }

    public static function apiVerificarCdc(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $raw = trim((string)($_GET['cdc'] ?? ''));
        $empCfg = $empresa !== '' ? self::getEmpresaSifenConfig($idProp, $empresa) : null;
        $info = SifenClientService::consultarCdc($raw, $empCfg);

        if (!empty($info['valido'])) {
            View::json([
                'valid'       => true,
                'message'     => 'CDC Válido. Estructura y Dígito Verificador (Mód. 11 SIFEN) verificados correctamente.',
                'dvCalculado' => $info['dvCalculado'] ?? null,
                'data'        => $info,
            ]);
        } else {
            View::json([
                'valid'   => false,
                'message' => $info['message'] ?? 'CDC Inválido.',
                'data'    => $info,
            ]);
        }
    }

    public static function emitirSifenParaIngreso(int $idProp, string $codEmpresa, int $idIngreso): array {
        $ing = Database::queryForMap(
            "SELECT * FROM co_ingresos WHERE id_propietario = ? AND cod_empresa = ? AND id_ingreso = ? LIMIT 1",
            [$idProp, $codEmpresa, $idIngreso]
        );
        if (!$ing) {
            return ['status' => 'error', 'message' => 'Comprobante de ingreso no encontrado.'];
        }

        $empCfg = self::getEmpresaSifenConfig($idProp, $codEmpresa);
        $timbRow = null;
        if (!empty($ing['nro_timbrado'])) {
            $timbRow = Database::queryForMap(
                "SELECT * FROM co_timbrados WHERE id_propietario = ? AND cod_empresa = ? AND nro_timbrado = ? LIMIT 1",
                [$idProp, $codEmpresa, $ing['nro_timbrado']]
            );
        }

        $tipDocStr = strtoupper(trim((string)($ing['tip_doc'] ?? 'FACTURA')));
        $tipoDocCodigo = 1;
        if (str_contains($tipDocStr, 'AUTO')) $tipoDocCodigo = 4;
        elseif (str_contains($tipDocStr, 'CRÉD') || str_contains($tipDocStr, 'CRED')) $tipoDocCodigo = 5;
        elseif (str_contains($tipDocStr, 'DÉB') || str_contains($tipDocStr, 'DEB')) $tipoDocCodigo = 6;

        $nroFacParts = explode('-', (string)($ing['nro_factura'] ?? '001-001-0000001'));
        $est = $nroFacParts[0] ?? ($timbRow['cod_establecimiento'] ?? '001');
        $pun = $nroFacParts[1] ?? ($timbRow['cod_punto_expedicion'] ?? '001');
        $num = $nroFacParts[2] ?? ($nroFacParts[0] ?? '0000001');

        $monCode = (string)($ing['cod_moneda'] ?? '1');
        $monIso = ($monCode === '2') ? 'USD' : (($monCode === '3') ? 'BRL' : 'PYG');

        $reqPayload = [
            'ambiente'                      => $empCfg['sifen_ambiente'] ?? 'TEST',
            'certificadoBase64'             => $empCfg['sifen_certificado_b64'] ?? null,
            'certificadoPassword'           => $empCfg['sifen_certificado_pass'] ?? null,
            'idCsc'                         => $empCfg['sifen_id_csc'] ?? '0001',
            'csc'                           => $empCfg['sifen_csc'] ?? null,
            'rucEmisor'                     => $empCfg['ruc_empresa'] ?? '80000000-0',
            'razonSocialEmisor'             => $empCfg['descripcion'] ?? 'CONTRIBUYENTE EMISOR',
            'nombreFantasia'                => $empCfg['descripcion'] ?? 'CONTRIBUYENTE EMISOR',
            'actividadEconomicaDescripcion' => $empCfg['actividad'] ?? 'ACTIVIDADES CONTABLES Y COMERCIALES',
            'direccionEmisor'               => $empCfg['direccion'] ?? 'ASUNCION',
            'descripcionCiudadEmisor'       => $empCfg['localidad'] ?? 'ASUNCION',
            'descripcionDepartamentoEmisor' => $empCfg['departamento'] ?? 'CAPITAL',
            'timbrado'                      => $ing['nro_timbrado'] ?? ($timbRow['nro_timbrado'] ?? '12345678'),
            'fechaInicioTimbrado'           => !empty($timbRow['fec_inicio']) ? substr((string)$timbRow['fec_inicio'], 0, 10) : '2025-01-01',
            'establecimiento'               => $est,
            'puntoExpedicion'               => $pun,
            'numeroDocumento'               => $num,
            'serie'                         => $ing['serie'] ?? null,
            'tipoDocumento'                 => $tipoDocCodigo,
            'fechaEmision'                  => !empty($ing['fec_ingreso']) ? substr((string)$ing['fec_ingreso'], 0, 10) . 'T' . date('H:i:s') : date('Y-m-d\TH:i:s'),
            'condicion'                     => $ing['condicion'] ?? 'CON',
            'moneda'                        => $monIso,
            'tasaCambio'                    => (float)($ing['tasa_cambio'] ?? 1),
            'rucCliente'                    => $ing['ruc_cliente'] ?? '44444401-7',
            'razonSocialCliente'            => $ing['nom_cliente'] ?? 'SIN NOMBRE',
            'grav10'                        => (float)($ing['grav10'] ?? 0),
            'iva10'                         => (float)($ing['iva10'] ?? 0),
            'grav5'                         => (float)($ing['grav5'] ?? 0),
            'iva5'                          => (float)($ing['iva5'] ?? 0),
            'exentas'                       => (float)($ing['exentas'] ?? 0),
            'observacion'                   => $ing['observacion'] ?? 'VENTA DE MERCADERÍAS / SERVICIOS',
        ];

        $sifenRes = SifenClientService::emitirDocumento($reqPayload, $empCfg['sifen_api_url'] ?? null);
        if (($sifenRes['status'] ?? '') === 'success' && !empty($sifenRes['cdc'])) {
            Database::update(
                "UPDATE co_ingresos SET
                    cdc = ?,
                    sifen_estado = ?,
                    sifen_protocolo = ?,
                    sifen_qr_url = ?,
                    sifen_xml = ?,
                    sifen_mensaje = ?,
                    sifen_fecha_firma = NOW()
                 WHERE id_propietario = ? AND cod_empresa = ? AND id_ingreso = ?",
                [
                    $sifenRes['cdc'],
                    $sifenRes['estadoSifen'] ?? 'EMITIDO',
                    $sifenRes['nroProtocolo'] ?? null,
                    $sifenRes['qrUrl'] ?? null,
                    $sifenRes['xmlFirmado'] ?? null,
                    substr((string)($sifenRes['mensajeRespuesta'] ?? ''), 0, 250),
                    $idProp,
                    $codEmpresa,
                    $idIngreso
                ]
            );
        }
        return $sifenRes;
    }

    public static function apiSifenEmitir(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $in = self::getInput();
        $idIngreso = (int)($in['idIngreso'] ?? 0);

        if ($idIngreso <= 0) {
            View::json(['status' => 'error', 'message' => 'Debe indicar el ID del comprobante de ingreso para emitir en SIFEN.']);
            return;
        }
        $res = self::emitirSifenParaIngreso($idProp, $empresa, $idIngreso);
        View::json($res);
    }

    public static function apiSifenCancelar(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $in = self::getInput();
        $idIngreso = (int)($in['idIngreso'] ?? 0);
        $cdc = preg_replace('/\D/', '', (string)($in['cdc'] ?? ''));
        $motivo = trim((string)($in['motivo'] ?? 'Cancelación de comprobante electrónico por error en emisión'));

        if ($idIngreso > 0 && $cdc === '') {
            $cdc = (string)(Database::queryForValue(
                "SELECT cdc FROM co_ingresos WHERE id_propietario = ? AND cod_empresa = ? AND id_ingreso = ?",
                [$idProp, $empresa, $idIngreso]
            ) ?? '');
        }
        if (strlen($cdc) !== 44) {
            View::json(['status' => 'error', 'message' => 'El comprobante debe contar con un CDC válido de 44 dígitos para cancelarse en SIFEN.']);
            return;
        }

        $empCfg = self::getEmpresaSifenConfig($idProp, $empresa);
        $res = SifenClientService::cancelarDocumento([
            'ambiente'            => $empCfg['sifen_ambiente'] ?? 'TEST',
            'certificadoBase64'   => $empCfg['sifen_certificado_b64'] ?? null,
            'certificadoPassword' => $empCfg['sifen_certificado_pass'] ?? null,
            'rucEmisor'           => $empCfg['ruc_empresa'] ?? '',
            'cdc'                 => $cdc,
            'motivo'              => $motivo,
        ], $empCfg['sifen_api_url'] ?? null);

        if ($idIngreso > 0 && ($res['status'] ?? '') === 'success') {
            Database::update(
                "UPDATE co_ingresos SET
                    anulada = 'S',
                    sifen_estado = 'CANCELADO',
                    sifen_mensaje = ?
                 WHERE id_propietario = ? AND cod_empresa = ? AND id_ingreso = ?",
                [substr((string)($res['mensajeRespuesta'] ?? 'Cancelado en SIFEN'), 0, 250), $idProp, $empresa, $idIngreso]
            );
        }
        View::json($res);
    }

    public static function apiSifenDescargarXml(string $idIngreso): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $id = (int)$idIngreso;

        $row = Database::queryForMap(
            "SELECT cdc, sifen_xml FROM co_ingresos WHERE id_propietario = ? AND cod_empresa = ? AND id_ingreso = ? LIMIT 1",
            [$idProp, $empresa, $id]
        );
        if (!$row) {
            http_response_code(404);
            echo 'Comprobante no encontrado.';
            return;
        }
        if (empty($row['sifen_xml'])) {
            $gen = self::emitirSifenParaIngreso($idProp, $empresa, $id);
            $row['sifen_xml'] = $gen['xmlFirmado'] ?? '';
            $row['cdc']       = $gen['cdc'] ?? ($row['cdc'] ?? 'DE');
        }
        $cdcName = !empty($row['cdc']) ? preg_replace('/\D/', '', (string)$row['cdc']) : ('INGRESO_' . $id);
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="DE_' . $cdcName . '.xml"');
        echo (string)$row['sifen_xml'];
    }

    public static function pagePrintKude(): void {
        if (!self::requireAuth(false)) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $idIngreso = (int)($_GET['id'] ?? $_GET['idIngreso'] ?? 0);

        $ing = Database::queryForMap(
            "SELECT * FROM co_ingresos WHERE id_propietario = ? AND cod_empresa = ? AND id_ingreso = ? LIMIT 1",
            [$idProp, $empresa, $idIngreso]
        );
        if (!$ing) {
            http_response_code(404);
            echo '<h3 style="font-family:sans-serif;text-align:center;margin-top:50px;">Comprobante no encontrado</h3>';
            return;
        }

        // Si aún no tiene CDC o QR generado, generarlo en el acto para el KuDE
        if (empty($ing['cdc']) || empty($ing['sifen_qr_url'])) {
            self::emitirSifenParaIngreso($idProp, $empresa, $idIngreso);
            $ing = Database::queryForMap(
                "SELECT * FROM co_ingresos WHERE id_propietario = ? AND cod_empresa = ? AND id_ingreso = ? LIMIT 1",
                [$idProp, $empresa, $idIngreso]
            );
        }

        $empCfg = self::getEmpresaSifenConfig($idProp, $empresa);
        $timbRow = null;
        if (!empty($ing['nro_timbrado'])) {
            $timbRow = Database::queryForMap(
                "SELECT * FROM co_timbrados WHERE id_propietario = ? AND cod_empresa = ? AND nro_timbrado = ? LIMIT 1",
                [$idProp, $empresa, $ing['nro_timbrado']]
            );
        }

        View::render('kude_print', [
            'ingreso'  => $ing,
            'empresa'  => $empCfg,
            'timbrado' => $timbRow ?: [],
        ]);
    }

    // =========================================================================
    // API EGRESOS (/api/egresos/*)
    // =========================================================================
    private static function resolveTipoEgresoId(?string $val, int $idProp): string {
        if (!$val || trim($val) === '' || strcasecmp(trim($val), 'null') === 0) return '1';
        $v = trim($val);
        if (is_numeric($v)) return (string)(int)round((float)$v);
        $up = strtoupper($v);
        if (str_contains($up, 'GASTOS GENERALES')) return '1';
        if (str_contains($up, 'MERCADER')) return '2';
        if (str_contains($up, 'COMBUSTIBLE')) return '3';
        if (str_contains($up, 'SERVICIO') || str_contains($up, 'LUZ') || str_contains($up, 'AGUA')) return '4';
        if (str_contains($up, 'ALQUILER')) return '5';
        if (str_contains($up, 'HONORARIO')) return '6';

        $id = Database::queryForValue(
            "SELECT cod_tipo FROM co_tipos_egreso WHERE id_propietario IN (?, 1) AND UPPER(TRIM(descripcion)) = ? LIMIT 1",
            [$idProp, $up]
        );
        return $id !== null ? (string)(int)round((float)$id) : '1';
    }

    private static function resolveClasificacionGastoId(?string $val, int $idProp): string {
        if (!$val || trim($val) === '' || strcasecmp(trim($val), 'null') === 0) return '1';
        $v = trim($val);
        if (is_numeric($v)) return (string)(int)round((float)$v);
        $up = strtoupper($v);
        if (str_contains($up, 'RELACIONADA')) return '2';
        if (str_contains($up, 'INVERSIONES PERSONAL') || str_contains($up, 'INVERSIONES DE FAMILIAR') || str_contains($up, 'INVERSIONES Y DE FAMILIAR')) return '3';
        if (str_contains($up, 'GASTOS PERSONAL') || str_contains($up, 'GASTOS FAMILIAR')) return '4';
        if ($up === 'GASTO') return '1';

        $id = Database::queryForValue(
            "SELECT cod_clasificacion FROM co_clasificacion_gasto WHERE id_propietario IN (?, 1) AND UPPER(TRIM(descripcion)) = ? LIMIT 1",
            [$idProp, $up]
        );
        return $id !== null ? (string)(int)round((float)$id) : '1';
    }

    private static function resolveRegimenId(?string $val, int $idProp): int {
        if (!$val || trim($val) === '' || strcasecmp(trim($val), 'null') === 0) return 1;
        $v = trim($val);
        if (is_numeric($v)) return (int)round((float)$v);
        $up = strtoupper($v);
        if (str_contains($up, 'IRP') && str_contains($up, 'IVA')) return 1;
        if (str_contains($up, 'IRP')) return 2;
        if (str_contains($up, 'IVA')) return 3;
        if (str_contains($up, 'CAPITAL')) return 4;

        $id = Database::queryForValue(
            "SELECT cod_regimen FROM co_regimen WHERE id_propietario IN (?, 1) AND UPPER(TRIM(descripcion)) = ? LIMIT 1",
            [$idProp, $up]
        );
        return $id !== null ? (int)round((float)$id) : 1;
    }

    public static function apiEgresosList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $anio       = trim((string)($_GET['anio'] ?? ''));
        $mes        = trim((string)($_GET['mes'] ?? ''));
        $fechaDesde = self::parseSqlDate((string)($_GET['fechaDesde'] ?? $_GET['desde'] ?? ''));
        $fechaHasta = self::parseSqlDate((string)($_GET['fechaHasta'] ?? $_GET['hasta'] ?? ''));

        try {
            $sql = "SELECT id_egreso, cdc, tip_doc, tip_timb, tip_egreso, clasificacion_gasto,
                           CAST(COALESCE(NULLIF(cod_regimen, 0), 1) AS UNSIGNED) AS cod_regimen,
                           COALESCE(NULLIF(TRIM(regimen), ''), CAST(CAST(COALESCE(NULLIF(cod_regimen, 0), 1) AS UNSIGNED) AS CHAR)) AS regimen,
                           nro_timbrado,
                           DATE_FORMAT(venc_timbrado, '%Y-%m-%d') AS venc_timbrado,
                           DATE_FORMAT(venc_timbrado, '%d/%m/%Y') AS venc_timbrado_fmt,
                           nro_factura, cod_proveedor,
                           DATE_FORMAT(fec_egreso, '%d/%m/%Y') AS fec_egreso_fmt,
                           DATE_FORMAT(fec_egreso, '%Y-%m-%d') AS fec_egreso,
                           condicion, cod_moneda, nom_proveedor, ruc_proveedor,
                           COALESCE(exentas, 0) AS exentas, COALESCE(grav5, 0) AS grav5, COALESCE(iva5, 0) AS iva5,
                           COALESCE(grav10, 0) AS grav10, COALESCE(iva10, 0) AS iva10, COALESCE(tot_iva, 0) AS tot_iva,
                           COALESCE(valor_total, 0) AS valor_total, estado
                    FROM co_egresos
                    WHERE id_propietario = ? AND cod_empresa = ? ";
            $params = [$idProp, $empresa];

            if ($fechaDesde !== null) {
                $sql .= "AND DATE(fec_egreso) >= ? ";
                $params[] = $fechaDesde;
            }
            if ($fechaHasta !== null) {
                $sql .= "AND DATE(fec_egreso) <= ? ";
                $params[] = $fechaHasta;
            }
            if ($fechaDesde === null && $fechaHasta === null) {
                if ($anio !== '' && strcasecmp($anio, 'TODOS') !== 0) {
                    $sql .= "AND YEAR(fec_egreso) = ? ";
                    $params[] = (int)$anio;
                }
                if ($mes !== '' && strcasecmp($mes, 'TODOS') !== 0) {
                    $sql .= "AND MONTH(fec_egreso) = ? ";
                    $params[] = (int)$mes;
                }
            }
            $sql .= "ORDER BY fec_egreso DESC, id_egreso DESC";

            $list = Database::queryForListUpper($sql, $params);
            View::json(['status' => 'success', 'data' => $list]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al listar egresos: ' . $e->getMessage()]);
        }
    }

    public static function apiEgresosGet(string $id): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();

        try {
            $sql = "SELECT id_egreso, cdc, tip_doc,
                           CAST(COALESCE(NULLIF(cod_regimen, 0), 1) AS UNSIGNED) AS cod_regimen,
                           COALESCE(NULLIF(TRIM(regimen), ''), CAST(CAST(COALESCE(NULLIF(cod_regimen, 0), 1) AS UNSIGNED) AS CHAR)) AS regimen,
                           clasificacion_gasto, tip_timb, nro_timbrado,
                           DATE_FORMAT(venc_timbrado, '%Y-%m-%d') AS venc_timbrado, nro_factura, serie,
                           DATE_FORMAT(fec_egreso, '%Y-%m-%d') AS fec_egreso, comprador, cod_proveedor,
                           nom_proveedor, ruc_proveedor, tip_egreso, form_120, condicion, form_145,
                           cod_moneda, tasa_cambio, grav10, iva10, grav5, iva5, exentas, tot_iva,
                           valor_total, observacion
                    FROM co_egresos
                    WHERE id_propietario = ? AND id_egreso = ? AND cod_empresa = ? LIMIT 1";
            $list = Database::queryForListUpper($sql, [$idProp, (int)$id, $empresa]);
            if (!empty($list)) {
                $row = $list[0];
                $nomEmp = trim((string)(Database::queryForValue(
                    "SELECT descripcion FROM empresas WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
                    [$idProp, $empresa]
                ) ?? ''));
                if ($nomEmp === '') {
                    $nomEmp = TenantContext::nomEmpresa();
                }
                if ($nomEmp !== '') {
                    $row['COMPRADOR'] = $nomEmp;
                    $row['CONTRIBUYENTE_NOMBRE'] = $nomEmp;
                }
                View::json(['status' => 'success', 'data' => $row]);
            } else {
                View::json(['status' => 'error', 'message' => 'Egreso no encontrado.']);
            }
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al obtener egreso: ' . $e->getMessage()]);
        }
    }

    public static function apiEgresosSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();

        try {
            $cdc       = isset($payload['cdc']) ? trim((string)$payload['cdc']) : null;
            $tipDoc    = self::resolveTipoDocCode((string)($payload['tipDoc'] ?? 'FAC'));
            $rawReg    = (string)($payload['codRegimen'] ?? $payload['regimen'] ?? '1');
            $codRegimen = self::resolveRegimenId($rawReg, $idProp);
            $regimen   = (string)$codRegimen;
            $clasificacionGasto = self::resolveClasificacionGastoId((string)($payload['clasificacionGasto'] ?? '1'), $idProp);
            $tipTimb   = trim((string)($payload['tipTimb'] ?? 'Pre-impreso')) ?: 'Pre-impreso';
            $nroTimbrado = isset($payload['nroTimbrado']) ? trim((string)$payload['nroTimbrado']) : null;
            $vencTimbrado = self::parseSqlDate((string)($payload['vencTimbrado'] ?? ''));
            if (stripos($tipTimb, 'electr') !== false && !$vencTimbrado) {
                $vencTimbrado = '2100-12-31';
            }
            $nroFactura = self::formatNroFactura((string)($payload['nroFactura'] ?? ''));
            $serie      = isset($payload['serie']) ? trim((string)$payload['serie']) : null;
            $fecEgreso  = self::parseSqlDate((string)($payload['fecEgreso'] ?? ''));

            if ($fecEgreso && $vencTimbrado && $fecEgreso > $vencTimbrado) {
                $f1 = date('d/m/Y', strtotime($fecEgreso));
                $f2 = date('d/m/Y', strtotime($vencTimbrado));
                View::json([
                    'status'  => 'error',
                    'message' => "La fecha del comprobante ({$f1}) no puede ser mayor a la fecha de vencimiento del timbrado ({$f2}).",
                ]);
                return;
            }

            $comprador = trim((string)($payload['comprador'] ?? ''));
            if ($comprador === '' || str_contains($comprador, '[[${') || str_contains(strtoupper($comprador), 'CONTRIBUYENTENOMBRE')) {
                $comprador = (string)(Database::queryForValue(
                    "SELECT descripcion FROM empresas WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
                    [$idProp, $empresa]
                ) ?? '');
                if (trim($comprador) === '') {
                    $comprador = TenantContext::nomEmpresa();
                }
            }

            $codProveedor = trim((string)($payload['codProveedor'] ?? ''));
            $nomProveedor = trim((string)($payload['nomProveedor'] ?? ''));
            $rucProveedor = trim((string)($payload['rucProveedor'] ?? ''));

            if ($codProveedor === '' || strcasecmp($codProveedor, 'null') === 0) {
                if ($rucProveedor !== '') {
                    $rucNoDash = str_replace(['-', ' '], '', $rucProveedor);
                    $foundProv = Database::queryForValue(
                        "SELECT cod_proveedor FROM co_proveedores
                         WHERE id_propietario = ?
                           AND (UPPER(TRIM(ruc)) = UPPER(?) OR UPPER(REPLACE(ruc, '-', '')) = UPPER(?))
                         ORDER BY CASE WHEN cod_empresa = ? THEN 1 ELSE 2 END, CAST(cod_proveedor AS UNSIGNED) DESC
                         LIMIT 1",
                        [$idProp, $rucProveedor, $rucNoDash, $empresa]
                    );
                    if ($foundProv !== null) {
                        $codProveedor = (string)$foundProv;
                    }
                }
            }

            $tipEgreso = self::resolveTipoEgresoId((string)($payload['tipEgreso'] ?? '1'), $idProp);
            $form120   = trim((string)($payload['form120'] ?? 'Directo')) ?: 'Directo';
            $rawCond   = strtoupper(trim((string)($payload['condicion'] ?? 'CON')));
            $condicion = (str_starts_with($rawCond, 'CR') || $rawCond === 'CRE') ? 'CRE' : 'CON';
            $form145   = trim((string)($payload['form145'] ?? 'Original')) ?: 'Original';

            $rawMoneda = strtoupper(trim((string)($payload['codMoneda'] ?? '1')));
            if (str_contains($rawMoneda, 'DOLAR') || str_contains($rawMoneda, 'DÓLAR') || $rawMoneda === '2' || $rawMoneda === 'USD') {
                $codMoneda = '2';
            } elseif (str_contains($rawMoneda, 'REAL') || $rawMoneda === '3' || $rawMoneda === 'BRL') {
                $codMoneda = '3';
            } elseif (ctype_digit($rawMoneda)) {
                $codMoneda = $rawMoneda;
            } else {
                $codMoneda = '1';
            }

            $tasaCambio = (float)($payload['tasaCambio'] ?? 1);
            $grav10     = (float)($payload['grav10'] ?? 0);
            $iva10      = (float)($payload['iva10'] ?? 0);
            $grav5      = (float)($payload['grav5'] ?? 0);
            $iva5       = (float)($payload['iva5'] ?? 0);
            $exentas    = (float)($payload['exentas'] ?? 0);
            $totIva     = (float)($payload['totIva'] ?? 0);
            $valorTotal = (float)($payload['valorTotal'] ?? 0);
            $observacion = isset($payload['observacion']) ? trim((string)$payload['observacion']) : null;

            $idEgreso = !empty($payload['idEgreso']) ? (int)$payload['idEgreso'] : null;

            // Validar comprobante duplicado
            if ($nroFactura !== '' && $nroTimbrado && $nroTimbrado !== '') {
                $rucClean = strtoupper(str_replace(['-', ' '], '', $rucProveedor));
                $facClean = str_replace(['-', ' '], '', $nroFactura);

                $dupSql = "SELECT COUNT(*) FROM co_egresos
                           WHERE id_propietario = ? AND cod_empresa = ?
                             AND UPPER(TRIM(tip_doc)) = UPPER(TRIM(?))
                             AND TRIM(nro_timbrado) = TRIM(?)
                             AND (REPLACE(nro_factura, '-', '') = ? OR TRIM(nro_factura) = ?) ";
                $dupParams = [$idProp, $empresa, $tipDoc, $nroTimbrado, $facClean, $nroFactura];

                if ($rucClean !== '' || $codProveedor !== '') {
                    $dupSql .= "AND (";
                    $hasRuc = false;
                    if ($rucClean !== '') {
                        $dupSql .= "UPPER(REPLACE(TRIM(ruc_proveedor), '-', '')) = ? ";
                        $dupParams[] = $rucClean;
                        $hasRuc = true;
                    }
                    if ($codProveedor !== '') {
                        if ($hasRuc) $dupSql .= "OR ";
                        $dupSql .= "cod_proveedor = ? ";
                        $dupParams[] = $codProveedor;
                    }
                    $dupSql .= ") ";
                }
                if ($idEgreso !== null) {
                    $dupSql .= "AND id_egreso <> ? ";
                    $dupParams[] = $idEgreso;
                }
                $countDup = (int)Database::queryForValue($dupSql, $dupParams);
                if ($countDup > 0) {
                    $provDesc = $nomProveedor !== '' ? $nomProveedor : ($rucProveedor ?: 'este proveedor');
                    View::json([
                        'status'  => 'error',
                        'message' => "El comprobante ya se encuentra registrado: Ya existe una " . strtoupper($tipDoc)
                                   . " con Factura N° {$nroFactura} y Timbrado {$nroTimbrado} para el proveedor {$provDesc}.",
                    ]);
                    return;
                }
            }

            $debeList  = is_array($payload['asientoDebe'] ?? null) ? $payload['asientoDebe'] : [];
            $haberList = is_array($payload['asientoHaber'] ?? null) ? $payload['asientoHaber'] : [];

            if ($idEgreso === null) {
                $maxId = (int)Database::queryForValue(
                    "SELECT COALESCE(MAX(id_egreso), 0) + 1 FROM co_egresos"
                );
                Database::update(
                    "INSERT INTO co_egresos (
                        id_propietario, id_egreso, cod_empresa, cdc, tip_doc, cod_regimen, regimen,
                        clasificacion_gasto, tip_timb, nro_timbrado, venc_timbrado, nro_factura, serie,
                        fec_egreso, comprador, cod_proveedor, nom_proveedor, ruc_proveedor, tip_egreso,
                        form_120, condicion, form_145, cod_moneda, tasa_cambio, grav10, iva10, grav5,
                        iva5, exentas, tot_iva, valor_total, observacion, estado, usuario_reg
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'A', ?)",
                    [
                        $idProp, $maxId, $empresa, $cdc, $tipDoc, $codRegimen, $regimen,
                        $clasificacionGasto, $tipTimb, $nroTimbrado, $vencTimbrado, $nroFactura, $serie,
                        $fecEgreso, $comprador, $codProveedor ?: null, $nomProveedor ?: null, $rucProveedor ?: null, $tipEgreso,
                        $form120, $condicion, $form145, $codMoneda, $tasaCambio, $grav10, $iva10, $grav5,
                        $iva5, $exentas, $totIva, $valorTotal, $observacion, $usuario
                    ]
                );
                self::registrarAsientoContableEgreso($idProp, $maxId, $empresa, $usuario, $fecEgreso, $nroFactura, $nomProveedor, $debeList, $haberList);

                View::json([
                    'status'   => 'success',
                    'message'  => "Egreso registrado correctamente con el ID #{$maxId}",
                    'idEgreso' => $maxId,
                ]);
            } else {
                Database::update(
                    "UPDATE co_egresos SET
                        cdc = ?, tip_doc = ?, cod_regimen = ?, regimen = ?, clasificacion_gasto = ?, tip_timb = ?,
                        nro_timbrado = ?, venc_timbrado = ?, nro_factura = ?, serie = ?, fec_egreso = ?,
                        comprador = ?, cod_proveedor = ?, nom_proveedor = ?, ruc_proveedor = ?, tip_egreso = ?,
                        form_120 = ?, condicion = ?, form_145 = ?, cod_moneda = ?, tasa_cambio = ?,
                        grav10 = ?, iva10 = ?, grav5 = ?, iva5 = ?, exentas = ?, tot_iva = ?, valor_total = ?, observacion = ?
                     WHERE id_propietario = ? AND id_egreso = ? AND cod_empresa = ?",
                    [
                        $cdc, $tipDoc, $codRegimen, $regimen, $clasificacionGasto, $tipTimb,
                        $nroTimbrado, $vencTimbrado, $nroFactura, $serie, $fecEgreso,
                        $comprador, $codProveedor ?: null, $nomProveedor ?: null, $rucProveedor ?: null, $tipEgreso,
                        $form120, $condicion, $form145, $codMoneda, $tasaCambio,
                        $grav10, $iva10, $grav5, $iva5, $exentas, $totIva, $valorTotal, $observacion,
                        $idProp, $idEgreso, $empresa
                    ]
                );
                self::registrarAsientoContableEgreso($idProp, $idEgreso, $empresa, $usuario, $fecEgreso, $nroFactura, $nomProveedor, $debeList, $haberList);

                View::json([
                    'status'   => 'success',
                    'message'  => "Registro de Egreso #{$idEgreso} actualizado con éxito.",
                    'idEgreso' => $idEgreso,
                ]);
            }
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar el egreso: ' . $e->getMessage()]);
        }
    }

    private static function registrarAsientoContableEgreso(
        int $idProp, int $idEgreso, string $empresa, string $usuario,
        ?string $fecEgreso, string $nroFactura, string $nomProveedor,
        array $debeList, array $haberList
    ): void {
        try {
            $existing = Database::queryForList(
                "SELECT num_asiento FROM co_asientos_cab
                 WHERE id_propietario = ? AND cod_empresa = ? AND origen_comprobante = 'COEGRES' AND id_comprobante = ?",
                [$idProp, $empresa, (string)$idEgreso]
            );
            foreach ($existing as $row) {
                $num = (int)$row['num_asiento'];
                Database::update("DELETE FROM co_asientos_det WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?", [$idProp, $empresa, $num]);
                Database::update("DELETE FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?", [$idProp, $empresa, $num]);
            }

            if (empty($debeList) && empty($haberList)) return;

            $numAsiento = (int)Database::queryForValue(
                "SELECT COALESCE(MAX(num_asiento), 0) + 1 FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ?",
                [$idProp, $empresa]
            );
            $fecUse = $fecEgreso ?: date('Y-m-d');
            $ejercicio = (int)substr($fecUse, 0, 4);

            $totalDebe  = 0.0;
            $totalHaber = 0.0;
            $detRows    = [];
            $secuencia  = 1;
            $descBase   = "Factura N° {$nroFactura} - {$nomProveedor}";

            foreach ($debeList as $d) {
                $fullCta = trim((string)($d['cuenta'] ?? ''));
                if ($fullCta === '') continue;
                $codCta = $fullCta;
                $nomCta = $fullCta;
                if (str_contains($fullCta, ' - ')) {
                    [$codCta, $nomCta] = array_map('trim', explode(' - ', $fullCta, 2));
                }
                $montoDebe = (float)($d['debe'] ?? 0);
                $totalDebe += $montoDebe;
                $detRows[] = [$idProp, $empresa, $numAsiento, $secuencia++, $codCta, $nomCta, $descBase, $montoDebe, 0.0];
            }

            foreach ($haberList as $h) {
                $fullCta = trim((string)($h['cuenta'] ?? ''));
                if ($fullCta === '') continue;
                $codCta = $fullCta;
                $nomCta = $fullCta;
                if (str_contains($fullCta, ' - ')) {
                    [$codCta, $nomCta] = array_map('trim', explode(' - ', $fullCta, 2));
                }
                $montoHaber = (float)($h['haber'] ?? 0);
                $totalHaber += $montoHaber;
                $detRows[] = [$idProp, $empresa, $numAsiento, $secuencia++, $codCta, $nomCta, $descBase, 0.0, $montoHaber];
            }

            Database::update(
                "INSERT INTO co_asientos_cab (
                    id_propietario, cod_empresa, num_asiento, fec_asiento, ejercicio, cod_sucursal,
                    origen_comprobante, id_comprobante, concepto, total_debe, total_haber, estado, usuario_reg
                 ) VALUES (?, ?, ?, ?, ?, '1', 'COEGRES', ?, ?, ?, ?, 'A', ?)",
                [$idProp, $empresa, $numAsiento, $fecUse, $ejercicio, (string)$idEgreso, $descBase, $totalDebe, $totalHaber, $usuario]
            );

            foreach ($detRows as $r) {
                Database::update(
                    "INSERT INTO co_asientos_det (
                        id_propietario, cod_empresa, num_asiento, secuencia, cod_cuenta, nom_cuenta, descripcion, debe, haber
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    $r
                );
            }
        } catch (\Throwable $ignored) {}
    }

    public static function apiEgresosDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $payload = self::getInput();
        $idEgreso = (int)($payload['idEgreso'] ?? 0);

        try {
            Database::update(
                "DELETE FROM co_egresos WHERE id_propietario = ? AND id_egreso = ? AND cod_empresa = ?",
                [$idProp, $idEgreso, $empresa]
            );
            View::json(['status' => 'success', 'message' => "Egreso #{$idEgreso} eliminado con éxito."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar egreso: ' . $e->getMessage()]);
        }
    }

    private static function resolveTipoDocCode(?string $val): string {
        if (!$val || trim($val) === '') return 'FAC';
        $u = strtoupper(trim($val));
        if ($u === 'FAC' || str_contains($u, 'FACTURA')) {
            if (str_contains($u, 'AUTO')) return 'AUT';
            return 'FAC';
        }
        if ($u === 'NCR' || str_contains($u, 'CRED') || str_contains($u, 'CRÉD')) return 'NCR';
        if ($u === 'DEB' || str_contains($u, 'DEB') || str_contains($u, 'DÉB')) return 'DEB';
        if ($u === 'AUT' || str_contains($u, 'AUTO')) return 'AUT';
        if ($u === 'BOL' || str_contains($u, 'BOLETA') || str_contains($u, 'RESPALDO')) return 'BOL';
        return substr($u, 0, 10);
    }

    private static function normalizarRucConDv(string $rawRuc, string $cdc = ''): string {
        $r = strtoupper(trim($rawRuc));
        if ($r === '') return '';
        if (str_contains($r, '-')) {
            $parts = explode('-', $r, 2);
            $base = preg_replace('/\D/', '', $parts[0]);
            $dv   = trim($parts[1]);
            if ($base !== '' && $dv !== '') {
                return $base . '-' . $dv;
            }
        }
        $baseDigits = preg_replace('/\D/', '', $r);
        if ($baseDigits === '') return $r;

        $cdcDigits = preg_replace('/\D/', '', $cdc);
        if (strlen($cdcDigits) === 44) {
            $cdcRuc = ltrim(substr($cdcDigits, 2, 8), '0');
            $cdcDv  = substr($cdcDigits, 10, 1);
            if ($cdcRuc !== '' && $cdcRuc === ltrim($baseDigits, '0')) {
                return $baseDigits . '-' . $cdcDv;
            }
        }

        $dvCalc = \App\Services\PersonaService::calcularDvParaguay($baseDigits);
        return $baseDigits . '-' . $dvCalc;
    }

    public static function apiEgresosValidarImportacion(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();

        try {
            // 1. Obtener comprobantes existentes de esta empresa para detectar duplicados y mostrar cuál es
            $egresosRows = Database::queryForList(
                "SELECT id_egreso, TRIM(COALESCE(cdc, '')) AS cdc,
                        UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) AS tip_doc,
                        TRIM(COALESCE(nro_timbrado, '')) AS nro_timbrado,
                        REPLACE(TRIM(COALESCE(nro_factura, '')), '-', '') AS fac_clean,
                        TRIM(COALESCE(nro_factura, '')) AS nro_factura,
                        DATE_FORMAT(fec_egreso, '%d/%m/%Y') AS fec_egreso_fmt,
                        TRIM(COALESCE(nom_proveedor, '')) AS nom_proveedor,
                        TRIM(COALESCE(ruc_proveedor, '')) AS ruc_proveedor,
                        COALESCE(valor_total, 0) AS valor_total,
                        UPPER(TRIM(SUBSTRING_INDEX(REPLACE(COALESCE(ruc_proveedor, ''), ' ', ''), '-', 1))) AS ruc_base
                 FROM co_egresos
                 WHERE id_propietario = ? AND cod_empresa = ?",
                [$idProp, $empresa]
            );

            $existingCdcs = [];
            $existingKeys = [];
            foreach ($egresosRows as $er) {
                $info = [
                    'idEgreso'     => (int)$er['id_egreso'],
                    'nroFactura'   => (string)$er['nro_factura'],
                    'nroTimbrado'  => (string)$er['nro_timbrado'],
                    'fecEgreso'    => (string)($er['fec_egreso_fmt'] ?? ''),
                    'nomProveedor' => (string)$er['nom_proveedor'],
                    'rucProveedor' => (string)$er['ruc_proveedor'],
                    'valorTotal'   => (float)$er['valor_total'],
                ];
                $c = preg_replace('/\D/', '', (string)$er['cdc']);
                if (strlen($c) === 44) {
                    $existingCdcs[$c] = $info;
                }
                $facClean = (string)$er['fac_clean'];
                $timb = (string)$er['nro_timbrado'];
                $rucB = ltrim((string)$er['ruc_base'], '0');
                if ($facClean !== '' && $timb !== '') {
                    $key1 = $timb . '|' . $facClean . '|' . $rucB;
                    $existingKeys[$key1] = $info;
                    $key2 = $timb . '|' . $facClean;
                    if (!isset($existingKeys[$key2])) {
                        $existingKeys[$key2] = $info;
                    }
                }
            }

            // 2. Obtener proveedores existentes
            $provRows = Database::queryForList(
                "SELECT cod_proveedor, nombre, ruc
                 FROM co_proveedores
                 WHERE id_propietario = ? AND cod_empresa = ?",
                [$idProp, $empresa]
            );
            $proveedoresMap = [];
            foreach ($provRows as $pr) {
                $rawR = trim((string)($pr['ruc'] ?? ''));
                if ($rawR === '') continue;
                $baseR = ltrim(strtoupper(trim(explode('-', $rawR)[0])), '0');
                if ($baseR !== '') {
                    $proveedoresMap[$baseR] = [
                        'codProveedor' => (string)$pr['cod_proveedor'],
                        'nombre'       => (string)$pr['nombre'],
                        'ruc'          => $rawR,
                    ];
                }
            }

            View::json([
                'status'         => 'success',
                'existingCdcs'   => $existingCdcs,
                'existingKeys'   => $existingKeys,
                'proveedoresMap' => $proveedoresMap,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al validar datos de importación: ' . $e->getMessage()]);
        }
    }

    public static function apiEgresosImportarLote(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        if (empty($items)) {
            View::json(['status' => 'error', 'message' => 'No hay comprobantes válidos para importar.']);
            return;
        }

        try {
            @set_time_limit(180);

            $comprador = trim((string)(Database::queryForValue(
                "SELECT descripcion FROM empresas WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
                [$idProp, $empresa]
            ) ?? ''));
            if ($comprador === '') {
                $comprador = TenantContext::nomEmpresa();
            }

            // Mapa de proveedores actuales
            $provRows = Database::queryForList(
                "SELECT cod_proveedor, cod_persona, nombre, ruc
                 FROM co_proveedores
                 WHERE id_propietario = ? AND cod_empresa = ?",
                [$idProp, $empresa]
            );
            $provCache = [];
            foreach ($provRows as $pr) {
                $rawR = trim((string)($pr['ruc'] ?? ''));
                if ($rawR === '') continue;
                $baseR = ltrim(preg_replace('/\D/', '', explode('-', $rawR)[0]), '0');
                if ($baseR !== '') {
                    $provCache[$baseR] = [
                        'codProveedor' => (string)$pr['cod_proveedor'],
                        'nombre'       => (string)$pr['nombre'],
                        'ruc'          => $rawR,
                    ];
                }
            }

            // Mapa de comprobantes existentes en BD
            $egresosRows = Database::queryForList(
                "SELECT id_egreso, TRIM(COALESCE(cdc, '')) AS cdc,
                        TRIM(COALESCE(nro_timbrado, '')) AS nro_timbrado,
                        REPLACE(TRIM(COALESCE(nro_factura, '')), '-', '') AS fac_clean,
                        SUBSTRING_INDEX(REPLACE(COALESCE(ruc_proveedor, ''), ' ', ''), '-', 1) AS ruc_base
                 FROM co_egresos
                 WHERE id_propietario = ? AND cod_empresa = ?",
                [$idProp, $empresa]
            );
            $existingCdcs = [];
            $existingKeys = [];
            foreach ($egresosRows as $er) {
                $c = preg_replace('/\D/', '', (string)$er['cdc']);
                if (strlen($c) === 44) {
                    $existingCdcs[$c] = true;
                }
                $facClean = (string)$er['fac_clean'];
                $timb = (string)$er['nro_timbrado'];
                $rucB = ltrim(preg_replace('/\D/', '', (string)$er['ruc_base']), '0');
                if ($facClean !== '' && $timb !== '') {
                    $existingKeys[$timb . '|' . $facClean . '|' . $rucB] = true;
                }
            }

            $proveedoresCreados = 0;
            $insertados = 0;
            $duplicadosOmitidos = 0;
            $minFecha = null;
            $maxFecha = null;

            $maxId = (int)Database::queryForValue("SELECT COALESCE(MAX(id_egreso), 0) FROM co_egresos");

            foreach ($items as $item) {
                $cdc = preg_replace('/\D/', '', (string)($item['cdc'] ?? ''));
                $tipDoc = self::resolveTipoDocCode((string)($item['tipDoc'] ?? 'FAC'));
                $rawReg = (string)($item['regimen'] ?? $item['codRegimen'] ?? '1');
                $codRegimen = self::resolveRegimenId($rawReg, $idProp);
                $regimen = (string)$codRegimen;
                $clasificacionGasto = (string)(int)self::resolveClasificacionGastoId((string)($item['clasificacionGasto'] ?? '1'), $idProp);
                $tipEgreso = (string)(int)self::resolveTipoEgresoId((string)($item['tipEgreso'] ?? '1'), $idProp);
                $fecEgreso = self::parseSqlDate((string)($item['fecEgreso'] ?? ''));
                $esElectr = (strlen($cdc) === 44) || stripos((string)($item['tipTimb'] ?? ''), 'electr') !== false;
                $tipTimb = $esElectr ? 'Electrónico' : 'Pre-impreso';
                $nroTimbrado = preg_replace('/\D/', '', (string)($item['nroTimbrado'] ?? ''));
                $defVenc = $esElectr ? '2100-12-31' : (($fecEgreso ? substr($fecEgreso, 0, 4) : date('Y')) . '-12-31');
                $vencTimbrado = self::parseSqlDate((string)($item['vencTimbrado'] ?? '')) ?: $defVenc;
                if (!$esElectr && $vencTimbrado === '2100-12-31') {
                    $vencTimbrado = $defVenc;
                }
                $nroFactura = self::formatNroFactura((string)($item['nroFactura'] ?? ''));
                $rawRuc = trim((string)($item['rucProveedor'] ?? ''));
                $nomProveedor = mb_strtoupper(trim((string)($item['nomProveedor'] ?? '')), 'UTF-8');

                if ($nroFactura === '' || $nroTimbrado === '' || !$fecEgreso || $rawRuc === '' || $nomProveedor === '') {
                    continue;
                }

                $rucConDv = self::normalizarRucConDv($rawRuc, $cdc);
                $rucBase = ltrim(preg_replace('/\D/', '', explode('-', $rucConDv)[0]), '0');
                $facClean = str_replace('-', '', $nroFactura);
                $dupKey = $nroTimbrado . '|' . $facClean . '|' . $rucBase;

                if (($cdc !== '' && isset($existingCdcs[$cdc])) || isset($existingKeys[$dupKey])) {
                    $duplicadosOmitidos++;
                    continue;
                }

                // Verificar o crear proveedor automáticamente
                if ($rucBase !== '' && isset($provCache[$rucBase])) {
                    $codProveedor = $provCache[$rucBase]['codProveedor'];
                    $rucFinal = $provCache[$rucBase]['ruc'] ?: $rucConDv;
                    if (!str_contains($rucFinal, '-')) {
                        $rucFinal = $rucConDv;
                    }
                } else {
                    $codPersona = \App\Services\PersonaService::syncPersonaData(null, $nomProveedor, $rucConDv, 'RUC');
                    $nextProvId = (int)Database::queryForValue(
                        "SELECT COALESCE(MAX(CAST(cod_proveedor AS UNSIGNED)), 0) + 1
                         FROM co_proveedores
                         WHERE id_propietario = ? AND CAST(cod_proveedor AS UNSIGNED) < 3000",
                        [$idProp]
                    );
                    $codProveedor = (string)max(1, $nextProvId);
                    Database::update(
                        "INSERT INTO co_proveedores (id_propietario, cod_proveedor, cod_empresa, cod_persona, nombre, ruc, tipo_documento, estado, usuario_reg, fec_reg)
                         VALUES (?, ?, ?, ?, ?, ?, 'RUC', 'A', ?, NOW())",
                        [$idProp, $codProveedor, $empresa, $codPersona, $nomProveedor, $rucConDv, $usuario]
                    );
                    $rucFinal = $rucConDv;
                    if ($rucBase !== '') {
                        $provCache[$rucBase] = [
                            'codProveedor' => $codProveedor,
                            'nombre'       => $nomProveedor,
                            'ruc'          => $rucFinal,
                        ];
                    }
                    $proveedoresCreados++;
                }

                // Condición: CONTADO -> CON, CRÉDITO -> CRE
                $rawCond = strtoupper(trim((string)($item['condicion'] ?? 'CON')));
                $condicion = (str_starts_with($rawCond, 'CR') || str_contains($rawCond, 'CRED') || str_contains($rawCond, 'CRÉD') || $rawCond === 'CRE') ? 'CRE' : 'CON';

                // Moneda: 1 = Guaraníes (Gs), 2 = Dólares (USD)
                $rawMon = strtoupper(trim((string)($item['codMoneda'] ?? '1')));
                $codMoneda = ($rawMon === '2' || $rawMon === 'SI' || str_contains($rawMon, 'USD') || str_contains($rawMon, 'DOLAR')) ? '2' : '1';
                $tasaCambio = (float)($item['tasaCambio'] ?? 1);

                $grav10 = round((float)($item['grav10'] ?? 0));
                $iva10  = round((float)($item['iva10'] ?? round($grav10 / 11)));
                $grav5  = round((float)($item['grav5'] ?? 0));
                $iva5   = round((float)($item['iva5'] ?? round($grav5 / 21)));
                $exentas = round((float)($item['exentas'] ?? 0));
                $totIva = $iva10 + $iva5;
                $valorTotal = round((float)($item['valorTotal'] ?? ($grav10 + $grav5 + $exentas)));

                $maxId++;
                Database::update(
                    "INSERT INTO co_egresos (
                        id_propietario, id_egreso, cod_empresa, cdc, tip_doc, cod_regimen, regimen,
                        clasificacion_gasto, tip_timb, nro_timbrado, venc_timbrado, nro_factura, serie,
                        fec_egreso, comprador, cod_proveedor, nom_proveedor, ruc_proveedor, tip_egreso,
                        form_120, condicion, form_145, cod_moneda, tasa_cambio, grav10, iva10, grav5,
                        iva5, exentas, tot_iva, valor_total, observacion, estado, usuario_reg
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?, ?, ?, ?, ?, 'Directo', ?, 'Original', ?, ?, ?, ?, ?, ?, ?, ?, ?, '', 'A', ?)",
                    [
                        $idProp, $maxId, $empresa, $cdc ?: null, $tipDoc, $codRegimen, $regimen,
                        $clasificacionGasto, $tipTimb, $nroTimbrado, $vencTimbrado, $nroFactura,
                        $fecEgreso, $comprador, $codProveedor, $nomProveedor, $rucFinal, $tipEgreso,
                        $condicion, $codMoneda, $tasaCambio, $grav10, $iva10, $grav5, $iva5, $exentas, $totIva, $valorTotal, $usuario
                    ]
                );

                // Construir asiento contable automático
                $neto10 = $grav10 - $iva10;
                $neto5  = $grav5 - $iva5;
                $debeList = [];
                if ($neto10 > 0) {
                    $debeList[] = ['cuenta' => '01.01.04.01.01 - MERCADERÍAS GRAVADAS POR EL IVA AL 10%', 'debe' => $neto10];
                }
                if ($iva10 > 0) {
                    $debeList[] = ['cuenta' => '01.01.03.05.03.01 - IVA CRÉDITO 10%', 'debe' => $iva10];
                }
                if ($neto5 > 0) {
                    $debeList[] = ['cuenta' => '01.01.04.01.02 - MERCADERÍAS GRAVADAS POR EL IVA AL 5%', 'debe' => $neto5];
                }
                if ($iva5 > 0) {
                    $debeList[] = ['cuenta' => '01.01.03.05.03.02 - IVA CRÉDITO 05%', 'debe' => $iva5];
                }
                if ($exentas > 0) {
                    $debeList[] = ['cuenta' => '01.01.04.01.03 - MERCADERÍAS EXENTAS DEL IVA', 'debe' => $exentas];
                }
                $haberList = [];
                if ($valorTotal > 0) {
                    $ctaHaber = ($condicion === 'CON')
                        ? '01.01.01.02.01 - CAJA GUARANÍES'
                        : '02.01.01.01 - PROVEEDORES LOCALES [ CORTO PLAZO ]';
                    $haberList[] = ['cuenta' => $ctaHaber, 'haber' => $valorTotal];
                }
                self::registrarAsientoContableEgreso($idProp, $maxId, $empresa, $usuario, $fecEgreso, $nroFactura, $nomProveedor, $debeList, $haberList);

                if ($cdc !== '') $existingCdcs[$cdc] = true;
                $existingKeys[$dupKey] = true;
                $insertados++;

                if ($minFecha === null || $fecEgreso < $minFecha) $minFecha = $fecEgreso;
                if ($maxFecha === null || $fecEgreso > $maxFecha) $maxFecha = $fecEgreso;
            }

            View::json([
                'status'             => 'success',
                'insertados'         => $insertados,
                'proveedoresCreados' => $proveedoresCreados,
                'duplicadosOmitidos' => $duplicadosOmitidos,
                'minFecha'           => $minFecha,
                'maxFecha'           => $maxFecha,
                'message'            => "Se importaron {$insertados} comprobante(s) de egreso exitosamente"
                                      . ($proveedoresCreados > 0 ? " y se crearon {$proveedoresCreados} nuevos proveedores." : "."),
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al importar lote de egresos: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API INGRESOS (/api/ingresos/*)
    // =========================================================================
    private static function resolveTipoIngresoId(?string $val, int $idProp): string {
        if (!$val || trim($val) === '' || strcasecmp(trim($val), 'null') === 0) return '1';
        $v = trim($val);
        if (ctype_digit($v)) return $v;
        $up = strtoupper($v);
        if (str_contains($up, 'VENTA') || str_contains($up, 'MERCADER')) return '1';
        if (str_contains($up, 'PROFESIONAL') || str_contains($up, 'SERVICIO')) return '2';
        if (str_contains($up, 'ALQUILER') || str_contains($up, 'INMUEBLE') || str_contains($up, 'EQUIPO')) return '3';
        if (str_contains($up, 'OTRO')) return '4';

        $id = Database::queryForValue(
            "SELECT cod_tipo_ingreso FROM co_tipo_ingreso WHERE id_propietario IN (?, 1) AND UPPER(TRIM(descripcion)) = ? LIMIT 1",
            [$idProp, $up]
        );
        return $id !== null ? (string)$id : '1';
    }

    public static function apiIngresosTiposDocumentos(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $list = Database::queryForListDual(
            "SELECT tip_comprobante AS cod, descripcion AS nombre
             FROM co_tipos_comprobantes
             WHERE id_propietario IN (?, 1)
               AND COALESCE(estado, 'A') = 'A'
               AND (UPPER(cod_modulo) = 'INGRESOS' OR UPPER(cod_modulo) = 'AMBOS')
             ORDER BY descripcion",
            [$idProp]
        );
        if (empty($list)) {
            foreach (['Factura', 'Nota de Débito', 'Nota de Crédito', 'Autofactura'] as $d) {
                $list[] = ['cod' => $d, 'COD' => $d, 'nombre' => $d, 'NOMBRE' => $d];
            }
        }
        View::json($list);
    }

    public static function apiIngresosList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $anio = trim((string)($_GET['anio'] ?? ''));
        $mes  = trim((string)($_GET['mes'] ?? ''));

        try {
            $sql = "SELECT id_ingreso, cdc, tip_doc,
                           CAST(COALESCE(NULLIF(cod_regimen, 0), 1) AS UNSIGNED) AS cod_regimen,
                           COALESCE(NULLIF(TRIM(regimen), ''), CAST(CAST(COALESCE(NULLIF(cod_regimen, 0), 1) AS UNSIGNED) AS CHAR)) AS regimen,
                           CAST(COALESCE(NULLIF(cod_tipo_ingreso, 0), 1) AS UNSIGNED) AS cod_tipo_ingreso,
                           tip_ingreso, nro_timbrado,
                           DATE_FORMAT(venc_timbrado, '%Y-%m-%d') AS venc_timbrado,
                           DATE_FORMAT(venc_timbrado, '%d/%m/%Y') AS venc_timbrado_fmt,
                           nro_factura, cod_cliente,
                           DATE_FORMAT(fec_ingreso, '%d/%m/%Y') AS fec_ingreso_fmt,
                           DATE_FORMAT(fec_ingreso, '%Y-%m-%d') AS fec_ingreso,
                           condicion, cod_moneda, nom_cliente, ruc_cliente,
                           COALESCE(exentas, 0) AS exentas, COALESCE(grav5, 0) AS grav5, COALESCE(iva5, 0) AS iva5,
                           COALESCE(grav10, 0) AS grav10, COALESCE(iva10, 0) AS iva10, COALESCE(tot_iva, 0) AS tot_iva,
                           COALESCE(valor_total, 0) AS valor_total, estado,
                           COALESCE(anulada, 'N') AS anulada, COALESCE(inutilizado, 'N') AS inutilizado,
                           sifen_estado, sifen_protocolo, sifen_qr_url, sifen_mensaje
                    FROM co_ingresos
                    WHERE id_propietario = ? AND cod_empresa = ? ";
            $params = [$idProp, $empresa];

            if ($anio !== '' && strcasecmp($anio, 'TODOS') !== 0) {
                $sql .= "AND YEAR(fec_ingreso) = ? ";
                $params[] = (int)$anio;
            }
            if ($mes !== '' && strcasecmp($mes, 'TODOS') !== 0) {
                $sql .= "AND MONTH(fec_ingreso) = ? ";
                $params[] = (int)$mes;
            }
            $sql .= "ORDER BY id_ingreso DESC";

            $list = Database::queryForListUpper($sql, $params);
            View::json(['status' => 'success', 'data' => $list]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al listar ingresos: ' . $e->getMessage()]);
        }
    }

    public static function apiIngresosGet(string $id): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();

        try {
            $sql = "SELECT id_ingreso, cdc, tip_doc,
                           CAST(COALESCE(NULLIF(cod_regimen, 0), 1) AS UNSIGNED) AS cod_regimen,
                           COALESCE(NULLIF(TRIM(regimen), ''), CAST(CAST(COALESCE(NULLIF(cod_regimen, 0), 1) AS UNSIGNED) AS CHAR)) AS regimen,
                           anulada, inutilizado, nro_timbrado,
                           DATE_FORMAT(venc_timbrado, '%Y-%m-%d') AS venc_timbrado, nro_factura, serie,
                           DATE_FORMAT(fec_ingreso, '%Y-%m-%d') AS fec_ingreso, condicion, cod_cliente,
                           nom_cliente, ruc_cliente,
                           CAST(COALESCE(NULLIF(cod_tipo_ingreso, 0), 1) AS UNSIGNED) AS cod_tipo_ingreso,
                           tip_ingreso, form_145, cod_moneda, tasa_cambio, grav10, iva10, grav5, iva5,
                           exentas, tot_iva, valor_bruto, no_gravado, redondeo, valor_total, observacion,
                           sifen_estado, sifen_protocolo, sifen_qr_url, sifen_mensaje, sifen_fecha_firma
                    FROM co_ingresos
                    WHERE id_propietario = ? AND id_ingreso = ? AND cod_empresa = ? LIMIT 1";
            $list = Database::queryForListUpper($sql, [$idProp, (int)$id, $empresa]);
            if (!empty($list)) {
                View::json(['status' => 'success', 'data' => $list[0]]);
            } else {
                View::json(['status' => 'error', 'message' => 'Ingreso no encontrado.']);
            }
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al obtener ingreso: ' . $e->getMessage()]);
        }
    }

    public static function apiIngresosSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();

        try {
            $cdc       = isset($payload['cdc']) ? trim((string)$payload['cdc']) : null;
            $tipDoc    = self::resolveTipoDocCode((string)($payload['tipDoc'] ?? 'FAC'));
            $rawReg    = (string)($payload['codRegimen'] ?? $payload['regimen'] ?? '1');
            $codRegimen = self::resolveRegimenId($rawReg, $idProp);
            $regimen   = (string)$codRegimen;
            $anulada   = (!empty($payload['anulada']) && $payload['anulada'] !== 'N' && $payload['anulada'] !== 'false') ? 'S' : 'N';
            $inutilizado = (!empty($payload['inutilizado']) && $payload['inutilizado'] !== 'N' && $payload['inutilizado'] !== 'false') ? 'S' : 'N';
            $nroTimbrado = isset($payload['nroTimbrado']) ? trim((string)$payload['nroTimbrado']) : null;
            $vencTimbrado = self::parseSqlDate((string)($payload['vencTimbrado'] ?? ''));
            $nroFactura = self::formatNroFactura((string)($payload['nroFactura'] ?? ''));
            $serie      = isset($payload['serie']) ? trim((string)$payload['serie']) : null;
            $fecIngreso = self::parseSqlDate((string)($payload['fecIngreso'] ?? ''));

            if ($fecIngreso && $vencTimbrado && $fecIngreso > $vencTimbrado) {
                $f1 = date('d/m/Y', strtotime($fecIngreso));
                $f2 = date('d/m/Y', strtotime($vencTimbrado));
                View::json([
                    'status'  => 'error',
                    'message' => "La fecha del comprobante ({$f1}) no puede ser mayor a la fecha de vencimiento del timbrado ({$f2}).",
                ]);
                return;
            }

            $rawCond = strtoupper(trim((string)($payload['condicion'] ?? 'CON')));
            $condicion = (str_contains($rawCond, 'CRED') || str_contains($rawCond, 'CRÉD') || $rawCond === 'CRE') ? 'CRE' : 'CON';

            $rucCliente = trim((string)($payload['rucCliente'] ?? ''));
            $codCliente = trim((string)($payload['codCliente'] ?? ''));
            $nomCliente = trim((string)($payload['nomCliente'] ?? ''));

            if ($codCliente === '' || strcasecmp($codCliente, 'null') === 0 || ($rucCliente !== '' && $codCliente === $rucCliente) || (str_contains($codCliente, '-') && strlen($codCliente) >= 5)) {
                if ($rucCliente !== '') {
                    $rucNoDash = str_replace(['-', ' '], '', $rucCliente);
                    $foundCli = Database::queryForValue(
                        "SELECT cod_cliente FROM co_clientes
                         WHERE id_propietario = ?
                           AND (UPPER(TRIM(ruc)) = UPPER(?) OR UPPER(REPLACE(ruc, '-', '')) = UPPER(?))
                         ORDER BY CASE WHEN cod_empresa = ? THEN 1 ELSE 2 END, CAST(cod_cliente AS UNSIGNED) DESC
                         LIMIT 1",
                        [$idProp, $rucCliente, $rucNoDash, $empresa]
                    );
                    if ($foundCli !== null) {
                        $codCliente = (string)$foundCli;
                    }
                }
            }

            $rawTipIng = (string)($payload['codTipoIngreso'] ?? $payload['tipIngreso'] ?? '1');
            $tipIngreso = self::resolveTipoIngresoId($rawTipIng, $idProp);
            $codTipoIngreso = (int)$tipIngreso;

            $form145 = trim((string)($payload['form145'] ?? 'Original')) ?: 'Original';

            $rawMoneda = strtoupper(trim((string)($payload['codMoneda'] ?? '1')));
            if (str_contains($rawMoneda, 'DOLAR') || str_contains($rawMoneda, 'DÓLAR') || $rawMoneda === '2' || $rawMoneda === 'USD') {
                $codMoneda = '2';
            } elseif (str_contains($rawMoneda, 'REAL') || $rawMoneda === '3' || $rawMoneda === 'BRL') {
                $codMoneda = '3';
            } elseif (ctype_digit($rawMoneda)) {
                $codMoneda = $rawMoneda;
            } else {
                $codMoneda = '1';
            }

            $tasaCambio = (float)($payload['tasaCambio'] ?? 1);
            $grav10     = (float)($payload['grav10'] ?? 0);
            $iva10      = (float)($payload['iva10'] ?? 0);
            $grav5      = (float)($payload['grav5'] ?? 0);
            $iva5       = (float)($payload['iva5'] ?? 0);
            $exentas    = (float)($payload['exentas'] ?? 0);
            $totIva     = (float)($payload['totIva'] ?? 0);
            $valorBruto = (float)($payload['valorBruto'] ?? 0);
            $noGravado  = (float)($payload['noGravado'] ?? 0);
            $redondeo   = (float)($payload['redondeo'] ?? 0);
            $valorTotal = (float)($payload['valorTotal'] ?? 0);
            $observacion = isset($payload['observacion']) ? trim((string)$payload['observacion']) : null;

            $idIngreso = !empty($payload['idIngreso']) ? (int)$payload['idIngreso'] : null;

            $isFactura = str_starts_with(strtoupper($tipDoc), 'FAC') && !str_contains(strtoupper($tipDoc), 'AUTO');
            if ($isFactura && $nroFactura !== '' && $nroTimbrado && $nroTimbrado !== '') {
                $cleanFac = str_replace('-', '', $nroFactura);
                $checkSql = "SELECT COUNT(*) FROM co_ingresos
                             WHERE id_propietario = ? AND cod_empresa = ?
                               AND (UPPER(tip_doc) LIKE 'FAC%' AND UPPER(tip_doc) NOT LIKE '%AUTO%')
                               AND (REPLACE(nro_factura, '-', '') = ? OR TRIM(nro_factura) = ?)
                               AND TRIM(nro_timbrado) = ? ";
                $checkParams = [$idProp, $empresa, $cleanFac, $nroFactura, $nroTimbrado];
                if ($idIngreso !== null) {
                    $checkSql .= "AND id_ingreso <> ? ";
                    $checkParams[] = $idIngreso;
                }
                $dupCount = (int)Database::queryForValue($checkSql, $checkParams);
                if ($dupCount > 0) {
                    View::json([
                        'status'  => 'error',
                        'message' => "Ya existe una Factura registrada con el N° {$nroFactura} y Timbrado {$nroTimbrado} para esta empresa.",
                    ]);
                    return;
                }
            }

            $savedId = $idIngreso;
            if ($idIngreso === null) {
                $maxId = (int)Database::queryForValue(
                    "SELECT COALESCE(MAX(id_ingreso), 0) + 1 FROM co_ingresos"
                );
                Database::update(
                    "INSERT INTO co_ingresos (
                        id_propietario, id_ingreso, cod_empresa, cdc, tip_doc, cod_regimen, regimen,
                        anulada, inutilizado, nro_timbrado, venc_timbrado, nro_factura, serie, fec_ingreso,
                        condicion, cod_cliente, nom_cliente, ruc_cliente, cod_tipo_ingreso, tip_ingreso,
                        form_145, cod_moneda, tasa_cambio, grav10, iva10, grav5, iva5, exentas, tot_iva,
                        valor_bruto, no_gravado, redondeo, valor_total, observacion, estado, usuario_reg
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'A', ?)",
                    [
                        $idProp, $maxId, $empresa, $cdc, $tipDoc, $codRegimen, $regimen,
                        $anulada, $inutilizado, $nroTimbrado, $vencTimbrado, $nroFactura, $serie, $fecIngreso,
                        $condicion, $codCliente ?: null, $nomCliente ?: null, $rucCliente ?: null, $codTipoIngreso, $tipIngreso,
                        $form145, $codMoneda, $tasaCambio, $grav10, $iva10, $grav5, $iva5, $exentas, $totIva,
                        $valorBruto, $noGravado, $redondeo, $valorTotal, $observacion, $usuario
                    ]
                );
                $savedId = $maxId;
                $msg = "Ingreso registrado correctamente con el ID #{$maxId}";
            } else {
                Database::update(
                    "UPDATE co_ingresos SET
                        cdc = ?, tip_doc = ?, cod_regimen = ?, regimen = ?, anulada = ?, inutilizado = ?,
                        nro_timbrado = ?, venc_timbrado = ?, nro_factura = ?, serie = ?, fec_ingreso = ?,
                        condicion = ?, cod_cliente = ?, nom_cliente = ?, ruc_cliente = ?,
                        cod_tipo_ingreso = ?, tip_ingreso = ?, form_145 = ?, cod_moneda = ?, tasa_cambio = ?,
                        grav10 = ?, iva10 = ?, grav5 = ?, iva5 = ?, exentas = ?, tot_iva = ?,
                        valor_bruto = ?, no_gravado = ?, redondeo = ?, valor_total = ?, observacion = ?
                     WHERE id_propietario = ? AND id_ingreso = ? AND cod_empresa = ?",
                    [
                        $cdc, $tipDoc, $codRegimen, $regimen, $anulada, $inutilizado,
                        $nroTimbrado, $vencTimbrado, $nroFactura, $serie, $fecIngreso,
                        $condicion, $codCliente ?: null, $nomCliente ?: null, $rucCliente ?: null,
                        $codTipoIngreso, $tipIngreso, $form145, $codMoneda, $tasaCambio,
                        $grav10, $iva10, $grav5, $iva5, $exentas, $totIva,
                        $valorBruto, $noGravado, $redondeo, $valorTotal, $observacion,
                        $idProp, $idIngreso, $empresa
                    ]
                );
                $msg = "Registro de Ingreso #{$idIngreso} actualizado con éxito.";
            }

            // Si se solicita emisión electrónica SIFEN o el timbrado es Electrónico (y no tiene CDC previo)
            $emitirSifen = !empty($payload['emitirSifen']);
            if (!$emitirSifen && ($cdc === null || $cdc === '') && $nroTimbrado) {
                $tipoTimb = (string)(Database::queryForValue(
                    "SELECT tipo_timbrado FROM co_timbrados WHERE id_propietario = ? AND cod_empresa = ? AND nro_timbrado = ? LIMIT 1",
                    [$idProp, $empresa, $nroTimbrado]
                ) ?? '');
                if (stripos($tipoTimb, 'electr') !== false) {
                    $emitirSifen = true;
                }
            }

            $sifenData = null;
            if ($emitirSifen && $anulada !== 'S' && $inutilizado !== 'S') {
                $sifenData = self::emitirSifenParaIngreso($idProp, $empresa, (int)$savedId);
            }

            View::json([
                'status'    => 'success',
                'message'   => $msg,
                'idIngreso' => $savedId,
                'sifen'     => $sifenData,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar el ingreso: ' . $e->getMessage()]);
        }
    }

    public static function apiIngresosDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $payload = self::getInput();
        $idIngreso = (int)($payload['idIngreso'] ?? 0);

        try {
            Database::update(
                "DELETE FROM co_ingresos WHERE id_propietario = ? AND id_ingreso = ? AND cod_empresa = ?",
                [$idProp, $idIngreso, $empresa]
            );
            View::json(['status' => 'success', 'message' => "Ingreso #{$idIngreso} eliminado con éxito."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar ingreso: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API ASIENTOS CONTABLES (/api/asientos/*)
    // =========================================================================
    public static function apiAsientosList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();

        $ejercicio   = isset($_GET['ejercicio']) && $_GET['ejercicio'] !== '' ? (int)$_GET['ejercicio'] : null;
        $mes         = isset($_GET['mes']) && $_GET['mes'] !== '' ? (int)$_GET['mes'] : null;
        $codSucursal = trim((string)($_GET['codSucursal'] ?? ''));
        $origen      = trim((string)($_GET['origen'] ?? ''));
        $search      = trim((string)($_GET['search'] ?? ''));

        $sql = "SELECT c.cod_empresa AS COD_EMPRESA, c.num_asiento AS NUM_ASIENTO,
                       DATE_FORMAT(c.fec_asiento, '%Y-%m-%d') AS FEC_ASIENTO_STR,
                       c.ejercicio AS EJERCICIO, c.cod_sucursal AS COD_SUCURSAL,
                       COALESCE(s.descripcion, c.cod_sucursal) AS NOM_SUCURSAL,
                       c.origen_comprobante AS ORIGEN_COMPROBANTE, c.id_comprobante AS ID_COMPROBANTE,
                       c.concepto AS CONCEPTO, c.total_debe AS TOTAL_DEBE, c.total_haber AS TOTAL_HABER,
                       c.estado AS ESTADO, c.usuario_reg AS USUARIO_REG
                FROM co_asientos_cab c
                LEFT JOIN sucursales s ON s.id_propietario = c.id_propietario
                     AND s.cod_empresa = c.cod_empresa
                     AND s.cod_sucursal = c.cod_sucursal
                WHERE c.id_propietario = ? AND c.cod_empresa = ? ";
        $params = [$idProp, $codEmpresa];

        if ($ejercicio !== null && $ejercicio > 0) {
            $sql .= "AND c.ejercicio = ? ";
            $params[] = $ejercicio;
        }
        if ($mes !== null && $mes >= 1 && $mes <= 12) {
            $sql .= "AND MONTH(c.fec_asiento) = ? ";
            $params[] = $mes;
        }
        if ($codSucursal !== '' && strcasecmp($codSucursal, 'TODAS') !== 0) {
            $sql .= "AND c.cod_sucursal = ? ";
            $params[] = $codSucursal;
        }
        if ($origen !== '' && strcasecmp($origen, 'TODOS') !== 0) {
            $sql .= "AND UPPER(c.origen_comprobante) = ? ";
            $params[] = strtoupper($origen);
        }
        if ($search !== '') {
            $sql .= "AND (UPPER(c.concepto) LIKE ? OR UPPER(c.id_comprobante) LIKE ? OR CAST(c.num_asiento AS CHAR) LIKE ?) ";
            $q = '%' . strtoupper($search) . '%';
            $params[] = $q; $params[] = $q; $params[] = $q;
        }
        $sql .= "ORDER BY c.num_asiento DESC";

        View::json(Database::queryForListUpper($sql, $params));
    }

    public static function apiAsientosObtener(string $numAsiento): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $num = (int)$numAsiento;

        try {
            $cabList = Database::queryForListUpper(
                "SELECT cod_empresa, num_asiento, DATE_FORMAT(fec_asiento, '%Y-%m-%d') AS fec_asiento,
                        ejercicio, cod_sucursal, origen_comprobante, id_comprobante, concepto,
                        total_debe, total_haber, estado, usuario_reg
                 FROM co_asientos_cab
                 WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ? LIMIT 1",
                [$idProp, $codEmpresa, $num]
            );
            if (empty($cabList)) {
                View::json(['status' => 'error', 'message' => 'No se encontró el asiento especificado.']);
                return;
            }
            $detalles = Database::queryForListUpper(
                "SELECT secuencia, cod_cuenta, nom_cuenta, descripcion, debe, haber
                 FROM co_asientos_det
                 WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?
                 ORDER BY secuencia ASC",
                [$idProp, $codEmpresa, $num]
            );
            View::json([
                'status'   => 'success',
                'cabecera' => $cabList[0],
                'detalles' => $detalles,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al obtener el asiento: ' . $e->getMessage()]);
        }
    }

    public static function apiAsientosSiguienteNumero(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();

        $maxNum = (int)Database::queryForValue(
            "SELECT COALESCE(MAX(num_asiento), 0) + 1 FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ?",
            [$idProp, $codEmpresa]
        );
        View::json(['status' => 'success', 'siguienteNumero' => $maxNum > 0 ? $maxNum : 1]);
    }

    public static function apiAsientosBuscarCuentas(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $search = trim((string)($_GET['search'] ?? ''));

        $sql = "SELECT cod_cuenta, descripcion, tipo_cuenta, nivel, asentable
                FROM co_plan_cuentas
                WHERE id_propietario = ? AND COALESCE(estado, 'A') = 'A' ";
        $params = [$idProp];
        if ($search !== '') {
            $sql .= "AND (UPPER(cod_cuenta) LIKE ? OR UPPER(descripcion) LIKE ?) ";
            $q = '%' . strtoupper($search) . '%';
            $params[] = $q; $params[] = $q;
        }
        $sql .= "ORDER BY cod_cuenta ASC";
        $rows = Database::queryForListUpper($sql, $params);
        if (empty($rows) && $idProp !== 1) {
            $params[0] = 1;
            $rows = Database::queryForListUpper($sql, $params);
        }
        View::json($rows);
    }

    public static function apiAsientosSucursales(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $rows = Database::queryForListUpper(
            "SELECT cod_sucursal, descripcion FROM sucursales
             WHERE id_propietario = ? AND cod_empresa = ?
             ORDER BY cod_sucursal ASC",
            [$idProp, $codEmpresa]
        );
        if (empty($rows)) {
            $rows = [['COD_SUCURSAL' => '1', 'DESCRIPCION' => 'CASA MATRIZ']];
        }
        View::json($rows);
    }

    public static function apiAsientosGuardar(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();

        $fecha       = trim((string)($payload['fecha'] ?? ''));
        $codSucursal = trim((string)($payload['codSucursal'] ?? '1'));
        $concepto    = trim((string)($payload['concepto'] ?? ''));
        $detalles    = is_array($payload['detalles'] ?? null) ? $payload['detalles'] : [];

        if ($fecha === '') {
            View::json(['status' => 'error', 'message' => 'La fecha del asiento es obligatoria.']);
            return;
        }
        if ($codSucursal === '') {
            View::json(['status' => 'error', 'message' => 'Debe seleccionar una sucursal.']);
            return;
        }
        if ($concepto === '') {
            View::json(['status' => 'error', 'message' => 'El concepto del asiento es obligatorio.']);
            return;
        }
        if (count($detalles) < 2) {
            View::json(['status' => 'error', 'message' => 'El asiento debe tener al menos 2 líneas contables.']);
            return;
        }

        $totalDebe = 0.0;
        $totalHaber = 0.0;
        foreach ($detalles as $idx => $det) {
            $cta = trim((string)($det['codCuenta'] ?? ''));
            if ($cta === '') {
                View::json(['status' => 'error', 'message' => 'La línea #' . ($idx + 1) . ' no tiene código de cuenta.']);
                return;
            }
            $debe  = (float)($det['debe'] ?? 0);
            $haber = (float)($det['haber'] ?? 0);
            if ($debe < 0 || $haber < 0) {
                View::json(['status' => 'error', 'message' => 'Los montos de la línea #' . ($idx + 1) . ' no pueden ser negativos.']);
                return;
            }
            $totalDebe += $debe;
            $totalHaber += $haber;
        }

        if (abs($totalDebe - $totalHaber) > 0.009) {
            View::json([
                'status'  => 'error',
                'message' => "El asiento está desbalanceado. Total Debe ({$totalDebe}) != Total Haber ({$totalHaber}).",
            ]);
            return;
        }
        if ($totalDebe <= 0) {
            View::json(['status' => 'error', 'message' => 'El monto total del asiento debe ser mayor a cero.']);
            return;
        }

        $ejercicio = !empty($payload['ejercicio']) && (int)$payload['ejercicio'] > 0
            ? (int)$payload['ejercicio']
            : (int)substr($fecha, 0, 4);
        $numAsiento = !empty($payload['numAsiento']) && (int)$payload['numAsiento'] > 0
            ? (int)$payload['numAsiento']
            : null;
        $origenComprobante = strtoupper(trim((string)($payload['origenComprobante'] ?? 'MANUAL'))) ?: 'MANUAL';
        $idComprobante     = trim((string)($payload['idComprobante'] ?? ''));

        try {
            if ($numAsiento === null) {
                $numAsiento = (int)Database::queryForValue(
                    "SELECT COALESCE(MAX(num_asiento), 0) + 1 FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ?",
                    [$idProp, $codEmpresa]
                );
                if ($numAsiento <= 0) $numAsiento = 1;

                Database::update(
                    "INSERT INTO co_asientos_cab (
                        id_propietario, cod_empresa, num_asiento, fec_asiento, ejercicio, cod_sucursal,
                        origen_comprobante, id_comprobante, concepto, total_debe, total_haber, estado, usuario_reg
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'A', ?)",
                    [$idProp, $codEmpresa, $numAsiento, $fecha, $ejercicio, $codSucursal, $origenComprobante, $idComprobante, $concepto, $totalDebe, $totalHaber, $usuario]
                );
            } else {
                Database::update(
                    "UPDATE co_asientos_cab SET
                        fec_asiento = ?, ejercicio = ?, cod_sucursal = ?, origen_comprobante = ?,
                        id_comprobante = ?, concepto = ?, total_debe = ?, total_haber = ?, usuario_reg = ?
                     WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?",
                    [$fecha, $ejercicio, $codSucursal, $origenComprobante, $idComprobante, $concepto, $totalDebe, $totalHaber, $usuario, $idProp, $codEmpresa, $numAsiento]
                );
                Database::update(
                    "DELETE FROM co_asientos_det WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?",
                    [$idProp, $codEmpresa, $numAsiento]
                );
            }

            foreach ($detalles as $idx => $det) {
                $secuencia = $idx + 1;
                $debe  = (float)($det['debe'] ?? 0);
                $haber = (float)($det['haber'] ?? 0);
                $descLinea = trim((string)($det['descripcion'] ?? '')) ?: $concepto;

                Database::update(
                    "INSERT INTO co_asientos_det (
                        id_propietario, cod_empresa, num_asiento, secuencia, cod_cuenta, nom_cuenta, descripcion, debe, haber
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $idProp, $codEmpresa, $numAsiento, $secuencia,
                        trim((string)($det['codCuenta'] ?? '')),
                        trim((string)($det['nomCuenta'] ?? '')),
                        $descLinea, $debe, $haber
                    ]
                );
            }

            View::json([
                'status'     => 'success',
                'numAsiento' => $numAsiento,
                'message'    => "Asiento Contable N° {$numAsiento} guardado correctamente.",
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar el asiento contable: ' . $e->getMessage()]);
        }
    }

    public static function apiAsientosAnular(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $payload = self::getInput();

        if (empty($payload['numAsiento'])) {
            View::json(['status' => 'error', 'message' => 'Número de asiento no especificado.']);
            return;
        }
        $numAsiento = (int)$payload['numAsiento'];

        try {
            Database::update(
                "UPDATE co_asientos_cab SET estado = 'I' WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?",
                [$idProp, $codEmpresa, $numAsiento]
            );
            View::json(['status' => 'success', 'message' => "Asiento Contable N° {$numAsiento} anulado correctamente."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al anular el asiento: ' . $e->getMessage()]);
        }
    }
}
