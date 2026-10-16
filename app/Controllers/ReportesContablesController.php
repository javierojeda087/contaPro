<?php
namespace App\Controllers;

use Core\Database;
use Core\TenantContext;
use Core\View;
use App\Services\MenuService;
use App\Services\EjercicioService;

class ReportesContablesController {

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

    private static function formatDateDmy(?string $ymd): string {
        if (!$ymd || strlen($ymd) < 10) return (string)$ymd;
        return substr($ymd, 8, 2) . '/' . substr($ymd, 5, 2) . '/' . substr($ymd, 0, 4);
    }

    private static function fetchEmpresaInfo(string $codEmpresa, string $ejercicio): array {
        $idProp = TenantContext::idPropietario();
        $result = [
            'codEmpresa'  => $codEmpresa,
            'descripcion' => 'CONTRIBUYENTE DEMOSTRATIVO',
            'rucEmpresa'  => '5555-7',
            'actividad'   => 'IRE-GENERAL/IRACIS',
        ];
        try {
            $row = Database::queryForMap(
                "SELECT cod_empresa, descripcion, ruc_empresa, actividad, titulo_reportes
                 FROM empresas WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
                [$idProp, $codEmpresa]
            );
            if ($row) {
                $result['codEmpresa']  = (string)($row['cod_empresa'] ?? $codEmpresa);
                $result['descripcion'] = (string)($row['descripcion'] ?? 'CONTRIBUYENTE DEMOSTRATIVO');
                $result['rucEmpresa']  = (string)($row['ruc_empresa'] ?? '');
                $act = trim((string)($row['actividad'] ?? ''));
                $result['actividad']   = $act !== '' ? $act : 'IRE-GENERAL/IRACIS';
            }
        } catch (\Throwable $ignored) {}

        $firm = EjercicioService::getFirmantes($codEmpresa, $ejercicio);
        $result['repLegal']    = $firm['repLegal'] ?? '';
        $result['nomContador'] = $firm['nomContador'] ?? '';
        $result['rucContador'] = $firm['rucContador'] ?? '';

        return $result;
    }

    // =========================================================================
    // 1. LIBRO COMPRAS (COLIBCOM)
    // =========================================================================
    public static function pageLibroCompras(): void {
        if (!self::requireAuth(false)) return;
        $codEmpresa = TenantContext::empresa();
        $ejercicio  = EjercicioService::getEjercicio();
        $empData    = self::fetchEmpresaInfo($codEmpresa, $ejercicio);
        $rango      = EjercicioService::getRango($codEmpresa, $ejercicio);
        $def        = EjercicioService::defaultMes($rango);

        self::renderFormPage('libro_compras', [
            'empresa'       => $empData,
            'defFechaDesde' => $def[0],
            'defFechaHasta' => $def[1],
            'ejerInicio'    => $rango[0],
            'ejerFinal'     => $rango[1],
            'ejercicio'     => $ejercicio,
        ]);
    }

    public static function apiLibroComprasData(): void {
        if (!self::requireAuth()) return;
        $codEmpresa      = TenantContext::empresa();
        $ejercicio       = EjercicioService::getEjercicio();
        $rango           = EjercicioService::getRango($codEmpresa, $ejercicio);
        $fechaDesde      = EjercicioService::clamp($_GET['fechaDesde'] ?? null, $rango, true);
        $fechaHasta      = EjercicioService::clamp($_GET['fechaHasta'] ?? null, $rango, false);
        $codProveedor    = trim((string)($_GET['codProveedor'] ?? ''));
        $condicion       = trim((string)($_GET['condicion'] ?? ''));
        $incluirAnulados = isset($_GET['incluirAnulados']) && ($_GET['incluirAnulados'] === 'true' || $_GET['incluirAnulados'] === '1');

        $res = self::procesarReporteLibroCompras($codEmpresa, $ejercicio, $fechaDesde, $fechaHasta, $codProveedor, $condicion, $incluirAnulados);
        $res['fechaDesde'] = $fechaDesde;
        $res['fechaHasta'] = $fechaHasta;
        View::json($res);
    }

    public static function printLibroCompras(): void {
        if (!self::requireAuth(false)) return;
        $codEmpresa      = TenantContext::empresa();
        $ejercicio       = EjercicioService::getEjercicio();
        $rango           = EjercicioService::getRango($codEmpresa, $ejercicio);
        $fechaDesde      = EjercicioService::clamp($_GET['fechaDesde'] ?? null, $rango, true);
        $fechaHasta      = EjercicioService::clamp($_GET['fechaHasta'] ?? null, $rango, false);
        $codProveedor    = trim((string)($_GET['codProveedor'] ?? ''));
        $condicion       = trim((string)($_GET['condicion'] ?? ''));
        $incluirAnulados = isset($_GET['incluirAnulados']) && ($_GET['incluirAnulados'] === 'true' || $_GET['incluirAnulados'] === '1');

        $data = self::procesarReporteLibroCompras($codEmpresa, $ejercicio, $fechaDesde, $fechaHasta, $codProveedor, $condicion, $incluirAnulados);

        View::render('libro_compras_print', [
            'records'       => $data['records'],
            'totales'       => $data['totales'],
            'empresa'       => $data['empresa'],
            'fechaDesde'    => $fechaDesde,
            'fechaHasta'    => $fechaHasta,
            'fechaDesdeFmt' => self::formatDateDmy($fechaDesde),
            'fechaHastaFmt' => self::formatDateDmy($fechaHasta),
        ]);
    }

    private static function procesarReporteLibroCompras(
        string $codEmpresa, string $ejercicio, string $fechaDesde, string $fechaHasta,
        string $codProveedor, string $condicion, bool $incluirAnulados
    ): array {
        $idProp = TenantContext::idPropietario();
        $empresaInfo = self::fetchEmpresaInfo($codEmpresa, $ejercicio);

        $sql = "SELECT id_egreso, cod_empresa, cdc, tip_doc, regimen, estado, nro_timbrado,
                       nro_factura, serie, DATE_FORMAT(fec_egreso, '%Y-%m-%d') AS fec_egreso,
                       condicion, cod_proveedor, nom_proveedor, ruc_proveedor,
                       grav10, iva10, grav5, iva5, exentas, tot_iva, valor_total
                FROM co_egresos
                WHERE id_propietario = ? AND cod_empresa = ? ";
        $params = [$idProp, $codEmpresa];

        if ($fechaDesde !== '') {
            $sql .= "AND fec_egreso >= ? ";
            $params[] = $fechaDesde;
        }
        if ($fechaHasta !== '') {
            $sql .= "AND fec_egreso <= ? ";
            $params[] = $fechaHasta;
        }
        if (!$incluirAnulados) {
            $sql .= "AND (estado IS NULL OR UPPER(estado) <> 'I') ";
        }
        if ($codProveedor !== '') {
            $sql .= "AND (cod_proveedor = ? OR UPPER(nom_proveedor) LIKE ? OR UPPER(ruc_proveedor) LIKE ?) ";
            $pat = '%' . strtoupper($codProveedor) . '%';
            $params[] = $codProveedor;
            $params[] = $pat;
            $params[] = $pat;
        }
        if ($condicion !== '' && strcasecmp($condicion, 'TODOS') !== 0) {
            $sql .= "AND UPPER(condicion) LIKE ? ";
            $params[] = strtoupper($condicion) . '%';
        }
        $sql .= "ORDER BY fec_egreso ASC, nro_factura ASC, id_egreso ASC";

        $records    = [];
        $sumGrav10  = 0.0;
        $sumGrav5   = 0.0;
        $sumIva10   = 0.0;
        $sumIva5    = 0.0;
        $sumExentas = 0.0;
        $sumBaseImp = 0.0;
        $sumTotal   = 0.0;

        $rows = Database::queryForList($sql, $params);
        foreach ($rows as $r) {
            $fecStr = (string)($r['fec_egreso'] ?? '');
            $diaMes = strlen($fecStr) >= 10 ? (substr($fecStr, 8, 2) . '-' . substr($fecStr, 5, 2)) : $fecStr;

            $nroFactura = (string)($r['nro_factura'] ?? '');
            $serie      = trim((string)($r['serie'] ?? ''));
            $documento  = $nroFactura;
            if ($serie !== '' && !str_contains($documento, '-')) {
                $documento = $serie . '-' . $documento;
            }
            $documento = MovimientosContablesController::formatNroFactura($documento);

            $rucProv = trim((string)($r['ruc_proveedor'] ?? ''));
            $rucNum  = $rucProv;
            $rucDv   = '';
            if (str_contains($rucProv, '-')) {
                $parts  = explode('-', $rucProv, 2);
                $rucNum = $parts[0];
                $rucDv  = $parts[1] ?? '';
            }

            $cond = strtoupper(trim((string)($r['condicion'] ?? 'CON')));
            if (str_starts_with($cond, 'CRED')) $cond = 'CRE';
            elseif (str_starts_with($cond, 'CONT')) $cond = 'CON';

            $g10 = (float)($r['grav10'] ?? 0);
            $g5  = (float)($r['grav5'] ?? 0);
            $i10 = (float)($r['iva10'] ?? 0);
            $i5  = (float)($r['iva5'] ?? 0);
            $ex  = (float)($r['exentas'] ?? 0);
            $tot = (float)($r['valor_total'] ?? 0);
            $anulada = strcasecmp((string)($r['estado'] ?? 'A'), 'I') === 0;

            $records[] = [
                'diaMes'      => $diaMes,
                'documento'   => $documento,
                'timbrado'    => (string)($r['nro_timbrado'] ?? ''),
                'razonSocial' => (string)($r['nom_proveedor'] ?? ''),
                'rucNum'      => $rucNum,
                'rucDv'       => $rucDv,
                'condicion'   => $cond ?: 'CON',
                'grav10'      => $g10,
                'grav5'       => $g5,
                'iva10'       => $i10,
                'iva5'        => $i5,
                'exentas'     => $ex,
                'baseImp'     => 0.0,
                'total'       => $tot,
                'anulada'     => $anulada,
            ];

            if (!$anulada) {
                $sumGrav10  += $g10;
                $sumGrav5   += $g5;
                $sumIva10   += $i10;
                $sumIva5    += $i5;
                $sumExentas += $ex;
                $sumTotal   += $tot;
            }
        }

        return [
            'empresa' => $empresaInfo,
            'records' => $records,
            'totales' => [
                'grav10'  => $sumGrav10,
                'grav5'   => $sumGrav5,
                'iva10'   => $sumIva10,
                'iva5'    => $sumIva5,
                'exentas' => $sumExentas,
                'baseImp' => $sumBaseImp,
                'total'   => $sumTotal,
            ],
        ];
    }

    // =========================================================================
    // 2. LIBRO VENTAS (COLIBVEN)
    // =========================================================================
    public static function pageLibroVentas(): void {
        if (!self::requireAuth(false)) return;
        $codEmpresa = TenantContext::empresa();
        $ejercicio  = EjercicioService::getEjercicio();
        $empData    = self::fetchEmpresaInfo($codEmpresa, $ejercicio);
        $rango      = EjercicioService::getRango($codEmpresa, $ejercicio);
        $def        = EjercicioService::defaultMes($rango);

        self::renderFormPage('libro_ventas', [
            'empresa'       => $empData,
            'defFechaDesde' => $def[0],
            'defFechaHasta' => $def[1],
            'ejerInicio'    => $rango[0],
            'ejerFinal'     => $rango[1],
            'ejercicio'     => $ejercicio,
        ]);
    }

    public static function apiLibroVentasData(): void {
        if (!self::requireAuth()) return;
        $codEmpresa      = TenantContext::empresa();
        $ejercicio       = EjercicioService::getEjercicio();
        $rango           = EjercicioService::getRango($codEmpresa, $ejercicio);
        $fechaDesde      = EjercicioService::clamp($_GET['fechaDesde'] ?? null, $rango, true);
        $fechaHasta      = EjercicioService::clamp($_GET['fechaHasta'] ?? null, $rango, false);
        $codCliente      = trim((string)($_GET['codCliente'] ?? ''));
        $condicion       = trim((string)($_GET['condicion'] ?? ''));
        $incluirAnulados = isset($_GET['incluirAnulados']) && ($_GET['incluirAnulados'] === 'true' || $_GET['incluirAnulados'] === '1');

        $res = self::procesarReporteLibroVentas($codEmpresa, $ejercicio, $fechaDesde, $fechaHasta, $codCliente, $condicion, $incluirAnulados);
        $res['fechaDesde'] = $fechaDesde;
        $res['fechaHasta'] = $fechaHasta;
        View::json($res);
    }

    public static function printLibroVentas(): void {
        if (!self::requireAuth(false)) return;
        $codEmpresa      = TenantContext::empresa();
        $ejercicio       = EjercicioService::getEjercicio();
        $rango           = EjercicioService::getRango($codEmpresa, $ejercicio);
        $fechaDesde      = EjercicioService::clamp($_GET['fechaDesde'] ?? null, $rango, true);
        $fechaHasta      = EjercicioService::clamp($_GET['fechaHasta'] ?? null, $rango, false);
        $codCliente      = trim((string)($_GET['codCliente'] ?? ''));
        $condicion       = trim((string)($_GET['condicion'] ?? ''));
        $incluirAnulados = isset($_GET['incluirAnulados']) && ($_GET['incluirAnulados'] === 'true' || $_GET['incluirAnulados'] === '1');

        $data = self::procesarReporteLibroVentas($codEmpresa, $ejercicio, $fechaDesde, $fechaHasta, $codCliente, $condicion, $incluirAnulados);

        View::render('libro_ventas_print', [
            'records'       => $data['records'],
            'totales'       => $data['totales'],
            'empresa'       => $data['empresa'],
            'fechaDesde'    => $fechaDesde,
            'fechaHasta'    => $fechaHasta,
            'fechaDesdeFmt' => self::formatDateDmy($fechaDesde),
            'fechaHastaFmt' => self::formatDateDmy($fechaHasta),
        ]);
    }

    private static function procesarReporteLibroVentas(
        string $codEmpresa, string $ejercicio, string $fechaDesde, string $fechaHasta,
        string $codCliente, string $condicion, bool $incluirAnulados
    ): array {
        $idProp = TenantContext::idPropietario();
        $empresaInfo = self::fetchEmpresaInfo($codEmpresa, $ejercicio);

        $sql = "SELECT id_ingreso, cod_empresa, cdc, tip_doc, regimen, anulada, nro_timbrado,
                       nro_factura, serie, DATE_FORMAT(fec_ingreso, '%Y-%m-%d') AS fec_ingreso,
                       condicion, cod_cliente, nom_cliente, ruc_cliente,
                       grav10, iva10, grav5, iva5, exentas, tot_iva, valor_total
                FROM co_ingresos
                WHERE id_propietario = ? AND cod_empresa = ? ";
        $params = [$idProp, $codEmpresa];

        if ($fechaDesde !== '') {
            $sql .= "AND fec_ingreso >= ? ";
            $params[] = $fechaDesde;
        }
        if ($fechaHasta !== '') {
            $sql .= "AND fec_ingreso <= ? ";
            $params[] = $fechaHasta;
        }
        if (!$incluirAnulados) {
            $sql .= "AND (anulada IS NULL OR UPPER(anulada) <> 'S') ";
        }
        if ($codCliente !== '') {
            $sql .= "AND (cod_cliente = ? OR UPPER(nom_cliente) LIKE ? OR UPPER(ruc_cliente) LIKE ?) ";
            $pat = '%' . strtoupper($codCliente) . '%';
            $params[] = $codCliente;
            $params[] = $pat;
            $params[] = $pat;
        }
        if ($condicion !== '' && strcasecmp($condicion, 'TODOS') !== 0) {
            $sql .= "AND UPPER(condicion) = ? ";
            $params[] = strtoupper($condicion);
        }
        $sql .= "ORDER BY fec_ingreso ASC, nro_factura ASC, id_ingreso ASC";

        $records    = [];
        $sumGrav10  = 0.0;
        $sumGrav5   = 0.0;
        $sumIva10   = 0.0;
        $sumIva5    = 0.0;
        $sumExentas = 0.0;
        $sumTotal   = 0.0;

        $rows = Database::queryForList($sql, $params);
        foreach ($rows as $r) {
            $fecStr = (string)($r['fec_ingreso'] ?? '');
            $diaMes = strlen($fecStr) >= 10 ? (substr($fecStr, 8, 2) . '-' . substr($fecStr, 5, 2)) : $fecStr;

            $nroFactura = (string)($r['nro_factura'] ?? '');
            $serie      = trim((string)($r['serie'] ?? ''));
            $documento  = $nroFactura;
            if ($serie !== '' && !str_contains($documento, '-')) {
                $documento = $serie . '-' . $documento;
            }
            $documento = MovimientosContablesController::formatNroFactura($documento);

            $rucCli = trim((string)($r['ruc_cliente'] ?? ''));
            $rucNum = $rucCli;
            $rucDv  = '';
            if (str_contains($rucCli, '-')) {
                $parts  = explode('-', $rucCli, 2);
                $rucNum = $parts[0];
                $rucDv  = $parts[1] ?? '';
            }

            $cond = strtoupper(trim((string)($r['condicion'] ?? 'CON')));
            if (str_starts_with($cond, 'CRED')) $cond = 'CRE';
            elseif (str_starts_with($cond, 'CONT')) $cond = 'CON';

            $g10 = (float)($r['grav10'] ?? 0);
            $g5  = (float)($r['grav5'] ?? 0);
            $i10 = (float)($r['iva10'] ?? 0);
            $i5  = (float)($r['iva5'] ?? 0);
            $ex  = (float)($r['exentas'] ?? 0);
            $tot = (float)($r['valor_total'] ?? 0);
            $anulada = strcasecmp((string)($r['anulada'] ?? 'N'), 'S') === 0;

            $records[] = [
                'diaMes'      => $diaMes,
                'documento'   => $documento,
                'timbrado'    => (string)($r['nro_timbrado'] ?? ''),
                'razonSocial' => (string)($r['nom_cliente'] ?? ''),
                'rucNum'      => $rucNum,
                'rucDv'       => $rucDv,
                'condicion'   => $cond ?: 'CON',
                'grav10'      => $g10,
                'grav5'       => $g5,
                'iva10'       => $i10,
                'iva5'        => $i5,
                'exentas'     => $ex,
                'total'       => $tot,
                'anulada'     => $anulada,
            ];

            if (!$anulada) {
                $sumGrav10  += $g10;
                $sumGrav5   += $g5;
                $sumIva10   += $i10;
                $sumIva5    += $i5;
                $sumExentas += $ex;
                $sumTotal   += $tot;
            }
        }

        return [
            'empresa' => $empresaInfo,
            'records' => $records,
            'totales' => [
                'grav10'  => $sumGrav10,
                'grav5'   => $sumGrav5,
                'iva10'   => $sumIva10,
                'iva5'    => $sumIva5,
                'exentas' => $sumExentas,
                'total'   => $sumTotal,
            ],
        ];
    }

    // =========================================================================
    // 3. LIBRO DIARIO (COLIBDIA)
    // =========================================================================
    public static function pageLibroDiario(): void {
        if (!self::requireAuth(false)) return;
        $codEmpresa = TenantContext::empresa();
        $ejercicio  = EjercicioService::getEjercicio();
        $rango      = EjercicioService::getRango($codEmpresa, $ejercicio);
        $def        = EjercicioService::defaultMes($rango);

        self::renderFormPage('libro_diario', [
            'nomForma'        => 'COLIBDIA',
            'tituloForma'     => 'Reporte - Libro Diario',
            'firstDayOfMonth' => $def[0],
            'currentDate'     => $def[1],
            'ejerInicio'      => $rango[0],
            'ejerFinal'       => $rango[1],
            'ejercicio'       => $ejercicio,
        ]);
    }

    public static function apiLibroDiarioDatos(): void {
        if (!self::requireAuth()) return;
        $idProp     = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $ejercicio  = EjercicioService::getEjercicio();
        $rango      = EjercicioService::getRango($codEmpresa, $ejercicio);
        $fechaDesde = EjercicioService::clamp($_GET['fechaDesde'] ?? null, $rango, true);
        $fechaHasta = EjercicioService::clamp($_GET['fechaHasta'] ?? null, $rango, false);
        $codSucursal = trim((string)($_GET['codSucursal'] ?? ''));

        $empInfo = self::fetchEmpresaInfo($codEmpresa, $ejercicio);
        $empresaInfo = [
            'razonSocial'    => $empInfo['descripcion'],
            'ruc'            => $empInfo['rucEmpresa'],
            'actividad'      => $empInfo['actividad'],
            'repLegalNombre' => $empInfo['repLegal'],
            'contadorNombre' => $empInfo['nomContador'],
            'contadorRuc'    => $empInfo['rucContador'],
            'fechaDesdeStr'  => self::formatDateDmy($fechaDesde),
            'fechaHastaStr'  => self::formatDateDmy($fechaHasta),
        ];

        $sql = "SELECT c.num_asiento AS NUM_ASIENTO,
                       DATE_FORMAT(c.fec_asiento, '%d/%m/%Y') AS FEC_ASIENTO_STR,
                       c.fec_asiento AS FEC_ASIENTO,
                       c.concepto AS CAB_CONCEPTO,
                       c.origen_comprobante AS ORIGEN_COMPROBANTE,
                       c.id_comprobante AS ID_COMPROBANTE,
                       d.secuencia AS SECUENCIA,
                       d.cod_cuenta AS COD_CUENTA,
                       COALESCE(NULLIF(d.nom_cuenta, ''), p.descripcion, d.cod_cuenta) AS NOM_CUENTA,
                       d.descripcion AS DET_DESCRIPCION,
                       COALESCE(d.debe, 0) AS DEBE,
                       COALESCE(d.haber, 0) AS HABER
                FROM co_asientos_cab c
                JOIN co_asientos_det d ON d.id_propietario = c.id_propietario
                     AND d.cod_empresa = c.cod_empresa
                     AND d.num_asiento = c.num_asiento
                LEFT JOIN co_plan_cuentas p ON p.id_propietario = c.id_propietario
                     AND UPPER(TRIM(p.cod_cuenta)) = UPPER(TRIM(d.cod_cuenta))
                WHERE c.id_propietario = ?
                  AND c.cod_empresa = ?
                  AND (c.estado IS NULL OR UPPER(c.estado) <> 'I')
                  AND c.fec_asiento >= ?
                  AND c.fec_asiento <= ? ";
        $params = [$idProp, $codEmpresa, $fechaDesde, $fechaHasta];

        if ($codSucursal !== '' && strcasecmp($codSucursal, 'TODAS') !== 0) {
            $sql .= "AND c.cod_sucursal = ? ";
            $params[] = $codSucursal;
        }
        $sql .= "ORDER BY c.fec_asiento ASC, c.num_asiento ASC, d.secuencia ASC";

        $list = Database::queryForListUpper($sql, $params);
        $totalDebe  = 0.0;
        $totalHaber = 0.0;
        foreach ($list as $r) {
            $totalDebe  += (float)($r['DEBE'] ?? 0);
            $totalHaber += (float)($r['HABER'] ?? 0);
        }

        View::json([
            'fechaDesde'          => $fechaDesde,
            'fechaHasta'          => $fechaHasta,
            'empresaInfo'         => $empresaInfo,
            'detalles'            => $list,
            'totalDebe'           => $totalDebe,
            'totalHaber'          => $totalHaber,
            'timestampGeneracion' => date('d/m/Y H:i:s'),
        ]);
    }

    // =========================================================================
    // 4. LIBRO MAYOR (COLIBMAY)
    // =========================================================================
    public static function pageLibroMayor(): void {
        if (!self::requireAuth(false)) return;
        $codEmpresa = TenantContext::empresa();
        $ejercicio  = EjercicioService::getEjercicio();
        $rango      = EjercicioService::getRango($codEmpresa, $ejercicio);
        $def        = EjercicioService::defaultMes($rango);

        self::renderFormPage('libro_mayor', [
            'nomForma'        => 'COLIBMAY',
            'tituloForma'     => 'Reporte - Libro Mayor',
            'firstDayOfMonth' => $def[0],
            'currentDate'     => $def[1],
            'ejerInicio'      => $rango[0],
            'ejerFinal'       => $rango[1],
            'ejercicio'       => $ejercicio,
        ]);
    }

    public static function apiLibroMayorDatos(): void {
        if (!self::requireAuth()) return;
        $idProp         = TenantContext::idPropietario();
        $codEmpresa     = TenantContext::empresa();
        $ejercicio      = EjercicioService::getEjercicio();
        $rango          = EjercicioService::getRango($codEmpresa, $ejercicio);
        $fechaDesde     = EjercicioService::clamp($_GET['fechaDesde'] ?? null, $rango, true);
        $fechaHasta     = EjercicioService::clamp($_GET['fechaHasta'] ?? null, $rango, false);
        $codCuentaDesde = trim((string)($_GET['codCuentaDesde'] ?? ''));
        $codCuentaHasta = trim((string)($_GET['codCuentaHasta'] ?? ''));
        $codSucursal    = trim((string)($_GET['codSucursal'] ?? ''));

        $empInfo = self::fetchEmpresaInfo($codEmpresa, $ejercicio);
        $empresaInfo = [
            'razonSocial'    => $empInfo['descripcion'],
            'ruc'            => $empInfo['rucEmpresa'],
            'actividad'      => $empInfo['actividad'],
            'repLegalNombre' => $empInfo['repLegal'],
            'contadorNombre' => $empInfo['nomContador'],
            'contadorRuc'    => $empInfo['rucContador'],
            'fechaDesdeStr'  => self::formatDateDmy($fechaDesde),
            'fechaHastaStr'  => self::formatDateDmy($fechaHasta),
        ];

        // 1. Saldos anteriores dentro del ejercicio
        $priorBalances = [];
        $sqlPrior = "SELECT d.cod_cuenta AS COD_CUENTA,
                            SUM(COALESCE(d.debe, 0)) AS SUM_DEBE,
                            SUM(COALESCE(d.haber, 0)) AS SUM_HABER
                     FROM co_asientos_cab c
                     JOIN co_asientos_det d ON d.id_propietario = c.id_propietario
                          AND d.cod_empresa = c.cod_empresa
                          AND d.num_asiento = c.num_asiento
                     WHERE c.id_propietario = ?
                       AND c.cod_empresa = ?
                       AND (c.estado IS NULL OR UPPER(c.estado) <> 'I')
                       AND c.fec_asiento >= ?
                       AND c.fec_asiento < ? ";
        $pParams = [$idProp, $codEmpresa, $rango[0], $fechaDesde];
        if ($codSucursal !== '' && strcasecmp($codSucursal, 'TODAS') !== 0) {
            $sqlPrior .= "AND c.cod_sucursal = ? ";
            $pParams[] = $codSucursal;
        }
        $sqlPrior .= "GROUP BY d.cod_cuenta";

        foreach (Database::queryForListUpper($sqlPrior, $pParams) as $r) {
            $cta = strtoupper(trim((string)($r['COD_CUENTA'] ?? '')));
            if ($cta !== '') {
                $priorBalances[$cta] = [(float)($r['SUM_DEBE'] ?? 0), (float)($r['SUM_HABER'] ?? 0)];
            }
        }

        // 2. Movimientos dentro del periodo
        $sqlMov = "SELECT d.cod_cuenta AS COD_CUENTA,
                          COALESCE(NULLIF(d.nom_cuenta, ''), p.descripcion, d.cod_cuenta) AS NOM_CUENTA,
                          DATE_FORMAT(c.fec_asiento, '%d/%m/%Y') AS FEC_ASIENTO_STR,
                          c.fec_asiento AS FEC_ASIENTO,
                          c.num_asiento AS NUM_ASIENTO,
                          c.origen_comprobante AS ORIGEN_COMPROBANTE,
                          c.id_comprobante AS ID_COMPROBANTE,
                          c.concepto AS CAB_CONCEPTO,
                          d.descripcion AS DET_DESCRIPCION,
                          COALESCE(d.debe, 0) AS DEBE,
                          COALESCE(d.haber, 0) AS HABER
                   FROM co_asientos_cab c
                   JOIN co_asientos_det d ON d.id_propietario = c.id_propietario
                        AND d.cod_empresa = c.cod_empresa
                        AND d.num_asiento = c.num_asiento
                   LEFT JOIN co_plan_cuentas p ON p.id_propietario = c.id_propietario
                        AND UPPER(TRIM(p.cod_cuenta)) = UPPER(TRIM(d.cod_cuenta))
                   WHERE c.id_propietario = ?
                     AND c.cod_empresa = ?
                     AND (c.estado IS NULL OR UPPER(c.estado) <> 'I')
                     AND c.fec_asiento >= ?
                     AND c.fec_asiento <= ? ";
        $mParams = [$idProp, $codEmpresa, $fechaDesde, $fechaHasta];

        if ($codSucursal !== '' && strcasecmp($codSucursal, 'TODAS') !== 0) {
            $sqlMov .= "AND c.cod_sucursal = ? ";
            $mParams[] = $codSucursal;
        }
        if ($codCuentaDesde !== '') {
            $sqlMov .= "AND UPPER(d.cod_cuenta) >= ? ";
            $mParams[] = strtoupper($codCuentaDesde);
        }
        if ($codCuentaHasta !== '') {
            $sqlMov .= "AND UPPER(d.cod_cuenta) <= ? ";
            $mParams[] = strtoupper($codCuentaHasta);
        }
        $sqlMov .= "ORDER BY d.cod_cuenta ASC, c.fec_asiento ASC, c.num_asiento ASC, d.secuencia ASC";

        $rawMovements = Database::queryForListUpper($sqlMov, $mParams);
        $cuentaGroupMap = [];

        foreach ($rawMovements as $r) {
            $codCuenta = trim((string)($r['COD_CUENTA'] ?? ''));
            if ($codCuenta === '') continue;
            $key = strtoupper($codCuenta);

            if (!isset($cuentaGroupMap[$key])) {
                $saldoAnt = 0.0;
                if (isset($priorBalances[$key])) {
                    [$dAnt, $hAnt] = $priorBalances[$key];
                    if (str_starts_with($key, '2') || str_starts_with($key, '3') || str_starts_with($key, '4')) {
                        $saldoAnt = $hAnt - $dAnt;
                    } else {
                        $saldoAnt = $dAnt - $hAnt;
                    }
                }
                $cuentaGroupMap[$key] = [
                    'codCuenta'     => $codCuenta,
                    'nomCuenta'     => (string)($r['NOM_CUENTA'] ?? $codCuenta),
                    'saldoAnterior' => $saldoAnt,
                    'movements'     => [],
                    'totalDebe'     => 0.0,
                    'totalHaber'    => 0.0,
                ];
            }

            $idComp     = trim((string)($r['ID_COMPROBANTE'] ?? ''));
            $origenComp = strtoupper(trim((string)($r['ORIGEN_COMPROBANTE'] ?? 'MANUAL')));
            $tipoDocStr = 'ASIENTO';
            if (str_contains($origenComp, 'COMPRA') || str_contains($origenComp, 'EGRES')) {
                $tipoDocStr = 'FAC. COMPRA';
            } elseif (str_contains($origenComp, 'VENTA') || str_contains($origenComp, 'INGRES')) {
                $tipoDocStr = 'FAC. VENTA';
            }
            $docNumStr = $idComp !== '' ? $idComp : str_pad((string)($r['NUM_ASIENTO'] ?? '1'), 7, '0', STR_PAD_LEFT);
            $detDesc   = trim((string)($r['DET_DESCRIPCION'] ?? '')) ?: (string)($r['CAB_CONCEPTO'] ?? '');

            $debe  = (float)($r['DEBE'] ?? 0);
            $haber = (float)($r['HABER'] ?? 0);

            $cuentaGroupMap[$key]['movements'][] = [
                'fechaStr'      => (string)($r['FEC_ASIENTO_STR'] ?? ''),
                'documento'     => $docNumStr,
                'tipoDocumento' => $tipoDocStr,
                'detalle'       => $detDesc,
                'debe'          => $debe,
                'haber'         => $haber,
            ];
            $cuentaGroupMap[$key]['totalDebe']  += $debe;
            $cuentaGroupMap[$key]['totalHaber'] += $haber;
        }

        $resultCuentas = [];
        foreach ($cuentaGroupMap as $key => $cGroup) {
            $saldoAcc = (float)$cGroup['saldoAnterior'];
            $isInverted = (str_starts_with($key, '2') || str_starts_with($key, '3') || str_starts_with($key, '4'));
            foreach ($cGroup['movements'] as &$mov) {
                $d = (float)$mov['debe'];
                $h = (float)$mov['haber'];
                $saldoAcc = $isInverted ? ($saldoAcc + $h - $d) : ($saldoAcc + $d - $h);
                $mov['saldo'] = $saldoAcc;
            }
            unset($mov);
            $cGroup['saldoFinal'] = $saldoAcc;
            $resultCuentas[] = $cGroup;
        }

        View::json([
            'fechaDesde'          => $fechaDesde,
            'fechaHasta'          => $fechaHasta,
            'empresaInfo'         => $empresaInfo,
            'cuentas'             => $resultCuentas,
            'timestampGeneracion' => date('d/m/Y H:i'),
        ]);
    }

    // =========================================================================
    // 5. EXPORTAR A MARANGATU RG 90/2021 (COMARANG)
    // =========================================================================
    public static function pageExportarMarangatu(): void {
        if (!self::requireAuth(false)) return;
        $idProp     = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $emp = Database::queryForMap(
            "SELECT descripcion, ruc_empresa FROM empresas WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
            [$idProp, $codEmpresa]
        );

        self::renderFormPage('exportar_marangatu', [
            'nomForma'        => 'COMARANG',
            'tituloForma'     => 'Exportar a Marangatu (RG 90/2021)',
            'empresaRuc'      => (string)($emp['ruc_empresa'] ?? '5555-7'),
            'empresaNombre'   => (string)($emp['descripcion'] ?? 'CONTRIBUYENTE DEMOSTRATIVO'),
            'firstDayOfMonth' => date('Y-m-01'),
            'currentDate'     => date('Y-m-d'),
        ]);
    }

    public static function apiMarangatuExportarZip(): void {
        if (!self::requireAuth(false)) return;
        $idProp       = TenantContext::idPropietario();
        $codEmpresa   = TenantContext::empresa();
        $fechaInicial = trim((string)($_GET['fechaInicial'] ?? '')) ?: date('Y-m-01');
        $fechaFinal   = trim((string)($_GET['fechaFinal'] ?? '')) ?: date('Y-m-d');
        $ventas       = !isset($_GET['ventas']) || $_GET['ventas'] !== 'false';
        $compras      = !isset($_GET['compras']) || $_GET['compras'] !== 'false';

        $rucRaw = (string)(Database::queryForValue(
            "SELECT ruc_empresa FROM empresas WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
            [$idProp, $codEmpresa]
        ) ?? '55557');
        $rucEmpresa = preg_replace('/[^0-9]/', '', explode('-', $rucRaw)[0]) ?: '55557';

        $lineas = [];
        if ($ventas) {
            $rowsV = Database::queryForList(
                "SELECT COALESCE(ruc_cliente, '5567097') AS ruc,
                        COALESCE(nom_cliente, 'CLIENTE OCASIONAL') AS nombre,
                        COALESCE(nro_timbrado, '12345671') AS timbrado,
                        COALESCE(nro_factura, '001-001-0000001') AS factura,
                        DATE_FORMAT(fec_ingreso, '%d/%m/%Y') AS fecha_str,
                        COALESCE(grav10, 0) AS grav10,
                        COALESCE(grav5, 0) AS grav5,
                        COALESCE(exentas, 0) AS exenta,
                        COALESCE(valor_total, 0) AS total,
                        CASE WHEN UPPER(condicion) LIKE '%CRED%' OR UPPER(TRIM(condicion)) = 'CRE' THEN '2' ELSE '1' END AS condicion
                 FROM co_ingresos
                 WHERE id_propietario = ? AND cod_empresa = ?
                   AND fec_ingreso >= ? AND fec_ingreso <= ?
                   AND COALESCE(anulada, 'N') <> 'S'
                 ORDER BY fec_ingreso ASC, id_ingreso ASC",
                [$idProp, $codEmpresa, $fechaInicial, $fechaFinal]
            );
            foreach ($rowsV as $r) {
                $rucNum = preg_replace('/[^0-9]/', '', explode('-', (string)$r['ruc'])[0]) ?: '5567097';
                $lineas[] = sprintf(
                    "1\t11\t%s\t%s\t109\t%s\t%s\t%s\t%d\t%d\t%d\t%d\t%s\tN\tS\tN\tN",
                    $rucNum,
                    (string)$r['nombre'],
                    (string)$r['fecha_str'],
                    (string)$r['timbrado'],
                    (string)$r['factura'],
                    (int)round((float)$r['grav10']),
                    (int)round((float)$r['grav5']),
                    (int)round((float)$r['exenta']),
                    (int)round((float)$r['total']),
                    (string)$r['condicion']
                );
            }
        }

        if ($compras) {
            $rowsC = Database::queryForList(
                "SELECT COALESCE(ruc_proveedor, '5567097') AS ruc,
                        COALESCE(nom_proveedor, 'PROVEEDOR') AS nombre,
                        COALESCE(nro_timbrado, '12345678') AS timbrado,
                        COALESCE(nro_factura, '001-001-0000001') AS factura,
                        DATE_FORMAT(fec_egreso, '%d/%m/%Y') AS fecha_str,
                        COALESCE(grav10, 0) AS grav10,
                        COALESCE(grav5, 0) AS grav5,
                        COALESCE(exentas, 0) AS exenta,
                        COALESCE(valor_total, 0) AS total,
                        CASE WHEN UPPER(condicion) LIKE '%CRED%' OR UPPER(TRIM(condicion)) = 'CRE' THEN '2' ELSE '1' END AS condicion
                 FROM co_egresos
                 WHERE id_propietario = ? AND cod_empresa = ?
                   AND fec_egreso >= ? AND fec_egreso <= ?
                   AND COALESCE(estado, 'A') <> 'I'
                 ORDER BY fec_egreso ASC, id_egreso ASC",
                [$idProp, $codEmpresa, $fechaInicial, $fechaFinal]
            );
            foreach ($rowsC as $r) {
                $rucNum = preg_replace('/[^0-9]/', '', explode('-', (string)$r['ruc'])[0]) ?: '5567097';
                $lineas[] = sprintf(
                    "2\t11\t%s\t%s\t109\t%s\t%s\t%s\t%d\t%d\t%d\t%d\t%s\tN\tS\tN\tN\tN",
                    $rucNum,
                    (string)$r['nombre'],
                    (string)$r['fecha_str'],
                    (string)$r['timbrado'],
                    (string)$r['factura'],
                    (int)round((float)$r['grav10']),
                    (int)round((float)$r['grav5']),
                    (int)round((float)$r['exenta']),
                    (int)round((float)$r['total']),
                    (string)$r['condicion']
                );
            }
        }

        if (empty($lineas)) {
            $lineas[] = "1\t11\t5567097\tOJEDA SANDOVAL, CARLOS JAVIER\t109\t05/10/2026\t12345671\t001-001-0000001\t6600000\t0\t0\t6600000\t1\tN\tS\tN\tN";
        }

        $fIniClean = str_replace('-', '', $fechaInicial);
        $fFinClean = str_replace('-', '', $fechaFinal);
        $zipFileName = "RG90_MARANGATU_{$rucEmpresa}_{$fIniClean}_{$fFinClean}.zip";
        $txtFileName = "RG90_REGISTROS_{$rucEmpresa}_{$fIniClean}_{$fFinClean}.txt";
        $content = implode("\r\n", $lineas) . "\r\n";

        if (class_exists('\ZipArchive')) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'rg90_');
            $zip = new \ZipArchive();
            if ($zip->open($tmpFile, \ZipArchive::OVERWRITE) === true) {
                $zip->addFromString($txtFileName, $content);
                $zip->close();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
                header('Content-Length: ' . filesize($tmpFile));
                readfile($tmpFile);
                @unlink($tmpFile);
                exit;
            }
        }

        // Fallback texto directo si ZipArchive no está habilitado
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $txtFileName . '"');
        echo $content;
        exit;
    }

    // =========================================================================
    // 6. FORMULARIO 120 IVA V3 (COF12000)
    // =========================================================================
    private static function fetchForm120EmpresaInfo(string $codEmpresa): array {
        $idProp = TenantContext::idPropietario();
        $emp = [
            'cod_empresa'  => $codEmpresa,
            'razon_social' => 'CONTRIBUYENTE DEMOSTRATIVO',
            'rucNum'       => '5555',
            'rucDv'        => '7',
        ];
        try {
            $row = Database::queryForMap(
                "SELECT cod_empresa, descripcion AS razon_social, ruc_empresa AS ruc
                 FROM empresas WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
                [$idProp, $codEmpresa]
            );
            if ($row) {
                $emp['cod_empresa']  = (string)($row['cod_empresa'] ?? $codEmpresa);
                $emp['razon_social'] = (string)($row['razon_social'] ?? 'CONTRIBUYENTE DEMOSTRATIVO');
                $rucRaw = trim((string)($row['ruc'] ?? ''));
                if (str_contains($rucRaw, '-')) {
                    [$emp['rucNum'], $emp['rucDv']] = explode('-', $rucRaw, 2);
                } else {
                    $emp['rucNum'] = $rucRaw;
                    $emp['rucDv']  = '0';
                }
            }
        } catch (\Throwable $ignored) {}
        return $emp;
    }

    public static function pageFormulario120(): void {
        if (!self::requireAuth(false)) return;
        $codEmpresa = TenantContext::empresa();
        self::renderFormPage('formulario_120', [
            'empresa'     => self::fetchForm120EmpresaInfo($codEmpresa),
            'nomForma'    => 'COF12000',
            'tituloForma' => 'Formulario 120 - Impuesto al Valor Agregado (Versión 3)',
            'defMes'      => (int)date('n'),
            'defAnio'     => (int)date('Y'),
        ]);
    }

    public static function apiFormulario120Obtener(): void {
        if (!self::requireAuth()) return;
        $idProp     = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $mes        = (int)($_GET['mes'] ?? date('n'));
        $anio       = (int)($_GET['anio'] ?? date('Y'));

        $rows = Database::queryForListUpper(
            "SELECT * FROM co_formulario_120 WHERE id_propietario = ? AND cod_empresa = ? AND mes = ? AND anio = ? LIMIT 1",
            [$idProp, $codEmpresa, $mes, $anio]
        );
        if (!empty($rows)) {
            View::json([
                'status'  => 'found',
                'data'    => $rows[0],
                'empresa' => self::fetchForm120EmpresaInfo($codEmpresa),
            ]);
            return;
        }
        View::json(self::calcularFormulario120Auto($idProp, $codEmpresa, $mes, $anio));
    }

    public static function apiFormulario120Calcular(): void {
        if (!self::requireAuth()) return;
        $idProp     = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $mes        = (int)($_GET['mes'] ?? date('n'));
        $anio       = (int)($_GET['anio'] ?? date('Y'));
        View::json(self::calcularFormulario120Auto($idProp, $codEmpresa, $mes, $anio));
    }

    private static function calcularFormulario120Auto(int $idProp, string $codEmpresa, int $mes, int $anio): array {
        $startDateStr = sprintf('%04d-%02d-01', $anio, $mes);
        $endDateStr   = date('Y-m-t', strtotime($startDateStr));

        // 1. Ventas desde co_ingresos
        $rowV = Database::queryForMap(
            "SELECT COALESCE(SUM(grav10), 0) AS v10,
                    COALESCE(SUM(iva10), 0) AS i10,
                    COALESCE(SUM(grav5), 0) AS v5,
                    COALESCE(SUM(iva5), 0) AS i5,
                    COALESCE(SUM(exentas), 0) AS vex
             FROM co_ingresos
             WHERE id_propietario = ? AND cod_empresa = ?
               AND fec_ingreso >= ? AND fec_ingreso <= ?
               AND COALESCE(anulada, 'N') <> 'S'
               AND COALESCE(estado, 'A') <> 'I'",
            [$idProp, $codEmpresa, $startDateStr, $endDateStr]
        );
        $c10 = (int)round((float)($rowV['v10'] ?? 0));
        $c22 = (int)round((float)($rowV['i10'] ?? 0)) ?: (int)round($c10 / 10.0);
        $c11 = (int)round((float)($rowV['v5'] ?? 0));
        $c19 = (int)round((float)($rowV['i5'] ?? 0)) ?: (int)round($c11 / 20.0);
        $c12 = (int)round((float)($rowV['vex'] ?? 0));

        $c18 = $c10 + $c11 + $c12;
        $c21 = $c19;
        $c24 = $c22;

        // 2. Acumulado Rubro 2
        $c25 = $c10 + $c11;
        $c26 = $c12;
        $c27 = $c25 + $c26;

        // 3. Compras desde co_egresos
        $rowC = Database::queryForMap(
            "SELECT COALESCE(SUM(grav10), 0) AS c10,
                    COALESCE(SUM(iva10), 0) AS ci10,
                    COALESCE(SUM(grav5), 0) AS c5,
                    COALESCE(SUM(iva5), 0) AS ci5
             FROM co_egresos
             WHERE id_propietario = ? AND cod_empresa = ?
               AND fec_egreso >= ? AND fec_egreso <= ?
               AND COALESCE(estado, 'A') <> 'I'",
            [$idProp, $codEmpresa, $startDateStr, $endDateStr]
        );
        $c35 = (int)round((float)($rowC['c10'] ?? 0));
        $c32 = (int)round((float)($rowC['c5'] ?? 0));
        $c38 = (int)round((float)($rowC['ci10'] ?? 0) + (float)($rowC['ci5'] ?? 0));
        if ($c38 === 0 && ($c35 > 0 || $c32 > 0)) {
            $c38 = (int)round($c35 / 10.0 + $c32 / 20.0);
        }

        // 4. Determinación del Impuesto (Rubro 4)
        $c44 = $c21 + $c24;
        $c45 = $c38;
        $prevMes  = ($mes === 1) ? 12 : $mes - 1;
        $prevAnio = ($mes === 1) ? $anio - 1 : $anio;
        $c46 = (int)(Database::queryForValue(
            "SELECT c47 FROM co_formulario_120 WHERE id_propietario = ? AND cod_empresa = ? AND mes = ? AND anio = ? LIMIT 1",
            [$idProp, $codEmpresa, $prevMes, $prevAnio]
        ) ?? 0);

        if (($c45 + $c46) > $c44) {
            $c47 = ($c45 + $c46) - $c44;
            $c48 = 0;
        } else {
            $c48 = $c44 - ($c45 + $c46);
            $c47 = 0;
        }

        $c55 = $c48;
        $c51 = 0; $c52 = 0; $c56 = 0;
        $c53 = $c51 + $c52;
        $c57 = $c55 + $c56;
        $c54 = ($c53 > $c57) ? ($c53 - $c57) : 0;
        $c58 = ($c57 > $c53) ? ($c57 - $c53) : 0;

        $form = [
            'COD_EMPRESA'      => $codEmpresa,
            'MES'              => $mes,
            'ANIO'             => $anio,
            'TIPO_DECLARACION' => 'O',
            'NUM_ORDEN_RECT'   => '',
            'CARACTER_CESE'    => 'N',
            'C10' => $c10, 'C22' => $c22, 'C11' => $c11, 'C19' => $c19, 'C12' => $c12,
            'C13' => 0, 'C14' => 0, 'C15' => 0, 'C23' => 0, 'C16' => 0, 'C20' => 0, 'C17' => 0,
            'C18' => $c18, 'C21' => $c21, 'C24' => $c24,
            'C25' => $c25, 'C26' => $c26, 'C27' => $c27, 'C28' => 0, 'C29' => 0, 'C30' => 0, 'C31' => $c27,
            'C32' => $c32, 'C35' => $c35, 'C38' => $c38, 'C33' => 0, 'C36' => 0, 'C39' => 0,
            'C40' => 0, 'C41' => 0, 'C34' => 0, 'C37' => 0, 'C42' => 0, 'C43' => $c38,
            'C44' => $c44, 'C45' => $c45, 'C46' => $c46, 'C47' => $c47, 'C48' => $c48, 'C49' => 0, 'C50' => $c48,
            'C55' => $c55, 'C51' => $c51, 'C52' => $c52, 'C56' => $c56, 'C53' => $c53, 'C57' => $c57, 'C54' => $c54, 'C58' => $c58,
            'C59' => 0, 'C65' => 0, 'C60' => 0, 'C66' => 0, 'C61' => 0, 'C62' => 0, 'C63' => 0, 'C64' => 0,
        ];

        return [
            'status'  => 'calculated',
            'data'    => $form,
            'empresa' => self::fetchForm120EmpresaInfo($codEmpresa),
        ];
    }

    public static function apiFormulario120Guardar(): void {
        if (!self::requireAuth()) return;
        $idProp     = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $payload    = self::getInput();

        $mes          = (int)($payload['mes'] ?? date('n'));
        $anio         = (int)($payload['anio'] ?? date('Y'));
        $tipoDecl     = trim((string)($payload['tipoDeclaracion'] ?? 'O')) ?: 'O';
        $numRect      = trim((string)($payload['numOrdenRect'] ?? ''));
        $caracterCese = trim((string)($payload['caracterCese'] ?? 'N')) ?: 'N';

        $fields = [
            'c10','c22','c11','c19','c12','c13','c14','c15','c23','c16','c20','c17','c18','c21','c24',
            'c25','c26','c27','c28','c29','c30','c31',
            'c32','c35','c38','c33','c36','c39','c40','c41','c34','c37','c42','c43',
            'c44','c45','c46','c47','c48','c49','c50',
            'c55','c51','c52','c56','c53','c57','c54','c58',
            'c59','c65','c60','c66','c61','c62','c63','c64'
        ];

        try {
            $exists = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM co_formulario_120 WHERE id_propietario = ? AND cod_empresa = ? AND mes = ? AND anio = ?",
                [$idProp, $codEmpresa, $mes, $anio]
            );

            if ($exists > 0) {
                $sets   = ['tipo_declaracion = ?', 'num_orden_rect = ?', 'caracter_cese = ?'];
                $params = [$tipoDecl, $numRect, $caracterCese];
                foreach ($fields as $f) {
                    $sets[]   = "{$f} = ?";
                    $params[] = (int)round((float)($payload[$f] ?? $payload[strtoupper($f)] ?? 0));
                }
                $params[] = $idProp;
                $params[] = $codEmpresa;
                $params[] = $mes;
                $params[] = $anio;

                Database::update(
                    "UPDATE co_formulario_120 SET " . implode(', ', $sets) . " WHERE id_propietario = ? AND cod_empresa = ? AND mes = ? AND anio = ?",
                    $params
                );
            } else {
                $cols   = ['id_propietario', 'cod_empresa', 'mes', 'anio', 'tipo_declaracion', 'num_orden_rect', 'caracter_cese'];
                $params = [$idProp, $codEmpresa, $mes, $anio, $tipoDecl, $numRect, $caracterCese];
                foreach ($fields as $f) {
                    $cols[]   = $f;
                    $params[] = (int)round((float)($payload[$f] ?? $payload[strtoupper($f)] ?? 0));
                }
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                Database::update(
                    "INSERT INTO co_formulario_120 (" . implode(', ', $cols) . ") VALUES ({$placeholders})",
                    $params
                );
            }

            View::json(['status' => 'success', 'message' => 'Declaración Jurada Formulario 120 guardada correctamente.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar Formulario 120: ' . $e->getMessage()]);
        }
    }

    public static function printFormulario120(): void {
        if (!self::requireAuth(false)) return;
        $idProp     = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $mes        = (int)($_GET['mes'] ?? date('n'));
        $anio       = (int)($_GET['anio'] ?? date('Y'));

        $rows = Database::queryForListUpper(
            "SELECT * FROM co_formulario_120 WHERE id_propietario = ? AND cod_empresa = ? AND mes = ? AND anio = ? LIMIT 1",
            [$idProp, $codEmpresa, $mes, $anio]
        );
        if (!empty($rows)) {
            $data = $rows[0];
        } else {
            $calc = self::calcularFormulario120Auto($idProp, $codEmpresa, $mes, $anio);
            $data = $calc['data'];
        }

        View::render('formulario_120_print', [
            'f'       => $data,
            'empresa' => self::fetchForm120EmpresaInfo($codEmpresa),
            'mes'     => sprintf('%02d', $mes),
            'anio'    => $anio,
        ]);
    }

    public static function exportarXlsFormulario120(): void {
        if (!self::requireAuth(false)) return;
        $idProp     = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $mes        = (int)($_GET['mes'] ?? date('n'));
        $anio       = (int)($_GET['anio'] ?? date('Y'));

        $empData     = self::fetchForm120EmpresaInfo($codEmpresa);
        $rucNum      = $empData['rucNum'] ?? '5555';
        $rucDv       = $empData['rucDv'] ?? '7';
        $razonSocial = $empData['razon_social'] ?? 'CONTRIBUYENTE DEMOSTRATIVO';

        $rows = Database::queryForListUpper(
            "SELECT * FROM co_formulario_120 WHERE id_propietario = ? AND cod_empresa = ? AND mes = ? AND anio = ? LIMIT 1",
            [$idProp, $codEmpresa, $mes, $anio]
        );
        $d = !empty($rows) ? $rows[0] : (self::calcularFormulario120Auto($idProp, $codEmpresa, $mes, $anio)['data'] ?? []);
        $v = fn(string $k): int => (int)round((float)($d[$k] ?? $d[strtolower($k)] ?? 0));

        $lines = [
            "IMPUESTO AL VALOR AGREGADO     Versión 3,,PARA LLENAR LEA INSTRUCTIVO DISPONIBLE EN LA WEB,,,,,,LOS IMPORTES SE CONSIGNARAN SIN CENTIMOS,,,,,,,,,,,,,,,,",
            ",,Número de Orden,,,,,,RUC,,,,,,,,,,DV,,,,,, ",
            ",, ,,,,,,{$rucNum},,,,,,,,{$rucDv},,,,,,",
            ",,Razón Social/Primer Apellido,,,,,,Segundo Apellido,,,,,,,,,,,,,,,,",
            ",,,,,,,, ,,,,,,,,,,,,,,,,",
            ",,Nombres,,,,,,,,,,,,,,,,,,,,,,",
            ",,{$razonSocial},,,,,,,,,,,,,,,,,,,,,,",
            ",,01,,[ ],Declaración Jurada Original,,,Número de Orden Declaración que se rectifica,,,,,,,,,,,,,,,,",
            ",,02,,[ ],Declaración Jurada Rectificativa,,,03, ,,,,,,,,,,,,,,",
            ",,05,,[ ],\"Declaración Jurada en Caracter de Cese de Actividades, Clausura o Cierre Definitivo\",,,04,Periodo/Ejercicio Fiscal,,,,,,,,,,,,,",
            "120,,,,,,,,,,,,Mes,,Año,,,,,,,,,",
            ",,,,,,,,,,,," . sprintf('%02d', $mes) . ",,{$anio},,,,,,,,,",
            "PARA CONTRIBUYENTES QUE REALICEN OPERACIONES GRAVADAS Y EXONERADAS (INCLUIDAS LAS ExportaciónES),,,,,,,,,,,,,,,,,,,,,,,,",
            ",,,,,,,,,,,,,,,,,,,,,,,,",
            "INC,RUBRO 1 - ENAJENACIÓN DE BIENES Y/O PRESTACIÓN DE SERVICIOS DEL PERIODO,,,,,MONTO IMPONIBLE -I-,,,IVA DÉBITO,,,,,,,,,,,,,,,",
            ",,,,,,,,,AL 5% -II-,,,,,AL 10% -III-,,,,,,,,,,",
            "a,Enajenación de Bienes y Prestación de Servicios gravadas con tasa del 10%,,,,,10,{$v('C10')},,,,,,,22,{$v('C22')},,,,,,,,,",
            "b,Enajenación de Bienes y Prestación de Servicios gravadas con tasa del 5%,,,,,11,{$v('C11')},,19,{$v('C19')},,,,,,,,,,,,,,",
            "c,Enajenación de bienes y/o prestación de servicios exonerados o no alcanzado por el tributo,,,,,12,{$v('C12')},,,,,,,,,,,,,",
            "d,Exportación de productos agropecuarios en estado natural,,,,,13,{$v('C13')},,,,,,,,,,,,,,,,,",
            "e,Exportación de otros bienes,,,,,14,{$v('C14')},,,,,,,,,,,,,,,,,",
            "f,\"Ajustes de precios, devoluciones realizadas, descuentos obtenidos y recupero de Impuesto por operaciones incobrables declaradas a la tasa del 10%\",,,,,15,{$v('C15')},,,,,,,,23,{$v('C23')},,,,,,,,,",
            "g,\"Ajustes de precios, devoluciones realizadas, descuentos obtenidos y recupero de Impuesto por operaciones incobrables declaradas a la tasa del 5%\",,,,,16,{$v('C16')},,,20,{$v('C20')},,,,,,,,,,,,,,",
            "h,\"Ajustes de precios, devoluciones realizadas, descuentos obtenidos y recupero por operaciones incobrables, exonerados o no alcanzados por el tributo\",,,,,17,{$v('C17')},,, , ,,,,,,,,,,,,,,",
            "i,TOTAL: ( Col.I: Inc. a+b+c+d+e+f+g+h); (Col.II: Inc. b+g); (Col.III: Inc. a+f) ,,,,,18,{$v('C18')},,21,{$v('C21')},,,,24,{$v('C24')},,,,,,,,,",
            "INC,RUBRO 3 - COMPRAS LOCALES E IMPORTACIONES DEL PERIODO,,,,,MONTO IMPONIBLE,,,,,,,,IVA CRÉDITO -III-,,,,,,,,,,",
            "a,Creditos Fiscales atribuidos directamente a operaciones gravadas en mercado interno,,,,,32,{$v('C32')},,35,{$v('C35')},,,,38,{$v('C38')},,,,,,,,,",
            "f,TOTAL DE CRÉDITO FISCAL PARA OPERACIONES EN EL MERCADO INTERNO (Col. III: Inc. a+c+d+e),,,,,,,,,,,,,43,{$v('C43')},,,,,,,,,",
            "Creado por ContaPro,,,,,,,,,,,,,,,,,,,,,,,,",
            "INC,RUBRO 4 – DETERMINACIÓN DEL IMPUESTO,,,,,,,,,,,,,MONTO,",
            "a,\"Débito fiscal (Proviene del Rubro 1, Col. II + III: Inc. i)\",,,,,,,,,,,,,44,{$v('C44')}",
            "b,\"Crédito fiscal (Proviene del Rubro 3, Inc. f)\",,,,,,,,,,,,,45,{$v('C45')}",
            "c,\"Saldo a favor del contribuyente del período anterior\",,,,,,,,,,,,,46,{$v('C46')}",
            "d,\"SALDO A FAVOR DEL CONTRIBUYENTE\",,,,,,,,,,,,,47,{$v('C47')}",
            "e,\"SALDO A FAVOR DEL FISCO\",,,,,,,,,,,,,48,{$v('C48')}",
            "g,IMPUESTO DETERMINADO Inc. e - Inc. F,,,,,,,,,,,,,50,{$v('C50')}",
            "INC,RUBRO 5 – LIQUIDACIÓN Y SALDO DEL IMPUESTO,,,,,,,,MONTO A FAVOR DEL,,,,,,",
            "a,\"Impuestos determinado para operaciones gravadas\",,,,,,,,,,,,,55,{$v('C55')}",
            "g,\"SALDO DEFINITIVO A PAGAR A FAVOR DEL FISCO\",,,,,,,,,,,,,58,{$v('C58')}",
        ];

        $filename = sprintf('FORM120_V3_%s_%02d_%04d.csv', $rucNum, $mes, $anio);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
        exit;
    }
}
