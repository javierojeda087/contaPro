<?php
namespace App\Controllers;

use Core\Database;
use Core\TenantContext;
use Core\View;
use App\Services\MenuService;
use App\Services\SifenClientService;
use App\Services\EjercicioService;

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

    private static function isLiquidacionSalario(?string $tipDoc): bool {
        if (!$tipDoc) return false;
        $u = strtoupper(trim($tipDoc));
        return $u === 'LIQ'
            || $u === 'LIQUIDACION DE SALARIO'
            || $u === 'LIQUIDACIÓN DE SALARIO'
            || (str_contains($u, 'LIQUIDAC') && str_contains($u, 'SALARIO'));
    }

    public static function formatNroFactura(?string $nroFactura, ?string $tipDoc = null): string {
        if (!$nroFactura || trim($nroFactura) === '') return '';
        $s = trim($nroFactura);
        if (self::isLiquidacionSalario($tipDoc)) {
            if (str_contains($s, '-')) {
                $parts = explode('-', $s);
                if (count($parts) === 3 && trim($parts[0]) === '001' && trim($parts[1]) === '001') {
                    return str_pad(trim($parts[2]), 7, '0', STR_PAD_LEFT);
                }
            }
            if (ctype_digit($s)) {
                return str_pad($s, 7, '0', STR_PAD_LEFT);
            }
            return $s;
        }
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
        $empRow = Database::queryForMap(
            "SELECT descripcion, ruc_empresa FROM empresas WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
            [$idProp, $emp]
        ) ?: [];
        $contribuyenteNombre = trim((string)($empRow['descripcion'] ?? ''));
        if ($contribuyenteNombre === '') {
            $contribuyenteNombre = TenantContext::nomEmpresa();
        }
        $contribuyenteRuc = trim((string)($empRow['ruc_empresa'] ?? ''));
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
            'contribuyenteRuc'    => $contribuyenteRuc,
            'empresaDesc'         => $contribuyenteNombre,
            'defFechaDesde'       => $defFechaDesde,
            'defFechaHasta'       => $defFechaHasta,
        ]);
    }

    public static function pageIngresos(): void {
        self::renderFormPage('ingresos_registro');
    }

    public static function pageRecibosCobro(): void {
        self::renderFormPage('recibos_cobro', [
            'nomForma'    => 'CORECIBO',
            'tituloForma' => 'Registro de Recibos de Cobro',
        ]);
    }

    public static function pageRecibosPago(): void {
        self::renderFormPage('recibos_pago', [
            'nomForma'    => 'CORECIBOPAGO',
            'tituloForma' => 'Registro de Recibos de Pago',
        ]);
    }

    public static function pageRetencionesSufridas(): void {
        self::ensureRetencionesSufridasTables();
        self::renderFormPage('retenciones_sufridas', [
            'nomForma'    => 'CORETSUF',
            'tituloForma' => 'Registro de Retenciones Sufridas',
        ]);
    }

    public static function pageAsientos(): void {
        self::asegurarColumnaAsiento();
        $codEmpresa = TenantContext::empresa();
        $ejercicio  = EjercicioService::getEjercicio();
        $rango      = EjercicioService::getRango($codEmpresa, $ejercicio);
        $def        = EjercicioService::defaultMes($rango);

        self::renderFormPage('asientos', [
            'nomForma'      => 'COASIENT',
            'tituloForma'   => 'Registro de Asientos Contables',
            'currentYear'   => (int)$ejercicio,
            'ejercicio'     => $ejercicio,
            'defFechaDesde' => $def[0],
            'defFechaHasta' => $def[1],
            'ejerInicio'    => $rango[0],
            'ejerFinal'     => $rango[1],
        ]);
    }

    public static function pageFiltroAsientos(): void {
        self::asegurarColumnaAsiento();
        $codEmpresa = TenantContext::empresa();
        $ejercicio  = EjercicioService::getEjercicio();
        $rango      = EjercicioService::getRango($codEmpresa, $ejercicio);
        $def        = EjercicioService::defaultMes($rango);

        self::renderFormPage('filtro_asientos', [
            'nomForma'      => 'COASFILT',
            'tituloForma'   => 'Filtro de Asientos Contables',
            'ejercicio'     => $ejercicio,
            'currentYear'   => (int)$ejercicio,
            'defFechaDesde' => $def[0],
            'defFechaHasta' => $def[1],
            'ejerInicio'    => $rango[0],
            'ejerFinal'     => $rango[1],
        ]);
    }

    public static function asegurarColumnaAsiento(): void {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            if (!\Core\DatabaseMigrator::columnExists('co_egresos', 'asiento')) {
                Database::execute("ALTER TABLE `co_egresos` ADD COLUMN `asiento` bigint DEFAULT NULL", true);
                Database::execute("ALTER TABLE `co_egresos` ADD KEY `idx_co_egresos_asiento` (`id_propietario`, `cod_empresa`, `asiento`)", true);
            }
            if (!\Core\DatabaseMigrator::columnExists('co_ingresos', 'asiento')) {
                Database::execute("ALTER TABLE `co_ingresos` ADD COLUMN `asiento` bigint DEFAULT NULL", true);
                Database::execute("ALTER TABLE `co_ingresos` ADD KEY `idx_co_ingresos_asiento` (`id_propietario`, `cod_empresa`, `asiento`)", true);
            }
        } catch (\Throwable $e) {}
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
        if (str_contains($up, 'IRE')) return 5;
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
        $filtroAnul = trim((string)($_GET['anulada'] ?? $_GET['estado'] ?? ''));

        try {
            self::asegurarColumnaAsiento();
            $sql = "SELECT id_egreso, cdc, tip_doc, tip_timb, tip_egreso, clasificacion_gasto,
                           asiento,
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

            $empRow = Database::queryForMap(
                "SELECT descripcion, ruc_empresa FROM empresas WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
                [$idProp, $empresa]
            ) ?: [];
            $rawRucEmp = trim((string)($empRow['ruc_empresa'] ?? ''));
            $rucInformante = preg_replace('/\D/', '', explode('-', $rawRucEmp)[0]);
            $rawNomEmp = trim((string)($empRow['descripcion'] ?? ''));
            if ($rawNomEmp === '') {
                $rawNomEmp = TenantContext::nomEmpresa();
            }
            $nombreInformante = trim((string)preg_replace('/\s+/', ' ', str_replace(',', ' ', strtoupper($rawNomEmp))));

            View::json([
                'status'           => 'success',
                'data'             => $list,
                'rucInformante'    => $rucInformante,
                'nombreInformante' => $nombreInformante,
            ]);
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

    public static function apiEgresosConsultarTimbrado(): void {
        if (!self::requireAuth()) return;
        $idProp      = TenantContext::idPropietario();
        $nroTimbrado = preg_replace('/\D/', '', (string)($_GET['timbrado'] ?? $_GET['nroTimbrado'] ?? ''));

        if ($nroTimbrado === '') {
            View::json(['status' => 'empty']);
            return;
        }

        try {
            // 1. Buscar el último vencimiento registrado con ese timbrado en co_egresos
            $row = Database::queryForMap(
                "SELECT DATE_FORMAT(venc_timbrado, '%Y-%m-%d') AS venc_timbrado,
                        ruc_proveedor, nom_proveedor, cod_proveedor
                 FROM co_egresos
                 WHERE id_propietario = ?
                   AND TRIM(nro_timbrado) = ?
                   AND venc_timbrado IS NOT NULL
                   AND DATE_FORMAT(venc_timbrado, '%Y-%m-%d') <> '0000-00-00'
                 ORDER BY id_egreso DESC
                 LIMIT 1",
                [$idProp, $nroTimbrado]
            );

            if ($row && !empty($row['venc_timbrado'])) {
                View::json([
                    'status'       => 'found',
                    'vencTimbrado' => $row['venc_timbrado'],
                    'rucProveedor' => $row['ruc_proveedor'] ?? '',
                    'nomProveedor' => $row['nom_proveedor'] ?? '',
                    'codProveedor' => $row['cod_proveedor'] ?? '',
                ]);
                return;
            }

            // 2. Fallback: buscar en co_timbrados si existiera allí
            $timb = Database::queryForMap(
                "SELECT DATE_FORMAT(fec_vencimiento, '%Y-%m-%d') AS venc_timbrado
                 FROM co_timbrados
                 WHERE id_propietario = ?
                   AND TRIM(nro_timbrado) = ?
                   AND fec_vencimiento IS NOT NULL
                   AND DATE_FORMAT(fec_vencimiento, '%Y-%m-%d') <> '0000-00-00'
                 ORDER BY id_timbrado DESC
                 LIMIT 1",
                [$idProp, $nroTimbrado]
            );

            if ($timb && !empty($timb['venc_timbrado'])) {
                View::json([
                    'status'       => 'found',
                    'vencTimbrado' => $timb['venc_timbrado'],
                ]);
                return;
            }

            View::json(['status' => 'not_found']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => $e->getMessage()]);
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
    ): ?int {
        try {
            self::asegurarColumnaAsiento();

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

            if (empty($debeList) && empty($haberList)) {
                Database::update(
                    "UPDATE co_egresos SET asiento = NULL WHERE id_propietario = ? AND cod_empresa = ? AND id_egreso = ?",
                    [$idProp, $empresa, $idEgreso]
                );
                return null;
            }

            $fecUse = $fecEgreso ?: date('Y-m-d');
            $ejercicio = (int)substr($fecUse, 0, 4);

            $numAsiento = (int)Database::queryForValue(
                "SELECT COALESCE(MAX(num_asiento), 0) + 1 FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ? AND ejercicio = ?",
                [$idProp, $empresa, $ejercicio]
            );
            if ($numAsiento <= 0) $numAsiento = 1;

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

            Database::update(
                "UPDATE co_egresos SET asiento = ? WHERE id_propietario = ? AND cod_empresa = ? AND id_egreso = ?",
                [$numAsiento, $idProp, $empresa, $idEgreso]
            );

            return $numAsiento;
        } catch (\Throwable $ignored) {
            return null;
        }
    }

    public static function apiEgresosDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $payload = self::getInput();
        $idEgreso = (int)($payload['idEgreso'] ?? 0);

        try {
            $asientos = Database::queryForList(
                "SELECT num_asiento FROM co_asientos_cab
                 WHERE id_propietario = ? AND cod_empresa = ? AND origen_comprobante = 'COEGRES' AND id_comprobante = ?",
                [$idProp, $empresa, (string)$idEgreso]
            );
            foreach ($asientos as $asRow) {
                $num = (int)$asRow['num_asiento'];
                Database::update("DELETE FROM co_asientos_det WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?", [$idProp, $empresa, $num]);
                Database::update("DELETE FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?", [$idProp, $empresa, $num]);
            }

            Database::update(
                "DELETE FROM co_egresos WHERE id_propietario = ? AND id_egreso = ? AND cod_empresa = ?",
                [$idProp, $idEgreso, $empresa]
            );
            View::json(['status' => 'success', 'message' => "Egreso #{$idEgreso} eliminado con éxito."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar egreso: ' . $e->getMessage()]);
        }
    }

    public static function apiEgresosDeleteMes(): void {
        if (!self::requireAuth()) return;
        $idProp  = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $payload = self::getInput();

        $rawAnio    = trim((string)($payload['anio'] ?? ''));
        $rawMes     = trim((string)($payload['mes'] ?? ''));
        $fechaDesde = self::parseSqlDate((string)($payload['fechaDesde'] ?? ''));
        $fechaHasta = self::parseSqlDate((string)($payload['fechaHasta'] ?? ''));

        // Si se envió rango de fechas, validar que ambas pertenezcan exactamente al mismo año y mes
        if ($fechaDesde !== null || $fechaHasta !== null) {
            if ($fechaDesde === null || $fechaHasta === null || $fechaDesde > $fechaHasta) {
                View::json([
                    'status'  => 'error',
                    'message' => 'El rango de fechas indicado no es válido.',
                ]);
                return;
            }
            $ymDesde = substr($fechaDesde, 0, 7);
            $ymHasta = substr($fechaHasta, 0, 7);
            if ($ymDesde !== $ymHasta) {
                View::json([
                    'status'  => 'error',
                    'message' => 'Por seguridad, solo se permite eliminar comprobantes dentro del rango de un único mes (por ejemplo, solo Septiembre).',
                ]);
                return;
            }
            $anio = (int)substr($fechaDesde, 0, 4);
            $mes  = (int)substr($fechaDesde, 5, 2);
        } else {
            if ($rawAnio === '' || strcasecmp($rawAnio, 'TODOS') === 0 || !ctype_digit($rawAnio)) {
                View::json([
                    'status'  => 'error',
                    'message' => 'Debe seleccionar un Año específico (no se permite "Todos").',
                ]);
                return;
            }
            if ($rawMes === '' || strcasecmp($rawMes, 'TODOS') === 0 || strcasecmp($rawMes, 'RANGO') === 0 || !ctype_digit($rawMes)) {
                View::json([
                    'status'  => 'error',
                    'message' => 'Debe seleccionar un único Mes específico (por ejemplo, Septiembre). No se permite borrar cuando el mes está en "Todos".',
                ]);
                return;
            }
            $anio = (int)$rawAnio;
            $mes  = (int)$rawMes;
        }

        if ($anio < 1990 || $anio > 2100 || $mes < 1 || $mes > 12) {
            View::json([
                'status'  => 'error',
                'message' => 'El periodo (Año/Mes) seleccionado no es válido para la eliminación masiva.',
            ]);
            return;
        }

        try {
            $whereSql = "id_propietario = ? AND cod_empresa = ? AND YEAR(fec_egreso) = ? AND MONTH(fec_egreso) = ?";
            $params   = [$idProp, $empresa, $anio, $mes];

            if ($fechaDesde !== null && $fechaHasta !== null) {
                $whereSql .= " AND DATE(fec_egreso) >= ? AND DATE(fec_egreso) <= ?";
                $params[] = $fechaDesde;
                $params[] = $fechaHasta;
            }

            $rowsToDelete = Database::queryForList(
                "SELECT id_egreso FROM co_egresos WHERE {$whereSql}",
                $params
            );
            $cant = count($rowsToDelete);

            if ($cant === 0) {
                View::json([
                    'status'     => 'error',
                    'message'    => 'No se encontraron comprobantes registrados en el mes seleccionado para eliminar.',
                    'eliminados' => 0,
                ]);
                return;
            }

            // Eliminar asientos contables asociados a esos egresos
            foreach ($rowsToDelete as $eg) {
                $idEg = (string)$eg['id_egreso'];
                $asientos = Database::queryForList(
                    "SELECT num_asiento FROM co_asientos_cab
                     WHERE id_propietario = ? AND cod_empresa = ? AND origen_comprobante = 'COEGRES' AND id_comprobante = ?",
                    [$idProp, $empresa, $idEg]
                );
                foreach ($asientos as $asRow) {
                    $num = (int)$asRow['num_asiento'];
                    Database::update("DELETE FROM co_asientos_det WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?", [$idProp, $empresa, $num]);
                    Database::update("DELETE FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?", [$idProp, $empresa, $num]);
                }
            }

            Database::update("DELETE FROM co_egresos WHERE {$whereSql}", $params);

            $nombresMeses = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            ];
            $nomMes = $nombresMeses[$mes] ?? (string)$mes;

            View::json([
                'status'     => 'success',
                'eliminados' => $cant,
                'message'    => "Se eliminaron correctamente {$cant} comprobante(s) de {$nomMes} {$anio}.",
            ]);
        } catch (\Throwable $e) {
            View::json([
                'status'  => 'error',
                'message' => 'Error al eliminar los comprobantes del mes: ' . $e->getMessage(),
            ]);
        }
    }

    private static function resolveTipoDocCode(?string $val): string {
        if (!$val || trim($val) === '') return 'FAC';
        $u = strtoupper(trim($val));
        if (self::isLiquidacionSalario($u)) {
            return 'LIQ';
        }
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
        if ($r === '' || $r === 'X' || $r === 'SIN RUC') return $r === 'SIN RUC' ? 'X' : $r;
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
               AND (UPPER(cod_modulo) IN ('INGRESOS', 'ST', 'VT', 'CC', 'AMBOS') OR UPPER(tipo) IN ('INGRESOS', 'AMBOS'))
             ORDER BY descripcion",
            [$idProp]
        );
        if (empty($list)) {
            $defaults = [
                'FAC' => 'FACTURA',
                'DEB' => 'NOTA DE DEBITO',
                'NCR' => 'NOTA DE CREDITO',
                'AUT' => 'AUTOFACTURA',
                'LIQ' => 'LIQUIDACION DE SALARIO',
            ];
            foreach ($defaults as $c => $d) {
                $list[] = ['cod' => $c, 'COD' => $c, 'nombre' => $d, 'NOMBRE' => $d];
            }
        }
        View::json($list);
    }

    private static function obtenerSiguienteNroLiquidacionSalario(int $idProp, string $codEmpresa): string {
        $rows = Database::queryForList(
            "SELECT nro_factura FROM co_ingresos
             WHERE id_propietario = ? AND cod_empresa = ?
               AND (UPPER(TRIM(tip_doc)) = 'LIQ' OR UPPER(tip_doc) LIKE '%LIQUIDAC%')",
            [$idProp, $codEmpresa]
        );
        $maxNum = 0;
        foreach ($rows as $r) {
            $raw = trim((string)($r['nro_factura'] ?? ''));
            if ($raw === '') continue;
            if (str_contains($raw, '-')) {
                $parts = explode('-', $raw);
                $raw = end($parts);
            }
            $digits = preg_replace('/\D/', '', (string)$raw);
            if ($digits !== '') {
                $num = (int)$digits;
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }
        return str_pad((string)($maxNum + 1), 7, '0', STR_PAD_LEFT);
    }

    public static function apiIngresosSiguienteNro(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $siguienteNro = self::obtenerSiguienteNroLiquidacionSalario($idProp, $empresa);
        View::json([
            'status'       => 'success',
            'siguienteNro' => $siguienteNro,
        ]);
    }

    public static function apiIngresosList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $anio       = trim((string)($_GET['anio'] ?? ''));
        $mes        = trim((string)($_GET['mes'] ?? ''));
        $fechaDesde = self::parseSqlDate((string)($_GET['fechaDesde'] ?? $_GET['desde'] ?? ''));
        $fechaHasta = self::parseSqlDate((string)($_GET['fechaHasta'] ?? $_GET['hasta'] ?? ''));
        $filtroAnul = trim((string)($_GET['anulada'] ?? $_GET['estado'] ?? ''));

        try {
            self::asegurarColumnaAsiento();
            $sql = "SELECT id_ingreso, cdc, tip_doc,
                           asiento,
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

            if ($fechaDesde !== null) {
                $sql .= "AND DATE(fec_ingreso) >= ? ";
                $params[] = $fechaDesde;
            }
            if ($fechaHasta !== null) {
                $sql .= "AND DATE(fec_ingreso) <= ? ";
                $params[] = $fechaHasta;
            }
            if ($fechaDesde === null && $fechaHasta === null) {
                if ($anio !== '' && strcasecmp($anio, 'TODOS') !== 0) {
                    $sql .= "AND YEAR(fec_ingreso) = ? ";
                    $params[] = (int)$anio;
                }
                if ($mes !== '' && strcasecmp($mes, 'TODOS') !== 0) {
                    $sql .= "AND MONTH(fec_ingreso) = ? ";
                    $params[] = (int)$mes;
                }
            }
            if (strcasecmp($filtroAnul, 'S') === 0 || strcasecmp($filtroAnul, 'ANULADOS') === 0 || strcasecmp($filtroAnul, 'ANULADA') === 0) {
                $sql .= "AND (COALESCE(anulada, 'N') = 'S' OR UPPER(COALESCE(sifen_estado, '')) = 'CANCELADO') ";
            } elseif (strcasecmp($filtroAnul, 'N') === 0 || strcasecmp($filtroAnul, 'ACTIVOS') === 0) {
                $sql .= "AND COALESCE(anulada, 'N') <> 'S' AND UPPER(COALESCE(sifen_estado, '')) <> 'CANCELADO' ";
            }
            $sql .= "ORDER BY fec_ingreso DESC, id_ingreso DESC";

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
            $esLiqSal  = self::isLiquidacionSalario($tipDoc);
            $rawReg    = (string)($payload['codRegimen'] ?? $payload['regimen'] ?? ($esLiqSal ? '2' : '1'));
            $codRegimen = self::resolveRegimenId($rawReg, $idProp);
            $regimen   = (string)$codRegimen;
            $anulada   = (!empty($payload['anulada']) && $payload['anulada'] !== 'N' && $payload['anulada'] !== 'false') ? 'S' : 'N';
            $inutilizado = (!empty($payload['inutilizado']) && $payload['inutilizado'] !== 'N' && $payload['inutilizado'] !== 'false') ? 'S' : 'N';
            $nroTimbrado = $esLiqSal ? null : (isset($payload['nroTimbrado']) ? trim((string)$payload['nroTimbrado']) : null);
            $vencTimbrado = $esLiqSal ? null : self::parseSqlDate((string)($payload['vencTimbrado'] ?? ''));
            $nroFactura = self::formatNroFactura((string)($payload['nroFactura'] ?? ''), $tipDoc);
            $serie      = isset($payload['serie']) ? trim((string)$payload['serie']) : null;
            $fecIngreso = self::parseSqlDate((string)($payload['fecIngreso'] ?? ''));

            if (!$esLiqSal && $fecIngreso && $vencTimbrado && $fecIngreso > $vencTimbrado) {
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

            // LIQUIDACION DE SALARIO no lleva IVA bajo ningún concepto
            if ($esLiqSal) {
                $grav10     = 0.0;
                $iva10      = 0.0;
                $grav5      = 0.0;
                $iva5      = 0.0;
                $totIva     = 0.0;
                $valorBruto = $exentas + $noGravado;
                $valorTotal = $valorBruto + $redondeo;
            }

            $idIngreso = !empty($payload['idIngreso']) ? (int)$payload['idIngreso'] : null;

            // Autogenerar correlativo (0000001, 0000002...) para nuevas Liquidaciones de Salario
            if ($esLiqSal && ($idIngreso === null || $nroFactura === '')) {
                $nroFactura = self::obtenerSiguienteNroLiquidacionSalario($idProp, $empresa);
            }

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
            $emitirSifen = !$esLiqSal && !empty($payload['emitirSifen']);
            if (!$esLiqSal && !$emitirSifen && ($cdc === null || $cdc === '') && $nroTimbrado) {
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

            $asientoNum = null;
            if ($anulada !== 'S' && $inutilizado !== 'S') {
                $asientoNum = self::registrarAsientoContableIngreso(
                    $idProp, (int)$savedId, $empresa, $usuario, $fecIngreso,
                    $nroFactura, $nomCliente ?: 'CLIENTES VARIOS', $condicion,
                    $grav10, $iva10, $grav5, $iva5, $exentas, $valorTotal
                );
            }

            View::json([
                'status'    => 'success',
                'message'   => $msg,
                'idIngreso' => $savedId,
                'asiento'   => $asientoNum,
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
            $asientos = Database::queryForList(
                "SELECT num_asiento FROM co_asientos_cab
                 WHERE id_propietario = ? AND cod_empresa = ? AND origen_comprobante = 'COINGRES' AND id_comprobante = ?",
                [$idProp, $empresa, (string)$idIngreso]
            );
            foreach ($asientos as $asRow) {
                $num = (int)$asRow['num_asiento'];
                Database::update("DELETE FROM co_asientos_det WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?", [$idProp, $empresa, $num]);
                Database::update("DELETE FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?", [$idProp, $empresa, $num]);
            }

            Database::update(
                "DELETE FROM co_ingresos WHERE id_propietario = ? AND id_ingreso = ? AND cod_empresa = ?",
                [$idProp, $idIngreso, $empresa]
            );
            View::json(['status' => 'success', 'message' => "Ingreso #{$idIngreso} eliminado con éxito."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar ingreso: ' . $e->getMessage()]);
        }
    }

    public static function apiIngresosAnular(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $payload = self::getInput();
        $idIngreso = (int)($payload['idIngreso'] ?? 0);

        if ($idIngreso <= 0) {
            View::json(['status' => 'error', 'message' => 'Debe seleccionar un comprobante de ingreso para anular.']);
            return;
        }

        try {
            $row = Database::queryForMap(
                "SELECT id_ingreso, nro_factura, COALESCE(anulada, 'N') AS anulada
                 FROM co_ingresos
                 WHERE id_propietario = ? AND id_ingreso = ? AND cod_empresa = ?
                 LIMIT 1",
                [$idProp, $idIngreso, $empresa]
            );

            if (!$row) {
                View::json(['status' => 'error', 'message' => 'El comprobante de ingreso seleccionado no fue encontrado.']);
                return;
            }

            $nroFactura = trim((string)($row['nro_factura'] ?? '')) ?: ('#' . $idIngreso);
            $nuevoEstadoAnulada = (isset($payload['anulada']) && strtoupper((string)$payload['anulada']) === 'N') ? 'N' : 'S';

            Database::update(
                "UPDATE co_ingresos
                 SET anulada = ?
                 WHERE id_propietario = ? AND id_ingreso = ? AND cod_empresa = ?",
                [$nuevoEstadoAnulada, $idProp, $idIngreso, $empresa]
            );

            if ($nuevoEstadoAnulada === 'S') {
                $asientos = Database::queryForList(
                    "SELECT num_asiento FROM co_asientos_cab
                     WHERE id_propietario = ? AND cod_empresa = ? AND origen_comprobante = 'COINGRES' AND id_comprobante = ?",
                    [$idProp, $empresa, (string)$idIngreso]
                );
                foreach ($asientos as $asRow) {
                    $num = (int)$asRow['num_asiento'];
                    Database::update("DELETE FROM co_asientos_det WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?", [$idProp, $empresa, $num]);
                    Database::update("DELETE FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?", [$idProp, $empresa, $num]);
                }
                Database::update(
                    "UPDATE co_ingresos SET asiento = NULL WHERE id_propietario = ? AND id_ingreso = ? AND cod_empresa = ?",
                    [$idProp, $idIngreso, $empresa]
                );
            }

            $msg = $nuevoEstadoAnulada === 'S'
                ? "El comprobante {$nroFactura} ha sido anulado correctamente."
                : "El comprobante {$nroFactura} ha sido reactivado correctamente.";

            View::json([
                'status'  => 'success',
                'anulada' => $nuevoEstadoAnulada,
                'message' => $msg,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al anular ingreso: ' . $e->getMessage()]);
        }
    }

    public static function apiIngresosValidarImportacion(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();

        try {
            // 1. Obtener comprobantes existentes de esta empresa para detectar duplicados y mostrar cuál es
            $ingresosRows = Database::queryForList(
                "SELECT id_ingreso, TRIM(COALESCE(cdc, '')) AS cdc,
                        UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) AS tip_doc,
                        TRIM(COALESCE(nro_timbrado, '')) AS nro_timbrado,
                        REPLACE(TRIM(COALESCE(nro_factura, '')), '-', '') AS fac_clean,
                        TRIM(COALESCE(nro_factura, '')) AS nro_factura,
                        DATE_FORMAT(fec_ingreso, '%d/%m/%Y') AS fec_ingreso_fmt,
                        TRIM(COALESCE(nom_cliente, '')) AS nom_cliente,
                        TRIM(COALESCE(ruc_cliente, '')) AS ruc_cliente,
                        COALESCE(valor_total, 0) AS valor_total,
                        COALESCE(anulada, 'N') AS anulada,
                        UPPER(TRIM(SUBSTRING_INDEX(REPLACE(COALESCE(ruc_cliente, ''), ' ', ''), '-', 1))) AS ruc_base
                 FROM co_ingresos
                 WHERE id_propietario = ? AND cod_empresa = ?",
                [$idProp, $empresa]
            );

            $existingCdcs = [];
            $existingKeys = [];
            foreach ($ingresosRows as $ir) {
                $info = [
                    'idIngreso'   => (int)$ir['id_ingreso'],
                    'nroFactura'  => (string)$ir['nro_factura'],
                    'nroTimbrado' => (string)$ir['nro_timbrado'],
                    'fecIngreso'  => (string)($ir['fec_ingreso_fmt'] ?? ''),
                    'nomCliente'  => (string)$ir['nom_cliente'],
                    'rucCliente'  => (string)$ir['ruc_cliente'],
                    'valorTotal'  => (float)$ir['valor_total'],
                    'anulada'     => (string)($ir['anulada'] ?? 'N'),
                ];
                $c = preg_replace('/\D/', '', (string)$ir['cdc']);
                if (strlen($c) === 44) {
                    $existingCdcs[$c] = $info;
                }
                $facClean = (string)$ir['fac_clean'];
                $timb = (string)$ir['nro_timbrado'];
                $rucB = ltrim((string)$ir['ruc_base'], '0');
                if ($facClean !== '' && $timb !== '') {
                    $key1 = $timb . '|' . $facClean . '|' . $rucB;
                    $existingKeys[$key1] = $info;
                    $key2 = $timb . '|' . $facClean;
                    if (!isset($existingKeys[$key2])) {
                        $existingKeys[$key2] = $info;
                    }
                }
            }

            // 2. Obtener clientes existentes
            $cliRows = Database::queryForList(
                "SELECT cod_cliente, nombre, ruc
                 FROM co_clientes
                 WHERE id_propietario = ? AND cod_empresa = ?",
                [$idProp, $empresa]
            );
            $clientesMap = [];
            foreach ($cliRows as $cr) {
                $rawR = trim((string)($cr['ruc'] ?? ''));
                if ($rawR === '') continue;
                $upperR = strtoupper($rawR);
                if ($upperR === 'X') {
                    $clientesMap['X'] = [
                        'codCliente' => (string)$cr['cod_cliente'],
                        'nombre'     => (string)$cr['nombre'],
                        'ruc'        => 'X',
                    ];
                    continue;
                }
                $baseR = ltrim(strtoupper(trim(explode('-', $rawR)[0])), '0');
                if ($baseR !== '') {
                    $clientesMap[$baseR] = [
                        'codCliente' => (string)$cr['cod_cliente'],
                        'nombre'     => (string)$cr['nombre'],
                        'ruc'        => $rawR,
                    ];
                }
            }

            View::json([
                'status'       => 'success',
                'existingCdcs' => $existingCdcs,
                'existingKeys' => $existingKeys,
                'clientesMap'  => $clientesMap,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al validar datos de importación de ingresos: ' . $e->getMessage()]);
        }
    }

    public static function apiIngresosImportarLote(): void {
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

            // Mapa de clientes actuales
            $cliRows = Database::queryForList(
                "SELECT cod_cliente, cod_persona, nombre, ruc
                 FROM co_clientes
                 WHERE id_propietario = ? AND cod_empresa = ?",
                [$idProp, $empresa]
            );
            $cliCache = [];
            foreach ($cliRows as $cr) {
                $rawR = trim((string)($cr['ruc'] ?? ''));
                if ($rawR === '') continue;
                $upperR = strtoupper($rawR);
                if ($upperR === 'X') {
                    $cliCache['X'] = [
                        'codCliente' => (string)$cr['cod_cliente'],
                        'nombre'     => (string)$cr['nombre'],
                        'ruc'        => 'X',
                    ];
                    continue;
                }
                $baseR = ltrim(preg_replace('/\D/', '', explode('-', $rawR)[0]), '0');
                if ($baseR !== '') {
                    $cliCache[$baseR] = [
                        'codCliente' => (string)$cr['cod_cliente'],
                        'nombre'     => (string)$cr['nombre'],
                        'ruc'        => $rawR,
                    ];
                }
            }

            // Mapa de comprobantes existentes en BD
            $ingresosRows = Database::queryForList(
                "SELECT id_ingreso, TRIM(COALESCE(cdc, '')) AS cdc,
                        TRIM(COALESCE(nro_timbrado, '')) AS nro_timbrado,
                        REPLACE(TRIM(COALESCE(nro_factura, '')), '-', '') AS fac_clean,
                        SUBSTRING_INDEX(REPLACE(COALESCE(ruc_cliente, ''), ' ', ''), '-', 1) AS ruc_base
                 FROM co_ingresos
                 WHERE id_propietario = ? AND cod_empresa = ?",
                [$idProp, $empresa]
            );
            $existingCdcs = [];
            $existingKeys = [];
            foreach ($ingresosRows as $ir) {
                $c = preg_replace('/\D/', '', (string)$ir['cdc']);
                if (strlen($c) === 44) {
                    $existingCdcs[$c] = true;
                }
                $facClean = (string)$ir['fac_clean'];
                $timb = (string)$ir['nro_timbrado'];
                $rucB = ltrim(preg_replace('/\D/', '', (string)$ir['ruc_base']), '0');
                if ($facClean !== '' && $timb !== '') {
                    $existingKeys[$timb . '|' . $facClean . '|' . $rucB] = true;
                    $existingKeys[$timb . '|' . $facClean] = true;
                }
            }

            $clientesCreados = 0;
            $insertados = 0;
            $anuladosInsertados = 0;
            $duplicadosOmitidos = 0;
            $minFecha = null;
            $maxFecha = null;

            $maxId = (int)Database::queryForValue("SELECT COALESCE(MAX(id_ingreso), 0) FROM co_ingresos");

            foreach ($items as $item) {
                $cdc = preg_replace('/\D/', '', (string)($item['cdc'] ?? ''));
                $tipDoc = self::resolveTipoDocCode((string)($item['tipDoc'] ?? 'FAC'));
                $rawReg = (string)($item['regimen'] ?? $item['codRegimen'] ?? '1');
                $codRegimen = self::resolveRegimenId($rawReg, $idProp);
                $regimen = (string)$codRegimen;
                $rawTipIng = (string)($item['tipIngreso'] ?? $item['codTipoIngreso'] ?? '1');
                $tipIngreso = self::resolveTipoIngresoId($rawTipIng, $idProp);
                $codTipoIngreso = (int)$tipIngreso;
                $fecIngreso = self::parseSqlDate((string)($item['fecIngreso'] ?? ''));
                $esElectr = (strlen($cdc) === 44) || stripos((string)($item['tipTimb'] ?? ''), 'electr') !== false || stripos((string)($item['origen'] ?? ''), 'electr') !== false;
                $tipTimb = $esElectr ? 'Electrónico' : 'Pre-impreso';
                $nroTimbrado = preg_replace('/\D/', '', (string)($item['nroTimbrado'] ?? ''));
                $defVenc = $esElectr ? '2100-12-31' : (($fecIngreso ? substr($fecIngreso, 0, 4) : date('Y')) . '-12-31');
                $vencTimbrado = self::parseSqlDate((string)($item['vencTimbrado'] ?? '')) ?: $defVenc;
                if (!$esElectr && $vencTimbrado === '2100-12-31') {
                    $vencTimbrado = $defVenc;
                }
                $nroFactura = self::formatNroFactura((string)($item['nroFactura'] ?? ''), $tipDoc);
                $rawRuc = strtoupper(trim((string)($item['rucCliente'] ?? '')));
                $nomCliente = mb_strtoupper(trim((string)($item['nomCliente'] ?? '')), 'UTF-8');
                $tipoDocCliente = strtoupper(trim((string)($item['tipoDocCliente'] ?? 'RUC'))) ?: 'RUC';

                // Detectar si el comprobante viene marcado como Anulado desde la planilla o grilla
                $rawAnulada = strtoupper(trim((string)($item['anulada'] ?? 'N')));
                $esAnulada = ($rawAnulada === 'S' || $rawAnulada === 'SI' || $rawAnulada === '1' || ($item['anulada'] ?? false) === true);
                $anulada = $esAnulada ? 'S' : 'N';

                // Si está anulado, asegurar que no falle por falta de RUC o Nombre
                if ($esAnulada) {
                    if ($rawRuc === '') $rawRuc = 'X';
                    if ($nomCliente === '') $nomCliente = 'ANULADO';
                }

                // Detectar si es Cliente X / Sin Nombre
                $isClienteX = ($rawRuc === 'X' || $rawRuc === 'SIN RUC' || $rawRuc === '' || $nomCliente === 'SIN NOMBRE' || $nomCliente === 'CLIENTE X' || $nomCliente === 'X' || $nomCliente === 'ANULADO');
                if ($isClienteX) {
                    $rawRuc = 'X';
                    $nomCliente = ($nomCliente !== '' && $nomCliente !== 'X') ? $nomCliente : ($esAnulada ? 'ANULADO' : 'SIN NOMBRE');
                    $tipoDocCliente = 'CI';
                }

                // Detectar si es CI (sin guión y puramente numérico, o declarado como CI / CEDULA)
                $isCi = ($tipoDocCliente === 'CI' || $tipoDocCliente === 'CEDULA' || (!str_contains($rawRuc, '-') && preg_match('/^\d+$/', $rawRuc)));
                if ($isCi && !$isClienteX) {
                    $tipoDocCliente = 'CI';
                }

                if ($nroFactura === '' || $nroTimbrado === '' || !$fecIngreso || ($rawRuc === '' && !$isClienteX) || ($nomCliente === '' && !$isClienteX)) {
                    continue;
                }

                $rucConDv = $isClienteX ? 'X' : self::normalizarRucConDv($rawRuc, $cdc);
                if ($isClienteX) {
                    $rucFinal = 'X';
                    $rucBase = 'X';
                } elseif ($isCi && !str_contains($rawRuc, '-')) {
                    $rucFinal = $rawRuc;
                    $rucBase = ltrim(preg_replace('/\D/', '', $rawRuc), '0');
                } else {
                    $rucFinal = $rucConDv;
                    $rucBase = ltrim(preg_replace('/\D/', '', explode('-', $rucConDv)[0]), '0');
                }

                $facClean = str_replace('-', '', $nroFactura);
                $dupKey1 = $nroTimbrado . '|' . $facClean . '|' . $rucBase;
                $dupKey2 = $nroTimbrado . '|' . $facClean;

                if (($cdc !== '' && isset($existingCdcs[$cdc])) || isset($existingKeys[$dupKey1]) || isset($existingKeys[$dupKey2])) {
                    $duplicadosOmitidos++;
                    continue;
                }

                // Verificar o crear cliente automáticamente si aún no existe
                if ($rucBase !== '' && isset($cliCache[$rucBase])) {
                    $codCliente = $cliCache[$rucBase]['codCliente'];
                    $rucFinal   = $cliCache[$rucBase]['ruc'] ?: $rucFinal;
                } else {
                    $codPersona = \App\Services\PersonaService::syncPersonaData(null, $nomCliente, $rucFinal, $tipoDocCliente);
                    $nextCliId  = (int)Database::queryForValue(
                        "SELECT COALESCE(MAX(CAST(cod_cliente AS UNSIGNED)), 0) + 1
                         FROM co_clientes
                         WHERE id_propietario = ? AND CAST(cod_cliente AS UNSIGNED) < 3000",
                        [$idProp]
                    );
                    $codCliente = (string)max(1, $nextCliId);
                    try {
                        Database::update(
                            "INSERT INTO co_clientes (id_propietario, cod_cliente, cod_empresa, cod_persona, nombre, ruc, tipo_documento, estado, usuario_reg, fec_reg)
                             VALUES (?, ?, ?, ?, ?, ?, ?, 'A', ?, NOW())",
                            [$idProp, $codCliente, $empresa, $codPersona, $nomCliente, $rucFinal, $tipoDocCliente, $usuario]
                        );
                    } catch (\Throwable $eCli) {
                        try {
                            Database::update(
                                "INSERT INTO co_clientes (id_propietario, cod_cliente, cod_empresa, cod_persona, nombre, ruc, estado, usuario_reg, fec_reg)
                                 VALUES (?, ?, ?, ?, ?, ?, 'A', ?, NOW())",
                                [$idProp, $codCliente, $empresa, $codPersona, $nomCliente, $rucFinal, $usuario]
                            );
                        } catch (\Throwable $e2) {}
                    }
                    if ($rucBase !== '') {
                        $cliCache[$rucBase] = [
                            'codCliente' => $codCliente,
                            'nombre'     => $nomCliente,
                            'ruc'        => $rucFinal,
                        ];
                    }
                    $clientesCreados++;
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
                $valorBruto = $grav10 + $grav5 + $exentas;
                $valorTotal = round((float)($item['valorTotal'] ?? $valorBruto));

                $maxId++;
                Database::update(
                    "INSERT INTO co_ingresos (
                        id_propietario, id_ingreso, cod_empresa, cdc, tip_doc, cod_regimen, regimen,
                        anulada, inutilizado, nro_timbrado, venc_timbrado, nro_factura, serie, fec_ingreso,
                        condicion, cod_cliente, nom_cliente, ruc_cliente, cod_tipo_ingreso, tip_ingreso,
                        form_145, cod_moneda, tasa_cambio, grav10, iva10, grav5, iva5, exentas, tot_iva,
                        valor_bruto, no_gravado, redondeo, valor_total, observacion, estado, usuario_reg
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'N', ?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?, 'Original', ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, '', 'A', ?)",
                    [
                        $idProp, $maxId, $empresa, $cdc ?: null, $tipDoc, $codRegimen, $regimen,
                        $anulada,
                        $nroTimbrado, $vencTimbrado, $nroFactura, $fecIngreso,
                        $condicion, $codCliente, $nomCliente, $rucFinal, $codTipoIngreso, $tipIngreso,
                        $codMoneda, $tasaCambio, $grav10, $iva10, $grav5, $iva5, $exentas, $totIva,
                        $valorBruto, $valorTotal, $usuario
                    ]
                );

                if ($cdc !== '') $existingCdcs[$cdc] = true;
                $existingKeys[$dupKey1] = true;
                $existingKeys[$dupKey2] = true;
                $insertados++;
                if ($esAnulada) $anuladosInsertados++;

                if ($minFecha === null || $fecIngreso < $minFecha) $minFecha = $fecIngreso;
                if ($maxFecha === null || $fecIngreso > $maxFecha) $maxFecha = $fecIngreso;
            }

            View::json([
                'status'             => 'success',
                'insertados'         => $insertados,
                'anulados'           => $anuladosInsertados,
                'clientesCreados'    => $clientesCreados,
                'duplicadosOmitidos' => $duplicadosOmitidos,
                'minFecha'           => $minFecha,
                'maxFecha'           => $maxFecha,
                'message'            => "Se importaron {$insertados} comprobante(s) de ingreso exitosamente"
                                      . ($anuladosInsertados > 0 ? " ({$anuladosInsertados} anulados guardados correctamente)" : "")
                                      . ($clientesCreados > 0 ? " y se crearon {$clientesCreados} nuevo(s) cliente(s)." : "."),
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al importar lote de ingresos: ' . $e->getMessage()]);
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
        $fechaDesde  = trim((string)($_GET['fechaDesde'] ?? ''));
        $fechaHasta  = trim((string)($_GET['fechaHasta'] ?? ''));
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

        if ($fechaDesde !== '') {
            $sql .= "AND c.fec_asiento >= ? ";
            $params[] = $fechaDesde;
        }
        if ($fechaHasta !== '') {
            $sql .= "AND c.fec_asiento <= ? ";
            $params[] = $fechaHasta;
        }

        if ($fechaDesde === '' && $fechaHasta === '') {
            if ($ejercicio !== null && $ejercicio > 0) {
                $sql .= "AND c.ejercicio = ? ";
                $params[] = $ejercicio;
            }
            if ($mes !== null && $mes >= 1 && $mes <= 12) {
                $sql .= "AND MONTH(c.fec_asiento) = ? ";
                $params[] = $mes;
            }
        } elseif ($ejercicio !== null && $ejercicio > 0) {
            $sql .= "AND c.ejercicio = ? ";
            $params[] = $ejercicio;
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
        $ejercicio = isset($_GET['ejercicio']) && (int)$_GET['ejercicio'] > 0
            ? (int)$_GET['ejercicio']
            : (int)TenantContext::ejercicio();

        try {
            $params = [$idProp, $codEmpresa, $num];
            $sqlEjer = "";
            if ($ejercicio > 0) {
                $sqlEjer = " AND ejercicio = ?";
                $params[] = $ejercicio;
            }
            $cabList = Database::queryForListUpper(
                "SELECT cod_empresa, num_asiento, DATE_FORMAT(fec_asiento, '%Y-%m-%d') AS fec_asiento,
                        ejercicio, cod_sucursal, origen_comprobante, id_comprobante, concepto,
                        total_debe, total_haber, estado, usuario_reg
                 FROM co_asientos_cab
                 WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ? {$sqlEjer}
                 ORDER BY ejercicio DESC LIMIT 1",
                $params
            );
            if (empty($cabList) && $ejercicio > 0) {
                // Reintentar sin restricción de ejercicio por si el comprobante pertenecía a otro año
                $cabList = Database::queryForListUpper(
                    "SELECT cod_empresa, num_asiento, DATE_FORMAT(fec_asiento, '%Y-%m-%d') AS fec_asiento,
                            ejercicio, cod_sucursal, origen_comprobante, id_comprobante, concepto,
                            total_debe, total_haber, estado, usuario_reg
                     FROM co_asientos_cab
                     WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?
                     ORDER BY ejercicio DESC LIMIT 1",
                    [$idProp, $codEmpresa, $num]
                );
            }
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
        $ejercicio = isset($_GET['ejercicio']) && (int)$_GET['ejercicio'] > 0
            ? (int)$_GET['ejercicio']
            : (int)TenantContext::ejercicio();
        if ($ejercicio < 1990 || $ejercicio > 2100) $ejercicio = (int)date('Y');

        $maxNum = (int)Database::queryForValue(
            "SELECT COALESCE(MAX(num_asiento), 0) + 1 FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ? AND ejercicio = ?",
            [$idProp, $codEmpresa, $ejercicio]
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
                    "SELECT COALESCE(MAX(num_asiento), 0) + 1 FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ? AND ejercicio = ?",
                    [$idProp, $codEmpresa, $ejercicio]
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

            // Si el asiento proviene de un comprobante de egreso o ingreso, asociar el identificador de asiento
            if ($origenComprobante === 'COEGRES' && ctype_digit($idComprobante)) {
                Database::update(
                    "UPDATE co_egresos SET asiento = ? WHERE id_propietario = ? AND cod_empresa = ? AND id_egreso = ?",
                    [$numAsiento, $idProp, $codEmpresa, (int)$idComprobante]
                );
            } elseif ($origenComprobante === 'COINGRES' && ctype_digit($idComprobante)) {
                Database::update(
                    "UPDATE co_ingresos SET asiento = ? WHERE id_propietario = ? AND cod_empresa = ? AND id_ingreso = ?",
                    [$numAsiento, $idProp, $codEmpresa, (int)$idComprobante]
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
            Database::update(
                "UPDATE co_egresos SET asiento = NULL WHERE id_propietario = ? AND cod_empresa = ? AND asiento = ?",
                [$idProp, $codEmpresa, $numAsiento]
            );
            Database::update(
                "UPDATE co_ingresos SET asiento = NULL WHERE id_propietario = ? AND cod_empresa = ? AND asiento = ?",
                [$idProp, $codEmpresa, $numAsiento]
            );
            View::json(['status' => 'success', 'message' => "Asiento Contable N° {$numAsiento} anulado correctamente."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al anular el asiento: ' . $e->getMessage()]);
        }
    }

    public static function apiAsientosEliminar(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $payload = self::getInput();

        $numAsiento = (int)($payload['numAsiento'] ?? 0);
        if ($numAsiento <= 0) {
            View::json(['status' => 'error', 'message' => 'Número de asiento no especificado.']);
            return;
        }

        try {
            Database::update(
                "UPDATE co_egresos SET asiento = NULL WHERE id_propietario = ? AND cod_empresa = ? AND asiento = ?",
                [$idProp, $codEmpresa, $numAsiento]
            );
            Database::update(
                "UPDATE co_ingresos SET asiento = NULL WHERE id_propietario = ? AND cod_empresa = ? AND asiento = ?",
                [$idProp, $codEmpresa, $numAsiento]
            );
            Database::update(
                "DELETE FROM co_asientos_det WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?",
                [$idProp, $codEmpresa, $numAsiento]
            );
            Database::update(
                "DELETE FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?",
                [$idProp, $codEmpresa, $numAsiento]
            );
            View::json(['status' => 'success', 'message' => "Asiento Contable N° {$numAsiento} eliminado correctamente y su comprobante fue liberado."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar el asiento: ' . $e->getMessage()]);
        }
    }

    public static function apiAsientosEliminarFiltrados(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $payload = self::getInput();

        $numAsientos = is_array($payload['numAsientos'] ?? null) ? $payload['numAsientos'] : [];
        $ejercicio   = isset($payload['ejercicio']) && (int)$payload['ejercicio'] > 0 ? (int)$payload['ejercicio'] : null;
        $fechaDesde  = trim((string)($payload['fechaDesde'] ?? ''));
        $fechaHasta  = trim((string)($payload['fechaHasta'] ?? ''));
        $origen      = trim((string)($payload['origen'] ?? ''));
        $search      = trim((string)($payload['search'] ?? ''));
        $codSucursal = trim((string)($payload['codSucursal'] ?? ''));

        // Si no se pasaron números explícitos, buscarlos usando los filtros
        if (empty($numAsientos)) {
            $sqlFind = "SELECT num_asiento FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ? ";
            $paramsFind = [$idProp, $codEmpresa];
            if ($ejercicio !== null) {
                $sqlFind .= "AND ejercicio = ? ";
                $paramsFind[] = $ejercicio;
            }
            if ($fechaDesde !== '') {
                $sqlFind .= "AND fec_asiento >= ? ";
                $paramsFind[] = $fechaDesde;
            }
            if ($fechaHasta !== '') {
                $sqlFind .= "AND fec_asiento <= ? ";
                $paramsFind[] = $fechaHasta;
            }
            if ($origen !== '' && strcasecmp($origen, 'TODOS') !== 0) {
                $sqlFind .= "AND UPPER(origen_comprobante) = ? ";
                $paramsFind[] = strtoupper($origen);
            }
            if ($codSucursal !== '' && strcasecmp($codSucursal, 'TODAS') !== 0) {
                $sqlFind .= "AND cod_sucursal = ? ";
                $paramsFind[] = $codSucursal;
            }
            if ($search !== '') {
                $sqlFind .= "AND (UPPER(concepto) LIKE ? OR UPPER(id_comprobante) LIKE ? OR CAST(num_asiento AS CHAR) LIKE ?) ";
                $pat = '%' . strtoupper($search) . '%';
                $paramsFind[] = $pat; $paramsFind[] = $pat; $paramsFind[] = $pat;
            }
            $rows = Database::queryForList($sqlFind, $paramsFind);
            $numAsientos = array_map('intval', array_column($rows, 'num_asiento'));
        }

        if (empty($numAsientos)) {
            View::json(['status' => 'error', 'message' => 'No se encontraron asientos contables para eliminar con los filtros seleccionados.']);
            return;
        }

        try {
            $cantTotal = count($numAsientos);
            $chunks = array_chunk(array_map('intval', $numAsientos), 500);

            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $params = array_merge([$idProp, $codEmpresa], $chunk);

                // 1. Desvincular en co_egresos
                Database::update(
                    "UPDATE co_egresos SET asiento = NULL WHERE id_propietario = ? AND cod_empresa = ? AND asiento IN ($placeholders)",
                    $params
                );

                // 2. Desvincular en co_ingresos
                Database::update(
                    "UPDATE co_ingresos SET asiento = NULL WHERE id_propietario = ? AND cod_empresa = ? AND asiento IN ($placeholders)",
                    $params
                );

                // 3. Eliminar líneas de detalle
                Database::update(
                    "DELETE FROM co_asientos_det WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento IN ($placeholders)",
                    $params
                );

                // 4. Eliminar cabeceras de asientos
                Database::update(
                    "DELETE FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento IN ($placeholders)",
                    $params
                );
            }

            View::json([
                'status'   => 'success',
                'cantidad' => $cantTotal,
                'message'  => "Se eliminaron {$cantTotal} asiento(s) contable(s) correctamente y se liberaron sus comprobantes vinculados para nueva contabilización.",
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar asientos: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API FILTRO DE ASIENTOS Y GENERACIÓN AUTOMÁTICA
    // =========================================================================
    public static function apiAsientosPendientesResumen(): void {
        if (!self::requireAuth()) return;
        self::asegurarColumnaAsiento();

        $idProp  = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();

        $fechaDesde = self::parseSqlDate((string)($_GET['fechaDesde'] ?? $_GET['desde'] ?? ''));
        $fechaHasta = self::parseSqlDate((string)($_GET['fechaHasta'] ?? $_GET['hasta'] ?? ''));
        $anio       = trim((string)($_GET['anio'] ?? ''));
        $mes        = trim((string)($_GET['mes'] ?? ''));
        $incluirLiq = !empty($_GET['incluirLiq']) && in_array(strtolower((string)$_GET['incluirLiq']), ['1', 'true', 's', 'on', 'yes'], true);

        try {
            // Egresos (Compras)
            $whereEg = "id_propietario = ? AND cod_empresa = ? AND COALESCE(estado, 'A') = 'A' ";
            $paramsEg = [$idProp, $empresa];
            if ($fechaDesde !== null && $fechaDesde !== '') {
                $whereEg .= "AND fec_egreso >= ? ";
                $paramsEg[] = $fechaDesde . ' 00:00:00';
            }
            if ($fechaHasta !== null && $fechaHasta !== '') {
                $whereEg .= "AND fec_egreso <= ? ";
                $paramsEg[] = $fechaHasta . ' 23:59:59';
            }
            if (($fechaDesde === null || $fechaDesde === '') && ($fechaHasta === null || $fechaHasta === '')) {
                if ($anio !== '' && strcasecmp($anio, 'TODOS') !== 0) {
                    $whereEg .= "AND YEAR(fec_egreso) = ? ";
                    $paramsEg[] = (int)$anio;
                }
                if ($mes !== '' && strcasecmp($mes, 'TODOS') !== 0) {
                    $whereEg .= "AND MONTH(fec_egreso) = ? ";
                    $paramsEg[] = (int)$mes;
                }
            }

            $rowEg = Database::queryForMap(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN asiento IS NOT NULL AND asiento > 0 THEN 1 ELSE 0 END) AS contabilizados,
                        SUM(CASE WHEN asiento IS NULL OR asiento = 0 THEN 1 ELSE 0 END) AS pendientes,
                        COALESCE(SUM(CASE WHEN asiento IS NULL OR asiento = 0 THEN valor_total ELSE 0 END), 0) AS monto_pendiente
                 FROM co_egresos WHERE {$whereEg}",
                $paramsEg
            ) ?: ['total' => 0, 'contabilizados' => 0, 'pendientes' => 0, 'monto_pendiente' => 0];

            // Ingresos (Ventas y Liquidaciones de Salario opcionales)
            $whereIn = "id_propietario = ? AND cod_empresa = ? AND COALESCE(anulada, 'N') <> 'S' AND COALESCE(inutilizado, 'N') <> 'S' AND COALESCE(estado, 'A') = 'A' ";
            if (!$incluirLiq) {
                $whereIn .= "AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) NOT IN ('LIQ', 'LIQ.') AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) NOT LIKE '%LIQ%' ";
            }
            $paramsIn = [$idProp, $empresa];
            if ($fechaDesde !== null && $fechaDesde !== '') {
                $whereIn .= "AND fec_ingreso >= ? ";
                $paramsIn[] = $fechaDesde . ' 00:00:00';
            }
            if ($fechaHasta !== null && $fechaHasta !== '') {
                $whereIn .= "AND fec_ingreso <= ? ";
                $paramsIn[] = $fechaHasta . ' 23:59:59';
            }
            if (($fechaDesde === null || $fechaDesde === '') && ($fechaHasta === null || $fechaHasta === '')) {
                if ($anio !== '' && strcasecmp($anio, 'TODOS') !== 0) {
                    $whereIn .= "AND YEAR(fec_ingreso) = ? ";
                    $paramsIn[] = (int)$anio;
                }
                if ($mes !== '' && strcasecmp($mes, 'TODOS') !== 0) {
                    $whereIn .= "AND MONTH(fec_ingreso) = ? ";
                    $paramsIn[] = (int)$mes;
                }
            }

            $rowIn = Database::queryForMap(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN asiento IS NOT NULL AND asiento > 0 THEN 1 ELSE 0 END) AS contabilizados,
                        SUM(CASE WHEN asiento IS NULL OR asiento = 0 THEN 1 ELSE 0 END) AS pendientes,
                        COALESCE(SUM(CASE WHEN asiento IS NULL OR asiento = 0 THEN valor_total ELSE 0 END), 0) AS monto_pendiente
                 FROM co_ingresos WHERE {$whereIn}",
                $paramsIn
            ) ?: ['total' => 0, 'contabilizados' => 0, 'pendientes' => 0, 'monto_pendiente' => 0];

            View::json([
                'status'   => 'success',
                'incluirLiq' => $incluirLiq,
                'egresos'  => [
                    'total'          => (int)($rowEg['total'] ?? 0),
                    'contabilizados' => (int)($rowEg['contabilizados'] ?? 0),
                    'pendientes'     => (int)($rowEg['pendientes'] ?? 0),
                    'montoPendiente' => (float)($rowEg['monto_pendiente'] ?? 0),
                ],
                'ingresos' => [
                    'total'          => (int)($rowIn['total'] ?? 0),
                    'contabilizados' => (int)($rowIn['contabilizados'] ?? 0),
                    'pendientes'     => (int)($rowIn['pendientes'] ?? 0),
                    'montoPendiente' => (float)($rowIn['monto_pendiente'] ?? 0),
                ],
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al obtener resumen de pendientes: ' . $e->getMessage()]);
        }
    }

    public static function apiAsientosPendientesDetalle(): void {
        if (!self::requireAuth()) return;
        self::asegurarColumnaAsiento();

        $idProp  = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();

        $tipo       = strtoupper(trim((string)($_GET['tipo'] ?? 'TODOS')));
        $fechaDesde = self::parseSqlDate((string)($_GET['fechaDesde'] ?? $_GET['desde'] ?? ''));
        $fechaHasta = self::parseSqlDate((string)($_GET['fechaHasta'] ?? $_GET['hasta'] ?? ''));
        $anio       = trim((string)($_GET['anio'] ?? ''));
        $mes        = trim((string)($_GET['mes'] ?? ''));
        $incluirLiq = !empty($_GET['incluirLiq']) && in_array(strtolower((string)$_GET['incluirLiq']), ['1', 'true', 's', 'on', 'yes'], true);

        $items = [];

        try {
            if ($tipo === 'TODOS' || $tipo === 'EGRESOS') {
                $whereEg = "id_propietario = ? AND cod_empresa = ? AND COALESCE(estado, 'A') = 'A' AND (asiento IS NULL OR asiento = 0) ";
                $paramsEg = [$idProp, $empresa];
                if ($fechaDesde !== null && $fechaDesde !== '') {
                    $whereEg .= "AND fec_egreso >= ? ";
                    $paramsEg[] = $fechaDesde . ' 00:00:00';
                }
                if ($fechaHasta !== null && $fechaHasta !== '') {
                    $whereEg .= "AND fec_egreso <= ? ";
                    $paramsEg[] = $fechaHasta . ' 23:59:59';
                }
                if (($fechaDesde === null || $fechaDesde === '') && ($fechaHasta === null || $fechaHasta === '')) {
                    if ($anio !== '' && strcasecmp($anio, 'TODOS') !== 0) {
                        $whereEg .= "AND YEAR(fec_egreso) = ? ";
                        $paramsEg[] = (int)$anio;
                    }
                    if ($mes !== '' && strcasecmp($mes, 'TODOS') !== 0) {
                        $whereEg .= "AND MONTH(fec_egreso) = ? ";
                        $paramsEg[] = (int)$mes;
                    }
                }
                $rowsEg = Database::queryForList(
                    "SELECT id_egreso AS id, 'EGRESOS' AS tipo, tip_doc,
                            DATE_FORMAT(fec_egreso, '%Y-%m-%d') AS fecha,
                            DATE_FORMAT(fec_egreso, '%d/%m/%Y') AS fecha_fmt,
                            nro_factura, nro_timbrado, ruc_proveedor AS ruc,
                            nom_proveedor AS razon_social, condicion,
                            COALESCE(grav10, 0) AS grav10, COALESCE(iva10, 0) AS iva10,
                            COALESCE(grav5, 0) AS grav5, COALESCE(iva5, 0) AS iva5,
                            COALESCE(exentas, 0) AS exentas, COALESCE(valor_total, 0) AS valor_total,
                            asiento
                     FROM co_egresos
                     WHERE {$whereEg}
                     ORDER BY fec_egreso ASC, id_egreso ASC",
                    $paramsEg
                );
                foreach ($rowsEg as $r) {
                    $r['tipo_lbl'] = 'Egreso / Compra';
                    $r['tipo_badge'] = 'danger';
                    $items[] = $r;
                }
            }

            if ($tipo === 'TODOS' || $tipo === 'INGRESOS') {
                $whereIn = "id_propietario = ? AND cod_empresa = ? AND COALESCE(anulada, 'N') <> 'S' AND COALESCE(inutilizado, 'N') <> 'S' AND COALESCE(estado, 'A') = 'A' AND (asiento IS NULL OR asiento = 0) ";
                if (!$incluirLiq) {
                    $whereIn .= "AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) NOT IN ('LIQ', 'LIQ.') AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) NOT LIKE '%LIQ%' ";
                }
                $paramsIn = [$idProp, $empresa];
                if ($fechaDesde !== null && $fechaDesde !== '') {
                    $whereIn .= "AND fec_ingreso >= ? ";
                    $paramsIn[] = $fechaDesde . ' 00:00:00';
                }
                if ($fechaHasta !== null && $fechaHasta !== '') {
                    $whereIn .= "AND fec_ingreso <= ? ";
                    $paramsIn[] = $fechaHasta . ' 23:59:59';
                }
                if (($fechaDesde === null || $fechaDesde === '') && ($fechaHasta === null || $fechaHasta === '')) {
                    if ($anio !== '' && strcasecmp($anio, 'TODOS') !== 0) {
                        $whereIn .= "AND YEAR(fec_ingreso) = ? ";
                        $paramsIn[] = (int)$anio;
                    }
                    if ($mes !== '' && strcasecmp($mes, 'TODOS') !== 0) {
                        $whereIn .= "AND MONTH(fec_ingreso) = ? ";
                        $paramsIn[] = (int)$mes;
                    }
                }
                $rowsIn = Database::queryForList(
                    "SELECT id_ingreso AS id, 'INGRESOS' AS tipo, tip_doc,
                            DATE_FORMAT(fec_ingreso, '%Y-%m-%d') AS fecha,
                            DATE_FORMAT(fec_ingreso, '%d/%m/%Y') AS fecha_fmt,
                            nro_factura, nro_timbrado, ruc_cliente AS ruc,
                            nom_cliente AS razon_social, condicion,
                            COALESCE(grav10, 0) AS grav10, COALESCE(iva10, 0) AS iva10,
                            COALESCE(grav5, 0) AS grav5, COALESCE(iva5, 0) AS iva5,
                            COALESCE(exentas, 0) AS exentas, COALESCE(valor_total, 0) AS valor_total,
                            asiento
                     FROM co_ingresos
                     WHERE {$whereIn}
                     ORDER BY fec_ingreso ASC, id_ingreso ASC",
                    $paramsIn
                );
                foreach ($rowsIn as $r) {
                    $isLiq = self::isLiquidacionSalario($r['tip_doc']);
                    $r['tipo_lbl'] = $isLiq ? 'Liquidación Salario (LIQ)' : 'Factura de Venta';
                    $r['tipo_badge'] = $isLiq ? 'info' : 'success';
                    $items[] = $r;
                }
            }

            View::json([
                'status'     => 'success',
                'incluirLiq' => $incluirLiq,
                'data'       => $items,
                'count'      => count($items),
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al obtener detalle de comprobantes pendientes: ' . $e->getMessage()]);
        }
    }

    public static function apiAsientosGenerarPendientes(): void {
        if (!self::requireAuth()) return;
        self::asegurarColumnaAsiento();

        $idProp  = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $usuario = TenantContext::usuario();

        $payload    = self::getInput();
        $tipo       = strtoupper(trim((string)($payload['tipo'] ?? 'TODOS')));
        $fechaDesde = self::parseSqlDate((string)($payload['fechaDesde'] ?? $payload['desde'] ?? ''));
        $fechaHasta = self::parseSqlDate((string)($payload['fechaHasta'] ?? $payload['hasta'] ?? ''));
        $anio       = trim((string)($payload['anio'] ?? ''));
        $mes        = trim((string)($payload['mes'] ?? ''));
        $incluirLiq = !empty($payload['incluirLiq']) && in_array(strtolower((string)$payload['incluirLiq']), ['1', 'true', 's', 'on', 'yes'], true);
        $ids        = $payload['ids'] ?? null;

        $generadosEg = 0;
        $generadosIn = 0;
        $asientosNumeros = [];

        try {
            if ($tipo === 'TODOS' || $tipo === 'EGRESOS') {
                $whereEg = "id_propietario = ? AND cod_empresa = ? AND COALESCE(estado, 'A') = 'A' AND (asiento IS NULL OR asiento = 0) ";
                $paramsEg = [$idProp, $empresa];
                if (is_array($ids) && !empty($ids) && ($tipo === 'EGRESOS')) {
                    $cleanIds = array_map('intval', $ids);
                    $inPlaceholders = implode(',', array_fill(0, count($cleanIds), '?'));
                    $whereEg .= "AND id_egreso IN ($inPlaceholders) ";
                    $paramsEg = array_merge($paramsEg, $cleanIds);
                } else {
                    if ($fechaDesde !== null && $fechaDesde !== '') {
                        $whereEg .= "AND fec_egreso >= ? ";
                        $paramsEg[] = $fechaDesde . ' 00:00:00';
                    }
                    if ($fechaHasta !== null && $fechaHasta !== '') {
                        $whereEg .= "AND fec_egreso <= ? ";
                        $paramsEg[] = $fechaHasta . ' 23:59:59';
                    }
                    if (($fechaDesde === null || $fechaDesde === '') && ($fechaHasta === null || $fechaHasta === '')) {
                        if ($anio !== '' && strcasecmp($anio, 'TODOS') !== 0) {
                            $whereEg .= "AND YEAR(fec_egreso) = ? ";
                            $paramsEg[] = (int)$anio;
                        }
                        if ($mes !== '' && strcasecmp($mes, 'TODOS') !== 0) {
                            $whereEg .= "AND MONTH(fec_egreso) = ? ";
                            $paramsEg[] = (int)$mes;
                        }
                    }
                }

                $egresos = Database::queryForList("SELECT id_egreso FROM co_egresos WHERE {$whereEg} ORDER BY fec_egreso ASC, id_egreso ASC", $paramsEg);
                foreach ($egresos as $row) {
                    $idEg = (int)$row['id_egreso'];
                    $numAs = self::generarAsientoParaEgresoId($idProp, $empresa, $idEg, $usuario);
                    if ($numAs !== null && $numAs > 0) {
                        $generadosEg++;
                        $asientosNumeros[] = $numAs;
                    }
                }
            }

            if ($tipo === 'TODOS' || $tipo === 'INGRESOS') {
                $whereIn = "id_propietario = ? AND cod_empresa = ? AND COALESCE(anulada, 'N') <> 'S' AND COALESCE(inutilizado, 'N') <> 'S' AND COALESCE(estado, 'A') = 'A' AND (asiento IS NULL OR asiento = 0) ";
                $paramsIn = [$idProp, $empresa];
                if (is_array($ids) && !empty($ids) && ($tipo === 'INGRESOS')) {
                    $cleanIds = array_map('intval', $ids);
                    $inPlaceholders = implode(',', array_fill(0, count($cleanIds), '?'));
                    $whereIn .= "AND id_ingreso IN ($inPlaceholders) ";
                    $paramsIn = array_merge($paramsIn, $cleanIds);
                } else {
                    if (!$incluirLiq) {
                        $whereIn .= "AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) NOT IN ('LIQ', 'LIQ.') AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) NOT LIKE '%LIQ%' ";
                    }
                    if ($fechaDesde !== null && $fechaDesde !== '') {
                        $whereIn .= "AND fec_ingreso >= ? ";
                        $paramsIn[] = $fechaDesde . ' 00:00:00';
                    }
                    if ($fechaHasta !== null && $fechaHasta !== '') {
                        $whereIn .= "AND fec_ingreso <= ? ";
                        $paramsIn[] = $fechaHasta . ' 23:59:59';
                    }
                    if (($fechaDesde === null || $fechaDesde === '') && ($fechaHasta === null || $fechaHasta === '')) {
                        if ($anio !== '' && strcasecmp($anio, 'TODOS') !== 0) {
                            $whereIn .= "AND YEAR(fec_ingreso) = ? ";
                            $paramsIn[] = (int)$anio;
                        }
                        if ($mes !== '' && strcasecmp($mes, 'TODOS') !== 0) {
                            $whereIn .= "AND MONTH(fec_ingreso) = ? ";
                            $paramsIn[] = (int)$mes;
                        }
                    }
                }

                $ingresos = Database::queryForList("SELECT id_ingreso FROM co_ingresos WHERE {$whereIn} ORDER BY fec_ingreso ASC, id_ingreso ASC", $paramsIn);
                foreach ($ingresos as $row) {
                    $idIn = (int)$row['id_ingreso'];
                    $numAs = self::generarAsientoParaIngresoId($idProp, $empresa, $idIn, $usuario);
                    if ($numAs !== null && $numAs > 0) {
                        $generadosIn++;
                        $asientosNumeros[] = $numAs;
                    }
                }
            }

            $total = $generadosEg + $generadosIn;
            $msg = ($total > 0)
                ? "Se generaron {$total} asiento(s) contable(s) con éxito ({$generadosEg} egresos, {$generadosIn} ingresos)."
                : "No se encontraron comprobantes pendientes para contabilizar en el rango seleccionado.";

            $rangoStr = '';
            if (!empty($asientosNumeros)) {
                $minAs = min($asientosNumeros);
                $maxAs = max($asientosNumeros);
                $rangoStr = ($minAs === $maxAs) ? "#{$minAs}" : "Del #{$minAs} al #{$maxAs}";
            }

            View::json([
                'status'            => 'success',
                'totalGenerados'    => $total,
                'generadosEgresos'  => $generadosEg,
                'generadosIngresos' => $generadosIn,
                'rangoAsientos'     => $rangoStr,
                'message'           => $msg,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al generar asientos contables: ' . $e->getMessage()]);
        }
    }

    public static function generarAsientoParaEgresoId(int $idProp, string $empresa, int $idEgreso, string $usuario): ?int {
        $row = Database::queryForMap(
            "SELECT * FROM co_egresos WHERE id_propietario = ? AND cod_empresa = ? AND id_egreso = ? LIMIT 1",
            [$idProp, $empresa, $idEgreso]
        );
        if (!$row) return null;

        $nroFactura = trim((string)($row['nro_factura'] ?? '')) ?: ('#' . $idEgreso);
        $nomProveedor = trim((string)($row['nom_proveedor'] ?? 'PROVEEDORES VARIOS'));
        $fecEgreso = $row['fec_egreso'];
        $condicion = strtoupper(trim((string)($row['condicion'] ?? 'CON')));
        $grav10 = (float)($row['grav10'] ?? 0);
        $iva10 = (float)($row['iva10'] ?? 0);
        $grav5 = (float)($row['grav5'] ?? 0);
        $iva5 = (float)($row['iva5'] ?? 0);
        $exentas = (float)($row['exentas'] ?? 0);
        $valorTotal = (float)($row['valor_total'] ?? 0);

        $debeList = [];
        $neto10 = round($grav10 - $iva10, 2);
        if ($neto10 > 0) {
            $ctaG10 = trim((string)($row['cta_grav10'] ?? '')) ?: '01.01.04.01.01 - MERCADERÍAS GRAVADAS POR EL IVA AL 10%';
            $debeList[] = ['cuenta' => $ctaG10, 'debe' => $neto10];
        }
        if ($iva10 > 0) {
            $ctaI10 = trim((string)($row['cta_iva10'] ?? '')) ?: '01.01.03.05.03.01 - IVA CRÉDITO 10%';
            $debeList[] = ['cuenta' => $ctaI10, 'debe' => $iva10];
        }
        $neto5 = round($grav5 - $iva5, 2);
        if ($neto5 > 0) {
            $ctaG5 = trim((string)($row['cta_grav5'] ?? '')) ?: '01.01.04.01.02 - MERCADERÍAS GRAVADAS POR EL IVA AL 5%';
            $debeList[] = ['cuenta' => $ctaG5, 'debe' => $neto5];
        }
        if ($iva5 > 0) {
            $ctaI5 = trim((string)($row['cta_iva5'] ?? '')) ?: '01.01.03.05.03.02 - IVA CRÉDITO 05%';
            $debeList[] = ['cuenta' => $ctaI5, 'debe' => $iva5];
        }
        if ($exentas > 0) {
            $ctaEx = trim((string)($row['cta_exentas'] ?? '')) ?: '01.01.04.01.03 - MERCADERÍAS EXENTAS DEL IVA';
            $debeList[] = ['cuenta' => $ctaEx, 'debe' => $exentas];
        }

        if (empty($debeList) && $valorTotal > 0) {
            $debeList[] = ['cuenta' => '01.01.04.01.01 - MERCADERÍAS GRAVADAS POR EL IVA AL 10%', 'debe' => $valorTotal];
        }

        $sumDebe = array_sum(array_column($debeList, 'debe'));
        $diff = round($valorTotal - $sumDebe, 2);
        if ($diff != 0 && !empty($debeList)) {
            $debeList[0]['debe'] = round($debeList[0]['debe'] + $diff, 2);
        }

        $esCredito = (substr($condicion, 0, 3) === 'CRE');
        $ctaHaber = $esCredito
            ? (trim((string)($row['cta_credito'] ?? '')) ?: '02.01.01.01 - PROVEEDORES LOCALES [ CORTO PLAZO ]')
            : '01.01.01.02.01 - CAJA GUARANÍES';
        $haberList = [['cuenta' => $ctaHaber, 'haber' => $valorTotal]];

        return self::registrarAsientoContableEgreso($idProp, $idEgreso, $empresa, $usuario, $fecEgreso, $nroFactura, $nomProveedor, $debeList, $haberList);
    }

    public static function generarAsientoParaIngresoId(int $idProp, string $empresa, int $idIngreso, string $usuario): ?int {
        $row = Database::queryForMap(
            "SELECT * FROM co_ingresos WHERE id_propietario = ? AND cod_empresa = ? AND id_ingreso = ? LIMIT 1",
            [$idProp, $empresa, $idIngreso]
        );
        if (!$row) return null;
        if (strtoupper((string)($row['anulada'] ?? 'N')) === 'S' || strtoupper((string)($row['inutilizado'] ?? 'N')) === 'S') {
            return null;
        }

        $tipDoc = strtoupper(trim((string)($row['tip_doc'] ?? 'FAC')));
        $esLiq = self::isLiquidacionSalario($tipDoc);
        $nroFactura = trim((string)($row['nro_factura'] ?? '')) ?: ('#' . $idIngreso);
        $nomCliente = trim((string)($row['nom_cliente'] ?? 'CLIENTES VARIOS'));
        $fecIngreso = $row['fec_ingreso'];
        $condicion = strtoupper(trim((string)($row['condicion'] ?? 'CON')));
        $grav10 = (float)($row['grav10'] ?? 0);
        $iva10 = (float)($row['iva10'] ?? 0);
        $grav5 = (float)($row['grav5'] ?? 0);
        $iva5 = (float)($row['iva5'] ?? 0);
        $exentas = (float)($row['exentas'] ?? 0);
        $valorTotal = (float)($row['valor_total'] ?? 0);

        if ($esLiq && $exentas <= 0 && $valorTotal > 0) {
            $exentas = $valorTotal;
        }

        return self::registrarAsientoContableIngreso(
            $idProp, $idIngreso, $empresa, $usuario, $fecIngreso, $nroFactura, $nomCliente,
            $condicion, $grav10, $iva10, $grav5, $iva5, $exentas, $valorTotal, [], [], $tipDoc
        );
    }

    public static function registrarAsientoContableIngreso(
        int $idProp, int $idIngreso, string $empresa, string $usuario,
        ?string $fecIngreso, string $nroFactura, string $nomCliente,
        string $condicion, float $grav10, float $iva10, float $grav5, float $iva5, float $exentas, float $valorTotal,
        array $debeList = [], array $haberList = [], ?string $tipDoc = null
    ): ?int {
        try {
            self::asegurarColumnaAsiento();

            $existing = Database::queryForList(
                "SELECT num_asiento FROM co_asientos_cab
                 WHERE id_propietario = ? AND cod_empresa = ? AND origen_comprobante = 'COINGRES' AND id_comprobante = ?",
                [$idProp, $empresa, (string)$idIngreso]
            );
            foreach ($existing as $row) {
                $num = (int)$row['num_asiento'];
                Database::update("DELETE FROM co_asientos_det WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?", [$idProp, $empresa, $num]);
                Database::update("DELETE FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ? AND num_asiento = ?", [$idProp, $empresa, $num]);
            }

            if ($valorTotal <= 0 && empty($debeList) && empty($haberList)) {
                Database::update("UPDATE co_ingresos SET asiento = NULL WHERE id_propietario = ? AND cod_empresa = ? AND id_ingreso = ?", [$idProp, $empresa, $idIngreso]);
                return null;
            }

            $fecUse = $fecIngreso ?: date('Y-m-d');
            $ejercicio = (int)substr($fecUse, 0, 4);

            $numAsiento = (int)Database::queryForValue(
                "SELECT COALESCE(MAX(num_asiento), 0) + 1 FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ? AND ejercicio = ?",
                [$idProp, $empresa, $ejercicio]
            );
            if ($numAsiento <= 0) $numAsiento = 1;

            $totalDebe  = 0.0;
            $totalHaber = 0.0;
            $detRows    = [];
            $secuencia  = 1;
            
            $esLiq = self::isLiquidacionSalario($tipDoc);
            $descBase = $esLiq
                ? "Liquidación de Salario N° {$nroFactura} - {$nomCliente}"
                : "Factura N° {$nroFactura} - {$nomCliente}";

            if (empty($debeList) && empty($haberList)) {
                $esCredito = (strtoupper(substr(trim($condicion), 0, 3)) === 'CRE');
                $ctaDebe = $esCredito ? '01.01.02.01.01 - CLIENTES LOCALES' : '01.01.01.02.01 - CAJA GUARANÍES';
                $debeList[] = ['cuenta' => $ctaDebe, 'debe' => $valorTotal];

                if ($esLiq) {
                    $haberList[] = ['cuenta' => '04.01.01.03.01 - VENTAS Y SERVICIOS EXENTOS', 'haber' => $valorTotal];
                } else {
                    $neto10 = round($grav10 - $iva10, 2);
                    if ($neto10 > 0) {
                        $haberList[] = ['cuenta' => '04.01.01.01.01 - VENTAS DE MERCADERÍAS 10%', 'haber' => $neto10];
                    }
                    if ($iva10 > 0) {
                        $haberList[] = ['cuenta' => '02.01.03.01.01 - IVA DÉBITO FISCAL 10%', 'haber' => $iva10];
                    }
                    $neto5 = round($grav5 - $iva5, 2);
                    if ($neto5 > 0) {
                        $haberList[] = ['cuenta' => '04.01.01.02.01 - VENTAS GRAVADAS AL 5%', 'haber' => $neto5];
                    }
                    if ($iva5 > 0) {
                        $haberList[] = ['cuenta' => '02.01.03.01.02 - IVA DÉBITO FISCAL 5%', 'haber' => $iva5];
                    }
                    if ($exentas > 0) {
                        $haberList[] = ['cuenta' => '04.01.01.03.01 - VENTAS Y SERVICIOS EXENTOS', 'haber' => $exentas];
                    }

                    if (empty($haberList) && $valorTotal > 0) {
                        $haberList[] = ['cuenta' => '04.01.01.01.01 - VENTAS DE MERCADERÍAS 10%', 'haber' => $valorTotal];
                    }

                    $sumHaber = array_sum(array_column($haberList, 'haber'));
                    $diff = round($valorTotal - $sumHaber, 2);
                    if ($diff != 0 && !empty($haberList)) {
                        $haberList[0]['haber'] = round($haberList[0]['haber'] + $diff, 2);
                    }
                }
            }

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
                 ) VALUES (?, ?, ?, ?, ?, '1', 'COINGRES', ?, ?, ?, ?, 'A', ?)",
                [$idProp, $empresa, $numAsiento, $fecUse, $ejercicio, (string)$idIngreso, $descBase, $totalDebe, $totalHaber, $usuario]
            );

            foreach ($detRows as $r) {
                Database::update(
                    "INSERT INTO co_asientos_det (
                        id_propietario, cod_empresa, num_asiento, secuencia, cod_cuenta, nom_cuenta, descripcion, debe, haber
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    $r
                );
            }

            Database::update(
                "UPDATE co_ingresos SET asiento = ? WHERE id_propietario = ? AND cod_empresa = ? AND id_ingreso = ?",
                [$numAsiento, $idProp, $empresa, $idIngreso]
            );

            return $numAsiento;
        } catch (\Throwable $ignored) {
            return null;
        }
    }

    // =========================================================================
    // API RECIBOS DE COBRO (/api/recibos-cobro/*)
    // =========================================================================
    private static function ensureRecibosCobroTables(): void {
        Database::execute("
            CREATE TABLE IF NOT EXISTS `co_recibos_cobro` (
              `id_propietario` int NOT NULL DEFAULT '1',
              `id_recibo` bigint NOT NULL AUTO_INCREMENT,
              `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tip_doc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'REC',
              `anulada` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'N',
              `talonario` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'RECIBO DE COBRO [001-001]',
              `cod_establecimiento` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT '001',
              `cod_punto_expedicion` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT '001',
              `nro_recibo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `serie` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_recibo` datetime DEFAULT NULL,
              `cod_cliente` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `nom_cliente` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `ruc_cliente` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tip_doc_cliente` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'RUC',
              `cod_moneda` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tasa_cambio` decimal(12,4) DEFAULT NULL,
              `valor_total` decimal(15,2) DEFAULT NULL,
              `valor_otras_facturas` decimal(15,2) DEFAULT NULL,
              `total_facturas` decimal(15,2) DEFAULT NULL,
              `diferencia` decimal(15,2) DEFAULT NULL,
              `concepto` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `metodo_pago` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `nro_cheque` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `banco` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `observacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'A',
              `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_reg` datetime DEFAULT NULL,
              PRIMARY KEY (`id_recibo`),
              KEY `idx_co_recibos_cobro_prop` (`id_propietario`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);

        foreach ([
            'concepto'    => "varchar(150) DEFAULT NULL",
            'metodo_pago' => "varchar(100) DEFAULT NULL",
            'nro_cheque'  => "varchar(50) DEFAULT NULL",
            'banco'       => "varchar(100) DEFAULT NULL",
        ] as $colName => $colDef) {
            if (!\Core\DatabaseMigrator::columnExists('co_recibos_cobro', $colName)) {
                Database::execute("ALTER TABLE `co_recibos_cobro` ADD COLUMN `$colName` $colDef", true);
            }
        }

        Database::execute("
            CREATE TABLE IF NOT EXISTS `co_recibos_cobro_det` (
              `id_detalle` bigint NOT NULL AUTO_INCREMENT,
              `id_propietario` int NOT NULL DEFAULT '1',
              `id_recibo` bigint NOT NULL,
              `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `id_ingreso` bigint DEFAULT NULL,
              `tip_doc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'FAC',
              `nro_timbrado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `nro_factura` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `serie` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_ingreso` datetime DEFAULT NULL,
              `valor_total` decimal(15,2) DEFAULT NULL,
              `saldo` decimal(15,2) DEFAULT NULL,
              `importe` decimal(15,2) DEFAULT NULL,
              `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'A',
              `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_reg` datetime DEFAULT NULL,
              PRIMARY KEY (`id_detalle`),
              KEY `idx_co_recibos_cobro_det_prop` (`id_propietario`),
              KEY `idx_co_recibos_cobro_det_rec` (`id_propietario`, `id_recibo`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);
    }

    public static function apiRecibosCobroSiguienteNro(): void {
        if (!self::requireAuth()) return;
        self::ensureRecibosCobroTables();
        $idProp  = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $est     = str_pad(trim((string)($_GET['est'] ?? '001')) ?: '001', 3, '0', STR_PAD_LEFT);
        $pto     = str_pad(trim((string)($_GET['pto'] ?? '001')) ?: '001', 3, '0', STR_PAD_LEFT);

        try {
            $rows = Database::queryForList(
                "SELECT nro_recibo FROM co_recibos_cobro
                 WHERE id_propietario = ? AND cod_empresa = ?
                   AND COALESCE(cod_establecimiento, '001') = ?
                   AND COALESCE(cod_punto_expedicion, '001') = ?",
                [$idProp, $empresa, $est, $pto]
            );
            $maxNro = 0;
            foreach ($rows as $r) {
                $raw = trim((string)($r['nro_recibo'] ?? ''));
                if (str_contains($raw, '-')) {
                    $parts = explode('-', $raw);
                    $last  = (int)end($parts);
                } else {
                    $last  = (int)preg_replace('/\D/', '', $raw);
                }
                if ($last > $maxNro) $maxNro = $last;
            }
            $sig = $maxNro + 1;
            View::json([
                'status'        => 'success',
                'nroSiguiente'  => $sig,
                'nroFormateado' => str_pad((string)$sig, 7, '0', STR_PAD_LEFT),
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'success', 'nroSiguiente' => 1, 'nroFormateado' => '0000001']);
        }
    }

    public static function apiRecibosCobroFacturasPendientes(): void {
        if (!self::requireAuth()) return;
        self::ensureRecibosCobroTables();
        $idProp       = TenantContext::idPropietario();
        $empresa      = TenantContext::empresa();
        $rucCliente   = trim((string)($_GET['rucCliente'] ?? ''));
        $codCliente   = trim((string)($_GET['codCliente'] ?? ''));
        $idReciboExcl = (int)($_GET['idRecibo'] ?? 0);

        try {
            $sql = "SELECT i.id_ingreso,
                           COALESCE(i.tip_doc, 'FAC') AS tip_doc,
                           COALESCE(i.nro_timbrado, '') AS nro_timbrado,
                           i.nro_factura,
                           COALESCE(i.serie, '') AS serie,
                           DATE_FORMAT(i.fec_ingreso, '%Y-%m-%d') AS fec_ingreso,
                           DATE_FORMAT(i.fec_ingreso, '%d/%m/%Y') AS fec_ingreso_fmt,
                           COALESCE(i.ruc_cliente, '') AS ruc_cliente,
                           COALESCE(i.nom_cliente, '') AS nom_cliente,
                           COALESCE(i.cod_cliente, '') AS cod_cliente,
                           COALESCE(i.cod_moneda, '1') AS cod_moneda,
                           COALESCE(i.valor_total, 0) AS valor_total
                    FROM co_ingresos i
                    WHERE i.id_propietario = ?
                      AND i.cod_empresa = ?
                      AND (UPPER(TRIM(i.condicion)) IN ('CRE', 'CREDITO', 'CRÉDITO') OR UPPER(i.condicion) LIKE 'CRE%')
                      AND COALESCE(i.anulada, 'N') <> 'S'
                      AND COALESCE(i.inutilizado, 'N') <> 'S'
                      AND COALESCE(i.estado, 'A') <> 'I' ";
            $params = [$idProp, $empresa];

            if ($rucCliente !== '') {
                $rucNoDash = str_replace(['-', ' '], '', $rucCliente);
                if ($codCliente !== '') {
                    $sql .= "AND (UPPER(TRIM(i.ruc_cliente)) = UPPER(?) OR UPPER(REPLACE(i.ruc_cliente, '-', '')) = UPPER(?) OR TRIM(i.cod_cliente) = ?) ";
                    $params[] = $rucCliente;
                    $params[] = $rucNoDash;
                    $params[] = $codCliente;
                } else {
                    $sql .= "AND (UPPER(TRIM(i.ruc_cliente)) = UPPER(?) OR UPPER(REPLACE(i.ruc_cliente, '-', '')) = UPPER(?)) ";
                    $params[] = $rucCliente;
                    $params[] = $rucNoDash;
                }
            } elseif ($codCliente !== '') {
                $sql .= "AND TRIM(i.cod_cliente) = ? ";
                $params[] = $codCliente;
            }

            $sql .= "ORDER BY i.fec_ingreso DESC, i.id_ingreso DESC";
            $facturas = Database::queryForList($sql, $params);

            // Calcular cobros aplicados previamente a cada factura en recibos activos
            $sqlCobros = "SELECT d.id_ingreso, d.nro_factura, SUM(COALESCE(d.importe, 0)) AS cobrado
                          FROM co_recibos_cobro_det d
                          JOIN co_recibos_cobro c
                            ON c.id_propietario = d.id_propietario AND c.id_recibo = d.id_recibo
                          WHERE d.id_propietario = ?
                            AND d.cod_empresa = ?
                            AND COALESCE(c.anulada, 'N') <> 'S'
                            AND COALESCE(c.estado, 'A') <> 'I' ";
            $paramsCobros = [$idProp, $empresa];
            if ($idReciboExcl > 0) {
                $sqlCobros .= "AND d.id_recibo <> ? ";
                $paramsCobros[] = $idReciboExcl;
            }
            $sqlCobros .= "GROUP BY d.id_ingreso, d.nro_factura";
            $cobrosRows = Database::queryForList($sqlCobros, $paramsCobros);

            $cobradoPorId  = [];
            $cobradoPorDoc = [];
            foreach ($cobrosRows as $cr) {
                $idIng = (int)($cr['id_ingreso'] ?? 0);
                $doc   = strtoupper(trim((string)($cr['nro_factura'] ?? '')));
                $imp   = (float)($cr['cobrado'] ?? 0);
                if ($idIng > 0) {
                    $cobradoPorId[$idIng] = ($cobradoPorId[$idIng] ?? 0.0) + $imp;
                } elseif ($doc !== '') {
                    $cobradoPorDoc[$doc] = ($cobradoPorDoc[$doc] ?? 0.0) + $imp;
                }
            }

            $result = [];
            foreach ($facturas as $f) {
                $idIng   = (int)($f['id_ingreso'] ?? 0);
                $tipDoc  = (string)($f['tip_doc'] ?? 'FAC');
                $nroFac  = self::formatNroFactura((string)($f['nro_factura'] ?? ''), $tipDoc);
                $docKey  = strtoupper(trim($nroFac));
                $total   = (float)($f['valor_total'] ?? 0);
                $cobrado = $cobradoPorId[$idIng] ?? ($cobradoPorDoc[$docKey] ?? 0.0);
                $saldo   = max(0.0, $total - $cobrado);

                $result[] = [
                    'id_ingreso'      => $idIng,
                    'tip_doc'         => $tipDoc,
                    'nro_timbrado'    => (string)($f['nro_timbrado'] ?? ''),
                    'nro_factura'     => $nroFac,
                    'serie'           => (string)($f['serie'] ?? ''),
                    'fec_ingreso'     => (string)($f['fec_ingreso'] ?? ''),
                    'fec_ingreso_fmt' => (string)($f['fec_ingreso_fmt'] ?? ''),
                    'ruc_cliente'     => (string)($f['ruc_cliente'] ?? ''),
                    'nom_cliente'     => (string)($f['nom_cliente'] ?? ''),
                    'cod_cliente'     => (string)($f['cod_cliente'] ?? ''),
                    'cod_moneda'      => (string)($f['cod_moneda'] ?? '1'),
                    'valor_total'     => $total,
                    'cobrado_previo'  => $cobrado,
                    'saldo'           => $saldo,
                ];
            }

            View::json(['status' => 'success', 'data' => $result]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al consultar facturas crédito: ' . $e->getMessage()]);
        }
    }

    public static function apiRecibosCobroList(): void {
        if (!self::requireAuth()) return;
        self::ensureRecibosCobroTables();
        $idProp     = TenantContext::idPropietario();
        $empresa    = TenantContext::empresa();
        $anio       = trim((string)($_GET['anio'] ?? ''));
        $mes        = trim((string)($_GET['mes'] ?? ''));
        $fechaDesde = self::parseSqlDate((string)($_GET['fechaDesde'] ?? $_GET['desde'] ?? ''));
        $fechaHasta = self::parseSqlDate((string)($_GET['fechaHasta'] ?? $_GET['hasta'] ?? ''));
        $filtroAnul = trim((string)($_GET['anulada'] ?? $_GET['estado'] ?? ''));

        try {
            $sql = "SELECT c.id_recibo,
                           c.cod_empresa,
                           COALESCE(c.tip_doc, 'REC') AS tip_doc,
                           c.cod_cliente,
                           c.ruc_cliente,
                           COALESCE(c.tip_doc_cliente, 'RUC') AS tip_doc_cliente,
                           c.nom_cliente,
                           c.talonario,
                           COALESCE(c.cod_establecimiento, '001') AS cod_establecimiento,
                           COALESCE(c.cod_punto_expedicion, '001') AS cod_punto_expedicion,
                           c.nro_recibo,
                           COALESCE(c.serie, '') AS serie,
                           COALESCE(c.anulada, 'N') AS anulada,
                           DATE_FORMAT(c.fec_recibo, '%Y-%m-%d') AS fec_recibo,
                           DATE_FORMAT(c.fec_recibo, '%d/%m/%Y') AS fec_recibo_fmt,
                           COALESCE(c.cod_moneda, '1') AS cod_moneda,
                           COALESCE(c.tasa_cambio, 1) AS tasa_cambio,
                           COALESCE(c.valor_total, 0) AS valor_total,
                           COALESCE(c.valor_otras_facturas, 0) AS valor_otras_facturas,
                           COALESCE(c.total_facturas, 0) AS total_facturas,
                           COALESCE(c.diferencia, 0) AS diferencia,
                           COALESCE(c.concepto, '') AS concepto,
                           COALESCE(c.metodo_pago, '') AS metodo_pago,
                           COALESCE(c.nro_cheque, '') AS nro_cheque,
                           COALESCE(c.banco, '') AS banco,
                           c.observacion,
                           COALESCE(c.estado, 'A') AS estado,
                           c.usuario_reg,
                           DATE_FORMAT(c.fec_reg, '%d/%m/%Y %H:%i') AS fec_reg_fmt,
                           (SELECT GROUP_CONCAT(
                                CONCAT(d.nro_factura, IF(COALESCE(TRIM(d.serie), '') <> '', CONCAT(' [Serie ', TRIM(d.serie), ']'), ''))
                                SEPARATOR ', '
                            )
                            FROM co_recibos_cobro_det d
                            WHERE d.id_propietario = c.id_propietario AND d.id_recibo = c.id_recibo
                           ) AS facturas_rel,
                           (SELECT COUNT(*)
                            FROM co_recibos_cobro_det d2
                            WHERE d2.id_propietario = c.id_propietario AND d2.id_recibo = c.id_recibo
                           ) AS cant_facturas
                    FROM co_recibos_cobro c
                    WHERE c.id_propietario = ? AND c.cod_empresa = ? ";
            $params = [$idProp, $empresa];

            if ($fechaDesde !== null) {
                $sql .= "AND DATE(c.fec_recibo) >= ? ";
                $params[] = $fechaDesde;
            }
            if ($fechaHasta !== null) {
                $sql .= "AND DATE(c.fec_recibo) <= ? ";
                $params[] = $fechaHasta;
            }
            if ($fechaDesde === null && $fechaHasta === null) {
                if ($anio !== '' && strcasecmp($anio, 'TODOS') !== 0) {
                    $sql .= "AND YEAR(c.fec_recibo) = ? ";
                    $params[] = (int)$anio;
                }
                if ($mes !== '' && strcasecmp($mes, 'TODOS') !== 0) {
                    $sql .= "AND MONTH(c.fec_recibo) = ? ";
                    $params[] = (int)$mes;
                }
            }
            $sql .= "ORDER BY c.fec_recibo DESC, c.id_recibo DESC";

            $list = Database::queryForListUpper($sql, $params);
            View::json(['status' => 'success', 'data' => $list]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al listar recibos de cobro: ' . $e->getMessage()]);
        }
    }

    public static function apiRecibosCobroGet(string $id): void {
        if (!self::requireAuth()) return;
        self::ensureRecibosCobroTables();
        $idProp   = TenantContext::idPropietario();
        $empresa  = TenantContext::empresa();
        $idRecibo = (int)$id;

        try {
            $cab = Database::queryForListUpper(
                "SELECT id_recibo, cod_empresa, COALESCE(tip_doc, 'REC') AS tip_doc,
                        cod_cliente, ruc_cliente, COALESCE(tip_doc_cliente, 'RUC') AS tip_doc_cliente,
                        nom_cliente, talonario, COALESCE(cod_establecimiento, '001') AS cod_establecimiento,
                        COALESCE(cod_punto_expedicion, '001') AS cod_punto_expedicion, nro_recibo,
                        COALESCE(serie, '') AS serie, COALESCE(anulada, 'N') AS anulada,
                        DATE_FORMAT(fec_recibo, '%Y-%m-%d') AS fec_recibo,
                        COALESCE(cod_moneda, '1') AS cod_moneda, COALESCE(tasa_cambio, 1) AS tasa_cambio,
                        COALESCE(valor_total, 0) AS valor_total,
                        COALESCE(valor_otras_facturas, 0) AS valor_otras_facturas,
                        COALESCE(total_facturas, 0) AS total_facturas,
                        COALESCE(diferencia, 0) AS diferencia,
                        COALESCE(concepto, '') AS concepto,
                        COALESCE(metodo_pago, '') AS metodo_pago,
                        COALESCE(nro_cheque, '') AS nro_cheque,
                        COALESCE(banco, '') AS banco,
                        observacion, COALESCE(estado, 'A') AS estado, usuario_reg,
                        DATE_FORMAT(fec_reg, '%d/%m/%Y %H:%i') AS fec_reg_fmt
                 FROM co_recibos_cobro
                 WHERE id_propietario = ? AND id_recibo = ? AND cod_empresa = ?
                 LIMIT 1",
                [$idProp, $idRecibo, $empresa]
            );
            if (empty($cab)) {
                View::json(['status' => 'error', 'message' => 'Recibo de cobro no encontrado.']);
                return;
            }

            $detalles = Database::queryForListUpper(
                "SELECT id_detalle, id_ingreso, COALESCE(tip_doc, 'FAC') AS tip_doc,
                        COALESCE(nro_timbrado, '') AS nro_timbrado,
                        nro_factura, COALESCE(serie, '') AS serie,
                        DATE_FORMAT(fec_ingreso, '%Y-%m-%d') AS fec_ingreso,
                        DATE_FORMAT(fec_ingreso, '%d/%m/%Y') AS fec_ingreso_fmt,
                        COALESCE(valor_total, 0) AS valor_total,
                        COALESCE(saldo, 0) AS saldo,
                        COALESCE(importe, 0) AS importe
                 FROM co_recibos_cobro_det
                 WHERE id_propietario = ? AND id_recibo = ? AND cod_empresa = ?
                 ORDER BY id_detalle ASC",
                [$idProp, $idRecibo, $empresa]
            );

            $data = $cab[0];
            $data['DETALLES'] = $detalles;
            View::json(['status' => 'success', 'data' => $data]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al obtener recibo de cobro: ' . $e->getMessage()]);
        }
    }

    public static function apiRecibosCobroSave(): void {
        if (!self::requireAuth()) return;
        self::ensureRecibosCobroTables();
        $idProp  = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();

        try {
            $idRecibo   = !empty($payload['idRecibo']) ? (int)$payload['idRecibo'] : null;
            $tipDoc     = strtoupper(trim((string)($payload['tipDoc'] ?? 'REC'))) ?: 'REC';
            $rucCliente = trim((string)($payload['rucCliente'] ?? ''));
            $tipDocCli  = strtoupper(trim((string)($payload['tipDocCliente'] ?? 'RUC'))) ?: 'RUC';
            $codCliente = trim((string)($payload['codCliente'] ?? ''));
            $nomCliente = substr(trim((string)($payload['nomCliente'] ?? '')), 0, 150);

            if ($rucCliente === '' || $nomCliente === '') {
                View::json(['status' => 'error', 'message' => 'Debe indicar el RUC y Nombre del Cliente.']);
                return;
            }

            if ($codCliente === '' || strcasecmp($codCliente, 'null') === 0) {
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

            $talonario = trim((string)($payload['talonario'] ?? ''));
            $est       = str_pad(trim((string)($payload['codEstablecimiento'] ?? '')) ?: '001', 3, '0', STR_PAD_LEFT);
            $pto       = str_pad(trim((string)($payload['codPuntoExpedicion'] ?? '')) ?: '001', 3, '0', STR_PAD_LEFT);

            if ($talonario === '') {
                View::json([
                    'status'  => 'error',
                    'message' => 'Debe seleccionar un Talonario / Timbrado de tipo RECIBO previamente creado en Definiciones > Timbrados.'
                ]);
                return;
            }

            $timbradoRecRow = Database::queryForMap(
                "SELECT id_timbrado, nro_inicial, nro_final, controla_correlatividad
                 FROM co_timbrados
                 WHERE id_propietario = ? AND cod_empresa = ?
                   AND (UPPER(TRIM(tip_doc)) = 'REC' OR UPPER(TRIM(tip_doc)) LIKE '%RECIBO%')
                   AND COALESCE(dar_de_baja, 'N') <> 'S'
                   AND LPAD(TRIM(COALESCE(cod_establecimiento, '001')), 3, '0') = ?
                   AND LPAD(TRIM(COALESCE(cod_punto_expedicion, '001')), 3, '0') = ?
                 ORDER BY id_timbrado DESC
                 LIMIT 1",
                [$idProp, $empresa, $est, $pto]
            );

            if (!$timbradoRecRow) {
                View::json([
                    'status'  => 'error',
                    'message' => "El Talonario [{$est}-{$pto}] no está registrado en Timbrados (Tipo de Doc: REC - RECIBO). Por favor créelo primero en Contabilidad > Definiciones > Timbrados o con el botón (+) junto a Talonario."
                ]);
                return;
            }

            $rawNro    = trim((string)($payload['nroRecibo'] ?? ''));
            if ($rawNro === '') {
                View::json(['status' => 'error', 'message' => 'Debe indicar el Número de Recibo.']);
                return;
            }
            if (str_contains($rawNro, '-')) {
                $nroRecibo = self::formatNroFactura($rawNro);
                $partesSeq = explode('-', $nroRecibo);
                $numSecuencial = (int)end($partesSeq);
            } else {
                $numSecuencial = (int)(preg_replace('/\D/', '', $rawNro) ?: '1');
                $nroRecibo = "{$est}-{$pto}-" . str_pad((string)$numSecuencial, 7, '0', STR_PAD_LEFT);
            }

            $nroIniTimb = (int)($timbradoRecRow['nro_inicial'] ?? 0);
            $nroFinTimb = (int)($timbradoRecRow['nro_final'] ?? 0);
            if ($nroFinTimb > 0 && ($numSecuencial < $nroIniTimb || $numSecuencial > $nroFinTimb)) {
                View::json([
                    'status'  => 'error',
                    'message' => "El número de recibo ({$numSecuencial}) está fuera del rango habilitado en el Talonario [{$est}-{$pto}] ({$nroIniTimb} al {$nroFinTimb})."
                ]);
                return;
            }

            $serie   = substr(strtoupper(trim((string)($payload['serie'] ?? ''))), 0, 10);
            $rawAnul = $payload['anulada'] ?? $payload['anulado'] ?? 'N';
            $anulada = (!empty($rawAnul) && $rawAnul !== 'N' && $rawAnul !== 'false') ? 'S' : 'N';
            $fecRecibo  = self::parseSqlDate((string)($payload['fecRecibo'] ?? '')) ?: date('Y-m-d');
            $codMoneda  = trim((string)($payload['codMoneda'] ?? '1')) ?: '1';
            $tasaCambio = (float)($payload['tasaCambio'] ?? 1);
            if ($tasaCambio <= 0) $tasaCambio = 1.0;

            $detallesRaw = is_array($payload['detalles'] ?? null) ? $payload['detalles'] : [];
            $sumFacturas = 0.0;
            $detallesLimpios = [];
            foreach ($detallesRaw as $d) {
                $nroFac = trim((string)($d['nroFactura'] ?? $d['nroDocumento'] ?? $d['documento'] ?? ''));
                if ($nroFac === '') continue;
                $idIng     = !empty($d['idIngreso']) ? (int)$d['idIngreso'] : null;
                $tipDocDet = strtoupper(trim((string)($d['tipDoc'] ?? 'FAC'))) ?: 'FAC';
                $nroTimb   = trim((string)($d['nroTimbrado'] ?? ''));
                $serieDet  = substr(strtoupper(trim((string)($d['serie'] ?? ''))), 0, 10);
                $fecIng    = self::parseSqlDate((string)($d['fecIngreso'] ?? $d['fecDocumento'] ?? $d['fecha'] ?? '')) ?: $fecRecibo;
                $vTotal    = (float)($d['valorTotal'] ?? $d['montoTotal'] ?? $d['total'] ?? 0);
                $vSaldo    = (float)($d['saldo'] ?? $d['saldoAnterior'] ?? $vTotal);
                $vImporte  = (float)($d['importe'] ?? $d['importeCobrado'] ?? 0);
                $sumFacturas += $vImporte;

                $detallesLimpios[] = [
                    'id_ingreso'   => $idIng,
                    'tip_doc'      => $tipDocDet,
                    'nro_timbrado' => $nroTimb ?: null,
                    'nro_factura'  => self::formatNroFactura($nroFac, $tipDocDet),
                    'serie'        => $serieDet ?: null,
                    'fec_ingreso'  => $fecIng,
                    'valor_total'  => $vTotal,
                    'saldo'        => $vSaldo,
                    'importe'      => $vImporte,
                ];
            }

            $valorOtrasFacturas = (float)($payload['valorOtrasFacturas'] ?? 0);
            $totalFacturas      = $sumFacturas + $valorOtrasFacturas;
            $valorTotal         = (float)($payload['valorTotal'] ?? 0);
            if ($valorTotal <= 0 && $totalFacturas > 0) {
                $valorTotal = $totalFacturas;
            }
            $diferencia  = $valorTotal - $totalFacturas;
            $concepto    = isset($payload['concepto']) ? substr(trim((string)$payload['concepto']), 0, 150) : null;
            $metodoPago  = isset($payload['metodoPago']) ? substr(trim((string)$payload['metodoPago']), 0, 100) : null;
            $nroCheque   = isset($payload['nroCheque']) ? substr(trim((string)$payload['nroCheque']), 0, 50) : null;
            $banco       = isset($payload['banco']) ? substr(trim((string)$payload['banco']), 0, 100) : null;
            $observacion = isset($payload['observacion']) ? trim((string)$payload['observacion']) : null;

            if ($idRecibo === null) {
                $savedId = (int)Database::insertGetId(
                    "INSERT INTO co_recibos_cobro (
                        id_propietario, cod_empresa, tip_doc, anulada, talonario,
                        cod_establecimiento, cod_punto_expedicion, nro_recibo, serie, fec_recibo,
                        cod_cliente, nom_cliente, ruc_cliente, tip_doc_cliente,
                        cod_moneda, tasa_cambio, valor_total, valor_otras_facturas, total_facturas,
                        diferencia, concepto, metodo_pago, nro_cheque, banco, observacion,
                        estado, usuario_reg, fec_reg
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'A', ?, NOW())",
                    [
                        $idProp, $empresa, $tipDoc, $anulada, $talonario,
                        $est, $pto, $nroRecibo, $serie ?: null, $fecRecibo,
                        $codCliente ?: null, $nomCliente, $rucCliente, $tipDocCli,
                        $codMoneda, $tasaCambio, $valorTotal, $valorOtrasFacturas, $totalFacturas,
                        $diferencia, $concepto ?: null, $metodoPago ?: null, $nroCheque ?: null, $banco ?: null, $observacion, $usuario
                    ]
                );
                $msg = "Recibo de Cobro N° {$nroRecibo} registrado con éxito (#{$savedId}).";
            } else {
                Database::update(
                    "UPDATE co_recibos_cobro SET
                        tip_doc = ?, anulada = ?, talonario = ?,
                        cod_establecimiento = ?, cod_punto_expedicion = ?, nro_recibo = ?,
                        serie = ?, fec_recibo = ?, cod_cliente = ?, nom_cliente = ?,
                        ruc_cliente = ?, tip_doc_cliente = ?, cod_moneda = ?, tasa_cambio = ?,
                        valor_total = ?, valor_otras_facturas = ?, total_facturas = ?,
                        diferencia = ?, concepto = ?, metodo_pago = ?, nro_cheque = ?, banco = ?, observacion = ?
                     WHERE id_propietario = ? AND id_recibo = ? AND cod_empresa = ?",
                    [
                        $tipDoc, $anulada, $talonario,
                        $est, $pto, $nroRecibo,
                        $serie ?: null, $fecRecibo, $codCliente ?: null, $nomCliente,
                        $rucCliente, $tipDocCli, $codMoneda, $tasaCambio,
                        $valorTotal, $valorOtrasFacturas, $totalFacturas,
                        $diferencia, $concepto ?: null, $metodoPago ?: null, $nroCheque ?: null, $banco ?: null, $observacion,
                        $idProp, $idRecibo, $empresa
                    ]
                );
                Database::update(
                    "DELETE FROM co_recibos_cobro_det WHERE id_propietario = ? AND id_recibo = ? AND cod_empresa = ?",
                    [$idProp, $idRecibo, $empresa]
                );
                $savedId = $idRecibo;
                $msg = "Recibo de Cobro N° {$nroRecibo} actualizado con éxito.";
            }

            foreach ($detallesLimpios as $det) {
                Database::update(
                    "INSERT INTO co_recibos_cobro_det (
                        id_propietario, id_recibo, cod_empresa, id_ingreso, tip_doc, nro_timbrado,
                        nro_factura, serie, fec_ingreso, valor_total, saldo, importe,
                        estado, usuario_reg, fec_reg
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'A', ?, NOW())",
                    [
                        $idProp, $savedId, $empresa, $det['id_ingreso'], $det['tip_doc'], $det['nro_timbrado'],
                        $det['nro_factura'], $det['serie'], $det['fec_ingreso'], $det['valor_total'], $det['saldo'], $det['importe'],
                        $usuario
                    ]
                );
            }

            View::json([
                'status'   => 'success',
                'message'  => $msg,
                'idRecibo' => $savedId,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar el recibo de cobro: ' . $e->getMessage()]);
        }
    }

    public static function apiRecibosCobroDelete(): void {
        if (!self::requireAuth()) return;
        self::ensureRecibosCobroTables();
        $idProp   = TenantContext::idPropietario();
        $empresa  = TenantContext::empresa();
        $payload  = self::getInput();
        $idRecibo = (int)($payload['idRecibo'] ?? 0);

        if ($idRecibo <= 0) {
            View::json(['status' => 'error', 'message' => 'ID de recibo inválido.']);
            return;
        }

        try {
            Database::update(
                "DELETE FROM co_recibos_cobro_det WHERE id_propietario = ? AND id_recibo = ? AND cod_empresa = ?",
                [$idProp, $idRecibo, $empresa]
            );
            Database::update(
                "DELETE FROM co_recibos_cobro WHERE id_propietario = ? AND id_recibo = ? AND cod_empresa = ?",
                [$idProp, $idRecibo, $empresa]
            );
            View::json(['status' => 'success', 'message' => "Recibo de Cobro #{$idRecibo} eliminado correctamente."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar recibo de cobro: ' . $e->getMessage()]);
        }
    }

    public static function pagePrintReciboCobro(): void {
        if (!self::requireAuth(false)) return;
        self::ensureRecibosCobroTables();
        $idProp   = TenantContext::idPropietario();
        $empresa  = TenantContext::empresa();
        $idRecibo = (int)($_GET['id'] ?? $_GET['idRecibo'] ?? 0);

        $recibo = Database::queryForMap(
            "SELECT * FROM co_recibos_cobro WHERE id_propietario = ? AND cod_empresa = ? AND id_recibo = ? LIMIT 1",
            [$idProp, $empresa, $idRecibo]
        );
        if (!$recibo) {
            http_response_code(404);
            echo '<h3 style="font-family:sans-serif;text-align:center;margin-top:50px;">Recibo de Cobro no encontrado</h3>';
            return;
        }

        $detalles = Database::queryForList(
            "SELECT * FROM co_recibos_cobro_det
             WHERE id_propietario = ? AND cod_empresa = ? AND id_recibo = ?
             ORDER BY id_detalle ASC",
            [$idProp, $empresa, $idRecibo]
        );

        $empCfg = self::getEmpresaSifenConfig($idProp, $empresa);
        $actividadDesc = trim((string)(Database::queryForValue(
            "SELECT descripcion FROM co_actividades
             WHERE id_propietario = ? AND cod_empresa = ? AND COALESCE(estado, 'A') = 'A'
             ORDER BY CASE WHEN por_defecto = 'S' THEN 0 ELSE 1 END, cod_actividad ASC
             LIMIT 1",
            [$idProp, $empresa]
        ) ?? ''));
        if ($actividadDesc === '') {
            $actividadDesc = trim((string)($empCfg['actividad'] ?? 'Actividades Profesionales, Científicas y Técnicas'));
        }

        View::render('recibo_cobro_print', [
            'recibo'    => $recibo,
            'detalles'  => $detalles,
            'empresa'   => $empCfg,
            'actividad' => $actividadDesc,
        ]);
    }

    // =========================================================================
    // API RECIBOS DE PAGO (/api/recibos-pago/*) - Alineado a co_egresos
    // =========================================================================
    private static function ensureRecibosPagoTables(): void {
        Database::execute("
            CREATE TABLE IF NOT EXISTS `co_recibos_pago` (
              `id_propietario` int NOT NULL DEFAULT '1',
              `id_recibo_pago` bigint NOT NULL AUTO_INCREMENT,
              `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tip_doc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'REC',
              `tip_proveedor` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'PROVEEDOR LOCAL',
              `cod_proveedor` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tip_doc_proveedor` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'RUC',
              `nom_proveedor` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `ruc_proveedor` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `nro_recibo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `serie` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `anulada` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'N',
              `fec_recibo` datetime DEFAULT NULL,
              `cod_moneda` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tasa_cambio` decimal(12,4) DEFAULT NULL,
              `valor_total` decimal(15,2) DEFAULT NULL,
              `valor_otras_facturas` decimal(15,2) DEFAULT NULL,
              `total_facturas` decimal(15,2) DEFAULT NULL,
              `diferencia` decimal(15,2) DEFAULT NULL,
              `observacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'A',
              `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_reg` datetime DEFAULT NULL,
              PRIMARY KEY (`id_recibo_pago`,`id_propietario`),
              KEY `idx_co_recibos_pago_prop` (`id_propietario`),
              KEY `idx_co_recibos_pago_emp_fec` (`id_propietario`,`cod_empresa`,`fec_recibo`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);

        Database::execute("
            CREATE TABLE IF NOT EXISTS `co_recibos_pago_det` (
              `id_propietario` int NOT NULL DEFAULT '1',
              `id_detalle` bigint NOT NULL AUTO_INCREMENT,
              `id_recibo_pago` bigint NOT NULL,
              `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `id_egreso` bigint DEFAULT NULL,
              `tip_comprobante` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'FAC',
              `nro_timbrado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `nro_factura` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `serie` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_egreso` datetime DEFAULT NULL,
              `valor_total` decimal(15,2) DEFAULT NULL,
              `saldo` decimal(15,2) DEFAULT NULL,
              `importe` decimal(15,2) DEFAULT NULL,
              `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'A',
              `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_reg` datetime DEFAULT NULL,
              PRIMARY KEY (`id_detalle`,`id_propietario`),
              KEY `idx_co_recibos_pago_det_rec` (`id_propietario`,`id_recibo_pago`),
              KEY `idx_co_recibos_pago_det_egr` (`id_propietario`,`id_egreso`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);
    }

    public static function apiRecibosPagoProveedores(): void {
        if (!self::requireAuth()) return;
        $idProp  = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();

        try {
            $provRows = Database::queryForList(
                "SELECT cod_proveedor, nombre, ruc, COALESCE(tipo_documento, 'RUC') AS tipo_documento
                 FROM co_proveedores
                 WHERE id_propietario = ? AND (cod_empresa = ? OR cod_empresa IS NULL OR cod_empresa = '')
                   AND COALESCE(estado, 'A') <> 'I'
                 ORDER BY nombre ASC",
                [$idProp, $empresa]
            );

            $map = [];
            foreach ($provRows as $r) {
                $ruc = trim((string)($r['ruc'] ?? ''));
                $nom = trim((string)($r['nombre'] ?? ''));
                if ($ruc === '' && $nom === '') continue;
                $key = strtoupper($ruc !== '' ? str_replace(['-', ' '], '', $ruc) : $nom);
                $map[$key] = [
                    'COD_PROVEEDOR'  => (string)($r['cod_proveedor'] ?? ''),
                    'NOMBRE'         => $nom,
                    'RUC'            => $ruc,
                    'TIPO_DOCUMENTO' => (string)($r['tipo_documento'] ?? 'RUC'),
                ];
            }

            // Complementar con proveedores registrados directamente en co_egresos
            $egrRows = Database::queryForList(
                "SELECT MAX(cod_proveedor) AS cod_proveedor,
                        MAX(nom_proveedor) AS nombre,
                        ruc_proveedor AS ruc
                 FROM co_egresos
                 WHERE id_propietario = ? AND cod_empresa = ?
                   AND COALESCE(TRIM(ruc_proveedor), '') <> ''
                 GROUP BY ruc_proveedor
                 ORDER BY nombre ASC",
                [$idProp, $empresa]
            );
            foreach ($egrRows as $er) {
                $ruc = trim((string)($er['ruc'] ?? ''));
                $nom = trim((string)($er['nombre'] ?? ''));
                if ($ruc === '' && $nom === '') continue;
                $key = strtoupper($ruc !== '' ? str_replace(['-', ' '], '', $ruc) : $nom);
                if (!isset($map[$key])) {
                    $map[$key] = [
                        'COD_PROVEEDOR'  => (string)($er['cod_proveedor'] ?? ''),
                        'NOMBRE'         => $nom,
                        'RUC'            => $ruc,
                        'TIPO_DOCUMENTO' => 'RUC',
                    ];
                }
            }

            View::json(['status' => 'success', 'data' => array_values($map)]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al listar proveedores: ' . $e->getMessage()]);
        }
    }

    public static function apiRecibosPagoFacturasPendientes(): void {
        if (!self::requireAuth()) return;
        self::ensureRecibosPagoTables();
        $idProp       = TenantContext::idPropietario();
        $empresa      = TenantContext::empresa();
        $rucProveedor = trim((string)($_GET['rucProveedor'] ?? ''));
        $codProveedor = trim((string)($_GET['codProveedor'] ?? ''));
        $idReciboExcl = (int)($_GET['idReciboPago'] ?? $_GET['idRecibo'] ?? 0);

        try {
            $sql = "SELECT e.id_egreso,
                           COALESCE(e.tip_doc, 'FAC') AS tip_doc,
                           COALESCE(e.nro_timbrado, '') AS nro_timbrado,
                           e.nro_factura,
                           COALESCE(e.serie, '') AS serie,
                           DATE_FORMAT(e.fec_egreso, '%Y-%m-%d') AS fec_egreso,
                           DATE_FORMAT(e.fec_egreso, '%d/%m/%Y') AS fec_egreso_fmt,
                           COALESCE(e.ruc_proveedor, '') AS ruc_proveedor,
                           COALESCE(e.nom_proveedor, '') AS nom_proveedor,
                           COALESCE(e.cod_proveedor, '') AS cod_proveedor,
                           COALESCE(e.cod_moneda, '1') AS cod_moneda,
                           COALESCE(e.tasa_cambio, 1) AS tasa_cambio,
                           COALESCE(e.valor_total, 0) AS valor_total
                    FROM co_egresos e
                    WHERE e.id_propietario = ?
                      AND e.cod_empresa = ?
                      AND (UPPER(TRIM(e.condicion)) IN ('CRE', 'CREDITO', 'CRÉDITO') OR UPPER(e.condicion) LIKE 'CRE%')
                      AND COALESCE(e.estado, 'A') <> 'I' ";
            $params = [$idProp, $empresa];

            if ($rucProveedor !== '') {
                $rucNoDash = str_replace(['-', ' '], '', $rucProveedor);
                if ($codProveedor !== '') {
                    $sql .= "AND (UPPER(TRIM(e.ruc_proveedor)) = UPPER(?) OR UPPER(REPLACE(e.ruc_proveedor, '-', '')) = UPPER(?) OR TRIM(e.cod_proveedor) = ?) ";
                    $params[] = $rucProveedor;
                    $params[] = $rucNoDash;
                    $params[] = $codProveedor;
                } else {
                    $sql .= "AND (UPPER(TRIM(e.ruc_proveedor)) = UPPER(?) OR UPPER(REPLACE(e.ruc_proveedor, '-', '')) = UPPER(?)) ";
                    $params[] = $rucProveedor;
                    $params[] = $rucNoDash;
                }
            } elseif ($codProveedor !== '') {
                $sql .= "AND TRIM(e.cod_proveedor) = ? ";
                $params[] = $codProveedor;
            }

            $sql .= "ORDER BY e.fec_egreso DESC, e.id_egreso DESC";
            $facturas = Database::queryForList($sql, $params);

            // Calcular pagos aplicados previamente a cada factura en recibos de pago activos
            $sqlPagos = "SELECT d.id_egreso, d.nro_factura, SUM(COALESCE(d.importe, 0)) AS pagado
                         FROM co_recibos_pago_det d
                         JOIN co_recibos_pago p
                           ON p.id_propietario = d.id_propietario AND p.id_recibo_pago = d.id_recibo_pago
                         WHERE d.id_propietario = ?
                           AND d.cod_empresa = ?
                           AND COALESCE(p.anulada, 'N') <> 'S'
                           AND COALESCE(p.estado, 'A') <> 'I' ";
            $paramsPagos = [$idProp, $empresa];
            if ($idReciboExcl > 0) {
                $sqlPagos .= "AND d.id_recibo_pago <> ? ";
                $paramsPagos[] = $idReciboExcl;
            }
            $sqlPagos .= "GROUP BY d.id_egreso, d.nro_factura";
            $pagosRows = Database::queryForList($sqlPagos, $paramsPagos);

            $pagadoPorId  = [];
            $pagadoPorDoc = [];
            foreach ($pagosRows as $pr) {
                $idEgr = (int)($pr['id_egreso'] ?? 0);
                $doc   = strtoupper(trim((string)($pr['nro_factura'] ?? '')));
                $imp   = (float)($pr['pagado'] ?? 0);
                if ($idEgr > 0) {
                    $pagadoPorId[$idEgr] = ($pagadoPorId[$idEgr] ?? 0.0) + $imp;
                } elseif ($doc !== '') {
                    $pagadoPorDoc[$doc] = ($pagadoPorDoc[$doc] ?? 0.0) + $imp;
                }
            }

            $result = [];
            foreach ($facturas as $f) {
                $idEgr  = (int)($f['id_egreso'] ?? 0);
                $tipDoc = (string)($f['tip_doc'] ?? 'FAC');
                $nroFac = self::formatNroFactura((string)($f['nro_factura'] ?? ''), $tipDoc);
                $docKey = strtoupper(trim($nroFac));
                $total  = (float)($f['valor_total'] ?? 0);
                $pagado = $pagadoPorId[$idEgr] ?? ($pagadoPorDoc[$docKey] ?? 0.0);
                $saldo  = max(0.0, $total - $pagado);

                $result[] = [
                    'id_egreso'      => $idEgr,
                    'tip_doc'        => $tipDoc,
                    'nro_timbrado'   => (string)($f['nro_timbrado'] ?? ''),
                    'nro_factura'    => $nroFac,
                    'serie'          => (string)($f['serie'] ?? ''),
                    'fec_egreso'     => (string)($f['fec_egreso'] ?? ''),
                    'fec_egreso_fmt' => (string)($f['fec_egreso_fmt'] ?? ''),
                    'ruc_proveedor'  => (string)($f['ruc_proveedor'] ?? ''),
                    'nom_proveedor'  => (string)($f['nom_proveedor'] ?? ''),
                    'cod_proveedor'  => (string)($f['cod_proveedor'] ?? ''),
                    'cod_moneda'     => (string)($f['cod_moneda'] ?? '1'),
                    'tasa_cambio'    => (float)($f['tasa_cambio'] ?? 1),
                    'valor_total'    => $total,
                    'pagado_previo'  => $pagado,
                    'saldo'          => $saldo,
                ];
            }

            View::json(['status' => 'success', 'data' => $result]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al consultar facturas crédito de proveedores: ' . $e->getMessage()]);
        }
    }

    public static function apiRecibosPagoList(): void {
        if (!self::requireAuth()) return;
        self::ensureRecibosPagoTables();
        $idProp     = TenantContext::idPropietario();
        $empresa    = TenantContext::empresa();
        $anio       = trim((string)($_GET['anio'] ?? ''));
        $mes        = trim((string)($_GET['mes'] ?? ''));
        $fechaDesde = self::parseSqlDate((string)($_GET['fechaDesde'] ?? $_GET['desde'] ?? ''));
        $fechaHasta = self::parseSqlDate((string)($_GET['fechaHasta'] ?? $_GET['hasta'] ?? ''));
        $filtroAnul = trim((string)($_GET['anulada'] ?? $_GET['estado'] ?? ''));

        try {
            $sql = "SELECT p.id_recibo_pago,
                           p.cod_empresa,
                           COALESCE(p.tip_doc, 'REC') AS tip_doc,
                           COALESCE(p.tip_proveedor, 'PROVEEDOR LOCAL') AS tip_proveedor,
                           p.cod_proveedor,
                           p.ruc_proveedor,
                           COALESCE(p.tip_doc_proveedor, 'RUC') AS tip_doc_proveedor,
                           p.nom_proveedor,
                           p.nro_recibo,
                           COALESCE(p.serie, '') AS serie,
                           COALESCE(p.anulada, 'N') AS anulada,
                           DATE_FORMAT(p.fec_recibo, '%Y-%m-%d') AS fec_recibo,
                           DATE_FORMAT(p.fec_recibo, '%d/%m/%Y') AS fec_recibo_fmt,
                           COALESCE(p.cod_moneda, '1') AS cod_moneda,
                           COALESCE(p.tasa_cambio, 1) AS tasa_cambio,
                           COALESCE(p.valor_total, 0) AS valor_total,
                           COALESCE(p.valor_otras_facturas, 0) AS valor_otras_facturas,
                           COALESCE(p.total_facturas, 0) AS total_facturas,
                           COALESCE(p.diferencia, 0) AS diferencia,
                           p.observacion,
                           COALESCE(p.estado, 'A') AS estado,
                           p.usuario_reg,
                           DATE_FORMAT(p.fec_reg, '%d/%m/%Y %H:%i') AS fec_reg_fmt,
                           (SELECT GROUP_CONCAT(
                                CONCAT(d.nro_factura, IF(COALESCE(TRIM(d.serie), '') <> '', CONCAT(' [Serie ', TRIM(d.serie), ']'), ''))
                                SEPARATOR ', '
                            )
                            FROM co_recibos_pago_det d
                            WHERE d.id_propietario = p.id_propietario AND d.id_recibo_pago = p.id_recibo_pago
                           ) AS facturas_rel,
                           (SELECT COUNT(*)
                            FROM co_recibos_pago_det d2
                            WHERE d2.id_propietario = p.id_propietario AND d2.id_recibo_pago = p.id_recibo_pago
                           ) AS cant_facturas
                    FROM co_recibos_pago p
                    WHERE p.id_propietario = ? AND p.cod_empresa = ? ";
            $params = [$idProp, $empresa];

            if ($fechaDesde !== null) {
                $sql .= "AND DATE(p.fec_recibo) >= ? ";
                $params[] = $fechaDesde;
            }
            if ($fechaHasta !== null) {
                $sql .= "AND DATE(p.fec_recibo) <= ? ";
                $params[] = $fechaHasta;
            }
            if ($fechaDesde === null && $fechaHasta === null) {
                if ($anio !== '' && strcasecmp($anio, 'TODOS') !== 0) {
                    $sql .= "AND YEAR(p.fec_recibo) = ? ";
                    $params[] = (int)$anio;
                }
                if ($mes !== '' && strcasecmp($mes, 'TODOS') !== 0) {
                    $sql .= "AND MONTH(p.fec_recibo) = ? ";
                    $params[] = (int)$mes;
                }
            }
            $sql .= "ORDER BY p.fec_recibo DESC, p.id_recibo_pago DESC";

            $list = Database::queryForListUpper($sql, $params);
            View::json(['status' => 'success', 'data' => $list]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al listar recibos de pago: ' . $e->getMessage()]);
        }
    }

    public static function apiRecibosPagoGet(string $id): void {
        if (!self::requireAuth()) return;
        self::ensureRecibosPagoTables();
        $idProp       = TenantContext::idPropietario();
        $empresa      = TenantContext::empresa();
        $idReciboPago = (int)$id;

        try {
            $cab = Database::queryForListUpper(
                "SELECT id_recibo_pago, cod_empresa, COALESCE(tip_doc, 'REC') AS tip_doc,
                        COALESCE(tip_proveedor, 'PROVEEDOR LOCAL') AS tip_proveedor,
                        cod_proveedor, ruc_proveedor, COALESCE(tip_doc_proveedor, 'RUC') AS tip_doc_proveedor,
                        nom_proveedor, nro_recibo, COALESCE(serie, '') AS serie,
                        COALESCE(anulada, 'N') AS anulada,
                        DATE_FORMAT(fec_recibo, '%Y-%m-%d') AS fec_recibo,
                        COALESCE(cod_moneda, '1') AS cod_moneda, COALESCE(tasa_cambio, 1) AS tasa_cambio,
                        COALESCE(valor_total, 0) AS valor_total,
                        COALESCE(valor_otras_facturas, 0) AS valor_otras_facturas,
                        COALESCE(total_facturas, 0) AS total_facturas,
                        COALESCE(diferencia, 0) AS diferencia,
                        COALESCE(concepto, '') AS concepto,
                        COALESCE(metodo_pago, '') AS metodo_pago,
                        COALESCE(nro_cheque, '') AS nro_cheque,
                        COALESCE(banco, '') AS banco,
                        observacion, COALESCE(estado, 'A') AS estado, usuario_reg,
                        DATE_FORMAT(fec_reg, '%d/%m/%Y %H:%i') AS fec_reg_fmt
                 FROM co_recibos_pago
                 WHERE id_propietario = ? AND id_recibo_pago = ? AND cod_empresa = ?
                 LIMIT 1",
                [$idProp, $idReciboPago, $empresa]
            );
            if (empty($cab)) {
                View::json(['status' => 'error', 'message' => 'Recibo de pago no encontrado.']);
                return;
            }

            $detalles = Database::queryForListUpper(
                "SELECT id_detalle, id_egreso, COALESCE(tip_comprobante, 'FAC') AS tip_comprobante,
                        COALESCE(nro_timbrado, '') AS nro_timbrado,
                        nro_factura, COALESCE(serie, '') AS serie,
                        DATE_FORMAT(fec_egreso, '%Y-%m-%d') AS fec_egreso,
                        DATE_FORMAT(fec_egreso, '%d/%m/%Y') AS fec_egreso_fmt,
                        COALESCE(valor_total, 0) AS valor_total,
                        COALESCE(saldo, 0) AS saldo,
                        COALESCE(importe, 0) AS importe
                 FROM co_recibos_pago_det
                 WHERE id_propietario = ? AND id_recibo_pago = ? AND cod_empresa = ?
                 ORDER BY id_detalle ASC",
                [$idProp, $idReciboPago, $empresa]
            );

            $data = $cab[0];
            $data['DETALLES'] = $detalles;
            View::json(['status' => 'success', 'data' => $data]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al obtener recibo de pago: ' . $e->getMessage()]);
        }
    }

    public static function apiRecibosPagoSave(): void {
        if (!self::requireAuth()) return;
        self::ensureRecibosPagoTables();
        $idProp  = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();

        try {
            $idReciboPago = !empty($payload['idReciboPago']) ? (int)$payload['idReciboPago'] : (!empty($payload['idRecibo']) ? (int)$payload['idRecibo'] : null);
            $tipDoc       = strtoupper(trim((string)($payload['tipDoc'] ?? 'REC'))) ?: 'REC';
            $tipProveedor = strtoupper(trim((string)($payload['tipProveedor'] ?? 'PROVEEDOR LOCAL'))) ?: 'PROVEEDOR LOCAL';
            $rucProveedor = trim((string)($payload['rucProveedor'] ?? ''));
            $tipDocProv   = strtoupper(trim((string)($payload['tipDocProveedor'] ?? 'RUC'))) ?: 'RUC';
            $codProveedor = trim((string)($payload['codProveedor'] ?? ''));
            $nomProveedor = substr(trim((string)($payload['nomProveedor'] ?? '')), 0, 150);

            if ($rucProveedor === '' || $nomProveedor === '') {
                View::json(['status' => 'error', 'message' => 'Debe indicar el RUC y Nombre del Proveedor.']);
                return;
            }

            if ($codProveedor === '' || strcasecmp($codProveedor, 'null') === 0) {
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

            $nroRecibo = substr(trim((string)($payload['nroRecibo'] ?? '')), 0, 30);
            if ($nroRecibo === '') {
                View::json(['status' => 'error', 'message' => 'Debe indicar el Número del Recibo de Pago.']);
                return;
            }

            $serie   = substr(strtoupper(trim((string)($payload['serie'] ?? ''))), 0, 10);
            $rawAnul = $payload['anulada'] ?? $payload['anulado'] ?? 'N';
            $anulada = (!empty($rawAnul) && $rawAnul !== 'N' && $rawAnul !== 'false') ? 'S' : 'N';
            $fecRecibo  = self::parseSqlDate((string)($payload['fecRecibo'] ?? '')) ?: date('Y-m-d');
            $codMoneda  = trim((string)($payload['codMoneda'] ?? '1')) ?: '1';
            $tasaCambio = (float)($payload['tasaCambio'] ?? 1);
            if ($tasaCambio <= 0) $tasaCambio = 1.0;

            $detallesRaw = is_array($payload['detalles'] ?? null) ? $payload['detalles'] : [];
            $sumFacturas = 0.0;
            $detallesLimpios = [];
            foreach ($detallesRaw as $d) {
                $nroFac = trim((string)($d['nroFactura'] ?? $d['nroDocumento'] ?? $d['documento'] ?? ''));
                if ($nroFac === '') continue;
                $idEgr     = !empty($d['idEgreso']) ? (int)$d['idEgreso'] : null;
                $tipDocDet = strtoupper(trim((string)($d['tipComprobante'] ?? $d['tipDoc'] ?? 'FAC'))) ?: 'FAC';
                $nroTimb   = trim((string)($d['nroTimbrado'] ?? ''));
                $serieDet  = substr(strtoupper(trim((string)($d['serie'] ?? ''))), 0, 10);
                $fecEgr    = self::parseSqlDate((string)($d['fecEgreso'] ?? $d['fecDocumento'] ?? $d['fecha'] ?? '')) ?: $fecRecibo;
                $vTotal    = (float)($d['valorTotal'] ?? $d['montoTotal'] ?? $d['total'] ?? 0);
                $vSaldo    = (float)($d['saldo'] ?? $d['saldoAnterior'] ?? $vTotal);
                $vImporte  = (float)($d['importe'] ?? $d['importePagado'] ?? 0);
                $sumFacturas += $vImporte;

                $detallesLimpios[] = [
                    'id_egreso'       => $idEgr,
                    'tip_comprobante' => $tipDocDet,
                    'nro_timbrado'    => $nroTimb ?: null,
                    'nro_factura'     => self::formatNroFactura($nroFac, $tipDocDet),
                    'serie'           => $serieDet ?: null,
                    'fec_egreso'      => $fecEgr,
                    'valor_total'     => $vTotal,
                    'saldo'           => $vSaldo,
                    'importe'         => $vImporte,
                ];
            }

            $valorOtrasFacturas = (float)($payload['valorOtrasFacturas'] ?? 0);
            $totalFacturas      = $sumFacturas + $valorOtrasFacturas;
            $valorTotal         = (float)($payload['valorTotal'] ?? 0);
            if ($valorTotal <= 0 && $totalFacturas > 0) {
                $valorTotal = $totalFacturas;
            }
            $diferencia  = $valorTotal - $totalFacturas;
            $observacion = isset($payload['observacion']) ? trim((string)$payload['observacion']) : null;

            if ($idReciboPago === null) {
                $savedId = (int)Database::insertGetId(
                    "INSERT INTO co_recibos_pago (
                        id_propietario, cod_empresa, tip_doc, tip_proveedor,
                        cod_proveedor, nom_proveedor, ruc_proveedor, tip_doc_proveedor,
                        nro_recibo, serie, anulada, fec_recibo,
                        cod_moneda, tasa_cambio, valor_total, valor_otras_facturas, total_facturas,
                        diferencia, observacion, estado, usuario_reg, fec_reg
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'A', ?, NOW())",
                    [
                        $idProp, $empresa, $tipDoc, $tipProveedor,
                        $codProveedor ?: null, $nomProveedor, $rucProveedor, $tipDocProv,
                        $nroRecibo, $serie ?: null, $anulada, $fecRecibo,
                        $codMoneda, $tasaCambio, $valorTotal, $valorOtrasFacturas, $totalFacturas,
                        $diferencia, $observacion, $usuario
                    ]
                );
                $msg = "Recibo de Pago N° {$nroRecibo} registrado con éxito (#{$savedId}).";
            } else {
                Database::update(
                    "UPDATE co_recibos_pago SET
                        tip_doc = ?, tip_proveedor = ?, cod_proveedor = ?, nom_proveedor = ?,
                        ruc_proveedor = ?, tip_doc_proveedor = ?, nro_recibo = ?, serie = ?,
                        anulada = ?, fec_recibo = ?, cod_moneda = ?, tasa_cambio = ?,
                        valor_total = ?, valor_otras_facturas = ?, total_facturas = ?,
                        diferencia = ?, observacion = ?
                     WHERE id_propietario = ? AND id_recibo_pago = ? AND cod_empresa = ?",
                    [
                        $tipDoc, $tipProveedor, $codProveedor ?: null, $nomProveedor,
                        $rucProveedor, $tipDocProv, $nroRecibo, $serie ?: null,
                        $anulada, $fecRecibo, $codMoneda, $tasaCambio,
                        $valorTotal, $valorOtrasFacturas, $totalFacturas,
                        $diferencia, $observacion,
                        $idProp, $idReciboPago, $empresa
                    ]
                );
                Database::update(
                    "DELETE FROM co_recibos_pago_det WHERE id_propietario = ? AND id_recibo_pago = ? AND cod_empresa = ?",
                    [$idProp, $idReciboPago, $empresa]
                );
                $savedId = $idReciboPago;
                $msg = "Recibo de Pago N° {$nroRecibo} actualizado con éxito.";
            }

            foreach ($detallesLimpios as $det) {
                Database::update(
                    "INSERT INTO co_recibos_pago_det (
                        id_propietario, id_recibo_pago, cod_empresa, id_egreso, tip_comprobante, nro_timbrado,
                        nro_factura, serie, fec_egreso, valor_total, saldo, importe,
                        estado, usuario_reg, fec_reg
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'A', ?, NOW())",
                    [
                        $idProp, $savedId, $empresa, $det['id_egreso'], $det['tip_comprobante'], $det['nro_timbrado'],
                        $det['nro_factura'], $det['serie'], $det['fec_egreso'], $det['valor_total'], $det['saldo'], $det['importe'],
                        $usuario
                    ]
                );
            }

            View::json([
                'status'       => 'success',
                'message'      => $msg,
                'idReciboPago' => $savedId,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar el recibo de pago: ' . $e->getMessage()]);
        }
    }

    public static function apiRecibosPagoDelete(): void {
        if (!self::requireAuth()) return;
        self::ensureRecibosPagoTables();
        $idProp       = TenantContext::idPropietario();
        $empresa      = TenantContext::empresa();
        $payload      = self::getInput();
        $idReciboPago = (int)($payload['idReciboPago'] ?? $payload['idRecibo'] ?? 0);

        if ($idReciboPago <= 0) {
            View::json(['status' => 'error', 'message' => 'ID de recibo de pago inválido.']);
            return;
        }

        try {
            Database::update(
                "DELETE FROM co_recibos_pago_det WHERE id_propietario = ? AND id_recibo_pago = ? AND cod_empresa = ?",
                [$idProp, $idReciboPago, $empresa]
            );
            Database::update(
                "DELETE FROM co_recibos_pago WHERE id_propietario = ? AND id_recibo_pago = ? AND cod_empresa = ?",
                [$idProp, $idReciboPago, $empresa]
            );
            View::json(['status' => 'success', 'message' => "Recibo de Pago #{$idReciboPago} eliminado correctamente."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar recibo de pago: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API RETENCIONES SUFRIDAS (/api/retenciones-sufridas/*)
    // =========================================================================
    public static function ensureRetencionesSufridasTables(): void {
        Database::execute("
            CREATE TABLE IF NOT EXISTS `co_retenciones_sufridas` (
              `id_retencion` bigint NOT NULL AUTO_INCREMENT,
              `id_propietario` int NOT NULL DEFAULT '1',
              `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tip_comprobante` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'RES',
              `cod_cliente` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `ruc_cliente` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tip_doc_cliente` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'RUC',
              `nom_cliente` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `nro_timbrado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tip_timbrado` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'Virtual',
              `form_145` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'ORIGINAL',
              `nro_retencion` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_retencion` datetime DEFAULT NULL,
              `cod_moneda` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '1',
              `tasa_cambio` decimal(15,4) DEFAULT '1.0000',
              `grav10` decimal(15,2) DEFAULT '0.00',
              `iva10` decimal(15,2) DEFAULT '0.00',
              `porc10` decimal(8,2) DEFAULT '30.00',
              `ret10` decimal(15,2) DEFAULT '0.00',
              `grav5` decimal(15,2) DEFAULT '0.00',
              `iva5` decimal(15,2) DEFAULT '0.00',
              `porc5` decimal(8,2) DEFAULT '0.00',
              `ret5` decimal(15,2) DEFAULT '0.00',
              `exentas` decimal(15,2) DEFAULT '0.00',
              `valor_total` decimal(15,2) DEFAULT '0.00',
              `importe_retenido` decimal(15,2) DEFAULT '0.00',
              `total_comprobantes` decimal(15,2) DEFAULT '0.00',
              `diferencia` decimal(15,2) DEFAULT '0.00',
              `observacion` text COLLATE utf8mb4_unicode_ci,
              `anulada` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'N',
              `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'A',
              `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_reg` datetime DEFAULT NULL,
              PRIMARY KEY (`id_retencion`),
              KEY `idx_co_ret_sufridas_prop_emp` (`id_propietario`, `cod_empresa`),
              KEY `idx_co_ret_sufridas_fec` (`id_propietario`, `cod_empresa`, `fec_retencion`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);

        Database::execute("
            CREATE TABLE IF NOT EXISTS `co_retenciones_sufridas_det` (
              `id_detalle` bigint NOT NULL AUTO_INCREMENT,
              `id_propietario` int NOT NULL DEFAULT '1',
              `id_retencion` bigint NOT NULL,
              `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `id_ingreso` bigint DEFAULT NULL,
              `tip_comprobante` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'Factura',
              `nro_comprobante` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_comprobante` datetime DEFAULT NULL,
              `grav10` decimal(15,2) DEFAULT '0.00',
              `iva10` decimal(15,2) DEFAULT '0.00',
              `grav5` decimal(15,2) DEFAULT '0.00',
              `iva5` decimal(15,2) DEFAULT '0.00',
              `exentas` decimal(15,2) DEFAULT '0.00',
              `tot_iva` decimal(15,2) DEFAULT '0.00',
              `valor_total` decimal(15,2) DEFAULT '0.00',
              `importe_retenido` decimal(15,2) DEFAULT '0.00',
              `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'A',
              `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_reg` datetime DEFAULT NULL,
              PRIMARY KEY (`id_detalle`),
              KEY `idx_co_ret_sufridas_det_prop` (`id_propietario`),
              KEY `idx_co_ret_sufridas_det_ret` (`id_propietario`, `id_retencion`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);

        // Asegurar que exista el tipo de comprobante RES - RETENCION SUFRIDA
        try {
            $idProp = TenantContext::idPropietario();
            Database::execute("ALTER TABLE co_tipos_comprobantes MODIFY COLUMN descripcion VARCHAR(100) DEFAULT NULL", true);
            $cntRes = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM co_tipos_comprobantes WHERE id_propietario = ? AND UPPER(TRIM(tip_comprobante)) = 'RES'",
                [$idProp]
            );
            if ($cntRes === 0) {
                Database::update(
                    "INSERT INTO co_tipos_comprobantes (id_propietario, tip_comprobante, descripcion, cod_modulo, tipo, estado, usuario_reg, fec_reg)
                     VALUES (?, 'RES', 'RETENCION SUFRIDA', 'ST', 'INGRESOS', 'A', 'CONTA', NOW())",
                    [$idProp]
                );
            }
        } catch (\Throwable $ignored) {}
    }

    public static function apiRetencionesSufridasComprobantesCliente(): void {
        if (!self::requireAuth()) return;
        self::ensureRetencionesSufridasTables();
        $idProp      = TenantContext::idPropietario();
        $empresa     = TenantContext::empresa();
        $rucCliente  = trim((string)($_GET['rucCliente'] ?? ''));
        $codCliente  = trim((string)($_GET['codCliente'] ?? ''));
        $nroFiltro   = trim((string)($_GET['nroComprobante'] ?? ''));
        $idRetActual = !empty($_GET['idRetencion']) ? (int)$_GET['idRetencion'] : 0;

        try {
            // Obtener mapa de facturas ya relacionadas en otras retenciones sufridas activas
            $yaRelParams = [$idProp, $empresa];
            $yaRelSql = "SELECT d.id_ingreso, d.nro_comprobante, r.nro_retencion
                         FROM co_retenciones_sufridas_det d
                         INNER JOIN co_retenciones_sufridas r
                            ON r.id_retencion = d.id_retencion
                           AND r.id_propietario = d.id_propietario
                         WHERE d.id_propietario = ?
                           AND d.cod_empresa = ?
                           AND COALESCE(d.estado, 'A') <> 'I'
                           AND COALESCE(r.anulada, 'N') <> 'S'
                           AND COALESCE(r.estado, 'A') <> 'I'";
            if ($idRetActual > 0) {
                $yaRelSql .= " AND r.id_retencion <> ?";
                $yaRelParams[] = $idRetActual;
            }
            $rowsRel = Database::queryForList($yaRelSql, $yaRelParams);
            $mapRelPorId  = [];
            $mapRelPorNro = [];
            foreach ($rowsRel as $rr) {
                $idI  = (int)($rr['id_ingreso'] ?? 0);
                $nroC = strtoupper(str_replace(['-', ' '], '', trim((string)($rr['nro_comprobante'] ?? ''))));
                $nroR = (string)($rr['nro_retencion'] ?? '');
                if ($idI > 0) {
                    $mapRelPorId[$idI] = $nroR;
                }
                if ($nroC !== '') {
                    $mapRelPorNro[$nroC] = $nroR;
                }
            }

            $sql = "SELECT i.id_ingreso,
                           COALESCE(i.tip_doc, 'FAC') AS tip_doc,
                           COALESCE(i.nro_timbrado, '') AS nro_timbrado,
                           i.nro_factura,
                           COALESCE(i.serie, '') AS serie,
                           DATE_FORMAT(i.fec_ingreso, '%Y-%m-%d') AS fec_ingreso,
                           DATE_FORMAT(i.fec_ingreso, '%d/%m/%Y') AS fec_ingreso_fmt,
                           COALESCE(i.ruc_cliente, '') AS ruc_cliente,
                           COALESCE(i.nom_cliente, '') AS nom_cliente,
                           COALESCE(i.cod_cliente, '') AS cod_cliente,
                           COALESCE(i.cod_moneda, '1') AS cod_moneda,
                           COALESCE(i.grav10, 0) AS grav10,
                           COALESCE(i.iva10, 0) AS iva10,
                           COALESCE(i.grav5, 0) AS grav5,
                           COALESCE(i.iva5, 0) AS iva5,
                           COALESCE(i.exentas, 0) AS exentas,
                           COALESCE(i.tot_iva, COALESCE(i.iva10, 0) + COALESCE(i.iva5, 0)) AS tot_iva,
                           COALESCE(i.valor_total, 0) AS valor_total
                    FROM co_ingresos i
                    WHERE i.id_propietario = ?
                      AND i.cod_empresa = ?
                      AND COALESCE(i.anulada, 'N') <> 'S'
                      AND COALESCE(i.inutilizado, 'N') <> 'S'
                      AND COALESCE(i.estado, 'A') <> 'I'
                      AND UPPER(TRIM(COALESCE(i.tip_doc, 'FAC'))) IN ('FAC', 'FACTURA')
                      AND COALESCE(i.nro_factura, '') LIKE '%-%' ";
            $params = [$idProp, $empresa];

            if ($rucCliente !== '') {
                $rucNoDash = str_replace(['-', ' '], '', $rucCliente);
                if ($codCliente !== '') {
                    $sql .= "AND (UPPER(TRIM(i.ruc_cliente)) = UPPER(?) OR UPPER(REPLACE(i.ruc_cliente, '-', '')) = UPPER(?) OR TRIM(i.cod_cliente) = ?) ";
                    $params[] = $rucCliente;
                    $params[] = $rucNoDash;
                    $params[] = $codCliente;
                } else {
                    $sql .= "AND (UPPER(TRIM(i.ruc_cliente)) = UPPER(?) OR UPPER(REPLACE(i.ruc_cliente, '-', '')) = UPPER(?)) ";
                    $params[] = $rucCliente;
                    $params[] = $rucNoDash;
                }
            } elseif ($codCliente !== '') {
                $sql .= "AND TRIM(i.cod_cliente) = ? ";
                $params[] = $codCliente;
            }

            if ($nroFiltro !== '') {
                $cleanNro = str_replace(['-', ' '], '', $nroFiltro);
                $sql .= "AND (REPLACE(REPLACE(TRIM(i.nro_factura), '-', ''), ' ', '') = ? OR UPPER(TRIM(i.nro_factura)) = UPPER(TRIM(?))) ";
                $params[] = $cleanNro;
                $params[] = $nroFiltro;
            }

            $sql .= "ORDER BY i.fec_ingreso DESC, i.id_ingreso DESC LIMIT 200";
            $facturas = Database::queryForList($sql, $params);

            $result = [];
            foreach ($facturas as $f) {
                $tipDocRaw = strtoupper(trim((string)($f['tip_doc'] ?? 'FAC')));
                if (self::isLiquidacionSalario($tipDocRaw) || !in_array($tipDocRaw, ['FAC', 'FACTURA'], true)) {
                    continue;
                }
                $tipLabel = 'Factura';
                $idIng    = (int)($f['id_ingreso'] ?? 0);
                $nroFac   = self::formatNroFactura((string)($f['nro_factura'] ?? ''), $tipDocRaw);
                $nroNorm  = strtoupper(str_replace(['-', ' '], '', $nroFac));

                $nroRetAsoc = $mapRelPorId[$idIng] ?? ($mapRelPorNro[$nroNorm] ?? null);
                $yaRelacionada = ($nroRetAsoc !== null && $nroRetAsoc !== '');

                // En el listado del botón Buscar (sin filtro puntual de número), ocultar las facturas ya relacionadas
                if ($nroFiltro === '' && $yaRelacionada) {
                    continue;
                }

                $result[] = [
                    'id_ingreso'         => $idIng,
                    'tip_comprobante'    => $tipLabel,
                    'tip_doc'            => $tipDocRaw,
                    'nro_timbrado'       => (string)($f['nro_timbrado'] ?? ''),
                    'nro_comprobante'    => $nroFac,
                    'serie'              => (string)($f['serie'] ?? ''),
                    'fec_comprobante'    => (string)($f['fec_ingreso'] ?? ''),
                    'fec_fmt'            => (string)($f['fec_ingreso_fmt'] ?? ''),
                    'ruc_cliente'        => (string)($f['ruc_cliente'] ?? ''),
                    'nom_cliente'        => (string)($f['nom_cliente'] ?? ''),
                    'cod_cliente'        => (string)($f['cod_cliente'] ?? ''),
                    'cod_moneda'         => (string)($f['cod_moneda'] ?? '1'),
                    'grav10'             => (float)($f['grav10'] ?? 0),
                    'iva10'              => (float)($f['iva10'] ?? 0),
                    'grav5'              => (float)($f['grav5'] ?? 0),
                    'iva5'               => (float)($f['iva5'] ?? 0),
                    'exentas'            => (float)($f['exentas'] ?? 0),
                    'tot_iva'            => (float)($f['tot_iva'] ?? 0),
                    'valor_total'        => (float)($f['valor_total'] ?? 0),
                    'ya_relacionada'     => $yaRelacionada,
                    'nro_retencion_asoc' => $nroRetAsoc ?? '',
                ];
            }

            View::json(['status' => 'success', 'data' => $result]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al consultar comprobantes del cliente: ' . $e->getMessage()]);
        }
    }

    public static function apiRetencionesSufridasList(): void {
        if (!self::requireAuth()) return;
        self::ensureRetencionesSufridasTables();
        $idProp     = TenantContext::idPropietario();
        $empresa    = TenantContext::empresa();
        $anio       = trim((string)($_GET['anio'] ?? ''));
        $mes        = trim((string)($_GET['mes'] ?? ''));
        $fechaDesde = self::parseSqlDate((string)($_GET['fechaDesde'] ?? $_GET['desde'] ?? ''));
        $fechaHasta = self::parseSqlDate((string)($_GET['fechaHasta'] ?? $_GET['hasta'] ?? ''));
        $filtroAnul = trim((string)($_GET['anulada'] ?? $_GET['estado'] ?? ''));

        try {
            $sql = "SELECT r.id_retencion,
                           r.cod_empresa,
                           COALESCE(r.tip_comprobante, 'RES') AS tip_comprobante,
                           r.cod_cliente,
                           r.ruc_cliente,
                           COALESCE(r.tip_doc_cliente, 'RUC') AS tip_doc_cliente,
                           r.nom_cliente,
                           COALESCE(r.nro_timbrado, '') AS nro_timbrado,
                           COALESCE(r.tip_timbrado, 'Virtual') AS tip_timbrado,
                           COALESCE(r.form_145, 'ORIGINAL') AS form_145,
                           r.nro_retencion,
                           DATE_FORMAT(r.fec_retencion, '%Y-%m-%d') AS fec_retencion,
                           DATE_FORMAT(r.fec_retencion, '%d/%m/%Y') AS fec_retencion_fmt,
                           COALESCE(r.cod_moneda, '1') AS cod_moneda,
                           COALESCE(
                               (SELECT m.descripcion FROM monedas m WHERE m.id_propietario = r.id_propietario AND TRIM(m.cod_moneda) = TRIM(r.cod_moneda) LIMIT 1),
                               (SELECT m2.descripcion FROM monedas m2 WHERE m2.id_propietario = 1 AND TRIM(m2.cod_moneda) = TRIM(r.cod_moneda) LIMIT 1),
                               'GUARANÍ'
                           ) AS desc_moneda,
                           COALESCE(r.tasa_cambio, 1) AS tasa_cambio,
                           COALESCE(r.grav10, 0) AS grav10,
                           COALESCE(r.iva10, 0) AS iva10,
                           COALESCE(r.porc10, 70) AS porc10,
                           COALESCE(r.ret10, 0) AS ret10,
                           COALESCE(r.grav5, 0) AS grav5,
                           COALESCE(r.iva5, 0) AS iva5,
                           COALESCE(r.porc5, 0) AS porc5,
                           COALESCE(r.ret5, 0) AS ret5,
                           COALESCE(r.exentas, 0) AS exentas,
                           COALESCE(r.valor_total, 0) AS valor_total,
                           COALESCE(r.importe_retenido, 0) AS importe_retenido,
                           COALESCE(r.total_comprobantes, 0) AS total_comprobantes,
                           COALESCE(r.diferencia, 0) AS diferencia,
                           r.observacion,
                           COALESCE(r.anulada, 'N') AS anulada,
                           COALESCE(r.estado, 'A') AS estado,
                           r.usuario_reg,
                           DATE_FORMAT(r.fec_reg, '%d/%m/%Y %H:%i') AS fec_reg_fmt,
                           (SELECT GROUP_CONCAT(d.nro_comprobante SEPARATOR ', ')
                            FROM co_retenciones_sufridas_det d
                            WHERE d.id_propietario = r.id_propietario AND d.id_retencion = r.id_retencion
                           ) AS comprobantes_rel,
                           (SELECT COUNT(*)
                            FROM co_retenciones_sufridas_det d2
                            WHERE d2.id_propietario = r.id_propietario AND d2.id_retencion = r.id_retencion
                           ) AS cant_comprobantes
                    FROM co_retenciones_sufridas r
                    WHERE r.id_propietario = ? AND r.cod_empresa = ? ";
            $params = [$idProp, $empresa];

            if ($fechaDesde !== null) {
                $sql .= "AND DATE(r.fec_retencion) >= ? ";
                $params[] = $fechaDesde;
            }
            if ($fechaHasta !== null) {
                $sql .= "AND DATE(r.fec_retencion) <= ? ";
                $params[] = $fechaHasta;
            }
            if ($fechaDesde === null && $fechaHasta === null) {
                if ($anio !== '' && strcasecmp($anio, 'TODOS') !== 0) {
                    $sql .= "AND YEAR(r.fec_retencion) = ? ";
                    $params[] = (int)$anio;
                }
                if ($mes !== '' && strcasecmp($mes, 'TODOS') !== 0) {
                    $sql .= "AND MONTH(r.fec_retencion) = ? ";
                    $params[] = (int)$mes;
                }
            }
            $sql .= "ORDER BY r.fec_retencion DESC, r.id_retencion DESC";

            $list = Database::queryForListUpper($sql, $params);
            View::json(['status' => 'success', 'data' => $list]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al listar retenciones sufridas: ' . $e->getMessage()]);
        }
    }

    public static function apiRetencionesSufridasGet(string $id): void {
        if (!self::requireAuth()) return;
        self::ensureRetencionesSufridasTables();
        $idProp      = TenantContext::idPropietario();
        $empresa     = TenantContext::empresa();
        $idRetencion = (int)$id;

        try {
            $cab = Database::queryForListUpper(
                "SELECT id_retencion, cod_empresa, COALESCE(tip_comprobante, 'RES') AS tip_comprobante,
                        cod_cliente, ruc_cliente, COALESCE(tip_doc_cliente, 'RUC') AS tip_doc_cliente,
                        nom_cliente, COALESCE(nro_timbrado, '') AS nro_timbrado,
                        COALESCE(tip_timbrado, 'Virtual') AS tip_timbrado,
                        COALESCE(form_145, 'ORIGINAL') AS form_145,
                        nro_retencion,
                        DATE_FORMAT(fec_retencion, '%Y-%m-%d') AS fec_retencion,
                        COALESCE(cod_moneda, '1') AS cod_moneda,
                        COALESCE(tasa_cambio, 1) AS tasa_cambio,
                        COALESCE(grav10, 0) AS grav10, COALESCE(iva10, 0) AS iva10,
                        COALESCE(porc10, 70) AS porc10, COALESCE(ret10, 0) AS ret10,
                        COALESCE(grav5, 0) AS grav5, COALESCE(iva5, 0) AS iva5,
                        COALESCE(porc5, 0) AS porc5, COALESCE(ret5, 0) AS ret5,
                        COALESCE(exentas, 0) AS exentas,
                        COALESCE(valor_total, 0) AS valor_total,
                        COALESCE(importe_retenido, 0) AS importe_retenido,
                        COALESCE(total_comprobantes, 0) AS total_comprobantes,
                        COALESCE(diferencia, 0) AS diferencia,
                        observacion, COALESCE(anulada, 'N') AS anulada,
                        COALESCE(estado, 'A') AS estado, usuario_reg,
                        DATE_FORMAT(fec_reg, '%d/%m/%Y %H:%i') AS fec_reg_fmt
                 FROM co_retenciones_sufridas
                 WHERE id_propietario = ? AND id_retencion = ? AND cod_empresa = ?
                 LIMIT 1",
                [$idProp, $idRetencion, $empresa]
            );
            if (empty($cab)) {
                View::json(['status' => 'error', 'message' => 'Retención sufrida no encontrada.']);
                return;
            }

            $detalles = Database::queryForListUpper(
                "SELECT id_detalle, id_ingreso, COALESCE(tip_comprobante, 'Factura') AS tip_comprobante,
                        nro_comprobante,
                        DATE_FORMAT(fec_comprobante, '%Y-%m-%d') AS fec_comprobante,
                        DATE_FORMAT(fec_comprobante, '%d/%m/%Y') AS fec_comprobante_fmt,
                        COALESCE(grav10, 0) AS grav10, COALESCE(iva10, 0) AS iva10,
                        COALESCE(grav5, 0) AS grav5, COALESCE(iva5, 0) AS iva5,
                        COALESCE(exentas, 0) AS exentas, COALESCE(tot_iva, 0) AS tot_iva,
                        COALESCE(valor_total, 0) AS valor_total,
                        COALESCE(importe_retenido, 0) AS importe_retenido
                 FROM co_retenciones_sufridas_det
                 WHERE id_propietario = ? AND id_retencion = ? AND cod_empresa = ?
                 ORDER BY id_detalle ASC",
                [$idProp, $idRetencion, $empresa]
            );

            $data = $cab[0];
            $data['DETALLES'] = $detalles;
            View::json(['status' => 'success', 'data' => $data]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al obtener retención sufrida: ' . $e->getMessage()]);
        }
    }

    public static function apiRetencionesSufridasSave(): void {
        if (!self::requireAuth()) return;
        self::ensureRetencionesSufridasTables();
        $idProp  = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();

        try {
            $idRetencion  = !empty($payload['idRetencion']) ? (int)$payload['idRetencion'] : null;
            $rucCliente   = trim((string)($payload['rucCliente'] ?? ''));
            $tipDocCli    = strtoupper(trim((string)($payload['tipDocCliente'] ?? 'RUC'))) ?: 'RUC';
            $codCliente   = trim((string)($payload['codCliente'] ?? ''));
            $nomCliente   = substr(trim((string)($payload['nomCliente'] ?? '')), 0, 150);

            if ($rucCliente === '' || $nomCliente === '') {
                View::json(['status' => 'error', 'message' => 'Debe indicar el RUC y Razón Social del Cliente (Agente de Retención).']);
                return;
            }

            if ($codCliente === '' || strcasecmp($codCliente, 'null') === 0) {
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

            $nroTimbrado  = substr(trim((string)($payload['nroTimbrado'] ?? '')), 0, 20);
            $tipTimbrado  = trim((string)($payload['tipTimbrado'] ?? 'Virtual')) ?: 'Virtual';
            $form145      = strtoupper(trim((string)($payload['form145'] ?? 'ORIGINAL'))) ?: 'ORIGINAL';
            $nroRetencion = self::formatNroFactura(trim((string)($payload['nroRetencion'] ?? '')), 'RES');

            if ($nroRetencion === '') {
                View::json(['status' => 'error', 'message' => 'Debe indicar el Número del Comprobante de Retención.']);
                return;
            }

            // Validar que no exista otro comprobante de retención igual para el mismo cliente y empresa
            $rucNorm = strtoupper(str_replace(['-', ' '], '', $rucCliente));
            $dupParams = [$idProp, $empresa, $nroRetencion, $rucNorm];
            $dupSql = "SELECT id_retencion, DATE_FORMAT(fec_retencion, '%d/%m/%Y') AS fec_fmt, nom_cliente
                       FROM co_retenciones_sufridas
                       WHERE id_propietario = ?
                         AND cod_empresa = ?
                         AND UPPER(TRIM(nro_retencion)) = UPPER(TRIM(?))
                         AND UPPER(REPLACE(REPLACE(TRIM(COALESCE(ruc_cliente, '')), '-', ''), ' ', '')) = ?
                         AND COALESCE(estado, 'A') <> 'I'";
            if ($idRetencion) {
                $dupSql .= " AND id_retencion <> ?";
                $dupParams[] = $idRetencion;
            }
            $dupRows = Database::queryForList($dupSql . " LIMIT 1", $dupParams);
            if (!empty($dupRows)) {
                $dRow = $dupRows[0];
                $fecDup = $dRow['fec_fmt'] ?? '-';
                View::json([
                    'status'  => 'error',
                    'message' => "El Comprobante de Retención N° {$nroRetencion} del cliente {$nomCliente} ({$rucCliente}) ya se encuentra registrado (Fecha: {$fecDup}). No se permiten comprobantes repetidos."
                ]);
                return;
            }

            $fecRetencion = self::parseSqlDate((string)($payload['fecRetencion'] ?? '')) ?: date('Y-m-d');
            $codMoneda    = trim((string)($payload['codMoneda'] ?? '1')) ?: '1';
            $tasaCambio   = (float)($payload['tasaCambio'] ?? 1);
            if ($tasaCambio <= 0) $tasaCambio = 1.0;

            $grav10       = (float)($payload['grav10'] ?? 0);
            $iva10        = (float)($payload['iva10'] ?? 0);
            $porc10       = (float)($payload['porc10'] ?? 30);
            $ret10        = (float)($payload['ret10'] ?? 0);
            $grav5        = (float)($payload['grav5'] ?? 0);
            $iva5         = (float)($payload['iva5'] ?? 0);
            $porc5        = (float)($payload['porc5'] ?? 0);
            $ret5         = (float)($payload['ret5'] ?? 0);
            $exentas      = (float)($payload['exentas'] ?? 0);
            $valorTotal   = (float)($payload['valorTotal'] ?? ($grav10 + $grav5 + $exentas));
            $impRetenido  = (float)($payload['importeRetenido'] ?? ($ret10 + $ret5));
            $observacion  = isset($payload['observacion']) ? trim((string)$payload['observacion']) : null;
            $rawAnul      = $payload['anulada'] ?? 'N';
            $anulada      = (!empty($rawAnul) && $rawAnul !== 'N' && $rawAnul !== 'false') ? 'S' : 'N';

            $detallesRaw = is_array($payload['detalles'] ?? null) ? $payload['detalles'] : [];
            $sumComprobantes = 0.0;
            $detallesLimpios = [];
            $vistosEnDetalle = [];
            foreach ($detallesRaw as $d) {
                $nroComp = trim((string)($d['nroComprobante'] ?? $d['comprobante'] ?? ''));
                if ($nroComp === '') continue;
                $idIng       = !empty($d['idIngreso']) ? (int)$d['idIngreso'] : null;
                $tipComp     = trim((string)($d['tipComprobante'] ?? $d['tipo'] ?? 'Factura')) ?: 'Factura';
                $fecComp     = self::parseSqlDate((string)($d['fecComprobante'] ?? '')) ?: $fecRetencion;
                $dGrav10     = (float)($d['grav10'] ?? 0);
                $dIva10      = (float)($d['iva10'] ?? 0);
                $dGrav5      = (float)($d['grav5'] ?? 0);
                $dIva5       = (float)($d['iva5'] ?? 0);
                $dExentas    = (float)($d['exentas'] ?? 0);
                $dTotIva     = (float)($d['totIva'] ?? ($dIva10 + $dIva5));
                $dValTotal   = (float)($d['valorTotal'] ?? $d['total'] ?? ($dGrav10 + $dGrav5 + $dExentas));
                $dRetenido   = (float)($d['importeRetenido'] ?? $d['retenido'] ?? 0);

                $nroFmt  = self::formatNroFactura($nroComp, 'FAC');
                $nroNorm = strtoupper(str_replace(['-', ' '], '', $nroFmt));
                if (isset($vistosEnDetalle[$nroNorm])) {
                    continue;
                }
                $vistosEnDetalle[$nroNorm] = true;

                // Validar que la factura no esté ya relacionada en otra retención activa
                $relParams = [$idProp, $empresa];
                $relConds  = [];
                if ($idIng) {
                    $relConds[]  = "d.id_ingreso = ?";
                    $relParams[] = $idIng;
                }
                if ($nroNorm !== '') {
                    $relConds[]  = "UPPER(REPLACE(REPLACE(TRIM(d.nro_comprobante), '-', ''), ' ', '')) = ?";
                    $relParams[] = $nroNorm;
                }
                if (!empty($relConds)) {
                    $relSql = "SELECT r.nro_retencion, DATE_FORMAT(r.fec_retencion, '%d/%m/%Y') AS fec_fmt
                               FROM co_retenciones_sufridas_det d
                               INNER JOIN co_retenciones_sufridas r
                                  ON r.id_retencion = d.id_retencion
                                 AND r.id_propietario = d.id_propietario
                               WHERE d.id_propietario = ?
                                 AND d.cod_empresa = ?
                                 AND (" . implode(' OR ', $relConds) . ")
                                 AND COALESCE(d.estado, 'A') <> 'I'
                                 AND COALESCE(r.anulada, 'N') <> 'S'
                                 AND COALESCE(r.estado, 'A') <> 'I'";
                    if ($idRetencion) {
                        $relSql .= " AND r.id_retencion <> ?";
                        $relParams[] = $idRetencion;
                    }
                    $rowsYaRel = Database::queryForList($relSql . " LIMIT 1", $relParams);
                    if (!empty($rowsYaRel)) {
                        $rRel = $rowsYaRel[0];
                        View::json([
                            'status'  => 'error',
                            'message' => "La factura N° {$nroFmt} ya se encuentra relacionada con el Comprobante de Retención N° " . ($rRel['nro_retencion'] ?? '-') . " (Fecha: " . ($rRel['fec_fmt'] ?? '-') . "). Cada factura se puede relacionar una sola vez."
                        ]);
                        return;
                    }
                }

                $sumComprobantes += $dRetenido;

                $detallesLimpios[] = [
                    'id_ingreso'       => $idIng,
                    'tip_comprobante'  => $tipComp,
                    'nro_comprobante'  => $nroFmt,
                    'fec_comprobante'  => $fecComp,
                    'grav10'           => $dGrav10,
                    'iva10'            => $dIva10,
                    'grav5'            => $dGrav5,
                    'iva5'             => $dIva5,
                    'exentas'          => $dExentas,
                    'tot_iva'          => $dTotIva,
                    'valor_total'      => $dValTotal,
                    'importe_retenido' => $dRetenido,
                ];
            }

            $totalComprobantes = $sumComprobantes > 0 ? $sumComprobantes : $impRetenido;
            $diferencia        = $impRetenido - $totalComprobantes;

            if ($idRetencion === null) {
                $savedId = (int)Database::insertGetId(
                    "INSERT INTO co_retenciones_sufridas (
                        id_propietario, cod_empresa, tip_comprobante, cod_cliente, ruc_cliente,
                        tip_doc_cliente, nom_cliente, nro_timbrado, tip_timbrado, form_145,
                        nro_retencion, fec_retencion, cod_moneda, tasa_cambio,
                        grav10, iva10, porc10, ret10, grav5, iva5, porc5, ret5,
                        exentas, valor_total, importe_retenido, total_comprobantes, diferencia,
                        observacion, anulada, estado, usuario_reg, fec_reg
                     ) VALUES (?, ?, 'RES', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'A', ?, NOW())",
                    [
                        $idProp, $empresa, $codCliente ?: null, $rucCliente,
                        $tipDocCli, $nomCliente, $nroTimbrado ?: null, $tipTimbrado, $form145,
                        $nroRetencion, $fecRetencion, $codMoneda, $tasaCambio,
                        $grav10, $iva10, $porc10, $ret10, $grav5, $iva5, $porc5, $ret5,
                        $exentas, $valorTotal, $impRetenido, $totalComprobantes, $diferencia,
                        $observacion, $anulada, $usuario
                    ]
                );
                $msg = "Retención Sufrida N° {$nroRetencion} registrada con éxito (#{$savedId}).";
            } else {
                Database::update(
                    "UPDATE co_retenciones_sufridas SET
                        cod_cliente = ?, ruc_cliente = ?, tip_doc_cliente = ?, nom_cliente = ?,
                        nro_timbrado = ?, tip_timbrado = ?, form_145 = ?, nro_retencion = ?,
                        fec_retencion = ?, cod_moneda = ?, tasa_cambio = ?,
                        grav10 = ?, iva10 = ?, porc10 = ?, ret10 = ?,
                        grav5 = ?, iva5 = ?, porc5 = ?, ret5 = ?,
                        exentas = ?, valor_total = ?, importe_retenido = ?,
                        total_comprobantes = ?, diferencia = ?, observacion = ?, anulada = ?
                     WHERE id_propietario = ? AND id_retencion = ? AND cod_empresa = ?",
                    [
                        $codCliente ?: null, $rucCliente, $tipDocCli, $nomCliente,
                        $nroTimbrado ?: null, $tipTimbrado, $form145, $nroRetencion,
                        $fecRetencion, $codMoneda, $tasaCambio,
                        $grav10, $iva10, $porc10, $ret10,
                        $grav5, $iva5, $porc5, $ret5,
                        $exentas, $valorTotal, $impRetenido,
                        $totalComprobantes, $diferencia, $observacion, $anulada,
                        $idProp, $idRetencion, $empresa
                    ]
                );
                Database::update(
                    "DELETE FROM co_retenciones_sufridas_det WHERE id_propietario = ? AND id_retencion = ? AND cod_empresa = ?",
                    [$idProp, $idRetencion, $empresa]
                );
                $savedId = $idRetencion;
                $msg = "Retención Sufrida N° {$nroRetencion} actualizada con éxito.";
            }

            foreach ($detallesLimpios as $det) {
                Database::update(
                    "INSERT INTO co_retenciones_sufridas_det (
                        id_propietario, id_retencion, cod_empresa, id_ingreso, tip_comprobante,
                        nro_comprobante, fec_comprobante, grav10, iva10, grav5, iva5,
                        exentas, tot_iva, valor_total, importe_retenido, estado, usuario_reg, fec_reg
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'A', ?, NOW())",
                    [
                        $idProp, $savedId, $empresa, $det['id_ingreso'], $det['tip_comprobante'],
                        $det['nro_comprobante'], $det['fec_comprobante'], $det['grav10'], $det['iva10'],
                        $det['grav5'], $det['iva5'], $det['exentas'], $det['tot_iva'],
                        $det['valor_total'], $det['importe_retenido'], $usuario
                    ]
                );
            }

            View::json([
                'status'      => 'success',
                'message'     => $msg,
                'idRetencion' => $savedId,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar retención sufrida: ' . $e->getMessage()]);
        }
    }

    public static function apiRetencionesSufridasDelete(): void {
        if (!self::requireAuth()) return;
        self::ensureRetencionesSufridasTables();
        $idProp      = TenantContext::idPropietario();
        $empresa     = TenantContext::empresa();
        $payload     = self::getInput();
        $idRetencion = (int)($payload['idRetencion'] ?? 0);

        if ($idRetencion <= 0) {
            View::json(['status' => 'error', 'message' => 'ID de retención sufrida inválido.']);
            return;
        }

        try {
            Database::update(
                "DELETE FROM co_retenciones_sufridas_det WHERE id_propietario = ? AND id_retencion = ? AND cod_empresa = ?",
                [$idProp, $idRetencion, $empresa]
            );
            Database::update(
                "DELETE FROM co_retenciones_sufridas WHERE id_propietario = ? AND id_retencion = ? AND cod_empresa = ?",
                [$idProp, $idRetencion, $empresa]
            );
            View::json(['status' => 'success', 'message' => "Retención Sufrida #{$idRetencion} eliminada correctamente."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar retención sufrida: ' . $e->getMessage()]);
        }
    }

    public static function apiRetencionesSufridasAnular(): void {
        if (!self::requireAuth()) return;
        self::ensureRetencionesSufridasTables();
        $idProp      = TenantContext::idPropietario();
        $empresa     = TenantContext::empresa();
        $payload     = self::getInput();
        $idRetencion = (int)($payload['idRetencion'] ?? 0);

        if ($idRetencion <= 0) {
            View::json(['status' => 'error', 'message' => 'Seleccione una retención sufrida para anular.']);
            return;
        }

        try {
            $row = Database::queryForMap(
                "SELECT id_retencion, nro_retencion, COALESCE(anulada, 'N') AS anulada
                 FROM co_retenciones_sufridas
                 WHERE id_propietario = ? AND id_retencion = ? AND cod_empresa = ?
                 LIMIT 1",
                [$idProp, $idRetencion, $empresa]
            );
            if (!$row) {
                View::json(['status' => 'error', 'message' => 'Retención sufrida no encontrada.']);
                return;
            }
            $nroRet = trim((string)($row['nro_retencion'] ?? '')) ?: ('#' . $idRetencion);
            $nuevoEstado = (isset($payload['anulada']) && strtoupper((string)$payload['anulada']) === 'N') ? 'N' : 'S';

            Database::update(
                "UPDATE co_retenciones_sufridas SET anulada = ?
                 WHERE id_propietario = ? AND id_retencion = ? AND cod_empresa = ?",
                [$nuevoEstado, $idProp, $idRetencion, $empresa]
            );

            View::json([
                'status'  => 'success',
                'anulada' => $nuevoEstado,
                'message' => $nuevoEstado === 'S'
                    ? "La Retención Sufrida {$nroRet} fue anulada correctamente."
                    : "La Retención Sufrida {$nroRet} fue reactivada correctamente.",
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al actualizar estado de retención sufrida: ' . $e->getMessage()]);
        }
    }

    public static function apiRetencionesSufridasValidarImportacion(): void {
        if (!self::requireAuth()) return;
        self::ensureRetencionesSufridasTables();
        $idProp  = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();

        try {
            // 1. Retenciones sufridas existentes
            $retRows = Database::queryForList(
                "SELECT id_retencion,
                        TRIM(COALESCE(nro_retencion, '')) AS nro_retencion,
                        REPLACE(REPLACE(UPPER(TRIM(COALESCE(nro_retencion, ''))), '-', ''), ' ', '') AS ret_clean,
                        TRIM(COALESCE(nro_timbrado, '')) AS nro_timbrado,
                        DATE_FORMAT(fec_retencion, '%d/%m/%Y') AS fec_retencion_fmt,
                        TRIM(COALESCE(nom_cliente, '')) AS nom_cliente,
                        TRIM(COALESCE(ruc_cliente, '')) AS ruc_cliente,
                        SUBSTRING_INDEX(REPLACE(COALESCE(ruc_cliente, ''), ' ', ''), '-', 1) AS ruc_base,
                        COALESCE(importe_retenido, 0) AS importe_retenido
                 FROM co_retenciones_sufridas
                 WHERE id_propietario = ? AND cod_empresa = ?
                   AND COALESCE(estado, 'A') <> 'I'",
                [$idProp, $empresa]
            );

            $existingRetenciones = [];
            $timbradosPorCliente = [];
            foreach ($retRows as $rr) {
                $retClean = (string)($rr['ret_clean'] ?? '');
                $rucB     = ltrim(preg_replace('/\D/', '', (string)($rr['ruc_base'] ?? '')), '0');
                $timb     = trim((string)($rr['nro_timbrado'] ?? ''));

                if ($rucB !== '' && $timb !== '' && !isset($timbradosPorCliente[$rucB])) {
                    $timbradosPorCliente[$rucB] = $timb;
                }

                if ($retClean !== '') {
                    $info = [
                        'idRetencion'     => (int)$rr['id_retencion'],
                        'nroRetencion'    => (string)$rr['nro_retencion'],
                        'nroTimbrado'     => $timb,
                        'fecRetencion'    => (string)($rr['fec_retencion_fmt'] ?? ''),
                        'nomCliente'      => (string)$rr['nom_cliente'],
                        'rucCliente'      => (string)$rr['ruc_cliente'],
                        'importeRetenido' => (float)$rr['importe_retenido'],
                    ];
                    if ($rucB !== '') {
                        $existingRetenciones[$retClean . '|' . $rucB] = $info;
                    }
                    if (!isset($existingRetenciones[$retClean])) {
                        $existingRetenciones[$retClean] = $info;
                    }
                }
            }

            // 2. Facturas ya relacionadas en alguna retención activa
            $relRows = Database::queryForList(
                "SELECT d.id_ingreso,
                        d.nro_comprobante,
                        REPLACE(REPLACE(UPPER(TRIM(COALESCE(d.nro_comprobante, ''))), '-', ''), ' ', '') AS comp_clean,
                        r.nro_retencion,
                        DATE_FORMAT(r.fec_retencion, '%d/%m/%Y') AS fec_retencion_fmt
                 FROM co_retenciones_sufridas_det d
                 INNER JOIN co_retenciones_sufridas r
                    ON r.id_retencion = d.id_retencion
                   AND r.id_propietario = d.id_propietario
                 WHERE d.id_propietario = ?
                   AND d.cod_empresa = ?
                   AND COALESCE(d.estado, 'A') <> 'I'
                   AND COALESCE(r.anulada, 'N') <> 'S'
                   AND COALESCE(r.estado, 'A') <> 'I'",
                [$idProp, $empresa]
            );
            $facturasRelacionadas = [];
            foreach ($relRows as $rel) {
                $compClean = (string)($rel['comp_clean'] ?? '');
                if ($compClean !== '') {
                    $facturasRelacionadas[$compClean] = [
                        'idIngreso'    => !empty($rel['id_ingreso']) ? (int)$rel['id_ingreso'] : null,
                        'nroFactura'   => (string)($rel['nro_comprobante'] ?? ''),
                        'nroRetencion' => (string)($rel['nro_retencion'] ?? ''),
                        'fecRetencion' => (string)($rel['fec_retencion_fmt'] ?? ''),
                    ];
                }
            }

            // 3. Facturas de Ingresos (solo FAC, excluyendo LIQ)
            $facRows = Database::queryForList(
                "SELECT id_ingreso,
                        TRIM(COALESCE(nro_factura, '')) AS nro_factura,
                        REPLACE(REPLACE(UPPER(TRIM(COALESCE(nro_factura, ''))), '-', ''), ' ', '') AS fac_clean,
                        DATE_FORMAT(fec_ingreso, '%Y-%m-%d') AS fec_ingreso,
                        DATE_FORMAT(fec_ingreso, '%d/%m/%Y') AS fec_ingreso_fmt,
                        TRIM(COALESCE(cod_cliente, '')) AS cod_cliente,
                        TRIM(COALESCE(ruc_cliente, '')) AS ruc_cliente,
                        SUBSTRING_INDEX(REPLACE(COALESCE(ruc_cliente, ''), ' ', ''), '-', 1) AS ruc_base,
                        TRIM(COALESCE(nom_cliente, '')) AS nom_cliente,
                        COALESCE(cod_moneda, '1') AS cod_moneda,
                        COALESCE(grav10, 0) AS grav10,
                        COALESCE(iva10, 0) AS iva10,
                        COALESCE(grav5, 0) AS grav5,
                        COALESCE(iva5, 0) AS iva5,
                        COALESCE(exentas, 0) AS exentas,
                        COALESCE(tot_iva, 0) AS tot_iva,
                        COALESCE(valor_total, 0) AS valor_total
                 FROM co_ingresos
                 WHERE id_propietario = ? AND cod_empresa = ?
                   AND COALESCE(anulada, 'N') <> 'S'
                   AND COALESCE(inutilizado, 'N') <> 'S'
                   AND COALESCE(estado, 'A') <> 'I'
                   AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) <> 'LIQ'
                   AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) NOT LIKE '%LIQUIDAC%'
                   AND (UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) = 'FAC' OR UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) LIKE '%FACTURA%')
                 ORDER BY fec_ingreso DESC, id_ingreso DESC",
                [$idProp, $empresa]
            );
            $facturasMap = [];
            foreach ($facRows as $fr) {
                $facClean = (string)($fr['fac_clean'] ?? '');
                if ($facClean === '') continue;
                $rucB = ltrim(preg_replace('/\D/', '', (string)($fr['ruc_base'] ?? '')), '0');
                $fInfo = [
                    'idIngreso'     => (int)$fr['id_ingreso'],
                    'nroFactura'    => (string)$fr['nro_factura'],
                    'fecIngreso'    => (string)($fr['fec_ingreso'] ?? ''),
                    'fecIngresoFmt' => (string)($fr['fec_ingreso_fmt'] ?? ''),
                    'codCliente'    => (string)($fr['cod_cliente'] ?? ''),
                    'rucCliente'    => (string)($fr['ruc_cliente'] ?? ''),
                    'nomCliente'    => (string)($fr['nom_cliente'] ?? ''),
                    'codMoneda'     => (string)($fr['cod_moneda'] ?? '1'),
                    'grav10'        => (float)$fr['grav10'],
                    'iva10'         => (float)$fr['iva10'],
                    'grav5'         => (float)$fr['grav5'],
                    'iva5'          => (float)$fr['iva5'],
                    'exentas'       => (float)$fr['exentas'],
                    'totIva'        => (float)$fr['tot_iva'],
                    'valorTotal'    => (float)$fr['valor_total'],
                ];
                if ($rucB !== '') {
                    $facturasMap[$facClean . '|' . $rucB] = $fInfo;
                }
                if (!isset($facturasMap[$facClean])) {
                    $facturasMap[$facClean] = $fInfo;
                }
            }

            // 4. Clientes existentes
            $cliRows = Database::queryForList(
                "SELECT cod_cliente, nombre, ruc
                 FROM co_clientes
                 WHERE id_propietario = ?
                 ORDER BY CASE WHEN cod_empresa = ? THEN 1 ELSE 2 END, CAST(cod_cliente AS UNSIGNED) DESC",
                [$idProp, $empresa]
            );
            $clientesMap = [];
            foreach ($cliRows as $cr) {
                $rawR = trim((string)($cr['ruc'] ?? ''));
                if ($rawR === '') continue;
                $baseR = ltrim(preg_replace('/\D/', '', explode('-', $rawR)[0]), '0');
                if ($baseR !== '' && !isset($clientesMap[$baseR])) {
                    $clientesMap[$baseR] = [
                        'codCliente'     => (string)$cr['cod_cliente'],
                        'nombre'         => (string)$cr['nombre'],
                        'ruc'            => $rawR,
                        'ultimoTimbrado' => $timbradosPorCliente[$baseR] ?? '',
                    ];
                }
            }

            View::json([
                'status'               => 'success',
                'existingRetenciones'  => $existingRetenciones,
                'facturasRelacionadas' => $facturasRelacionadas,
                'facturasMap'          => $facturasMap,
                'clientesMap'          => $clientesMap,
                'timbradosPorCliente'  => $timbradosPorCliente,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al validar datos de importación de retenciones: ' . $e->getMessage()]);
        }
    }

    public static function apiRetencionesSufridasImportarLote(): void {
        if (!self::requireAuth()) return;
        self::ensureRetencionesSufridasTables();
        $idProp  = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();
        $items   = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        if (empty($items)) {
            View::json(['status' => 'error', 'message' => 'No hay retenciones válidas para importar.']);
            return;
        }

        try {
            @set_time_limit(180);

            // Cache de clientes existentes
            $cliRows = Database::queryForList(
                "SELECT cod_cliente, nombre, ruc
                 FROM co_clientes
                 WHERE id_propietario = ?
                 ORDER BY CASE WHEN cod_empresa = ? THEN 1 ELSE 2 END, CAST(cod_cliente AS UNSIGNED) DESC",
                [$idProp, $empresa]
            );
            $cliCache = [];
            foreach ($cliRows as $cr) {
                $rawR = trim((string)($cr['ruc'] ?? ''));
                if ($rawR === '') continue;
                $baseR = ltrim(preg_replace('/\D/', '', explode('-', $rawR)[0]), '0');
                if ($baseR !== '' && !isset($cliCache[$baseR])) {
                    $cliCache[$baseR] = [
                        'codCliente' => (string)$cr['cod_cliente'],
                        'nombre'     => (string)$cr['nombre'],
                        'ruc'        => $rawR,
                    ];
                }
            }

            // Cache de retenciones existentes
            $retRows = Database::queryForList(
                "SELECT REPLACE(REPLACE(UPPER(TRIM(COALESCE(nro_retencion, ''))), '-', ''), ' ', '') AS ret_clean,
                        SUBSTRING_INDEX(REPLACE(COALESCE(ruc_cliente, ''), ' ', ''), '-', 1) AS ruc_base
                 FROM co_retenciones_sufridas
                 WHERE id_propietario = ? AND cod_empresa = ?
                   AND COALESCE(estado, 'A') <> 'I'",
                [$idProp, $empresa]
            );
            $existingRetKeys = [];
            foreach ($retRows as $rr) {
                $retC = (string)($rr['ret_clean'] ?? '');
                $rucB = ltrim(preg_replace('/\D/', '', (string)($rr['ruc_base'] ?? '')), '0');
                if ($retC !== '') {
                    $existingRetKeys[$retC . '|' . $rucB] = true;
                    $existingRetKeys[$retC] = true;
                }
            }

            // Cache de facturas ya relacionadas
            $relRows = Database::queryForList(
                "SELECT REPLACE(REPLACE(UPPER(TRIM(COALESCE(d.nro_comprobante, ''))), '-', ''), ' ', '') AS comp_clean
                 FROM co_retenciones_sufridas_det d
                 INNER JOIN co_retenciones_sufridas r
                    ON r.id_retencion = d.id_retencion
                   AND r.id_propietario = d.id_propietario
                 WHERE d.id_propietario = ?
                   AND d.cod_empresa = ?
                   AND COALESCE(d.estado, 'A') <> 'I'
                   AND COALESCE(r.anulada, 'N') <> 'S'
                   AND COALESCE(r.estado, 'A') <> 'I'",
                [$idProp, $empresa]
            );
            $existingFacRel = [];
            foreach ($relRows as $rel) {
                $c = (string)($rel['comp_clean'] ?? '');
                if ($c !== '') {
                    $existingFacRel[$c] = true;
                }
            }

            // Cache de facturas de ingresos (solo FAC, no LIQ)
            $facRows = Database::queryForList(
                "SELECT id_ingreso,
                        TRIM(COALESCE(nro_factura, '')) AS nro_factura,
                        REPLACE(REPLACE(UPPER(TRIM(COALESCE(nro_factura, ''))), '-', ''), ' ', '') AS fac_clean,
                        DATE_FORMAT(fec_ingreso, '%Y-%m-%d') AS fec_ingreso,
                        COALESCE(grav10, 0) AS grav10,
                        COALESCE(iva10, 0) AS iva10,
                        COALESCE(grav5, 0) AS grav5,
                        COALESCE(iva5, 0) AS iva5,
                        COALESCE(exentas, 0) AS exentas,
                        COALESCE(tot_iva, 0) AS tot_iva,
                        COALESCE(valor_total, 0) AS valor_total
                 FROM co_ingresos
                 WHERE id_propietario = ? AND cod_empresa = ?
                   AND COALESCE(anulada, 'N') <> 'S'
                   AND COALESCE(inutilizado, 'N') <> 'S'
                   AND COALESCE(estado, 'A') <> 'I'
                   AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) <> 'LIQ'
                   AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) NOT LIKE '%LIQUIDAC%'
                   AND (UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) = 'FAC' OR UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) LIKE '%FACTURA%')
                 ORDER BY fec_ingreso DESC, id_ingreso DESC",
                [$idProp, $empresa]
            );
            $facCache = [];
            foreach ($facRows as $fr) {
                $fc = (string)($fr['fac_clean'] ?? '');
                if ($fc !== '' && !isset($facCache[$fc])) {
                    $facCache[$fc] = $fr;
                }
            }

            $clientesCreados    = 0;
            $insertados         = 0;
            $duplicadosOmitidos = 0;
            $minFecha           = null;
            $maxFecha           = null;

            foreach ($items as $item) {
                $nroRetencion = self::formatNroFactura(trim((string)($item['nroRetencion'] ?? '')), 'RES');
                $fecRetencion = self::parseSqlDate((string)($item['fecRetencion'] ?? ''));
                $rawRuc       = trim((string)($item['rucCliente'] ?? ''));
                $nomCliente   = mb_strtoupper(trim((string)($item['nomCliente'] ?? '')), 'UTF-8');
                $nroTimbrado  = substr(preg_replace('/\D/', '', (string)($item['nroTimbrado'] ?? '')), 0, 20);
                $tipTimbrado  = trim((string)($item['tipTimbrado'] ?? 'Virtual')) ?: 'Virtual';

                if ($nroRetencion === '' || !$fecRetencion || $rawRuc === '' || $nomCliente === '') {
                    continue;
                }

                $rucConDv = self::normalizarRucConDv($rawRuc);
                $rucBase  = ltrim(preg_replace('/\D/', '', explode('-', $rucConDv)[0]), '0');
                $retClean = str_replace(['-', ' '], '', strtoupper($nroRetencion));
                $dupKey   = $retClean . '|' . $rucBase;

                if (isset($existingRetKeys[$dupKey]) || isset($existingRetKeys[$retClean])) {
                    $duplicadosOmitidos++;
                    continue;
                }

                $rawNroComp = trim((string)($item['nroComprobante'] ?? ''));
                $nroCompFmt = $rawNroComp !== '' ? self::formatNroFactura($rawNroComp, 'FAC') : '';
                $compClean  = str_replace(['-', ' '], '', strtoupper($nroCompFmt));

                if ($compClean !== '' && isset($existingFacRel[$compClean])) {
                    $duplicadosOmitidos++;
                    continue;
                }

                // Verificar que la factura exista en Ingresos si fue informada
                $facRow = ($compClean !== '' && isset($facCache[$compClean])) ? $facCache[$compClean] : null;
                if ($compClean !== '' && !$facRow) {
                    $duplicadosOmitidos++;
                    continue;
                }

                // Verificar o crear cliente automáticamente
                if ($rucBase !== '' && isset($cliCache[$rucBase])) {
                    $codCliente = $cliCache[$rucBase]['codCliente'];
                    $rucFinal   = $cliCache[$rucBase]['ruc'] ?: $rucConDv;
                    if (!str_contains($rucFinal, '-')) {
                        $rucFinal = $rucConDv;
                    }
                } else {
                    $codPersona = \App\Services\PersonaService::syncPersonaData(null, $nomCliente, $rucConDv, 'RUC');
                    $nextCliId  = (int)Database::queryForValue(
                        "SELECT COALESCE(MAX(CAST(cod_cliente AS UNSIGNED)), 0) + 1
                         FROM co_clientes
                         WHERE id_propietario = ? AND CAST(cod_cliente AS UNSIGNED) < 3000",
                        [$idProp]
                    );
                    $codCliente = (string)max(1, $nextCliId);
                    Database::update(
                        "INSERT INTO co_clientes (id_propietario, cod_cliente, cod_empresa, cod_persona, nombre, ruc, tip_documento, estado, usuario_reg, fec_reg)
                         VALUES (?, ?, ?, ?, ?, ?, 'RUC', 'A', ?, NOW())",
                        [$idProp, $codCliente, $empresa, $codPersona, $nomCliente, $rucConDv, $usuario]
                    );
                    $rucFinal = $rucConDv;
                    if ($rucBase !== '') {
                        $cliCache[$rucBase] = [
                            'codCliente' => $codCliente,
                            'nombre'     => $nomCliente,
                            'ruc'        => $rucFinal,
                        ];
                    }
                    $clientesCreados++;
                }

                $impRetenido = round((float)($item['importeRetenido'] ?? 0));
                $porc10      = (float)($item['porc10'] ?? 30);
                if ($porc10 <= 0) $porc10 = 30.0;

                if ($facRow) {
                    $idIng      = (int)$facRow['id_ingreso'];
                    $fecComp    = (string)($facRow['fec_ingreso'] ?: $fecRetencion);
                    $grav10     = round((float)$facRow['grav10']);
                    $iva10      = round((float)($facRow['iva10'] > 0 ? $facRow['iva10'] : round($grav10 / 11)));
                    $grav5      = round((float)$facRow['grav5']);
                    $iva5       = round((float)($facRow['iva5'] > 0 ? $facRow['iva5'] : round($grav5 / 21)));
                    $exentas    = round((float)$facRow['exentas']);
                    $valorTotal = round((float)($facRow['valor_total'] > 0 ? $facRow['valor_total'] : ($grav10 + $grav5 + $exentas)));
                    if ($iva10 > 0 && $impRetenido > 0) {
                        $calcPorc = round(($impRetenido / $iva10) * 100, 2);
                        if (abs($calcPorc - 30) < 0.5) $porc10 = 30.0;
                        elseif (abs($calcPorc - 70) < 0.5) $porc10 = 70.0;
                        elseif (abs($calcPorc - 100) < 0.5) $porc10 = 100.0;
                        else $porc10 = $calcPorc;
                    }
                } else {
                    $idIng      = null;
                    $fecComp    = self::parseSqlDate((string)($item['fecComprobante'] ?? '')) ?: $fecRetencion;
                    $grav10     = round((float)($item['grav10'] ?? 0));
                    $iva10      = round((float)($item['iva10'] ?? round($grav10 / 11)));
                    $grav5      = round((float)($item['grav5'] ?? 0));
                    $iva5       = round((float)($item['iva5'] ?? 0));
                    $exentas    = round((float)($item['exentas'] ?? 0));
                    $valorTotal = round((float)($item['valorTotal'] ?? ($grav10 + $grav5 + $exentas)));
                }

                $ret10      = $impRetenido;
                $porc5      = 0.0;
                $ret5       = 0.0;
                $totIva     = $iva10 + $iva5;
                $observacion = trim((string)($item['observacion'] ?? '')) ?: null;

                $savedId = (int)Database::insertGetId(
                    "INSERT INTO co_retenciones_sufridas (
                        id_propietario, cod_empresa, tip_comprobante, cod_cliente, ruc_cliente,
                        tip_doc_cliente, nom_cliente, nro_timbrado, tip_timbrado, form_145,
                        nro_retencion, fec_retencion, cod_moneda, tasa_cambio,
                        grav10, iva10, porc10, ret10, grav5, iva5, porc5, ret5,
                        exentas, valor_total, importe_retenido, total_comprobantes, diferencia,
                        observacion, anulada, estado, usuario_reg, fec_reg
                     ) VALUES (?, ?, 'RES', ?, ?, 'RUC', ?, ?, ?, 'ORIGINAL', ?, ?, '1', 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'N', 'A', ?, NOW())",
                    [
                        $idProp, $empresa, $codCliente ?: null, $rucFinal,
                        $nomCliente, $nroTimbrado ?: null, $tipTimbrado,
                        $nroRetencion, $fecRetencion,
                        $grav10, $iva10, $porc10, $ret10, $grav5, $iva5, $porc5, $ret5,
                        $exentas, $valorTotal, $impRetenido, $impRetenido,
                        $observacion, $usuario
                    ]
                );

                if ($nroCompFmt !== '') {
                    Database::update(
                        "INSERT INTO co_retenciones_sufridas_det (
                            id_propietario, id_retencion, cod_empresa, id_ingreso, tip_comprobante,
                            nro_comprobante, fec_comprobante, grav10, iva10, grav5, iva5,
                            exentas, tot_iva, valor_total, importe_retenido, estado, usuario_reg, fec_reg
                         ) VALUES (?, ?, ?, ?, 'Factura', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'A', ?, NOW())",
                        [
                            $idProp, $savedId, $empresa, $idIng,
                            $nroCompFmt, $fecComp, $grav10, $iva10, $grav5, $iva5,
                            $exentas, $totIva, $valorTotal, $impRetenido, $usuario
                        ]
                    );
                    $existingFacRel[$compClean] = true;
                }

                $existingRetKeys[$dupKey]   = true;
                $existingRetKeys[$retClean] = true;
                $insertados++;

                if ($minFecha === null || $fecRetencion < $minFecha) $minFecha = $fecRetencion;
                if ($maxFecha === null || $fecRetencion > $maxFecha) $maxFecha = $fecRetencion;
            }

            View::json([
                'status'             => 'success',
                'insertados'         => $insertados,
                'clientesCreados'    => $clientesCreados,
                'duplicadosOmitidos' => $duplicadosOmitidos,
                'minFecha'           => $minFecha,
                'maxFecha'           => $maxFecha,
                'message'            => "Se importaron {$insertados} comprobante(s) de retención sufrida exitosamente"
                                      . ($clientesCreados > 0 ? " y se crearon {$clientesCreados} nuevo(s) cliente(s)." : "."),
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al importar lote de retenciones sufridas: ' . $e->getMessage()]);
        }
    }
}
