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
        $res['fechaDesde']    = $fechaDesde;
        $res['fechaHasta']    = $fechaHasta;
        $res['fechaDesdeFmt'] = self::formatDateDmy($fechaDesde);
        $res['fechaHastaFmt'] = self::formatDateDmy($fechaHasta);
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

    public static function exportarExcelLibroCompras(): void {
        if (!self::requireAuth(false)) return;
        $codEmpresa      = TenantContext::empresa();
        $ejercicio       = EjercicioService::getEjercicio();
        $rango           = EjercicioService::getRango($codEmpresa, $ejercicio);
        $fechaDesde      = EjercicioService::clamp($_GET['fechaDesde'] ?? null, $rango, true);
        $fechaHasta      = EjercicioService::clamp($_GET['fechaHasta'] ?? null, $rango, false);
        $codProveedor    = trim((string)($_GET['codProveedor'] ?? ''));
        $condicion       = trim((string)($_GET['condicion'] ?? ''));
        $incluirAnulados = isset($_GET['incluirAnulados']) && ($_GET['incluirAnulados'] === 'true' || $_GET['incluirAnulados'] === '1');

        $data    = self::procesarReporteLibroCompras($codEmpresa, $ejercicio, $fechaDesde, $fechaHasta, $codProveedor, $condicion, $incluirAnulados);
        $records = $data['records'] ?? [];

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $filename = sprintf('Libro_Compras_%s_%s.xls', str_replace('-', '', $fechaDesde), str_replace('-', '', $fechaHasta));
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF";
        ?>
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <style>
                body, table { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #000000; }
                th { border: 1px solid #cbd5e1; background-color: #f1f5f9; font-weight: bold; text-align: center; }
                td.txt { border: 1px solid #e2e8f0; mso-number-format:"\@"; }
                td.num { border: 1px solid #e2e8f0; text-align: right; mso-number-format:"\#\,\#\#0"; }
            </style>
        </head>
        <body>
            <table border="1" cellpadding="3" cellspacing="0">
                <thead>
                    <tr>
                        <th>DIA-MES</th>
                        <th>DOCUMENTO</th>
                        <th>TIMB.</th>
                        <th>PROVEEDOR / RAZÓN SOCIAL</th>
                        <th>RUC</th>
                        <th>DV</th>
                        <th>COND</th>
                        <th>GRAV. 10%</th>
                        <th>GRAV. 5%</th>
                        <th>IVA 10%</th>
                        <th>IVA 5%</th>
                        <th>EXENTAS</th>
                        <th>BASE IMP.</th>
                        <th>TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $r): ?>
                    <tr>
                        <td class="txt"><?= htmlspecialchars((string)($r['diaMes'] ?? '')) ?></td>
                        <td class="txt"><?= htmlspecialchars((string)($r['documento'] ?? '')) ?></td>
                        <td class="txt"><?= htmlspecialchars((string)($r['timbrado'] ?? '')) ?></td>
                        <td class="txt"><?= htmlspecialchars((string)($r['razonSocial'] ?? '')) ?></td>
                        <td class="txt"><?= htmlspecialchars((string)($r['rucNum'] ?? '')) ?></td>
                        <td class="txt"><?= htmlspecialchars((string)($r['rucDv'] ?? '')) ?></td>
                        <td class="txt"><?= htmlspecialchars((string)($r['condicion'] ?? '')) ?></td>
                        <td class="num"><?= (int)round((float)($r['grav10'] ?? 0)) ?></td>
                        <td class="num"><?= (int)round((float)($r['grav5'] ?? 0)) ?></td>
                        <td class="num"><?= (int)round((float)($r['iva10'] ?? 0)) ?></td>
                        <td class="num"><?= (int)round((float)($r['iva5'] ?? 0)) ?></td>
                        <td class="num"><?= (int)round((float)($r['exentas'] ?? 0)) ?></td>
                        <td class="num"><?= (int)round((float)($r['baseImp'] ?? 0)) ?></td>
                        <td class="num"><?= (int)round((float)($r['total'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        exit;
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
            if ($tot > 0 && abs(($g10 + $g5 + $ex) - $tot) <= 5 && ($i10 > 0 || $i5 > 0)) {
                $g10 = max(0.0, $g10 - $i10);
                $g5  = max(0.0, $g5 - $i5);
            }
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

    public static function exportarExcelLibroVentas(): void {
        if (!self::requireAuth(false)) return;
        $codEmpresa      = TenantContext::empresa();
        $ejercicio       = EjercicioService::getEjercicio();
        $rango           = EjercicioService::getRango($codEmpresa, $ejercicio);
        $fechaDesde      = EjercicioService::clamp($_GET['fechaDesde'] ?? null, $rango, true);
        $fechaHasta      = EjercicioService::clamp($_GET['fechaHasta'] ?? null, $rango, false);
        $codCliente      = trim((string)($_GET['codCliente'] ?? ''));
        $condicion       = trim((string)($_GET['condicion'] ?? ''));
        $incluirAnulados = isset($_GET['incluirAnulados']) && ($_GET['incluirAnulados'] === 'true' || $_GET['incluirAnulados'] === '1');

        $data    = self::procesarReporteLibroVentas($codEmpresa, $ejercicio, $fechaDesde, $fechaHasta, $codCliente, $condicion, $incluirAnulados);
        $records = $data['records'] ?? [];

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $filename = sprintf('Libro_Ventas_%s_%s.xls', str_replace('-', '', $fechaDesde), str_replace('-', '', $fechaHasta));
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF";
        ?>
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <style>
                body, table { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #000000; }
                th { border: 1px solid #cbd5e1; background-color: #f1f5f9; font-weight: bold; text-align: center; }
                td.txt { border: 1px solid #e2e8f0; mso-number-format:"\@"; }
                td.num { border: 1px solid #e2e8f0; text-align: right; mso-number-format:"\#\,\#\#0"; }
            </style>
        </head>
        <body>
            <table border="1" cellpadding="3" cellspacing="0">
                <thead>
                    <tr>
                        <th>DIA-MES</th>
                        <th>DOCUMENTO</th>
                        <th>TIMB.</th>
                        <th>CLIENTE / RAZÓN SOCIAL</th>
                        <th>RUC</th>
                        <th>DV</th>
                        <th>COND</th>
                        <th>GRAV. 10%</th>
                        <th>GRAV. 5%</th>
                        <th>IVA 10%</th>
                        <th>IVA 5%</th>
                        <th>EXENTAS</th>
                        <th>TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $r): ?>
                    <tr>
                        <td class="txt"><?= htmlspecialchars((string)($r['diaMes'] ?? '')) ?></td>
                        <td class="txt"><?= htmlspecialchars((string)($r['documento'] ?? '')) ?></td>
                        <td class="txt"><?= htmlspecialchars((string)($r['timbrado'] ?? '')) ?></td>
                        <td class="txt"><?= htmlspecialchars((string)($r['razonSocial'] ?? '')) ?></td>
                        <td class="txt"><?= htmlspecialchars((string)($r['rucNum'] ?? '')) ?></td>
                        <td class="txt"><?= htmlspecialchars((string)($r['rucDv'] ?? '')) ?></td>
                        <td class="txt"><?= htmlspecialchars((string)($r['condicion'] ?? '')) ?></td>
                        <td class="num"><?= (int)round((float)($r['grav10'] ?? 0)) ?></td>
                        <td class="num"><?= (int)round((float)($r['grav5'] ?? 0)) ?></td>
                        <td class="num"><?= (int)round((float)($r['iva10'] ?? 0)) ?></td>
                        <td class="num"><?= (int)round((float)($r['iva5'] ?? 0)) ?></td>
                        <td class="num"><?= (int)round((float)($r['exentas'] ?? 0)) ?></td>
                        <td class="num"><?= (int)round((float)($r['total'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        exit;
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
                WHERE id_propietario = ? AND cod_empresa = ?
                  AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) <> 'LIQ'
                  AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) NOT LIKE '%LIQUIDAC%' ";
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
        $ejercicio  = EjercicioService::getEjercicio();
        $rango      = EjercicioService::getRango($codEmpresa, $ejercicio);
        $def        = EjercicioService::defaultMes($rango);
        $emp = Database::queryForMap(
            "SELECT descripcion, ruc_empresa FROM empresas WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
            [$idProp, $codEmpresa]
        );

        self::renderFormPage('exportar_marangatu', [
            'nomForma'        => 'COMARANG',
            'tituloForma'     => 'Exportar a Marangatu (RG 90/2021)',
            'empresaRuc'      => (string)($emp['ruc_empresa'] ?? '5555-7'),
            'empresaNombre'   => (string)($emp['descripcion'] ?? 'CONTRIBUYENTE DEMOSTRATIVO'),
            'firstDayOfMonth' => $def[0],
            'currentDate'     => $def[1],
            'ejerInicio'      => $rango[0],
            'ejerFinal'       => $rango[1],
            'ejercicio'       => $ejercicio,
        ]);
    }

    public static function apiMarangatuExportarZip(): void {
        if (!self::requireAuth(false)) return;
        $idProp       = TenantContext::idPropietario();
        $codEmpresa   = TenantContext::empresa();
        $fechaInicial = trim((string)($_GET['fechaInicial'] ?? '')) ?: date('Y-m-01');
        $fechaFinal   = trim((string)($_GET['fechaFinal'] ?? '')) ?: date('Y-m-d');

        // En un <form method="GET">, los checkboxes desmarcados no se envían en la URL.
        // Si viene el formulario (fechaInicial/fechaFinal o cualquier opción), evaluar cada checkbox como false cuando no esté presente.
        $hasFormParams = isset($_GET['fechaInicial']) || isset($_GET['fechaFinal']) || isset($_GET['ventas']) || isset($_GET['compras']) || isset($_GET['incluirExentas']);
        if ($hasFormParams) {
            $ventas         = isset($_GET['ventas']) && $_GET['ventas'] !== 'false' && $_GET['ventas'] !== '0';
            $compras        = isset($_GET['compras']) && $_GET['compras'] !== 'false' && $_GET['compras'] !== '0';
            $incluirExentas = !isset($_GET['incluirExentas']) ? false : ($_GET['incluirExentas'] !== 'false' && $_GET['incluirExentas'] !== '0');
        } else {
            $ventas         = true;
            $compras        = true;
            $incluirExentas = true;
        }

        $rucRaw = (string)(Database::queryForValue(
            "SELECT ruc_empresa FROM empresas WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
            [$idProp, $codEmpresa]
        ) ?? '55557');
        $rucEmpresa = preg_replace('/[^0-9]/', '', explode('-', $rucRaw)[0]) ?: '55557';

        $lineas = [];
        if ($ventas) {
            $sqlV = "SELECT COALESCE(ruc_cliente, '5567097') AS ruc,
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
                       AND COALESCE(inutilizado, 'N') <> 'S'
                       AND COALESCE(estado, 'A') <> 'I'
                       AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) <> 'LIQ'
                       AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) NOT LIKE '%LIQUIDAC%' ";
            if (!$incluirExentas) {
                $sqlV .= "AND (COALESCE(grav10, 0) > 0 OR COALESCE(grav5, 0) > 0) ";
            }
            $sqlV .= "ORDER BY fec_ingreso ASC, id_ingreso ASC";

            $rowsV = Database::queryForList($sqlV, [$idProp, $codEmpresa, $fechaInicial, $fechaFinal]);
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
                    $incluirExentas ? (int)round((float)$r['exenta']) : 0,
                    (int)round((float)$r['total']),
                    (string)$r['condicion']
                );
            }
        }

        if ($compras) {
            $sqlC = "SELECT COALESCE(ruc_proveedor, '5567097') AS ruc,
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
                       AND COALESCE(estado, 'A') <> 'I' ";
            if (!$incluirExentas) {
                $sqlC .= "AND (COALESCE(grav10, 0) > 0 OR COALESCE(grav5, 0) > 0) ";
            }
            $sqlC .= "ORDER BY fec_egreso ASC, id_egreso ASC";

            $rowsC = Database::queryForList($sqlC, [$idProp, $codEmpresa, $fechaInicial, $fechaFinal]);
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
                    $incluirExentas ? (int)round((float)$r['exenta']) : 0,
                    (int)round((float)$r['total']),
                    (string)$r['condicion']
                );
            }
        }

        $fIniClean = str_replace('-', '', $fechaInicial);
        $fFinClean = str_replace('-', '', $fechaFinal);
        $zipFileName = "RG90_MARANGATU_{$rucEmpresa}_{$fIniClean}_{$fFinClean}.zip";
        $txtFileName = "RG90_REGISTROS_{$rucEmpresa}_{$fIniClean}_{$fFinClean}.txt";
        $content = empty($lineas) ? "" : (implode("\r\n", $lineas) . "\r\n");

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
    // 6. FORMULARIO 120 IVA VERSIÓN 4 (COF12000)
    // =========================================================================
    private static function fetchForm120EmpresaInfo(string $codEmpresa): array {
        $idProp = TenantContext::idPropietario();
        $emp = [
            'cod_empresa'      => $codEmpresa,
            'razon_social'     => 'CONTRIBUYENTE DEMOSTRATIVO',
            'primer_apellido'  => 'CONTRIBUYENTE DEMOSTRATIVO',
            'segundo_apellido' => '',
            'nombres'          => '',
            'rucNum'           => '5555',
            'rucDv'            => '7',
        ];
        try {
            $row = Database::queryForMap(
                "SELECT cod_empresa, descripcion AS razon_social, ruc_empresa AS ruc
                 FROM empresas WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
                [$idProp, $codEmpresa]
            );
            if ($row) {
                $emp['cod_empresa']  = (string)($row['cod_empresa'] ?? $codEmpresa);
                $rs = trim((string)($row['razon_social'] ?? 'CONTRIBUYENTE DEMOSTRATIVO'));
                $emp['razon_social'] = $rs;
                $rucRaw = trim((string)($row['ruc'] ?? ''));
                if (str_contains($rucRaw, '-')) {
                    [$emp['rucNum'], $emp['rucDv']] = explode('-', $rucRaw, 2);
                } else {
                    $emp['rucNum'] = $rucRaw;
                    $emp['rucDv']  = '0';
                }

                // Separar Primer Apellido, Segundo Apellido y Nombres para personas físicas en el encabezado V4
                $esJuridica = preg_match('/\b(S\.?A\.?|S\.?R\.?L\.?|LTDA|SOCIEDAD|EMPRESA|COOPERATIVA|E\.?A\.?S\.?|FUNDACION|ASOCIACION)\b/i', $rs);
                if (!$esJuridica && str_contains($rs, ',')) {
                    [$apellidosPart, $nombresPart] = array_map('trim', explode(',', $rs, 2));
                    $apWords = preg_split('/\s+/', $apellidosPart) ?: [];
                    $emp['primer_apellido']  = $apWords[0] ?? $apellidosPart;
                    $emp['segundo_apellido'] = isset($apWords[1]) ? implode(' ', array_slice($apWords, 1)) : '';
                    $emp['nombres']          = $nombresPart;
                } elseif (!$esJuridica) {
                    $words = preg_split('/\s+/', $rs) ?: [];
                    if (count($words) >= 3 && count($words) <= 5) {
                        $emp['primer_apellido']  = $words[0];
                        $emp['segundo_apellido'] = $words[1];
                        $emp['nombres']          = implode(' ', array_slice($words, 2));
                    } else {
                        $emp['primer_apellido'] = $rs;
                    }
                } else {
                    $emp['primer_apellido'] = $rs;
                }
            }
        } catch (\Throwable $ignored) {}
        return $emp;
    }

    public static function pageFormulario120(): void {
        if (!self::requireAuth(false)) return;
        $idProp     = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();

        // Detectar el último periodo con datos cargados en co_egresos o co_ingresos para sugerirlo por defecto
        $defMes  = (int)date('n');
        $defAnio = (int)date('Y');
        try {
            $lastPeriod = Database::queryForMap(
                "SELECT anio, mes, SUM(cant) AS total_comp FROM (
                    SELECT YEAR(fec_egreso) AS anio, MONTH(fec_egreso) AS mes, COUNT(*) AS cant
                    FROM co_egresos
                    WHERE id_propietario = ? AND cod_empresa = ? AND COALESCE(estado, 'A') <> 'I' AND fec_egreso IS NOT NULL
                    GROUP BY YEAR(fec_egreso), MONTH(fec_egreso)
                    UNION ALL
                    SELECT YEAR(fec_ingreso) AS anio, MONTH(fec_ingreso) AS mes, COUNT(*) AS cant
                    FROM co_ingresos
                    WHERE id_propietario = ? AND cod_empresa = ? AND COALESCE(anulada, 'N') <> 'S' AND COALESCE(estado, 'A') <> 'I' AND fec_ingreso IS NOT NULL
                    GROUP BY YEAR(fec_ingreso), MONTH(fec_ingreso)
                ) t
                GROUP BY anio, mes
                ORDER BY total_comp DESC, anio DESC, mes DESC
                LIMIT 1",
                [$idProp, $codEmpresa, $idProp, $codEmpresa]
            );
            if ($lastPeriod && !empty($lastPeriod['anio']) && !empty($lastPeriod['mes'])) {
                $defAnio = (int)$lastPeriod['anio'];
                $defMes  = (int)$lastPeriod['mes'];
            }
        } catch (\Throwable $ignored) {}

        self::renderFormPage('formulario_120', [
            'empresa'     => self::fetchForm120EmpresaInfo($codEmpresa),
            'nomForma'    => 'COF12000',
            'tituloForma' => 'Formulario N° 120 - Impuesto al Valor Agregado (Versión 4)',
            'defMes'      => $defMes,
            'defAnio'     => $defAnio,
        ]);
    }

    private static function normalizarSavedForm120V4(array $saved, array $autoData): array {
        // Asegurar retrocompatibilidad si el registro guardado venía de V3 (ej. C151 desde C11, C160 desde C25, C166 desde C47, C62 desde C40)
        if (((int)($saved['C151'] ?? 0) === 0) && ((int)($saved['C11'] ?? 0) > 0)) {
            $saved['C151'] = (int)$saved['C11'];
            $saved['C157'] = (int)($saved['C19'] ?? round((int)$saved['C11'] * 0.05));
        }
        if (((int)($saved['C155'] ?? 0) === 0) && ((int)($saved['C16'] ?? 0) > 0)) {
            $saved['C155'] = (int)$saved['C16'];
            $saved['C159'] = (int)($saved['C20'] ?? round((int)$saved['C16'] * 0.05));
        }
        if (((int)($saved['C160'] ?? 0) === 0) && ((int)($saved['C25'] ?? 0) > 0)) {
            $saved['C160'] = (int)$saved['C25'];
        }
        if (((int)($saved['C164'] ?? 0) === 0) && ((int)($saved['C39'] ?? 0) > 0)) {
            $saved['C164'] = (int)$saved['C39'];
        }
        if (((int)($saved['C166'] ?? 0) === 0) && ((int)($saved['C47'] ?? 0) > 0)) {
            $saved['C166'] = (int)$saved['C47'];
        }
        if (((int)($saved['C12'] ?? 0) === 0) && ((int)($autoData['C12'] ?? 0) === 0) && ((int)($autoData['C62'] ?? 0) === 0)) {
            foreach (['C40', 'C41', 'C59', 'C65', 'C60', 'C66', 'C61', 'C62', 'C64'] as $kFixR6) {
                $saved[$kFixR6] = (int)($autoData[$kFixR6] ?? 0);
            }
        } elseif (((int)($saved['C62'] ?? 0) === 0) && ((int)($saved['C40'] ?? 0) > 0)) {
            $saved['C62'] = (int)$saved['C40'];
        }
        if (((int)($saved['C12'] ?? 0) > 0) && ((int)($autoData['C12'] ?? 0) === 0)) {
            foreach (['C12', 'C18', 'C160', 'C161', 'C25', 'C26', 'C27', 'C30', 'C31', 'C164', 'C43', 'C45', 'C166', 'C47', 'C48', 'C50', 'C55', 'C53', 'C57', 'C54', 'C58'] as $kFix) {
                if (isset($autoData[$kFix])) {
                    $saved[$kFix] = (int)$autoData[$kFix];
                }
            }
        }
        if (((int)($saved['C160'] ?? 0) > 0 || (int)($saved['C26'] ?? 0) > 0) && ((int)($autoData['C160'] ?? 0) === 0)) {
            foreach (['C160', 'C161', 'C25', 'C26', 'C27', 'C30', 'C31'] as $kFixR2) {
                if (isset($autoData[$kFixR2])) {
                    $saved[$kFixR2] = (int)$autoData[$kFixR2];
                }
            }
        }
        if (((int)($saved['C52'] ?? 0) === 0) && ((int)($autoData['C52'] ?? 0) > 0)) {
            $saved['C52'] = (int)$autoData['C52'];
            $c51s = (int)($saved['C51'] ?? 0);
            $c52s = (int)$saved['C52'];
            $c169s = (int)($saved['C169'] ?? 0);
            $c55s = (int)($saved['C55'] ?? $saved['C50'] ?? 0);
            $c56s = (int)($saved['C56'] ?? 0);
            $c53s = $c51s + $c52s + $c169s;
            $c57s = $c55s + $c56s;
            $saved['C53'] = $c53s;
            $saved['C57'] = $c57s;
            $saved['C54'] = ($c53s > $c57s) ? ($c53s - $c57s) : 0;
            $saved['C58'] = ($c57s > $c53s) ? ($c57s - $c53s) : 0;
        }
        foreach ($autoData as $k => $v) {
            if (!array_key_exists($k, $saved) || $saved[$k] === null) {
                $saved[$k] = $v;
            }
        }
        return $saved;
    }

    public static function apiFormulario120Obtener(): void {
        if (!self::requireAuth()) return;
        $idProp     = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $mes        = (int)($_GET['mes'] ?? date('n'));
        $anio       = (int)($_GET['anio'] ?? date('Y'));

        $autoCalc = self::calcularFormulario120Auto($idProp, $codEmpresa, $mes, $anio);

        $rows = Database::queryForListUpper(
            "SELECT * FROM co_formulario_120 WHERE id_propietario = ? AND cod_empresa = ? AND mes = ? AND anio = ? LIMIT 1",
            [$idProp, $codEmpresa, $mes, $anio]
        );
        if (!empty($rows)) {
            $autoData = $autoCalc['data'];
            $saved    = self::normalizarSavedForm120V4($rows[0], $autoData);
            $savedMov = (int)($saved['C10'] ?? 0) + (int)($saved['C151'] ?? 0) + (int)($saved['C12'] ?? 0) + (int)($saved['C32'] ?? 0) + (int)($saved['C35'] ?? 0) + (int)($saved['C62'] ?? 0) + (int)($saved['C52'] ?? 0);
            $autoMov  = (int)($autoData['C10'] ?? 0) + (int)($autoData['C151'] ?? 0) + (int)($autoData['C12'] ?? 0) + (int)($autoData['C32'] ?? 0) + (int)($autoData['C35'] ?? 0) + (int)($autoData['C62'] ?? 0) + (int)($autoData['C52'] ?? 0);

            View::json([
                'status'           => ($savedMov === 0 && $autoMov > 0) ? 'calculated' : 'found',
                'data'             => ($savedMov === 0 && $autoMov > 0) ? $autoData : $saved,
                'calculadoLibros'  => $autoData,
                'resumenLibros'    => $autoCalc['resumenLibros'],
                'periodosConDatos' => $autoCalc['periodosConDatos'],
                'empresa'          => self::fetchForm120EmpresaInfo($codEmpresa),
            ]);
            return;
        }
        View::json($autoCalc);
    }

    public static function apiFormulario120Calcular(): void {
        if (!self::requireAuth()) return;
        $idProp     = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $mes        = (int)($_GET['mes'] ?? date('n'));
        $anio       = (int)($_GET['anio'] ?? date('Y'));
        View::json(self::calcularFormulario120Auto($idProp, $codEmpresa, $mes, $anio));
    }

    private static function extraerBaseImponibleEImpuesto(float $montoGravado, float $montoIva, int $tasaPorcentaje): array {
        $g = max(0.0, $montoGravado);
        $i = max(0.0, $montoIva);
        if ($g <= 0.0) {
            return [0.0, 0.0];
        }
        $divisorInc = ($tasaPorcentaje === 5) ? 21.0 : 11.0;
        $divisorNet = ($tasaPorcentaje === 5) ? 20.0 : 10.0;

        if ($i <= 0.0) {
            $i = round($g / $divisorInc);
            return [max(0.0, $g - $i), $i];
        }

        // En ContaPro, grav10 y grav5 se guardan con IVA incluido (ej: grav10=199000, iva10=18091).
        // Verificamos si gravado incluye IVA (g / 11 ≈ i) o si ya es neto (g / 10 ≈ i).
        $diffInc = abs(($g / $divisorInc) - $i);
        $diffNet = abs(($g / $divisorNet) - $i);

        if ($diffInc <= $diffNet || $g >= ($i * ($divisorNet + 0.5))) {
            return [max(0.0, $g - $i), $i];
        }
        return [$g, $i];
    }

    private static function calcularFormulario120Auto(int $idProp, string $codEmpresa, int $mes, int $anio): array {
        $startDateStr = sprintf('%04d-%02d-01', $anio, $mes);
        $endDateStr   = date('Y-m-t', strtotime($startDateStr));
        MovimientosContablesController::ensureRetencionesSufridasTables();

        // 0. Obtener lista de periodos con movimientos cargados (para navegación rápida en la UI)
        $periodosConDatos = [];
        try {
            $periodosConDatos = Database::queryForList(
                "SELECT anio, mes,
                        SUM(cant_egresos) AS cant_egresos,
                        SUM(total_egresos) AS total_egresos,
                        SUM(cant_ingresos) AS cant_ingresos,
                        SUM(total_ingresos) AS total_ingresos
                 FROM (
                    SELECT YEAR(fec_egreso) AS anio, MONTH(fec_egreso) AS mes,
                           COUNT(*) AS cant_egresos, COALESCE(SUM(valor_total), 0) AS total_egresos,
                           0 AS cant_ingresos, 0 AS total_ingresos
                    FROM co_egresos
                    WHERE id_propietario = ? AND cod_empresa = ? AND COALESCE(estado, 'A') <> 'I' AND fec_egreso IS NOT NULL
                    GROUP BY YEAR(fec_egreso), MONTH(fec_egreso)
                    UNION ALL
                    SELECT YEAR(fec_ingreso) AS anio, MONTH(fec_ingreso) AS mes,
                           0 AS cant_egresos, 0 AS total_egresos,
                           COUNT(*) AS cant_ingresos, COALESCE(SUM(valor_total), 0) AS total_ingresos
                    FROM co_ingresos
                    WHERE id_propietario = ? AND cod_empresa = ? AND COALESCE(anulada, 'N') <> 'S' AND COALESCE(estado, 'A') <> 'I' AND fec_ingreso IS NOT NULL
                    GROUP BY YEAR(fec_ingreso), MONTH(fec_ingreso)
                    UNION ALL
                    SELECT YEAR(fec_retencion) AS anio, MONTH(fec_retencion) AS mes,
                           0 AS cant_egresos, 0 AS total_egresos,
                           COUNT(*) AS cant_ingresos, COALESCE(SUM(importe_retenido), 0) AS total_ingresos
                    FROM co_retenciones_sufridas
                    WHERE id_propietario = ? AND cod_empresa = ? AND COALESCE(anulada, 'N') <> 'S' AND COALESCE(estado, 'A') <> 'I' AND fec_retencion IS NOT NULL
                    GROUP BY YEAR(fec_retencion), MONTH(fec_retencion)
                 ) p
                 WHERE anio IS NOT NULL AND mes IS NOT NULL
                 GROUP BY anio, mes
                 ORDER BY anio DESC, mes DESC",
                [$idProp, $codEmpresa, $idProp, $codEmpresa, $idProp, $codEmpresa]
            );
        } catch (\Throwable $ignored) {}

        // 1. Ventas del periodo desde co_ingresos (excluyendo Liquidaciones de Salario LIQ y regímenes que no imputan IVA)
        $rowsVentasRaw = Database::queryForList(
            "SELECT id_ingreso, tip_doc, CAST(COALESCE(cod_regimen, 1) AS UNSIGNED) AS cod_regimen, regimen,
                    nro_factura, nro_timbrado, DATE_FORMAT(fec_ingreso, '%d/%m/%Y') AS fec_fmt,
                    nom_cliente, ruc_cliente, condicion,
                    COALESCE(grav10, 0) AS grav10, COALESCE(iva10, 0) AS iva10,
                    COALESCE(grav5, 0) AS grav5, COALESCE(iva5, 0) AS iva5,
                    COALESCE(exentas, 0) AS exentas, COALESCE(valor_total, 0) AS valor_total
             FROM co_ingresos
             WHERE id_propietario = ? AND cod_empresa = ?
               AND fec_ingreso >= ? AND fec_ingreso <= ?
               AND COALESCE(anulada, 'N') <> 'S'
               AND COALESCE(inutilizado, 'N') <> 'S'
               AND COALESCE(estado, 'A') <> 'I'
               AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) <> 'LIQ'
               AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) NOT LIKE '%LIQUIDAC%'
             ORDER BY fec_ingreso ASC, id_ingreso ASC",
            [$idProp, $codEmpresa, $startDateStr, $endDateStr]
        );

        $rowsVentas = [];
        $vBase10 = 0.0; $vIva10 = 0.0;
        $vBase5  = 0.0; $vIva5  = 0.0;
        $vExenta = 0.0;
        $vNcrBase10 = 0.0; $vNcrIva10 = 0.0;
        $vNcrBase5  = 0.0; $vNcrIva5  = 0.0;
        $vNcrExenta = 0.0;
        $vBrutoTotal = 0.0;

        foreach ($rowsVentasRaw as $rv) {
            $rawRegV = strtoupper(trim((string)($rv['regimen'] ?? $rv['cod_regimen'] ?? '1')));
            $codRegV = (int)($rv['cod_regimen'] ?? 1);
            // Excluir ingresos que son exclusivamente IRP (2) o Ganancias de Capital (4)
            if ($rawRegV === '2' || $rawRegV === 'IRP' || $codRegV === 2 || $rawRegV === '4' || str_contains($rawRegV, 'CAPITAL') || $codRegV === 4) {
                continue;
            }
            $rowsVentas[] = $rv;

            $tipDocV = strtoupper(trim((string)($rv['tip_doc'] ?? 'FAC')));
            $isNcr   = ($tipDocV === 'NCR' || str_contains($tipDocV, 'CRED') || str_contains($tipDocV, 'CRÉD'));

            [$b10, $i10] = self::extraerBaseImponibleEImpuesto((float)$rv['grav10'], (float)$rv['iva10'], 10);
            [$b5,  $i5]  = self::extraerBaseImponibleEImpuesto((float)$rv['grav5'],  (float)$rv['iva5'],  5);
            $ex          = max(0.0, (float)$rv['exentas']);
            $tot         = max(0.0, (float)$rv['valor_total']);

            if ($isNcr) {
                $vNcrBase10 += $b10; $vNcrIva10 += $i10;
                $vNcrBase5  += $b5;  $vNcrIva5  += $i5;
                $vNcrExenta += $ex;
                $vBrutoTotal -= $tot;
            } else {
                $vBase10 += $b10; $vIva10 += $i10;
                $vBase5  += $b5;  $vIva5  += $i5;
                $vExenta += $ex;
                $vBrutoTotal += $tot;
            }
        }

        // Rubro 1 (Versión 4)
        $c10  = (int)round($vBase10);
        $c22  = (int)round($vIva10);
        $c150 = 0; // Productos agrícolas al 5%
        $c156 = 0;
        $c151 = (int)round($vBase5); // Otros bienes y/o servicios gravados al 5%
        $c157 = (int)round($vIva5);
        $c11  = $c151; // Alias retrocompatible V3
        $c19  = $c157;
        $c12  = (int)round($vExenta);
        $c152 = 0; // Exportación productos agrícolas
        $c153 = 0; // Flete internacional exportación
        $c13  = 0;
        $c14  = 0; // Exportación otros bienes
        $c15  = (int)round($vNcrBase10);
        $c23  = (int)round($vNcrIva10);
        $c154 = 0; // Ajustes 5% agrícolas
        $c158 = 0;
        $c155 = (int)round($vNcrBase5); // Ajustes 5% otros bienes y servicios
        $c159 = (int)round($vNcrIva5);
        $c16  = $c155;
        $c20  = $c159;
        $c17  = (int)round($vNcrExenta);

        $c18 = $c10 + $c150 + $c151 + $c12 + $c152 + $c153 + $c14 + $c15 + $c154 + $c155 + $c17;
        $c21 = $c156 + $c157 + $c158 + $c159;
        $c24 = $c22 + $c23;

        // 2. Rubro 2 (Versión 4): Acumulado de los últimos 6 meses (incluyendo el periodo que se declara, sin LIQ ni Solo IRP)
        $sixMonthsStart = date('Y-m-01', strtotime("-5 months", strtotime($startDateStr)));
        $rowsVentas6M = Database::queryForList(
            "SELECT tip_doc, CAST(COALESCE(cod_regimen, 1) AS UNSIGNED) AS cod_regimen, regimen,
                    COALESCE(grav10, 0) AS grav10, COALESCE(iva10, 0) AS iva10,
                    COALESCE(grav5, 0) AS grav5, COALESCE(iva5, 0) AS iva5, COALESCE(exentas, 0) AS exentas
             FROM co_ingresos
             WHERE id_propietario = ? AND cod_empresa = ?
               AND fec_ingreso >= ? AND fec_ingreso <= ?
               AND COALESCE(anulada, 'N') <> 'S'
               AND COALESCE(inutilizado, 'N') <> 'S'
               AND COALESCE(estado, 'A') <> 'I'
               AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) <> 'LIQ'
               AND UPPER(TRIM(COALESCE(tip_doc, 'FAC'))) NOT LIKE '%LIQUIDAC%'",
            [$idProp, $codEmpresa, $sixMonthsStart, $endDateStr]
        );
        $acumGrav6M = 0.0;
        $acumEx6M   = 0.0;
        foreach ($rowsVentas6M as $rv6) {
            $rawReg6 = strtoupper(trim((string)($rv6['regimen'] ?? $rv6['cod_regimen'] ?? '1')));
            $codReg6 = (int)($rv6['cod_regimen'] ?? 1);
            if ($rawReg6 === '2' || $rawReg6 === 'IRP' || $codReg6 === 2 || $rawReg6 === '4' || str_contains($rawReg6, 'CAPITAL') || $codReg6 === 4) {
                continue;
            }
            $tipV6 = strtoupper(trim((string)($rv6['tip_doc'] ?? 'FAC')));
            $sign  = ($tipV6 === 'NCR' || str_contains($tipV6, 'CRED') || str_contains($tipV6, 'CRÉD')) ? -1.0 : 1.0;
            [$b10_6, ] = self::extraerBaseImponibleEImpuesto((float)$rv6['grav10'], (float)$rv6['iva10'], 10);
            [$b5_6,  ] = self::extraerBaseImponibleEImpuesto((float)$rv6['grav5'],  (float)$rv6['iva5'],  5);
            $acumGrav6M += $sign * ($b10_6 + $b5_6);
            $acumEx6M   += $sign * max(0.0, (float)$rv6['exentas']);
        }
        $c160 = max(0, (int)round($acumGrav6M));
        $c161 = 0;
        $c25  = $c160;
        $c26  = max(0, (int)round($acumEx6M));
        $c27  = $c160 + $c161 + $c26;
        $c162 = 0; $c163 = 0; $c28 = 0; $c29 = 0;
        $c30  = $c162 + $c163 + $c29;
        $c31  = $c27 + $c30;

        // 3. Compras del periodo desde co_egresos (respetando régimen, form_120, clasificacion_gasto y tipo de comprobante)
        $rowsCompras = Database::queryForList(
            "SELECT id_egreso, cdc, tip_doc, tip_timb,
                    CAST(COALESCE(cod_regimen, 1) AS UNSIGNED) AS cod_regimen, regimen,
                    clasificacion_gasto, tip_egreso, form_120, condicion,
                    nro_factura, nro_timbrado, DATE_FORMAT(fec_egreso, '%d/%m/%Y') AS fec_fmt,
                    nom_proveedor, ruc_proveedor,
                    COALESCE(grav10, 0) AS grav10, COALESCE(iva10, 0) AS iva10,
                    COALESCE(grav5, 0) AS grav5, COALESCE(iva5, 0) AS iva5,
                    COALESCE(exentas, 0) AS exentas, COALESCE(valor_total, 0) AS valor_total
             FROM co_egresos
             WHERE id_propietario = ? AND cod_empresa = ?
               AND fec_egreso >= ? AND fec_egreso <= ?
               AND COALESCE(estado, 'A') <> 'I'
             ORDER BY fec_egreso ASC, id_egreso ASC",
            [$idProp, $codEmpresa, $startDateStr, $endDateStr]
        );

        // Acumuladores Rubro 3 y Rubro 6
        $dirBase10 = 0.0; $dirIva10 = 0.0;
        $dirBase5  = 0.0; $dirIva5  = 0.0;
        $proBase10 = 0.0; $proIva10 = 0.0;
        $proBase5  = 0.0; $proIva5  = 0.0;
        $compExentasDirecto    = 0.0;
        $compExentasIndistinto = 0.0;
        $noIvaBase10 = 0.0; $noIva10 = 0.0;
        $noIvaBase5  = 0.0; $noIva5  = 0.0;

        // Estadísticas detalladas de los datos cargados
        $cantCompTotal      = 0;
        $cantCompElectronic = 0;
        $cantCompManual     = 0;
        $cantCompImputaIva  = 0;
        $cantCompSoloIrp    = 0;
        $cantCompContado    = 0;
        $cantCompCredito    = 0;
        $brutoComprasTotal  = 0.0;
        $brutoGrav10Total   = 0.0;
        $brutoGrav5Total    = 0.0;
        $montoNoImputaIva   = 0.0;

        $porRegimen = [
            '1' => ['codigo' => '1', 'nombre' => 'IRP & IVA',  'cantidad' => 0, 'grav10' => 0.0, 'iva10' => 0.0, 'grav5' => 0.0, 'iva5' => 0.0, 'exentas' => 0.0, 'total' => 0.0],
            '3' => ['codigo' => '3', 'nombre' => 'IVA',        'cantidad' => 0, 'grav10' => 0.0, 'iva10' => 0.0, 'grav5' => 0.0, 'iva5' => 0.0, 'exentas' => 0.0, 'total' => 0.0],
            '2' => ['codigo' => '2', 'nombre' => 'IRP (Solo)', 'cantidad' => 0, 'grav10' => 0.0, 'iva10' => 0.0, 'grav5' => 0.0, 'iva5' => 0.0, 'exentas' => 0.0, 'total' => 0.0],
            '4' => ['codigo' => '4', 'nombre' => 'G. CAPITAL', 'cantidad' => 0, 'grav10' => 0.0, 'iva10' => 0.0, 'grav5' => 0.0, 'iva5' => 0.0, 'exentas' => 0.0, 'total' => 0.0],
            '5' => ['codigo' => '5', 'nombre' => 'IVA e IRE',  'cantidad' => 0, 'grav10' => 0.0, 'iva10' => 0.0, 'grav5' => 0.0, 'iva5' => 0.0, 'exentas' => 0.0, 'total' => 0.0],
        ];
        $proveedoresResumen = [];

        foreach ($rowsCompras as $rc) {
            $cantCompTotal++;
            $tipDocC = strtoupper(trim((string)($rc['tip_doc'] ?? 'FAC')));
            $sign    = ($tipDocC === 'NCR' || str_contains($tipDocC, 'CRED') || str_contains($tipDocC, 'CRÉD')) ? -1.0 : 1.0;

            $tipTimbC = strtoupper(trim((string)($rc['tip_timb'] ?? '')));
            $cdcC     = preg_replace('/\D/', '', (string)($rc['cdc'] ?? ''));
            if (str_contains($tipTimbC, 'ELECTR') || strlen($cdcC) === 44) {
                $cantCompElectronic++;
            } else {
                $cantCompManual++;
            }

            $condC = strtoupper(trim((string)($rc['condicion'] ?? 'CON')));
            if (str_starts_with($condC, 'CR')) {
                $cantCompCredito++;
            } else {
                $cantCompContado++;
            }

            // Determinar régimen (1 = IRP & IVA, 2 = IRP, 3 = IVA, 4 = G. CAPITAL, 5 = IVA e IRE)
            $rawReg  = strtoupper(trim((string)($rc['regimen'] ?? $rc['cod_regimen'] ?? '1')));
            $codReg  = (int)($rc['cod_regimen'] ?? 1);
            if ($rawReg === '2' || $rawReg === 'IRP' || $codReg === 2) {
                $regKey = '2';
            } elseif ($rawReg === '3' || $rawReg === 'IVA' || $codReg === 3) {
                $regKey = '3';
            } elseif ($rawReg === '4' || str_contains($rawReg, 'CAPITAL') || $codReg === 4) {
                $regKey = '4';
            } elseif ($rawReg === '5' || str_contains($rawReg, 'IRE') || $codReg === 5) {
                $regKey = '5';
            } else {
                $regKey = '1';
            }

            // Determinar si clasificacion_gasto es personal/familiar (3 o 4) o relacionada a la actividad (1 o 2)
            $clasG = trim((string)($rc['clasificacion_gasto'] ?? '1'));
            if (is_numeric($clasG)) {
                $clasG = (string)(int)round((float)$clasG);
            }
            $esGastoPersonal = ($clasG === '3' || $clasG === '4' || stripos($clasG, 'PERSONAL') !== false || stripos($clasG, 'FAMILIAR') !== false);

            // Imputa IVA si el régimen es 1 (IRP & IVA), 3 (IVA) o 5 (IVA e IRE) y no es gasto personal
            $imputaIva = ($regKey === '1' || $regKey === '3' || $regKey === '5') && !$esGastoPersonal;

            $g10Raw = (float)$rc['grav10'];
            $i10Raw = (float)$rc['iva10'];
            $g5Raw  = (float)$rc['grav5'];
            $i5Raw  = (float)$rc['iva5'];
            $exRaw  = max(0.0, (float)$rc['exentas']);
            $totRaw = max(0.0, (float)$rc['valor_total']);

            [$b10, $i10] = self::extraerBaseImponibleEImpuesto($g10Raw, $i10Raw, 10);
            [$b5,  $i5]  = self::extraerBaseImponibleEImpuesto($g5Raw,  $i5Raw,  5);

            $brutoComprasTotal += $sign * $totRaw;
            $brutoGrav10Total  += $sign * $g10Raw;
            $brutoGrav5Total   += $sign * $g5Raw;

            $porRegimen[$regKey]['cantidad']++;
            $porRegimen[$regKey]['grav10']  += $sign * $b10;
            $porRegimen[$regKey]['iva10']   += $sign * $i10;
            $porRegimen[$regKey]['grav5']   += $sign * $b5;
            $porRegimen[$regKey]['iva5']    += $sign * $i5;
            $porRegimen[$regKey]['exentas'] += $sign * $exRaw;
            $porRegimen[$regKey]['total']   += $sign * $totRaw;

            // Agrupar por proveedor para el resumen ejecutivo
            $rucP = trim((string)($rc['ruc_proveedor'] ?? 'S/RUC'));
            $nomP = trim((string)($rc['nom_proveedor'] ?? 'PROVEEDOR'));
            $pKey = $rucP ?: $nomP;
            if (!isset($proveedoresResumen[$pKey])) {
                $proveedoresResumen[$pKey] = [
                    'ruc'      => $rucP,
                    'nombre'   => $nomP,
                    'cantidad' => 0,
                    'base10'   => 0.0,
                    'iva10'    => 0.0,
                    'base5'    => 0.0,
                    'iva5'     => 0.0,
                    'exentas'  => 0.0,
                    'total'    => 0.0,
                ];
            }
            $proveedoresResumen[$pKey]['cantidad']++;
            $proveedoresResumen[$pKey]['base10']  += $sign * $b10;
            $proveedoresResumen[$pKey]['iva10']   += $sign * $i10;
            $proveedoresResumen[$pKey]['base5']   += $sign * $b5;
            $proveedoresResumen[$pKey]['iva5']    += $sign * $i5;
            $proveedoresResumen[$pKey]['exentas'] += $sign * $exRaw;
            $proveedoresResumen[$pKey]['total']   += $sign * $totRaw;

            $f120Modo = strtoupper(trim((string)($rc['form_120'] ?? 'DIRECTO')));
            $esProrrateo  = str_contains($f120Modo, 'PRORRAT') || str_contains($f120Modo, 'INDIST');
            $esExonerada  = str_contains($f120Modo, 'EXONER') || str_contains($f120Modo, 'RUBRO6') || str_contains($f120Modo, 'EXENTA');

            if ($imputaIva && !$esExonerada) {
                $cantCompImputaIva++;
                if ($esProrrateo) {
                    $proBase10 += $sign * $b10;
                    $proIva10  += $sign * $i10;
                    $proBase5  += $sign * $b5;
                    $proIva5   += $sign * $i5;
                    $compExentasIndistinto += $sign * $exRaw;
                } else {
                    $dirBase10 += $sign * $b10;
                    $dirIva10  += $sign * $i10;
                    $dirBase5  += $sign * $b5;
                    $dirIva5   += $sign * $i5;
                }
            } else {
                $cantCompSoloIrp++;
                $montoNoImputaIva += $sign * $totRaw;
                if ($esExonerada) {
                    $noIvaBase10 += $sign * $b10;
                    $noIva10     += $sign * $i10;
                    $noIvaBase5  += $sign * $b5;
                    $noIva5      += $sign * $i5;
                    $compExentasDirecto += $sign * $exRaw;
                }
            }
        }

        // Ordenar Top Proveedores por monto total descendente
        $topProveedores = array_values($proveedoresResumen);
        usort($topProveedores, fn($a, $b) => $b['total'] <=> $a['total']);
        $topProveedores = array_slice($topProveedores, 0, 15);

        // Rubro 3 (Versión 4)
        $c32 = max(0, (int)round($dirBase5));
        $c35 = max(0, (int)round($dirBase10));
        $c38 = max(0, (int)round($dirIva10 + $dirIva5));

        $c33 = max(0, (int)round($proBase5));
        $c36 = max(0, (int)round($proBase10));
        $c39 = max(0, (int)round($proIva10 + $proIva5));

        // Según instructivo DNIT: si no hay ventas exoneradas (C12/C26 = 0) ni compras sujetas a prorrateo (C39 = 0),
        // el Rubro 2 queda íntegramente en 0.
        if ($c12 <= 0 && $c26 <= 0 && $c39 <= 0) {
            $c160 = 0;
            $c161 = 0;
            $c25  = 0;
            $c26  = 0;
            $c27  = 0;
            $c30  = 0;
            $c31  = 0;
        }

        // Inc. c [164]: Rubro 3, Col. III: Inc. b [39] x (Rubro 2 Inc. a+b / Inc. d)
        $propMercInterno = ($c27 > 0) ? (($c160 + $c161) / $c27) : 1.0;
        $c164 = max(0, (int)round($c39 * $propMercInterno));
        $c165 = 0; // Hoja de cálculo exportadores (casilla 216)

        $c34 = 0; $c37 = 0; $c42 = 0;
        $c40 = max(0, (int)round($compExentasDirecto));
        $c41 = max(0, (int)round($compExentasIndistinto));
        $c43 = $c38 + $c164 + $c165 + $c42;

        // 4. Determinación del Impuesto o del Saldo Técnico (Rubro 4 Versión 4)
        $c44 = $c21 + $c24;
        $c45 = $c43;
        $prevMes  = ($mes === 1) ? 12 : $mes - 1;
        $prevAnio = ($mes === 1) ? $anio - 1 : $anio;
        $c46 = (int)(Database::queryForValue(
            "SELECT c47 FROM co_formulario_120 WHERE id_propietario = ? AND cod_empresa = ? AND mes = ? AND anio = ? LIMIT 1",
            [$idProp, $codEmpresa, $prevMes, $prevAnio]
        ) ?? 0);

        if (($c45 + $c46) > $c44) {
            $c166 = ($c45 + $c46) - $c44;
            $c48  = 0;
        } else {
            $c48  = $c44 - ($c45 + $c46);
            $c166 = 0;
        }
        $c167 = 0; // Saldo a favor a remitir en beneficio del Fisco (Art. 91)
        $c47  = max(0, $c166 - $c167);
        $c49  = 0; // Anexo Exportador casilla 148
        $c168 = 0; // Deducción Ley N° 4.962/2013
        $c50  = max(0, $c48 - $c49 - $c168);

        // 5. Impuesto Determinado y/o Saldo Financiero a Favor del Contribuyente (Rubro 5 Versión 4)
        $c55  = $c50;
        $c51  = (int)(Database::queryForValue(
            "SELECT c54 FROM co_formulario_120 WHERE id_propietario = ? AND cod_empresa = ? AND mes = ? AND anio = ? LIMIT 1",
            [$idProp, $codEmpresa, $prevMes, $prevAnio]
        ) ?? 0);
        $rowRetMes = Database::queryForList(
            "SELECT COUNT(*) AS cant_ret,
                    COALESCE(SUM(COALESCE(importe_retenido, 0) * COALESCE(NULLIF(tasa_cambio, 0), 1)), 0) AS total_ret
             FROM co_retenciones_sufridas
             WHERE id_propietario = ? AND cod_empresa = ?
               AND fec_retencion >= ? AND fec_retencion <= ?
               AND COALESCE(anulada, 'N') <> 'S'
               AND COALESCE(estado, 'A') <> 'I'",
            [$idProp, $codEmpresa, $startDateStr . ' 00:00:00', $endDateStr . ' 23:59:59']
        );
        $cantRetencionesMes = (int)($rowRetMes[0]['cant_ret'] ?? 0);
        $c52  = max(0, (int)round((float)($rowRetMes[0]['total_ret'] ?? 0))); // Retenciones computables
        $c169 = 0; // Percepciones computables
        $c56  = 0; // Multa por contravención
        $c53  = $c51 + $c52 + $c169;
        $c57  = $c55 + $c56;
        $c54  = ($c53 > $c57) ? ($c53 - $c57) : 0;
        $c58  = ($c57 > $c53) ? ($c57 - $c53) : 0;

        // 6. Información de las Compras vinculadas a operaciones exoneradas o no alcanzadas (Rubro 6 Versión 4)
        // Según instructivo DNIT, si el contribuyente no realiza operaciones exoneradas o no alcanzadas (C12=0 y C26=0),
        // las compras exentas vinculadas a su actividad gravada no se declaran en el Rubro 6.
        $tieneOpExoneradas = ($c12 > 0 || $c26 > 0);
        $c59  = $tieneOpExoneradas ? max(0, (int)round($noIvaBase10)) : 0;
        $c65  = $tieneOpExoneradas ? max(0, (int)round($noIva10)) : 0;
        $c60  = $tieneOpExoneradas ? max(0, (int)round($noIvaBase5)) : 0;
        $c66  = $tieneOpExoneradas ? max(0, (int)round($noIva5)) : 0;
        $c61  = $tieneOpExoneradas ? max(0, (int)round($compExentasIndistinto)) : 0;
        $c62  = $tieneOpExoneradas ? max(0, (int)round($compExentasDirecto)) : 0;
        $c63  = 0;
        $c64  = $tieneOpExoneradas ? max(0, $c65 + $c66 + max(0, $c39 - $c164)) : 0; // IVA Costo o Gasto deducible en IRE o IRP
        $c170 = $c167;

        $form = [
            'COD_EMPRESA'      => $codEmpresa,
            'MES'              => $mes,
            'ANIO'             => $anio,
            'TIPO_DECLARACION' => 'O',
            'NUM_ORDEN_RECT'   => '',
            'CARACTER_CESE'    => 'N',
            // Rubro 1 V4
            'C10'  => $c10,  'C22'  => $c22,
            'C150' => $c150, 'C156' => $c156,
            'C151' => $c151, 'C157' => $c157,
            'C11'  => $c11,  'C19'  => $c19,
            'C12'  => $c12,  'C152' => $c152, 'C153' => $c153, 'C13' => $c13, 'C14' => $c14,
            'C15'  => $c15,  'C23'  => $c23,
            'C154' => $c154, 'C158' => $c158,
            'C155' => $c155, 'C159' => $c159,
            'C16'  => $c16,  'C20'  => $c20,
            'C17'  => $c17,
            'C18'  => $c18,  'C21'  => $c21,  'C24'  => $c24,
            // Rubro 2 V4
            'C160' => $c160, 'C161' => $c161, 'C25' => $c25, 'C26' => $c26, 'C27' => $c27,
            'C162' => $c162, 'C163' => $c163, 'C28' => $c28, 'C29' => $c29, 'C30' => $c30, 'C31' => $c31,
            // Rubro 3 V4
            'C32'  => $c32,  'C35'  => $c35,  'C38' => $c38,
            'C33'  => $c33,  'C36'  => $c36,  'C39' => $c39,
            'C164' => $c164, 'C165' => $c165,
            'C40'  => $c40,  'C41'  => $c41,
            'C34'  => $c34,  'C37'  => $c37,  'C42' => $c42, 'C43' => $c43,
            // Rubro 4 V4
            'C44'  => $c44,  'C45'  => $c45,  'C46' => $c46,
            'C166' => $c166, 'C167' => $c167, 'C47' => $c47,
            'C48'  => $c48,  'C49'  => $c49,  'C168'=> $c168, 'C50' => $c50,
            // Rubro 5 V4
            'C55'  => $c55,  'C51'  => $c51,  'C52' => $c52, 'C169' => $c169,
            'C56'  => $c56,  'C53'  => $c53,  'C57' => $c57, 'C54'  => $c54, 'C58' => $c58,
            // Rubro 6 V4
            'C59'  => $c59,  'C65'  => $c65,  'C60' => $c60, 'C66'  => $c66,
            'C61'  => $c61,  'C62'  => $c62,  'C63' => $c63, 'C64'  => $c64, 'C170' => $c170,
        ];

        $resumenLibros = [
            'periodoStr'          => sprintf('%02d/%04d', $mes, $anio),
            'fechaDesde'          => self::formatDateDmy($startDateStr),
            'fechaHasta'          => self::formatDateDmy($endDateStr),
            'cantVentas'          => count($rowsVentas),
            'ventasBrutoTotal'    => (int)round($vBrutoTotal),
            'ventasBase10'        => $c10,
            'ventasIva10'         => $c22,
            'ventasBase5'         => $c151,
            'ventasIva5'          => $c157,
            'ventasExentas'       => $c12,
            'cantCompras'         => $cantCompTotal,
            'cantComprasElectr'   => $cantCompElectronic,
            'cantComprasManual'   => $cantCompManual,
            'cantComprasImputaIva'=> $cantCompImputaIva,
            'cantComprasSoloIrp'  => $cantCompSoloIrp,
            'cantComprasContado'  => $cantCompContado,
            'cantComprasCredito'  => $cantCompCredito,
            'comprasBrutoTotal'   => (int)round($brutoComprasTotal),
            'comprasBrutoGrav10'  => (int)round($brutoGrav10Total),
            'comprasBrutoGrav5'   => (int)round($brutoGrav5Total),
            'comprasBase10'       => $c35 + $c36,
            'comprasIva10'        => (int)round($dirIva10 + $proIva10),
            'comprasBase5'        => $c32 + $c33,
            'comprasIva5'         => (int)round($dirIva5 + $proIva5),
            'comprasExentas'      => $c61 + $c62,
            'comprasTotalIva'     => $c43,
            'montoNoImputaIva'    => (int)round($montoNoImputaIva),
            'cantRetenciones'     => $cantRetencionesMes,
            'totalRetenciones'    => $c52,
            'porRegimen'          => array_values($porRegimen),
            'topProveedores'      => $topProveedores,
        ];

        return [
            'status'           => 'calculated',
            'data'             => $form,
            'calculadoLibros'  => $form,
            'resumenLibros'    => $resumenLibros,
            'periodosConDatos' => $periodosConDatos,
            'empresa'          => self::fetchForm120EmpresaInfo($codEmpresa),
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

        // Mantener sincronizados los alias V3 con las casillas equivalentes de V4
        if (isset($payload['c151']) && !isset($payload['c11'])) {
            $payload['c11'] = (int)($payload['c150'] ?? 0) + (int)$payload['c151'];
            $payload['c19'] = (int)($payload['c156'] ?? 0) + (int)($payload['c157'] ?? 0);
        }
        if (isset($payload['c155']) && !isset($payload['c16'])) {
            $payload['c16'] = (int)($payload['c154'] ?? 0) + (int)$payload['c155'];
            $payload['c20'] = (int)($payload['c158'] ?? 0) + (int)($payload['c159'] ?? 0);
        }
        if (isset($payload['c160']) && !isset($payload['c25'])) {
            $payload['c25'] = (int)$payload['c160'] + (int)($payload['c161'] ?? 0);
        }
        if (isset($payload['c62']) && !isset($payload['c40'])) {
            $payload['c40'] = (int)$payload['c62'];
            $payload['c41'] = (int)($payload['c61'] ?? 0);
        }

        $allFields = [
            'c10','c22','c11','c19','c12','c13','c14','c15','c23','c16','c20','c17','c18','c21','c24',
            'c150','c151','c152','c153','c154','c155','c156','c157','c158','c159',
            'c25','c26','c27','c28','c29','c30','c31','c160','c161','c162','c163',
            'c32','c35','c38','c33','c36','c39','c164','c165','c40','c41','c34','c37','c42','c43',
            'c44','c45','c46','c166','c167','c47','c48','c49','c168','c50',
            'c55','c51','c52','c169','c56','c53','c57','c54','c58',
            'c59','c65','c60','c66','c61','c62','c63','c64','c170'
        ];

        try {
            $fields = [];
            foreach ($allFields as $f) {
                if (\Core\DatabaseMigrator::columnExists('co_formulario_120', $f)) {
                    $fields[] = $f;
                }
            }

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

            View::json(['status' => 'success', 'message' => 'Declaración Jurada Formulario N° 120 (Versión 4) guardada correctamente.']);
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

        $calc = self::calcularFormulario120Auto($idProp, $codEmpresa, $mes, $anio);
        $autoData = $calc['data'];

        $origen = strtolower(trim((string)($_GET['origen'] ?? '')));
        $rows = Database::queryForListUpper(
            "SELECT * FROM co_formulario_120 WHERE id_propietario = ? AND cod_empresa = ? AND mes = ? AND anio = ? LIMIT 1",
            [$idProp, $codEmpresa, $mes, $anio]
        );
        if (!empty($rows) && $origen !== 'calc') {
            $saved    = self::normalizarSavedForm120V4($rows[0], $autoData);
            $savedMov = (int)($saved['C10'] ?? 0) + (int)($saved['C151'] ?? 0) + (int)($saved['C12'] ?? 0) + (int)($saved['C32'] ?? 0) + (int)($saved['C35'] ?? 0) + (int)($saved['C62'] ?? 0) + (int)($saved['C52'] ?? 0);
            $autoMov  = (int)($autoData['C10'] ?? 0) + (int)($autoData['C151'] ?? 0) + (int)($autoData['C12'] ?? 0) + (int)($autoData['C32'] ?? 0) + (int)($autoData['C35'] ?? 0) + (int)($autoData['C62'] ?? 0) + (int)($autoData['C52'] ?? 0);
            $data = ($savedMov === 0 && $autoMov > 0) ? $autoData : $saved;
        } else {
            $data = $autoData;
        }

        View::render('formulario_120_print', [
            'f'       => $data,
            'resumen' => $calc['resumenLibros'],
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

        $empData         = self::fetchForm120EmpresaInfo($codEmpresa);
        $rucNum          = $empData['rucNum'] ?? '5555';
        $rucDv           = $empData['rucDv'] ?? '7';
        $primerApellido  = $empData['primer_apellido'] ?? $empData['razon_social'] ?? '';
        $segundoApellido = $empData['segundo_apellido'] ?? '';
        $nombres         = $empData['nombres'] ?? '';

        $calc = self::calcularFormulario120Auto($idProp, $codEmpresa, $mes, $anio);
        $autoData = $calc['data'] ?? [];
        $resumen  = $calc['resumenLibros'] ?? [];

        $rows = Database::queryForListUpper(
            "SELECT * FROM co_formulario_120 WHERE id_propietario = ? AND cod_empresa = ? AND mes = ? AND anio = ? LIMIT 1",
            [$idProp, $codEmpresa, $mes, $anio]
        );
        if (!empty($rows)) {
            $saved    = self::normalizarSavedForm120V4($rows[0], $autoData);
            $savedMov = (int)($saved['C10'] ?? 0) + (int)($saved['C151'] ?? 0) + (int)($saved['C12'] ?? 0) + (int)($saved['C32'] ?? 0) + (int)($saved['C35'] ?? 0) + (int)($saved['C62'] ?? 0);
            $autoMov  = (int)($autoData['C10'] ?? 0) + (int)($autoData['C151'] ?? 0) + (int)($autoData['C12'] ?? 0) + (int)($autoData['C32'] ?? 0) + (int)($autoData['C35'] ?? 0) + (int)($autoData['C62'] ?? 0);
            $d = ($savedMov === 0 && $autoMov > 0) ? $autoData : $saved;
        } else {
            $d = $autoData;
        }
        $v = fn(string $k): int => (int)round((float)($d[$k] ?? $d[strtolower($k)] ?? 0));

        $lines = [
            "IMPUESTO AL VALOR AGREGADO - VERSIÓN 4 (FORMULARIO 120),,PARA LLENAR LEA EL INSTRUCTIVO DISPONIBLE EN LA WEB,,,,,,LOS IMPORTES SE CONSIGNARAN SIN CENTIMOS,,,,,,,,,,,,,,,,",
            ",,Número de Orden,,,,,,RUC,,,,,,,,,,DV,,,,,,",
            ",, ,,,,,,{$rucNum},,,,,,,,,{$rucDv},,,,,,",
            ",,Razón Social/Primer Apellido,,,,,,Segundo Apellido,,,,,,,,,,,,,,,,",
            ",,\"{$primerApellido}\",,,,,,\"{$segundoApellido}\",,,,,,,,,,,,,,,,",
            ",,Nombres,,,,,,,,,,,,,,,,,,,,,,",
            ",,\"{$nombres}\",,,,,,,,,,,,,,,,,,,,,,",
            ",,01,,[X],Declaración Jurada Original,,,Número de Orden de Declaración que se rectifica,,,,,,,,,,,,,,,,",
            ",,02,,[ ],Declaración Jurada Rectificativa,,,03, ,,,,,,,,,,,,,,",
            ",,05,,[ ],\"Declaración Jurada en Carácter de Cese de Actividades, Clausura o Cierre Definitivo\",,,04,Periodo / Ejercicio Fiscal:," . sprintf('%02d', $mes) . "/{$anio},,,,,,,,,,,,",
            "PARA CONTRIBUYENTES QUE REALICEN OPERACIONES GRAVADAS Y EXONERADAS (INCLUIDAS LAS EXPORTACIONES),,,,,,,,,,,,,,,,,,,,,,,,",
            ",,,,,,,,,,,,,,,,,,,,,,,,",
            "INC.,RUBRO 1 - ENAJENACIÓN DE BIENES Y/O PRESTACIÓN DE SERVICIOS DEL PERIODO,,,,,MONTO IMPONIBLE -I-,,,IVA DÉBITO AL 5% -II-,,,,,IVA DÉBITO AL 10% -III-,,,,,,,,,,",
            "a,Enajenación de bienes y/o prestación de servicios gravados con tasa del 10%,,,,,10,{$v('C10')},,,,,,,22,{$v('C22')},,,,,,,,,",
            "b,Enajenación de productos agrícolas en estado natural y sus derivados del primer proceso de elaboración o industrialización gravados con tasa del 5%,,,,,150,{$v('C150')},,156,{$v('C156')},,,,,,,,,,,,,,",
            "c,Enajenación de otros bienes y/o prestación de servicios gravados con tasa del 5%,,,,,151,{$v('C151')},,157,{$v('C157')},,,,,,,,,,,,,,",
            "d,\"Enajenación de bienes, prestación de servicios o ingresos exonerados o no alcanzados por el Impuesto\",,,,,12,{$v('C12')},,,,,,,,,,,,,,,,,",
            "e,Exportación de productos agrícolas en estado natural y sus derivados del primer proceso de elaboración o industrialización,,,,,152,{$v('C152')},,,,,,,,,,,,,,,,,",
            "f,Prestación de Servicios de Flete Internacional para la exportación de bienes,,,,,153,{$v('C153')},,,,,,,,,,,,,,,,,",
            "g,Exportación de otros bienes,,,,,14,{$v('C14')},,,,,,,,,,,,,,,,,",
            "h,\"Ajustes de precios, devoluciones realizadas, descuentos obtenidos y recupero de Impuestos por operaciones incobrables, declaradas a la tasa del 10%\",,,,,15,{$v('C15')},,,,,,,23,{$v('C23')},,,,,,,,,",
            "i,\"Ajustes de precios, devoluciones realizadas, descuentos obtenidos y recupero de Impuestos por operaciones incobrables, declaradas a la tasa del 5% (productos agrícolas)\",,,,,154,{$v('C154')},,158,{$v('C158')},,,,,,,,,,,,,,",
            "j,\"Ajustes de precios, devoluciones realizadas, descuentos obtenidos y recupero de Impuestos por operaciones incobrables, declaradas a la tasa del 5% (otros bienes y servicios)\",,,,,155,{$v('C155')},,159,{$v('C159')},,,,,,,,,,,,,,",
            "k,\"Ajustes de precios, devoluciones realizadas, descuentos obtenidos y recupero por operaciones incobrables, exonerados o no alcanzados por el Impuesto\",,,,,17,{$v('C17')},,,,,,,,,,,,,,,,,",
            "l,\"TOTAL (Col. I: Inc. a+b+c+d+e+f+g+h+i+j+k); (Col. II: b+c+i+j); (Col. III: Inc. a+h)\",,,,,18,{$v('C18')},,21,{$v('C21')},,,,,24,{$v('C24')},,,,,,,,,",
            "INC.,RUBRO 2 - ENAJENACIÓN DE BIENES Y/O PRESTACIÓN DE SERVICIOS DE LOS ÚLTIMOS SEIS (6) MESES (INCLUIDO EL PERIODO QUE SE DECLARA),,,,,MONTO ACUMULADO,,,,,,,,,,,,,,,,,,",
            "a,\"Enajenación de bienes y/o prestación de servicios gravados en el mercado interno, con excepción de los productos agrícolas en estado natural\",,,,,160,{$v('C160')},,,,,,,,,,,,,,,,,",
            "b,Enajenación de productos agrícolas en estado natural y sus derivados del primer proceso de elaboración o industrialización en el mercado interno,,,,,161,{$v('C161')},,,,,,,,,,,,,,,,,",
            "c,Enajenación de bienes y/o prestación de servicios exonerados o no alcanzados,,,,,26,{$v('C26')},,,,,,,,,,,,,,,,,",
            "d,TOTAL DE OPERACIONES EN EL MERCADO INTERNO (Inc. a+b+c),,,,,27,{$v('C27')},,,,,,,,,,,,,,,,,",
            "e,Exportación de productos agrícolas en estado natural y sus derivados del primer proceso de elaboración o industrialización,,,,,162,{$v('C162')},,,,,,,,,,,,,,,,,",
            "f,Servicios de flete internacional prestados por empresas transportadoras para la exportación de bienes,,,,,163,{$v('C163')},,,,,,,,,,,,,,,,,",
            "g,Exportación de otros bienes,,,,,29,{$v('C29')},,,,,,,,,,,,,,,,,",
            "h,TOTAL DE OPERACIONES DE EXPORTACIÓN (Inc. e+f+g),,,,,30,{$v('C30')},,,,,,,,,,,,,,,,,",
            "i,TOTAL DE OPERACIONES ACUMULADAS (Inc. d+h),,,,,31,{$v('C31')},,,,,,,,,,,,,,,,,",
            "INC.,RUBRO 3 - COMPRAS LOCALES E IMPORTACIONES DEL PERIODO,,,,,MONTO IMPONIBLE AL 5% -I-,,,MONTO IMPONIBLE AL 10% -II-,,,,,IVA CRÉDITO -III-,,,,,,,,,,",
            "a,IVA Crédito atribuido directamente a operaciones gravadas en el mercado interno,,,,,32,{$v('C32')},,35,{$v('C35')},,,,,38,{$v('C38')},,,,,,,,,",
            "b,\"IVA Crédito atribuido indistintamente a operaciones gravadas, exoneradas o no alcanzadas\",,,,,33,{$v('C33')},,36,{$v('C36')},,,,,39,{$v('C39')},,,,,,,,,",
            "c,IVA Crédito atribuido proporcionalmente a operaciones gravadas en el mercado interno [Rubro 3 Col. III Inc. b x (Rubro 2 Inc. a+b / Inc. d)],,,,,,,,,,,,,164,{$v('C164')},,,,,,,,,",
            "d,IVA Crédito atribuido proporcionalmente a operaciones gravadas en el mercado interno y a exportación (Casilla 216 hoja de cálculo),,,,,,,,,,,,,165,{$v('C165')},,,,,,,,,",
            "e,\"IVA Crédito por ajustes de precios, devoluciones y descuentos otorgados, por ventas ya declaradas y operaciones declaradas incobrables\",,,,,34,{$v('C34')},,37,{$v('C37')},,,,,42,{$v('C42')},,,,,,,,,",
            "f,TOTAL DE IVA CRÉDITO PARA OPERACIONES EN EL MERCADO INTERNO (Col. III: Inc. a+c+d+e),,,,,,,,,,,,,43,{$v('C43')},,,,,,,,,",
            "INC.,RUBRO 4 - DETERMINACIÓN DEL IMPUESTO O DEL SALDO TÉCNICO,,,,,,,,,,,,,MONTO,,,,,,,,,,",
            "a,\"IVA Débito (Proviene del Rubro 1, Col. II y III: Inc. l)\",,,,,,,,,,,,,44,{$v('C44')},,,,,,,,,",
            "b,\"IVA Crédito (Proviene del Rubro 3, Inc. f)\",,,,,,,,,,,,,45,{$v('C45')},,,,,,,,,",
            "c,\"Saldo a favor del contribuyente del período anterior por operaciones en el mercado interno (Proviene del Rubro 4, Inc. f)\",,,,,,,,,,,,,46,{$v('C46')},,,,,,,,,",
            "d,SALDO A FAVOR DEL CONTRIBUYENTE cuando el Inc. a sea menor que Inc. b+Inc. c,,,,,,,,,,,,,166,{$v('C166')},,,,,,,,,",
            "e,Saldo a favor del contribuyente a remitir en beneficio del Fisco (Art. 91 de la Ley). Hasta el monto del Inc. d,,,,,,,,,,,,,167,{$v('C167')},,,,,,,,,",
            "f,\"SALDO A FAVOR DEL CONTRIBUYENTE (Monto a trasladar en el Inc. c del presente Rubro, en el siguiente periodo fiscal), Inc. d - Inc. e\",,,,,,,,,,,,,47,{$v('C47')},,,,,,,,,",
            "g,\"Saldo a favor del fisco, cuando Inc. a sea mayor que Inc. b+Inc. c\",,,,,,,,,,,,,48,{$v('C48')},,,,,,,,,",
            "h,Monto del IVA Crédito utilizado en el periodo que se liquida para operaciones gravadas (Casilla 148 del Anexo Exportador),,,,,,,,,,,,,49,{$v('C49')},,,,,,,,,",
            "i,\"Deducción conforme al Art. 7° y 8° de la Ley N° 4.962/2013 por incorporación de personas con discapacidad (No trasladable)\",,,,,,,,,,,,,168,{$v('C168')},,,,,,,,,",
            "j,IMPUESTO DETERMINADO (Inc. g - Inc. h - Inc. i),,,,,,,,,,,,,50,{$v('C50')},,,,,,,,,",
            "INC.,RUBRO 5 - IMPUESTO DETERMINADO Y/O SALDO FINANCIERO A FAVOR DEL CONTRIBUYENTE,,,,,MONTO A FAVOR DEL CONTRIBUYENTE -I-,,,,,,,MONTO A FAVOR DEL FISCO -II-,,,,,,,,,,,",
            "a,\"Impuesto determinado para operaciones gravadas (Proviene del Rubro 4, Inc. j)\",,,,,,,,,,,,,55,{$v('C55')},,,,,,,,,",
            "b,\"Saldo a favor del contribuyente del período anterior (Proviene del Rubro 5, Col. I: Inc. g)\",,,,,51,{$v('C51')},,,,,,,,,,,,,,,,,",
            "c,Retenciones computables por operaciones gravadas,,,,,52,{$v('C52')},,,,,,,,,,,,,,,,,",
            "d,Percepciones computables por operaciones gravadas,,,,,169,{$v('C169')},,,,,,,,,,,,,,,,,",
            "e,Multa por presentar la Declaración Jurada con posterioridad al vencimiento,,,,,,,,,,,,,56,{$v('C56')},,,,,,,,,",
            "f,SUBTOTALES: (Col. I: Inc. b+c+d); (Col. II: Inc. a+e),,,,,53,{$v('C53')},,,,,,,57,{$v('C57')},,,,,,,,,",
            "g,SALDO A FAVOR DEL CONTRIBUYENTE (Monto a trasladar al siguiente periodo fiscal en el Inc. b del presente Rubro),,,,,54,{$v('C54')},,,,,,,,,,,,,,,,,",
            "h,SALDO A PAGAR A FAVOR DEL FISCO (Diferencia entre Col. I y II: Inc. f cuando Col. II sea mayor que Col. I),,,,,,,,,,,,,58,{$v('C58')},,,,,,,,,",
            "INC.,\"RUBRO 6 - INFORMACIÓN DE LAS COMPRAS DEL PERIODO, VINCULADAS A OPERACIONES EXONERADAS O NO ALCANZADAS POR EL IMPUESTO\",,,,,MONTO -I-,,,,,,,IVA -II-,,,,,,,,,,,",
            "a,Compras con IVA Crédito del 10% relacionadas directamente a operaciones exoneradas o no alcanzadas por el Impuesto,,,,,59,{$v('C59')},,,,,,,65,{$v('C65')},,,,,,,,,",
            "b,Compras con IVA Crédito del 5% relacionadas directamente a operaciones exoneradas o no alcanzadas por el Impuesto,,,,,60,{$v('C60')},,,,,,,66,{$v('C66')},,,,,,,,,",
            "c,\"Compras exentas relacionadas a operaciones exoneradas, no alcanzadas y a exportaciones\",,,,,61,{$v('C61')},,,,,,,,,,,,,,,,,",
            "d,Compras exentas relacionadas a operaciones exoneradas o no alcanzadas por el Impuesto,,,,,62,{$v('C62')},,,,,,,,,,,,,,,,,",
            "e,\"Ajustes de precios, descuentos otorgados y/o devoluciones recibidas, por enajenación de bienes o prestación de servicios exonerados\",,,,,63,{$v('C63')},,,,,,,,,,,,,,,,,",
            "f,Impuesto al Valor Agregado - Costo o Gasto deducible en el IRE o IRP,,,,,64,{$v('C64')},,,,,,,,,,,,,,,,,",
            "g,\"Impuesto al Valor Agregado - Costo o Gasto por remisión del saldo IVA Crédito al fisco, no deducible en el IRE o IRP\",,,,,170,{$v('C170')},,,,,,,,,,,,,,,,,",
            ",,,,,,,,,,,,,,,,,,,,,,,,",
            "RESUMEN DE LIBROS IVA DEL PERIODO,,Cant. Ventas:,{$resumen['cantVentas']},,Cant. Compras:,{$resumen['cantCompras']},,Compras Electrónicas:,{$resumen['cantComprasElectr']},,Total Bruto Compras:,{$resumen['comprasBrutoTotal']}",
        ];

        $filename = sprintf('FORM120_V4_%s_%02d_%04d.csv', $rucNum, $mes, $anio);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
        exit;
    }

    // =========================================================================
    // 7. RESUMEN COMPARATIVO INGRESOS VS. EGRESOS (CORESCOM)
    // =========================================================================
    public static function pageResumenComparativo(): void {
        if (!self::requireAuth(false)) return;
        $codEmpresa = TenantContext::empresa();
        $ejercicio  = EjercicioService::getEjercicio();
        $empData    = self::fetchEmpresaInfo($codEmpresa, $ejercicio);
        $rango      = EjercicioService::getRango($codEmpresa, $ejercicio);
        $def        = EjercicioService::defaultMes($rango);

        self::renderFormPage('resumen_comparativo', [
            'empresa'       => $empData,
            'defFechaDesde' => $def[0],
            'defFechaHasta' => $def[1],
            'ejerInicio'    => $rango[0],
            'ejerFinal'     => $rango[1],
            'ejercicio'     => $ejercicio,
        ]);
    }

    public static function apiResumenComparativoData(): void {
        if (!self::requireAuth()) return;
        try {
        $idProp          = TenantContext::idPropietario();
        $codEmpresa      = TenantContext::empresa();
        $ejercicio       = EjercicioService::getEjercicio();
        $rango           = EjercicioService::getRango($codEmpresa, $ejercicio);
        $fechaDesde      = EjercicioService::clamp($_GET['fechaDesde'] ?? null, $rango, true);
        $fechaHasta      = EjercicioService::clamp($_GET['fechaHasta'] ?? null, $rango, false);
        $regimen         = strtoupper(trim((string)($_GET['regimen'] ?? 'TODOS')));
        $condicion       = strtoupper(trim((string)($_GET['condicion'] ?? 'TODOS')));
        $incluirAnulados = isset($_GET['incluirAnulados']) && ($_GET['incluirAnulados'] === 'true' || $_GET['incluirAnulados'] === '1');

        $empresaInfo = self::fetchEmpresaInfo($codEmpresa, $ejercicio);

        // 1. Consultar Ingresos del ejercicio completo para evolucion mensual + filtrar rango para el periodo
        $sqlIng = "SELECT id_ingreso, DATE_FORMAT(fec_ingreso, '%Y-%m-%d') AS fecha,
                          nro_factura, serie, nro_timbrado, ruc_cliente AS ruc, nom_cliente AS razon_social,
                          condicion, regimen, anulada,
                          COALESCE(grav10, 0) AS grav10, COALESCE(iva10, 0) AS iva10,
                          COALESCE(grav5, 0) AS grav5, COALESCE(iva5, 0) AS iva5,
                          COALESCE(exentas, 0) AS exentas, COALESCE(tot_iva, 0) AS tot_iva,
                          COALESCE(valor_total, 0) AS valor_total
                   FROM co_ingresos
                   WHERE id_propietario = ? AND cod_empresa = ?
                     AND fec_ingreso >= ? AND fec_ingreso <= ?";
        $paramsIng = [$idProp, $codEmpresa, $rango[0], $rango[1]];
        if (!$incluirAnulados) {
            $sqlIng .= " AND (anulada IS NULL OR UPPER(anulada) <> 'S')";
        }
        if ($regimen !== '' && $regimen !== 'TODOS') {
            $sqlIng .= " AND UPPER(TRIM(COALESCE(regimen, ''))) = ?";
            $paramsIng[] = $regimen;
        }
        if ($condicion !== '' && $condicion !== 'TODOS') {
            $sqlIng .= " AND UPPER(LEFT(TRIM(COALESCE(condicion, 'CON')), 3)) = ?";
            $paramsIng[] = substr($condicion, 0, 3);
        }
        $sqlIng .= " ORDER BY fec_ingreso ASC, id_ingreso ASC";

        // 2. Consultar Egresos del ejercicio completo
        $sqlEgr = "SELECT id_egreso, DATE_FORMAT(fec_egreso, '%Y-%m-%d') AS fecha,
                          nro_factura, serie, nro_timbrado, ruc_proveedor AS ruc, nom_proveedor AS razon_social,
                          condicion, regimen, CASE WHEN UPPER(COALESCE(estado, 'A')) = 'I' THEN 'S' ELSE 'N' END AS anulada,
                          COALESCE(grav10, 0) AS grav10, COALESCE(iva10, 0) AS iva10,
                          COALESCE(grav5, 0) AS grav5, COALESCE(iva5, 0) AS iva5,
                          COALESCE(exentas, 0) AS exentas, COALESCE(tot_iva, 0) AS tot_iva,
                          COALESCE(valor_total, 0) AS valor_total
                   FROM co_egresos
                   WHERE id_propietario = ? AND cod_empresa = ?
                     AND fec_egreso >= ? AND fec_egreso <= ?";
        $paramsEgr = [$idProp, $codEmpresa, $rango[0], $rango[1]];
        if (!$incluirAnulados) {
            $sqlEgr .= " AND (estado IS NULL OR UPPER(estado) <> 'I')";
        }
        if ($regimen !== '' && $regimen !== 'TODOS') {
            $sqlEgr .= " AND UPPER(TRIM(COALESCE(regimen, ''))) = ?";
            $paramsEgr[] = $regimen;
        }
        if ($condicion !== '' && $condicion !== 'TODOS') {
            $sqlEgr .= " AND UPPER(LEFT(TRIM(COALESCE(condicion, 'CON')), 3)) = ?";
            $paramsEgr[] = substr($condicion, 0, 3);
        }
        $sqlEgr .= " ORDER BY fec_egreso ASC, id_egreso ASC";

        $rowsIng = Database::queryForList($sqlIng, $paramsIng);
        $rowsEgr = Database::queryForList($sqlEgr, $paramsEgr);

        $nombresMes = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $porMes = [];
        for ($m = 1; $m <= 12; $m++) {
            $porMes[$m] = [
                'mes'        => $m,
                'nombreMes'  => $nombresMes[$m],
                'cantIng'    => 0,
                'ingGrav10'  => 0.0,
                'ingIva10'   => 0.0,
                'ingGrav5'   => 0.0,
                'ingIva5'    => 0.0,
                'ingExentas' => 0.0,
                'ingIva'     => 0.0,
                'ingTotal'   => 0.0,
                'cantEgr'    => 0,
                'egrGrav10'  => 0.0,
                'egrIva10'   => 0.0,
                'egrGrav5'   => 0.0,
                'egrIva5'    => 0.0,
                'egrExentas' => 0.0,
                'egrIva'     => 0.0,
                'egrTotal'   => 0.0,
                'diferencia' => 0.0,
                'saldoIva'   => 0.0,
            ];
        }

        $totIng = [
            'cant' => 0, 'grav10' => 0.0, 'iva10' => 0.0, 'grav5' => 0.0, 'iva5' => 0.0,
            'exentas' => 0.0, 'totIva' => 0.0, 'baseImp' => 0.0, 'total' => 0.0,
            'contado' => 0.0, 'credito' => 0.0,
        ];
        $totEgr = [
            'cant' => 0, 'grav10' => 0.0, 'iva10' => 0.0, 'grav5' => 0.0, 'iva5' => 0.0,
            'exentas' => 0.0, 'totIva' => 0.0, 'baseImp' => 0.0, 'total' => 0.0,
            'contado' => 0.0, 'credito' => 0.0,
        ];

        $porFechaMap = [];
        $movimientos = [];

        $initFecha = function (string $fec) use (&$porFechaMap): void {
            if (!isset($porFechaMap[$fec])) {
                $diaMes = strlen($fec) >= 10 ? (substr($fec, 8, 2) . '/' . substr($fec, 5, 2) . '/' . substr($fec, 0, 4)) : $fec;
                $porFechaMap[$fec] = [
                    'fecha'      => $fec,
                    'diaMes'     => $diaMes,
                    'cantIng'    => 0,
                    'ingGrav10'  => 0.0,
                    'ingGrav5'   => 0.0,
                    'ingExentas' => 0.0,
                    'ingIva'     => 0.0,
                    'ingTotal'   => 0.0,
                    'cantEgr'    => 0,
                    'egrGrav10'  => 0.0,
                    'egrGrav5'   => 0.0,
                    'egrExentas' => 0.0,
                    'egrIva'     => 0.0,
                    'egrTotal'   => 0.0,
                    'diferencia' => 0.0,
                    'saldoIva'   => 0.0,
                ];
            }
        };

        foreach ($rowsIng as $r) {
            $fec = (string)($r['fecha'] ?? '');
            $anulada = strcasecmp((string)($r['anulada'] ?? 'N'), 'S') === 0;
            $g10 = (float)$r['grav10'];
            $i10 = (float)$r['iva10'];
            $g5  = (float)$r['grav5'];
            $i5  = (float)$r['iva5'];
            $ex  = (float)$r['exentas'];
            $iva = ($i10 + $i5) > 0 ? ($i10 + $i5) : (float)$r['tot_iva'];
            $tot = (float)$r['valor_total'];
            $mesNum = strlen($fec) >= 7 ? (int)substr($fec, 5, 2) : 0;

            if (!$anulada && isset($porMes[$mesNum])) {
                $porMes[$mesNum]['cantIng']++;
                $porMes[$mesNum]['ingGrav10']  += $g10;
                $porMes[$mesNum]['ingIva10']   += $i10;
                $porMes[$mesNum]['ingGrav5']   += $g5;
                $porMes[$mesNum]['ingIva5']    += $i5;
                $porMes[$mesNum]['ingExentas'] += $ex;
                $porMes[$mesNum]['ingIva']     += $iva;
                $porMes[$mesNum]['ingTotal']   += $tot;
            }

            if ($fec >= $fechaDesde && $fec <= $fechaHasta) {
                $nroFactura = (string)($r['nro_factura'] ?? '');
                $serie      = trim((string)($r['serie'] ?? ''));
                $doc        = ($serie !== '' && !str_contains($nroFactura, '-')) ? ($serie . '-' . $nroFactura) : $nroFactura;
                $doc        = MovimientosContablesController::formatNroFactura($doc);
                $cond       = strtoupper(trim((string)($r['condicion'] ?? 'CON')));
                $esCre      = str_starts_with($cond, 'CRE');

                if (!$anulada) {
                    $totIng['cant']++;
                    $totIng['grav10']  += $g10;
                    $totIng['iva10']   += $i10;
                    $totIng['grav5']   += $g5;
                    $totIng['iva5']    += $i5;
                    $totIng['exentas'] += $ex;
                    $totIng['totIva']  += $iva;
                    $totIng['baseImp'] += ($g10 + $g5);
                    $totIng['total']   += $tot;
                    if ($esCre) $totIng['credito'] += $tot;
                    else $totIng['contado'] += $tot;

                    $initFecha($fec);
                    $porFechaMap[$fec]['cantIng']++;
                    $porFechaMap[$fec]['ingGrav10']  += $g10;
                    $porFechaMap[$fec]['ingGrav5']   += $g5;
                    $porFechaMap[$fec]['ingExentas'] += $ex;
                    $porFechaMap[$fec]['ingIva']     += $iva;
                    $porFechaMap[$fec]['ingTotal']   += $tot;
                }

                $movimientos[] = [
                    'fecha'       => $fec,
                    'diaMes'      => strlen($fec) >= 10 ? (substr($fec, 8, 2) . '-' . substr($fec, 5, 2)) : $fec,
                    'tipo'        => 'INGRESO',
                    'documento'   => $doc,
                    'timbrado'    => (string)($r['nro_timbrado'] ?? ''),
                    'ruc'         => (string)($r['ruc'] ?? ''),
                    'razonSocial' => (string)($r['razon_social'] ?? ''),
                    'condicion'   => $esCre ? 'CRE' : 'CON',
                    'regimen'     => (string)($r['regimen'] ?? ''),
                    'grav10'      => $g10,
                    'iva10'       => $i10,
                    'grav5'       => $g5,
                    'iva5'        => $i5,
                    'exentas'     => $ex,
                    'totIva'      => $iva,
                    'total'       => $tot,
                    'anulada'     => $anulada,
                ];
            }
        }

        foreach ($rowsEgr as $r) {
            $fec = (string)($r['fecha'] ?? '');
            $anulada = strcasecmp((string)($r['anulada'] ?? 'N'), 'S') === 0;
            $g10 = (float)$r['grav10'];
            $i10 = (float)$r['iva10'];
            $g5  = (float)$r['grav5'];
            $i5  = (float)$r['iva5'];
            $ex  = (float)$r['exentas'];
            $iva = ($i10 + $i5) > 0 ? ($i10 + $i5) : (float)$r['tot_iva'];
            $tot = (float)$r['valor_total'];
            $mesNum = strlen($fec) >= 7 ? (int)substr($fec, 5, 2) : 0;

            if (!$anulada && isset($porMes[$mesNum])) {
                $porMes[$mesNum]['cantEgr']++;
                $porMes[$mesNum]['egrGrav10']  += $g10;
                $porMes[$mesNum]['egrIva10']   += $i10;
                $porMes[$mesNum]['egrGrav5']   += $g5;
                $porMes[$mesNum]['egrIva5']    += $i5;
                $porMes[$mesNum]['egrExentas'] += $ex;
                $porMes[$mesNum]['egrIva']     += $iva;
                $porMes[$mesNum]['egrTotal']   += $tot;
            }

            if ($fec >= $fechaDesde && $fec <= $fechaHasta) {
                $nroFactura = (string)($r['nro_factura'] ?? '');
                $serie      = trim((string)($r['serie'] ?? ''));
                $doc        = ($serie !== '' && !str_contains($nroFactura, '-')) ? ($serie . '-' . $nroFactura) : $nroFactura;
                $doc        = MovimientosContablesController::formatNroFactura($doc);
                $cond       = strtoupper(trim((string)($r['condicion'] ?? 'CON')));
                $esCre      = str_starts_with($cond, 'CRE');

                if (!$anulada) {
                    $totEgr['cant']++;
                    $totEgr['grav10']  += $g10;
                    $totEgr['iva10']   += $i10;
                    $totEgr['grav5']   += $g5;
                    $totEgr['iva5']    += $i5;
                    $totEgr['exentas'] += $ex;
                    $totEgr['totIva']  += $iva;
                    $totEgr['baseImp'] += ($g10 + $g5);
                    $totEgr['total']   += $tot;
                    if ($esCre) $totEgr['credito'] += $tot;
                    else $totEgr['contado'] += $tot;

                    $initFecha($fec);
                    $porFechaMap[$fec]['cantEgr']++;
                    $porFechaMap[$fec]['egrGrav10']  += $g10;
                    $porFechaMap[$fec]['egrGrav5']   += $g5;
                    $porFechaMap[$fec]['egrExentas'] += $ex;
                    $porFechaMap[$fec]['egrIva']     += $iva;
                    $porFechaMap[$fec]['egrTotal']   += $tot;
                }

                $movimientos[] = [
                    'fecha'       => $fec,
                    'diaMes'      => strlen($fec) >= 10 ? (substr($fec, 8, 2) . '-' . substr($fec, 5, 2)) : $fec,
                    'tipo'        => 'EGRESO',
                    'documento'   => $doc,
                    'timbrado'    => (string)($r['nro_timbrado'] ?? ''),
                    'ruc'         => (string)($r['ruc'] ?? ''),
                    'razonSocial' => (string)($r['razon_social'] ?? ''),
                    'condicion'   => $esCre ? 'CRE' : 'CON',
                    'regimen'     => (string)($r['regimen'] ?? ''),
                    'grav10'      => $g10,
                    'iva10'       => $i10,
                    'grav5'       => $g5,
                    'iva5'        => $i5,
                    'exentas'     => $ex,
                    'totIva'      => $iva,
                    'total'       => $tot,
                    'anulada'     => $anulada,
                ];
            }
        }

        foreach ($porMes as $m => &$pm) {
            $pm['diferencia'] = $pm['ingTotal'] - $pm['egrTotal'];
            $pm['saldoIva']   = $pm['ingIva'] - $pm['egrIva'];
        }
        unset($pm);

        ksort($porFechaMap);
        foreach ($porFechaMap as &$pf) {
            $pf['diferencia'] = $pf['ingTotal'] - $pf['egrTotal'];
            $pf['saldoIva']   = $pf['ingIva'] - $pf['egrIva'];
        }
        unset($pf);

        usort($movimientos, function (array $a, array $b): int {
            $cmp = strcmp($a['fecha'], $b['fecha']);
            if ($cmp !== 0) return $cmp;
            return strcmp($a['documento'], $b['documento']);
        });

        $resultadoNeto = $totIng['total'] - $totEgr['total'];
        $saldoIva      = $totIng['totIva'] - $totEgr['totIva'];
        $margenPct     = $totIng['total'] > 0 ? round(($resultadoNeto / $totIng['total']) * 100, 2) : 0.0;

        View::json([
            'status'      => 'success',
            'empresa'     => $empresaInfo,
            'ejercicio'   => $ejercicio,
            'fechaDesde'  => $fechaDesde,
            'fechaHasta'  => $fechaHasta,
            'totales'     => [
                'ingresos'   => $totIng,
                'egresos'    => $totEgr,
                'diferencia' => [
                    'resultadoNeto' => $resultadoNeto,
                    'base10'        => $totIng['grav10'] - $totEgr['grav10'],
                    'iva10'         => $totIng['iva10'] - $totEgr['iva10'],
                    'base5'         => $totIng['grav5'] - $totEgr['grav5'],
                    'iva5'          => $totIng['iva5'] - $totEgr['iva5'],
                    'exentas'       => $totIng['exentas'] - $totEgr['exentas'],
                    'saldoIva'      => $saldoIva,
                    'margenPct'     => $margenPct,
                ],
            ],
            'porMes'      => array_values($porMes),
            'porFecha'    => array_values($porFechaMap),
            'movimientos' => $movimientos,
        ]);
        } catch (\Throwable $e) {
            View::json([
                'status'  => 'error',
                'message' => 'Error en resumen comparativo: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // 8. RESUMEN IRP - IMPUESTO A LA RENTA PERSONAL (CORESIRP)
    // =========================================================================
    public static function pageResumenIrp(): void {
        if (!self::requireAuth(false)) return;
        $codEmpresa = TenantContext::empresa();
        $ejercicio  = EjercicioService::getEjercicio();
        $empData    = self::fetchEmpresaInfo($codEmpresa, $ejercicio);
        $rango      = EjercicioService::getRango($codEmpresa, $ejercicio);

        // Por defecto en IRP se toma el ejercicio anual completo (01/01 al 31/12)
        self::renderFormPage('resumen_irp', [
            'empresa'       => $empData,
            'defFechaDesde' => $rango[0],
            'defFechaHasta' => $rango[1],
            'ejerInicio'    => $rango[0],
            'ejerFinal'     => $rango[1],
            'ejercicio'     => $ejercicio,
        ]);
    }

    public static function apiResumenIrpData(): void {
        if (!self::requireAuth()) return;
        try {
            $codEmpresa      = TenantContext::empresa();
            $ejercicio       = EjercicioService::getEjercicio();
            $rango           = EjercicioService::getRango($codEmpresa, $ejercicio);
            $fechaDesde      = EjercicioService::clamp($_GET['fechaDesde'] ?? $rango[0], $rango, true);
            $fechaHasta      = EjercicioService::clamp($_GET['fechaHasta'] ?? $rango[1], $rango, false);
            $incluirAnulados = isset($_GET['incluirAnulados']) && ($_GET['incluirAnulados'] === 'true' || $_GET['incluirAnulados'] === '1');

            $data = self::procesarReporteResumenIrp($codEmpresa, $ejercicio, $fechaDesde, $fechaHasta, $incluirAnulados);
            View::json($data);
        } catch (\Throwable $e) {
            View::json([
                'status'  => 'error',
                'message' => 'Error al calcular Resumen IRP: ' . $e->getMessage(),
            ], 500);
        }
    }

    public static function printResumenIrp(): void {
        if (!self::requireAuth(false)) return;
        $codEmpresa      = TenantContext::empresa();
        $ejercicio       = EjercicioService::getEjercicio();
        $rango           = EjercicioService::getRango($codEmpresa, $ejercicio);
        $fechaDesde      = EjercicioService::clamp($_GET['fechaDesde'] ?? $rango[0], $rango, true);
        $fechaHasta      = EjercicioService::clamp($_GET['fechaHasta'] ?? $rango[1], $rango, false);
        $incluirAnulados = isset($_GET['incluirAnulados']) && ($_GET['incluirAnulados'] === 'true' || $_GET['incluirAnulados'] === '1');

        $data = self::procesarReporteResumenIrp($codEmpresa, $ejercicio, $fechaDesde, $fechaHasta, $incluirAnulados);

        View::render('resumen_irp_print', [
            'irp'           => $data['irp'],
            'empresa'       => $data['empresa'],
            'ejercicio'     => $data['anioPeriodo'],
            'fechaDesde'    => $fechaDesde,
            'fechaHasta'    => $fechaHasta,
            'fechaDesdeFmt' => self::formatDateDmy($fechaDesde),
            'fechaHastaFmt' => self::formatDateDmy($fechaHasta),
        ]);
    }

    public static function exportarExcelResumenIrp(): void {
        if (!self::requireAuth(false)) return;
        $codEmpresa      = TenantContext::empresa();
        $ejercicio       = EjercicioService::getEjercicio();
        $rango           = EjercicioService::getRango($codEmpresa, $ejercicio);
        $fechaDesde      = EjercicioService::clamp($_GET['fechaDesde'] ?? $rango[0], $rango, true);
        $fechaHasta      = EjercicioService::clamp($_GET['fechaHasta'] ?? $rango[1], $rango, false);
        $incluirAnulados = isset($_GET['incluirAnulados']) && ($_GET['incluirAnulados'] === 'true' || $_GET['incluirAnulados'] === '1');

        $data = self::procesarReporteResumenIrp($codEmpresa, $ejercicio, $fechaDesde, $fechaHasta, $incluirAnulados);
        $irp  = $data['irp'];
        $anio = $data['anioPeriodo'];

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $filename = sprintf('Resumen_IRP_%s_%s.xls', str_replace('-', '', $fechaDesde), str_replace('-', '', $fechaHasta));
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF";
        ?>
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <style>
                body, table { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #000000; }
                th { border: 1px solid #cbd5e1; background-color: #f1f5f9; font-weight: bold; text-align: left; }
                td.txt { border: 1px solid #e2e8f0; mso-number-format:"\@"; }
                td.num { border: 1px solid #e2e8f0; text-align: right; mso-number-format:"\#\,\#\#0"; }
                td.bold { font-weight: bold; }
            </style>
        </head>
        <body>
            <table border="1" cellpadding="4" cellspacing="0">
                <thead>
                    <tr>
                        <th>DESCRIPCIÓN</th>
                        <th>IMPORTE</th>
                        <th>DEDUCIBLES</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="txt">Total de ingresos</td>
                        <td class="num"><?= (int)$irp['ingresosImporte'] ?></td>
                        <td class="num"><?= (int)$irp['ingresosDeducibles'] ?></td>
                    </tr>
                    <tr>
                        <td class="txt">Total de egresos</td>
                        <td class="num"><?= (int)$irp['egresosImporte'] ?></td>
                        <td class="num"><?= (int)$irp['egresosDeducibles'] ?></td>
                    </tr>
                    <tr>
                        <td class="txt">Donaciones, aportes y contribuciones</td>
                        <td class="num"><?= (int)$irp['donacionesImporte'] ?></td>
                        <td class="num"><?= (int)$irp['donacionesDeducibles'] ?></td>
                    </tr>
                    <tr>
                        <td class="txt bold">Saldo al final del periodo</td>
                        <td class="num bold"><?= (int)$irp['saldoImporte'] ?></td>
                        <td class="num bold"><?= (int)$irp['saldoDeducibles'] ?></td>
                    </tr>
                    <tr>
                        <td class="txt bold" colspan="3">Impuesto a la Renta Personal en <?= htmlspecialchars((string)$anio) ?></td>
                    </tr>
                    <tr>
                        <td class="txt">Monto sobre el que se calcula el impuesto a la renta (Aplica si los ingresos deducibles superan Gs. 80.000.000)</td>
                        <td class="num" colspan="2"><?= (int)$irp['montoBaseIrp'] ?></td>
                    </tr>
                    <tr>
                        <td class="txt">Impuesto al 8% (Aplicable hasta Gs. 50.000.000)</td>
                        <td class="num" colspan="2"><?= (int)$irp['impuesto8'] ?></td>
                    </tr>
                    <tr>
                        <td class="txt">Impuesto al 9% (De Gs. 50.000.001 a Gs. 150.000.000)</td>
                        <td class="num" colspan="2"><?= (int)$irp['impuesto9'] ?></td>
                    </tr>
                    <tr>
                        <td class="txt">Impuesto al 10% (Por ingresos mayores a Gs. 150.000.000)</td>
                        <td class="num" colspan="2"><?= (int)$irp['impuesto10'] ?></td>
                    </tr>
                    <tr>
                        <td class="txt bold">Impuesto a pagar</td>
                        <td class="num bold" colspan="2"><?= (int)$irp['impuestoAPagar'] ?></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        exit;
    }

    private static function resolveNombreTipoComprobante(string $rawCode, array $mapTiposComp = []): string {
        $code = strtoupper(trim($rawCode));
        if ($code === '') $code = 'FAC';
        if (!empty($mapTiposComp[$code])) {
            return $mapTiposComp[$code];
        }
        $defaults = [
            'FAC'      => 'FACTURA',
            'FACTURA'  => 'FACTURA',
            'LIQ'      => 'LIQUIDACIÓN DE SALARIO',
            'AUT'      => 'AUTOFACTURA',
            'NCR'      => 'NOTA DE CRÉDITO',
            'NDB'      => 'NOTA DE DÉBITO',
            'BOL'      => 'BOLETA DE VENTA / TICKET',
            'TIC'      => 'TICKET / COMPROBANTE',
            'REC'      => 'RECIBO / COMPROBANTE',
            'DES'      => 'DESPACHO DE IMPORTACIÓN',
            'EXT'      => 'EXTRACTO DE CUENTA / TARJETA',
            'RES'      => 'COMPROBANTE DE RETENCIÓN',
            'COM'      => 'COMPROBANTE DE INGRESO/EGRESO',
            'OTR'      => 'OTROS COMPROBANTES',
        ];
        if (isset($defaults[$code])) {
            return $defaults[$code];
        }
        if (str_contains($code, 'LIQUIDAC')) return 'LIQUIDACIÓN DE SALARIO';
        if (str_contains($code, 'AUTO')) return 'AUTOFACTURA';
        if (str_contains($code, 'CRED') || str_contains($code, 'CRÉD')) return 'NOTA DE CRÉDITO';
        if (str_contains($code, 'DEB') || str_contains($code, 'DÉB')) return 'NOTA DE DÉBITO';
        if (str_contains($code, 'BOLET') || str_contains($code, 'TICK')) return 'BOLETA DE VENTA / TICKET';
        return $code;
    }

    private static function procesarReporteResumenIrp(
        string $codEmpresa, string $ejercicio, string $fechaDesde, string $fechaHasta, bool $incluirAnulados
    ): array {
        $idProp      = TenantContext::idPropietario();
        $empresaInfo = self::fetchEmpresaInfo($codEmpresa, $ejercicio);
        $anioPeriodo = strlen($fechaHasta) >= 4 ? substr($fechaHasta, 0, 4) : $ejercicio;

        // Mapas auxiliares opcionales en PHP (evita problemas de collation en JOIN SQL)
        $mapTiposEgreso = [];
        $mapClasifGasto = [];
        $mapTiposComp   = [];
        try {
            foreach (Database::queryForList("SELECT cod_tipo, descripcion FROM co_tipos_egreso WHERE id_propietario = ?", [$idProp]) as $te) {
                $k1 = trim((string)($te['cod_tipo'] ?? ''));
                $k2 = (string)(int)((float)($te['cod_tipo'] ?? 0));
                $desc = strtoupper(trim((string)($te['descripcion'] ?? '')));
                if ($k1 !== '') $mapTiposEgreso[$k1] = $desc;
                if ($k2 !== '') $mapTiposEgreso[$k2] = $desc;
            }
        } catch (\Throwable $ignored) {}
        try {
            foreach (Database::queryForList("SELECT cod_clasificacion, descripcion FROM co_clasificacion_gasto WHERE id_propietario = ?", [$idProp]) as $cg) {
                $k1 = trim((string)($cg['cod_clasificacion'] ?? ''));
                $k2 = (string)(int)((float)($cg['cod_clasificacion'] ?? 0));
                $desc = strtoupper(trim((string)($cg['descripcion'] ?? '')));
                if ($k1 !== '') $mapClasifGasto[$k1] = $desc;
                if ($k2 !== '') $mapClasifGasto[$k2] = $desc;
            }
        } catch (\Throwable $ignored) {}
        try {
            foreach (Database::queryForList("SELECT tip_comprobante, descripcion FROM co_tipos_comprobantes WHERE id_propietario = ?", [$idProp]) as $tc) {
                $k = strtoupper(trim((string)($tc['tip_comprobante'] ?? '')));
                $d = strtoupper(trim((string)($tc['descripcion'] ?? '')));
                if ($k !== '' && $d !== '') $mapTiposComp[$k] = $d;
            }
        } catch (\Throwable $ignored) {}

        $nombresMes = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $porMes = [];
        for ($m = 1; $m <= 12; $m++) {
            $porMes[$m] = [
                'mes'            => $m,
                'nombreMes'      => $nombresMes[$m],
                'cantIng'        => 0,
                'ingImporte'     => 0,
                'ingDeducible'   => 0,
                'cantEgr'        => 0,
                'egrImporte'     => 0,
                'egrDeducible'   => 0,
                'saldoDeducible' => 0,
            ];
        }

        // 1. Consultar Ingresos del rango
        $sqlIng = "SELECT *, DATE_FORMAT(fec_ingreso, '%Y-%m-%d') AS fec_str
                   FROM co_ingresos
                   WHERE id_propietario = ? AND cod_empresa = ?
                     AND fec_ingreso >= ? AND fec_ingreso <= ?";
        $paramsIng = [$idProp, $codEmpresa, $fechaDesde, $fechaHasta];
        if (!$incluirAnulados) {
            $sqlIng .= " AND (anulada IS NULL OR UPPER(anulada) <> 'S')";
        }
        $rowsIng = Database::queryForList($sqlIng, $paramsIng);

        $ingresosImporte    = 0.0;
        $ingresosDeducibles = 0.0;
        $cantIngresos       = 0;
        $mapCompIng         = [];

        foreach ($rowsIng as $r) {
            $anulada = strcasecmp((string)($r['anulada'] ?? 'N'), 'S') === 0;
            if ($anulada && !$incluirAnulados) continue;
            if ($anulada) continue;

            $tot    = (float)($r['valor_total'] ?? 0);
            $g10    = (float)($r['grav10'] ?? 0);
            $i10    = (float)($r['iva10'] ?? 0);
            $g5     = (float)($r['grav5'] ?? 0);
            $i5     = (float)($r['iva5'] ?? 0);
            $ex     = (float)($r['exentas'] ?? 0);
            $noGrv  = (float)($r['no_gravado'] ?? 0);
            $tipDoc = strtoupper(trim((string)($r['tip_doc'] ?? 'FAC')));
            if ($tipDoc === '') $tipDoc = 'FAC';
            $esLiqSal = ($tipDoc === 'LIQ' || str_contains($tipDoc, 'LIQUIDAC'));

            [$b10, ] = self::extraerBaseImponibleEImpuesto($g10, $i10, 10);
            [$b5,  ] = self::extraerBaseImponibleEImpuesto($g5,  $i5,  5);
            $deducible = $b10 + $b5 + $ex;

            if ($tot > 0 && $deducible <= 0 && !$esLiqSal && $noGrv <= 0) {
                $totIva    = (float)($r['tot_iva'] ?? 0);
                $deducible = $totIva > 0 ? max(0.0, $tot - $totIva) : round($tot / 1.1);
            }

            $sign = ($tipDoc === 'NCR' || str_contains($tipDoc, 'CRED') || str_contains($tipDoc, 'CRÉD')) ? -1.0 : 1.0;

            $ingresosImporte    += ($sign * $tot);
            $ingresosDeducibles += ($sign * $deducible);
            $cantIngresos++;

            $fecStr = (string)($r['fec_str'] ?? $r['fec_ingreso'] ?? '');
            $mesNum = strlen($fecStr) >= 7 ? (int)substr($fecStr, 5, 2) : 0;
            if (isset($porMes[$mesNum])) {
                $porMes[$mesNum]['cantIng']++;
                $porMes[$mesNum]['ingImporte']   += (int)round($sign * $tot);
                $porMes[$mesNum]['ingDeducible'] += (int)round($sign * $deducible);
            }

            if (!isset($mapCompIng[$tipDoc])) {
                $mapCompIng[$tipDoc] = [
                    'operacion' => 'INGRESO',
                    'codigo'    => $tipDoc,
                    'nombre'    => self::resolveNombreTipoComprobante($tipDoc, $mapTiposComp),
                    'cantidad'  => 0,
                    'importe'   => 0.0,
                    'deducible' => 0.0,
                ];
            }
            $mapCompIng[$tipDoc]['cantidad']++;
            $mapCompIng[$tipDoc]['importe']   += ($sign * $tot);
            $mapCompIng[$tipDoc]['deducible'] += ($sign * $deducible);
        }

        // 2. Consultar Egresos del rango
        $sqlEgr = "SELECT *, DATE_FORMAT(fec_egreso, '%Y-%m-%d') AS fec_str
                   FROM co_egresos
                   WHERE id_propietario = ? AND cod_empresa = ?
                     AND fec_egreso >= ? AND fec_egreso <= ?";
        $paramsEgr = [$idProp, $codEmpresa, $fechaDesde, $fechaHasta];
        if (!$incluirAnulados) {
            $sqlEgr .= " AND (estado IS NULL OR UPPER(estado) <> 'I')";
        }
        $rowsEgr = Database::queryForList($sqlEgr, $paramsEgr);

        $egresosImporte       = 0.0;
        $egresosDeducibles    = 0.0;
        $donacionesImporte    = 0.0;
        $donacionesDeducibles = 0.0;
        $cantEgresos          = 0;
        $mapCompEgr           = [];
        $mapClasifResumen     = [];

        foreach ($rowsEgr as $r) {
            $anulada = strcasecmp((string)($r['estado'] ?? 'A'), 'I') === 0;
            if ($anulada) continue;

            $tot = (float)($r['valor_total'] ?? 0);
            $g10 = (float)($r['grav10'] ?? 0);
            $i10 = (float)($r['iva10'] ?? 0);
            $g5  = (float)($r['grav5'] ?? 0);
            $i5  = (float)($r['iva5'] ?? 0);
            $ex  = (float)($r['exentas'] ?? 0);

            [$b10, ] = self::extraerBaseImponibleEImpuesto($g10, $i10, 10);
            [$b5,  ] = self::extraerBaseImponibleEImpuesto($g5,  $i5,  5);
            $deducible = $b10 + $b5 + $ex;

            if ($tot > 0 && $deducible <= 0) {
                $totIva    = (float)($r['tot_iva'] ?? 0);
                $deducible = $totIva > 0 ? max(0.0, $tot - $totIva) : round($tot / 1.1);
            }

            $tipDoc = strtoupper(trim((string)($r['tip_doc'] ?? 'FAC')));
            if ($tipDoc === '') $tipDoc = 'FAC';
            $sign   = ($tipDoc === 'NCR' || str_contains($tipDoc, 'CRED') || str_contains($tipDoc, 'CRÉD')) ? -1.0 : 1.0;

            $codTe  = trim((string)($r['tip_egreso'] ?? ''));
            $codCg  = trim((string)($r['clasificacion_gasto'] ?? ''));
            $descTe = $mapTiposEgreso[$codTe] ?? '';
            $descCg = $mapClasifGasto[$codCg] ?? '';

            $textoClasif = strtoupper(
                $codTe . ' ' . $descTe . ' ' .
                $codCg . ' ' . $descCg . ' ' .
                trim((string)($r['observacion'] ?? ''))
            );

            $esDonacionOAporte = str_contains($textoClasif, 'DONAC')
                              || str_contains($textoClasif, 'APORTE')
                              || str_contains($textoClasif, 'CONTRIBUC');

            if ($esDonacionOAporte) {
                $donacionesImporte    += ($sign * $tot);
                $donacionesDeducibles += ($sign * $deducible);
            } else {
                $egresosImporte    += ($sign * $tot);
                $egresosDeducibles += ($sign * $deducible);
            }
            $cantEgresos++;

            $fecStr = (string)($r['fec_str'] ?? $r['fec_egreso'] ?? '');
            $mesNum = strlen($fecStr) >= 7 ? (int)substr($fecStr, 5, 2) : 0;
            if (isset($porMes[$mesNum])) {
                $porMes[$mesNum]['cantEgr']++;
                $porMes[$mesNum]['egrImporte']   += (int)round($sign * $tot);
                $porMes[$mesNum]['egrDeducible'] += (int)round($sign * $deducible);
            }

            if (!isset($mapCompEgr[$tipDoc])) {
                $mapCompEgr[$tipDoc] = [
                    'operacion' => 'EGRESO',
                    'codigo'    => $tipDoc,
                    'nombre'    => self::resolveNombreTipoComprobante($tipDoc, $mapTiposComp),
                    'cantidad'  => 0,
                    'importe'   => 0.0,
                    'deducible' => 0.0,
                ];
            }
            $mapCompEgr[$tipDoc]['cantidad']++;
            $mapCompEgr[$tipDoc]['importe']   += ($sign * $tot);
            $mapCompEgr[$tipDoc]['deducible'] += ($sign * $deducible);

            $catLabel = $descCg !== '' ? $descCg : ($descTe !== '' ? $descTe : ($esDonacionOAporte ? 'DONACIONES Y APORTES' : 'GASTOS DE LA ACTIVIDAD'));
            $catKey   = $catLabel;
            if (!isset($mapClasifResumen[$catKey])) {
                $mapClasifResumen[$catKey] = [
                    'nombre'    => $catLabel,
                    'cantidad'  => 0,
                    'importe'   => 0.0,
                    'deducible' => 0.0,
                ];
            }
            $mapClasifResumen[$catKey]['cantidad']++;
            $mapClasifResumen[$catKey]['importe']   += ($sign * $tot);
            $mapClasifResumen[$catKey]['deducible'] += ($sign * $deducible);
        }

        foreach ($porMes as $m => &$pm) {
            $pm['saldoDeducible'] = $pm['ingDeducible'] - $pm['egrDeducible'];
        }
        unset($pm);

        $ingImpRound  = (int)round($ingresosImporte);
        $ingDedRound  = (int)round($ingresosDeducibles);
        $egrImpRound  = (int)round($egresosImporte);
        $egrDedRound  = (int)round($egresosDeducibles);
        $donImpRound  = (int)round($donacionesImporte);
        $donDedRound  = (int)round($donacionesDeducibles);

        $totEgrConDonImp = $egrImpRound + $donImpRound;
        $totEgrConDonDed = $egrDedRound + $donDedRound;

        $porTipoComprobanteIng = array_values(array_map(function (array $it) use ($ingImpRound, $ingDedRound): array {
            $imp = (int)round($it['importe']);
            $ded = (int)round($it['deducible']);
            return [
                'operacion'    => 'INGRESO',
                'codigo'       => $it['codigo'],
                'nombre'       => $it['nombre'],
                'cantidad'     => $it['cantidad'],
                'importe'      => $imp,
                'deducible'    => $ded,
                'pctImporte'   => $ingImpRound > 0 ? round(($imp / $ingImpRound) * 100, 1) : 0.0,
                'pctDeducible' => $ingDedRound > 0 ? round(($ded / $ingDedRound) * 100, 1) : 0.0,
            ];
        }, $mapCompIng));
        usort($porTipoComprobanteIng, fn($a, $b) => $b['importe'] <=> $a['importe']);

        $porTipoComprobanteEgr = array_values(array_map(function (array $it) use ($totEgrConDonImp, $totEgrConDonDed): array {
            $imp = (int)round($it['importe']);
            $ded = (int)round($it['deducible']);
            return [
                'operacion'    => 'EGRESO',
                'codigo'       => $it['codigo'],
                'nombre'       => $it['nombre'],
                'cantidad'     => $it['cantidad'],
                'importe'      => $imp,
                'deducible'    => $ded,
                'pctImporte'   => $totEgrConDonImp > 0 ? round(($imp / $totEgrConDonImp) * 100, 1) : 0.0,
                'pctDeducible' => $totEgrConDonDed > 0 ? round(($ded / $totEgrConDonDed) * 100, 1) : 0.0,
            ];
        }, $mapCompEgr));
        usort($porTipoComprobanteEgr, fn($a, $b) => $b['importe'] <=> $a['importe']);

        $porClasificacionEgreso = array_values(array_map(function (array $it) use ($totEgrConDonImp, $totEgrConDonDed): array {
            $imp = (int)round($it['importe']);
            $ded = (int)round($it['deducible']);
            return [
                'nombre'       => $it['nombre'],
                'cantidad'     => $it['cantidad'],
                'importe'      => $imp,
                'deducible'    => $ded,
                'pctImporte'   => $totEgrConDonImp > 0 ? round(($imp / $totEgrConDonImp) * 100, 1) : 0.0,
                'pctDeducible' => $totEgrConDonDed > 0 ? round(($ded / $totEgrConDonDed) * 100, 1) : 0.0,
            ];
        }, $mapClasifResumen));
        usort($porClasificacionEgreso, fn($a, $b) => $b['deducible'] <=> $a['deducible']);

        $saldoImporte    = $ingImpRound - $egrImpRound - $donImpRound;
        $saldoDeducibles = $ingDedRound - $egrDedRound - $donDedRound;

        // Cálculo de IRP por tramos progresivos (Ley 6380/19 - Renta de Servicios Personales):
        // Base imponible: Saldo al final del periodo (Deducibles) cuando es positivo.
        $montoBaseIrp = max(0, $saldoDeducibles);

        // Tramo 1: Hasta Gs. 50.000.000 -> 8%
        $baseTramo8 = min($montoBaseIrp, 50000000);
        $impuesto8  = (int)round($baseTramo8 * 0.08);

        // Tramo 2: De Gs. 50.000.001 a Gs. 150.000.000 -> 9%
        $baseTramo9 = max(0, min($montoBaseIrp, 150000000) - 50000000);
        $impuesto9  = (int)round($baseTramo9 * 0.09);

        // Tramo 3: Más de Gs. 150.000.000 -> 10%
        $baseTramo10 = max(0, $montoBaseIrp - 150000000);
        $impuesto10  = (int)round($baseTramo10 * 0.10);

        $impuestoAPagar = $impuesto8 + $impuesto9 + $impuesto10;

        return [
            'status'                 => 'success',
            'empresa'                => $empresaInfo,
            'ejercicio'              => $ejercicio,
            'anioPeriodo'            => $anioPeriodo,
            'fechaDesde'             => $fechaDesde,
            'fechaHasta'             => $fechaHasta,
            'fechaDesdeFmt'          => self::formatDateDmy($fechaDesde),
            'fechaHastaFmt'          => self::formatDateDmy($fechaHasta),
            'porTipoComprobanteIng'  => $porTipoComprobanteIng,
            'porTipoComprobanteEgr'  => $porTipoComprobanteEgr,
            'porClasificacionEgreso' => $porClasificacionEgreso,
            'porMes'                 => array_values($porMes),
            'irp'                    => [
                'cantIngresos'         => $cantIngresos,
                'cantEgresos'          => $cantEgresos,
                'ingresosImporte'      => $ingImpRound,
                'ingresosDeducibles'   => $ingDedRound,
                'egresosImporte'       => $egrImpRound,
                'egresosDeducibles'    => $egrDedRound,
                'donacionesImporte'    => $donImpRound,
                'donacionesDeducibles' => $donDedRound,
                'saldoImporte'         => $saldoImporte,
                'saldoDeducibles'      => $saldoDeducibles,
                'superaRangoNoIncidido'=> ($ingDedRound > 80000000),
                'montoBaseIrp'         => $montoBaseIrp,
                'impuesto8'            => $impuesto8,
                'impuesto9'            => $impuesto9,
                'impuesto10'           => $impuesto10,
                'impuestoAPagar'       => $impuestoAPagar,
            ],
        ];
    }

    // =========================================================================
    // 9. FORMULARIO N° 515 - IMPUESTO A LA RENTA PERSONAL (VERSIÓN 1 - DNIT)
    // =========================================================================
    private static function ensureFormulario515Table(): void {
        try {
            Database::execute("
                CREATE TABLE IF NOT EXISTS `co_formulario_515` (
                  `id_propietario` INT NOT NULL DEFAULT 1,
                  `cod_empresa` VARCHAR(10) NOT NULL,
                  `anio` INT NOT NULL,
                  `tipo_declaracion` VARCHAR(1) DEFAULT 'O',
                  `num_orden` VARCHAR(30) DEFAULT NULL,
                  `num_orden_rect` VARCHAR(30) DEFAULT NULL,
                  `caracter_cese` VARCHAR(1) DEFAULT 'N',
                  `modo_egresos` VARCHAR(15) DEFAULT 'SIN_IVA',
                  `c10` BIGINT DEFAULT 0, `c12` BIGINT DEFAULT 0,
                  `c11` BIGINT DEFAULT 0, `c13` BIGINT DEFAULT 0,
                  `c14` BIGINT DEFAULT 0,
                  `c15` BIGINT DEFAULT 0, `c16` BIGINT DEFAULT 0, `c17` BIGINT DEFAULT 0,
                  `c18` BIGINT DEFAULT 0, `c19` BIGINT DEFAULT 0, `c20` BIGINT DEFAULT 0,
                  `c21` BIGINT DEFAULT 0, `c22` BIGINT DEFAULT 0, `c23` BIGINT DEFAULT 0, `c24` BIGINT DEFAULT 0,
                  `c25` BIGINT DEFAULT 0, `c26` BIGINT DEFAULT 0, `c27` BIGINT DEFAULT 0,
                  `c28` BIGINT DEFAULT 0, `c29` BIGINT DEFAULT 0, `c30` BIGINT DEFAULT 0,
                  `c31` BIGINT DEFAULT 0, `c32` BIGINT DEFAULT 0,
                  `c33` BIGINT DEFAULT 0, `c34` BIGINT DEFAULT 0, `c35` BIGINT DEFAULT 0,
                  `c36` BIGINT DEFAULT 0, `c37` BIGINT DEFAULT 0, `c38` BIGINT DEFAULT 0, `c39` BIGINT DEFAULT 0,
                  `c45` BIGINT DEFAULT 0, `c46` BIGINT DEFAULT 0, `c47` BIGINT DEFAULT 0, `c48` BIGINT DEFAULT 0,
                  `c40` BIGINT DEFAULT 0, `c41` BIGINT DEFAULT 0, `c42` BIGINT DEFAULT 0, `c49` BIGINT DEFAULT 0,
                  `c43` BIGINT DEFAULT 0, `c50` BIGINT DEFAULT 0, `c44` BIGINT DEFAULT 0, `c51` BIGINT DEFAULT 0,
                  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id_propietario`, `cod_empresa`, `anio`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ", true);
        } catch (\Throwable $ignored) {}
    }

    public static function pageFormulario515(): void {
        if (!self::requireAuth(false)) return;
        self::ensureFormulario515Table();
        $idProp     = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $ejercicio  = (int)EjercicioService::getEjercicio();
        $defAnio    = isset($_GET['anio']) && (int)$_GET['anio'] >= 2018 ? (int)$_GET['anio'] : ($ejercicio > 0 ? $ejercicio : (int)date('Y'));

        if (!isset($_GET['anio'])) {
            try {
                $bestYear = Database::queryForMap(
                    "SELECT anio, SUM(cnt) AS total_comp FROM (
                        SELECT YEAR(fec_egreso) AS anio, COUNT(*) AS cnt
                        FROM co_egresos
                        WHERE id_propietario = ? AND cod_empresa = ? AND COALESCE(estado, 'A') <> 'I' AND fec_egreso IS NOT NULL
                        GROUP BY YEAR(fec_egreso)
                        UNION ALL
                        SELECT YEAR(fec_ingreso) AS anio, COUNT(*) AS cnt
                        FROM co_ingresos
                        WHERE id_propietario = ? AND cod_empresa = ? AND COALESCE(anulada, 'N') <> 'S' AND fec_ingreso IS NOT NULL
                        GROUP BY YEAR(fec_ingreso)
                    ) t
                    WHERE anio IS NOT NULL
                    GROUP BY anio
                    ORDER BY total_comp DESC, anio DESC
                    LIMIT 1",
                    [$idProp, $codEmpresa, $idProp, $codEmpresa]
                );
                if ($bestYear && !empty($bestYear['anio'])) {
                    $defAnio = (int)$bestYear['anio'];
                }
            } catch (\Throwable $ignored) {}
        }

        self::renderFormPage('formulario_515', [
            'empresa'     => self::fetchForm120EmpresaInfo($codEmpresa),
            'nomForma'    => 'COF51500',
            'tituloForma' => 'Formulario N° 515 - Impuesto a la Renta Personal (Versión 1)',
            'defAnio'     => $defAnio,
        ]);
    }

    public static function apiFormulario515Obtener(): void {
        if (!self::requireAuth()) return;
        try {
            self::ensureFormulario515Table();
            $idProp      = TenantContext::idPropietario();
            $codEmpresa  = TenantContext::empresa();
            $anio        = (int)($_GET['anio'] ?? date('Y'));
            $modoEgresos = strtoupper(trim((string)($_GET['modoEgresos'] ?? 'SIN_IVA')));

            $autoCalc = self::calcularFormulario515Auto($idProp, $codEmpresa, $anio, $modoEgresos);

            $rows = Database::queryForListUpper(
                "SELECT * FROM co_formulario_515 WHERE id_propietario = ? AND cod_empresa = ? AND anio = ? LIMIT 1",
                [$idProp, $codEmpresa, $anio]
            );
            if (!empty($rows) && !isset($_GET['forzarRecalculo'])) {
                $saved    = $rows[0];
                $autoData = $autoCalc['data'];
                foreach ($autoData as $k => $v) {
                    if (!array_key_exists($k, $saved) || $saved[$k] === null) {
                        $saved[$k] = $v;
                    }
                }
                $savedMov = (int)($saved['C14'] ?? 0) + (int)($saved['C24'] ?? 0) + (int)($saved['C41'] ?? 0);
                $autoMov  = (int)($autoData['C14'] ?? 0) + (int)($autoData['C24'] ?? 0) + (int)($autoData['C41'] ?? 0);

                View::json([
                    'status'          => ($savedMov === 0 && $autoMov > 0) ? 'calculated' : 'found',
                    'data'            => ($savedMov === 0 && $autoMov > 0) ? $autoData : $saved,
                    'calculadoLibros' => $autoData,
                    'resumenLibros'   => $autoCalc['resumenLibros'],
                    'aniosConDatos'   => $autoCalc['aniosConDatos'],
                    'empresa'         => self::fetchForm120EmpresaInfo($codEmpresa),
                ]);
                return;
            }
            View::json($autoCalc);
        } catch (\Throwable $e) {
            View::json([
                'status'  => 'error',
                'message' => 'Error al obtener Formulario 515: ' . $e->getMessage(),
            ], 500);
        }
    }

    public static function apiFormulario515Calcular(): void {
        if (!self::requireAuth()) return;
        try {
            self::ensureFormulario515Table();
            $idProp      = TenantContext::idPropietario();
            $codEmpresa  = TenantContext::empresa();
            $anio        = (int)($_GET['anio'] ?? date('Y'));
            $modoEgresos = strtoupper(trim((string)($_GET['modoEgresos'] ?? 'SIN_IVA')));
            View::json(self::calcularFormulario515Auto($idProp, $codEmpresa, $anio, $modoEgresos));
        } catch (\Throwable $e) {
            View::json([
                'status'  => 'error',
                'message' => 'Error al calcular Formulario 515: ' . $e->getMessage(),
            ], 500);
        }
    }

    private static function calcularFormulario515Auto(int $idProp, string $codEmpresa, int $anio, string $modoEgresos = 'SIN_IVA'): array {
        $startDateStr = sprintf('%04d-01-01', $anio);
        $endDateStr   = sprintf('%04d-12-31', $anio);
        try {
            MovimientosContablesController::ensureRetencionesSufridasTables();
        } catch (\Throwable $ignored) {}

        // 0. Ejercicios con movimientos cargados para barra de acceso rápido
        $aniosConDatos = [];
        try {
            $aniosConDatos = Database::queryForList(
                "SELECT anio,
                        SUM(cant_egresos) AS cant_egresos,
                        SUM(total_egresos) AS total_egresos,
                        SUM(cant_ingresos) AS cant_ingresos,
                        SUM(total_ingresos) AS total_ingresos
                 FROM (
                    SELECT YEAR(fec_egreso) AS anio,
                           COUNT(*) AS cant_egresos, COALESCE(SUM(valor_total), 0) AS total_egresos,
                           0 AS cant_ingresos, 0 AS total_ingresos
                    FROM co_egresos
                    WHERE id_propietario = ? AND cod_empresa = ? AND COALESCE(estado, 'A') <> 'I' AND fec_egreso IS NOT NULL
                    GROUP BY YEAR(fec_egreso)
                    UNION ALL
                    SELECT YEAR(fec_ingreso) AS anio,
                           0 AS cant_egresos, 0 AS total_egresos,
                           COUNT(*) AS cant_ingresos, COALESCE(SUM(valor_total), 0) AS total_ingresos
                    FROM co_ingresos
                    WHERE id_propietario = ? AND cod_empresa = ? AND COALESCE(anulada, 'N') <> 'S' AND fec_ingreso IS NOT NULL
                    GROUP BY YEAR(fec_ingreso)
                 ) y
                 WHERE anio IS NOT NULL
                 GROUP BY anio
                 ORDER BY anio DESC",
                [$idProp, $codEmpresa, $idProp, $codEmpresa]
            );
        } catch (\Throwable $ignored) {}

        // 1. Ingresos del ejercicio fiscal (Rubro 1)
        $rowsIng = Database::queryForList(
            "SELECT * FROM co_ingresos
             WHERE id_propietario = ? AND cod_empresa = ?
               AND fec_ingreso >= ? AND fec_ingreso <= ?
             ORDER BY fec_ingreso ASC, id_ingreso ASC",
            [$idProp, $codEmpresa, $startDateStr, $endDateStr]
        );

        $cantIngresos   = 0;
        $cantFacturas   = 0;
        $cantLiqSalario = 0;
        $ingBrutoTotal  = 0.0;
        $ingExonerados  = 0.0;
        $ingGravados    = 0.0;
        $mapTiposComp   = [];
        try {
            foreach (Database::queryForList("SELECT cod_tipo, descripcion FROM co_tipos_comprobante WHERE id_propietario = ?", [$idProp]) as $tc) {
                $k = strtoupper(trim((string)($tc['cod_tipo'] ?? '')));
                $d = trim((string)($tc['descripcion'] ?? ''));
                if ($k !== '' && $d !== '') $mapTiposComp[$k] = $d;
            }
        } catch (\Throwable $ignored) {}
        $mapCompIng = [];
        $mapCompEgr = [];

        foreach ($rowsIng as $ri) {
            if (strcasecmp((string)($ri['anulada'] ?? 'N'), 'S') === 0) continue;
            if (strcasecmp((string)($ri['inutilizado'] ?? 'N'), 'S') === 0) continue;
            if (strcasecmp((string)($ri['estado'] ?? 'A'), 'I') === 0) continue;

            $rawReg = strtoupper(trim((string)($ri['regimen'] ?? $ri['cod_regimen'] ?? '1')));
            $codReg = (int)($ri['cod_regimen'] ?? 1);
            // Excluir ingresos que sean exclusivamente IVA (3), IVA e IRE (5) o Ganancias de Capital (4 - Form. 516)
            if ($rawReg === '3' || $rawReg === 'IVA' || $codReg === 3 || $rawReg === '4' || str_contains($rawReg, 'CAPITAL') || $codReg === 4 || $rawReg === '5' || str_contains($rawReg, 'IRE') || $codReg === 5) {
                continue;
            }
            $cantIngresos++;

            $tipDoc = strtoupper(trim((string)($ri['tip_doc'] ?? 'FAC')));
            if ($tipDoc === '') $tipDoc = 'FAC';
            $esLiqSal = ($tipDoc === 'LIQ' || str_contains($tipDoc, 'LIQUIDAC'));
            if ($esLiqSal) {
                $cantLiqSalario++;
            } else {
                $cantFacturas++;
            }

            $sign  = ($tipDoc === 'NCR' || str_contains($tipDoc, 'CRED') || str_contains($tipDoc, 'CRÉD')) ? -1.0 : 1.0;
            $tot   = max(0.0, (float)($ri['valor_total'] ?? 0));
            $g10   = max(0.0, (float)($ri['grav10'] ?? 0));
            $i10   = max(0.0, (float)($ri['iva10'] ?? 0));
            $g5    = max(0.0, (float)($ri['grav5'] ?? 0));
            $i5    = max(0.0, (float)($ri['iva5'] ?? 0));
            $ex    = max(0.0, (float)($ri['exentas'] ?? 0));
            $noGrv = max(0.0, (float)($ri['no_gravado'] ?? 0));

            [$b10, ] = self::extraerBaseImponibleEImpuesto($g10, $i10, 10);
            [$b5,  ] = self::extraerBaseImponibleEImpuesto($g5,  $i5,  5);
            $gravIrp = $b10 + $b5 + $ex;

            if ($tot > 0 && $gravIrp <= 0 && !$esLiqSal && $noGrv <= 0) {
                $totIva  = max(0.0, (float)($ri['tot_iva'] ?? 0));
                $gravIrp = $totIva > 0 ? max(0.0, $tot - $totIva) : round($tot / 1.1);
            }

            $ingBrutoTotal += $sign * $tot;
            $ingExonerados += $sign * $noGrv;
            $ingGravados   += $sign * $gravIrp;

            if (!isset($mapCompIng[$tipDoc])) {
                $mapCompIng[$tipDoc] = [
                    'operacion' => 'INGRESO',
                    'codigo'    => $tipDoc,
                    'nombre'    => self::resolveNombreTipoComprobante($tipDoc, $mapTiposComp),
                    'cantidad'  => 0,
                    'importe'   => 0.0,
                    'deducible' => 0.0,
                ];
            }
            $mapCompIng[$tipDoc]['cantidad']++;
            $mapCompIng[$tipDoc]['importe']   += $sign * $tot;
            $mapCompIng[$tipDoc]['deducible'] += $sign * $gravIrp;
        }

        $c10 = max(0, (int)round($ingExonerados));
        $c12 = max(0, (int)round($ingGravados));
        $c11 = 0;
        $c13 = 0;
        $c14 = $c12 + $c13;

        // 2. Mapas de Tipos de Egreso y Clasificación de Gastos
        $mapTiposEgreso = [];
        $mapClasifGasto = [];
        try {
            foreach (Database::queryForList("SELECT cod_tipo, descripcion FROM co_tipos_egreso WHERE id_propietario = ?", [$idProp]) as $te) {
                $k1 = trim((string)($te['cod_tipo'] ?? ''));
                $k2 = (string)(int)((float)($te['cod_tipo'] ?? 0));
                $desc = strtoupper(trim((string)($te['descripcion'] ?? '')));
                if ($k1 !== '') $mapTiposEgreso[$k1] = $desc;
                if ($k2 !== '') $mapTiposEgreso[$k2] = $desc;
            }
        } catch (\Throwable $ignored) {}
        try {
            foreach (Database::queryForList("SELECT cod_clasificacion, descripcion FROM co_clasificacion_gasto WHERE id_propietario = ?", [$idProp]) as $cg) {
                $k1 = trim((string)($cg['cod_clasificacion'] ?? ''));
                $k2 = (string)(int)((float)($cg['cod_clasificacion'] ?? 0));
                $desc = strtoupper(trim((string)($cg['descripcion'] ?? '')));
                if ($k1 !== '') $mapClasifGasto[$k1] = $desc;
                if ($k2 !== '') $mapClasifGasto[$k2] = $desc;
            }
        } catch (\Throwable $ignored) {}

        // 3. Egresos Deducibles del Ejercicio Fiscal (Rubro 2)
        $rowsEgr = Database::queryForList(
            "SELECT * FROM co_egresos
             WHERE id_propietario = ? AND cod_empresa = ?
               AND fec_egreso >= ? AND fec_egreso <= ?
             ORDER BY fec_egreso ASC, id_egreso ASC",
            [$idProp, $codEmpresa, $startDateStr, $endDateStr]
        );

        $cantEgresos       = 0;
        $egrTotalSinIva    = 0.0;
        $egrTotalConIva    = 0.0;
        $sumC15 = 0.0; // Actividad gravada
        $sumC16 = 0.0; // Personales y familiares en el país
        $sumC17 = 0.0; // Personales en el exterior (salud y educación)
        $sumC18 = 0.0; // Autovehículo cada 3 años
        $sumC19 = 0.0; // Aporte patronal seguridad social
        $sumC20 = 0.0; // Aporte sistema privado seguridad social
        $sumC21 = 0.0; // Donaciones, cuotas, diezmos (tope 5% de C14)
        $sumC23 = 0.0; // Adquisición al contado de inmueble para vivienda cada 5 años

        $porIncisoResumen = [
            '15' => ['casilla' => '15', 'inciso' => 'a', 'nombre' => 'Egresos directamente relacionados con la actividad gravada', 'cantidad' => 0, 'sinIva' => 0.0, 'conIva' => 0.0],
            '16' => ['casilla' => '16', 'inciso' => 'b', 'nombre' => 'Egresos personales y a favor de familiares a cargo en el país', 'cantidad' => 0, 'sinIva' => 0.0, 'conIva' => 0.0],
            '17' => ['casilla' => '17', 'inciso' => 'c', 'nombre' => 'Egresos personales y familiares en el exterior (salud/educación)', 'cantidad' => 0, 'sinIva' => 0.0, 'conIva' => 0.0],
            '18' => ['casilla' => '18', 'inciso' => 'd', 'nombre' => 'Adquisición de un autovehículo cada 3 (tres) años', 'cantidad' => 0, 'sinIva' => 0.0, 'conIva' => 0.0],
            '19' => ['casilla' => '19', 'inciso' => 'e', 'nombre' => 'Aporte patronal al Sistema de Seguridad Social', 'cantidad' => 0, 'sinIva' => 0.0, 'conIva' => 0.0],
            '20' => ['casilla' => '20', 'inciso' => 'f', 'nombre' => 'Aporte a un Sistema privado de seguridad social independiente', 'cantidad' => 0, 'sinIva' => 0.0, 'conIva' => 0.0],
            '21' => ['casilla' => '21', 'inciso' => 'g', 'nombre' => 'Cuotas, aportes, diezmos y donaciones (hasta 5% renta bruta)', 'cantidad' => 0, 'sinIva' => 0.0, 'conIva' => 0.0],
            '23' => ['casilla' => '23', 'inciso' => 'i', 'nombre' => 'Adquisición al contado de un inmueble para la vivienda cada 5 años', 'cantidad' => 0, 'sinIva' => 0.0, 'conIva' => 0.0],
        ];
        $proveedoresResumen = [];

        foreach ($rowsEgr as $re) {
            if (strcasecmp((string)($re['estado'] ?? 'A'), 'I') === 0) continue;
            if (strcasecmp((string)($re['anulada'] ?? 'N'), 'S') === 0) continue;

            $rawRegE = strtoupper(trim((string)($re['regimen'] ?? $re['cod_regimen'] ?? '1')));
            $codRegE = (int)($re['cod_regimen'] ?? 1);
            // Excluir comprobantes que sean exclusivamente IVA (3), IVA e IRE (5) o Ganancias de Capital (4)
            if ($rawRegE === '3' || $rawRegE === 'IVA' || $codRegE === 3 || $rawRegE === '4' || str_contains($rawRegE, 'CAPITAL') || $codRegE === 4 || $rawRegE === '5' || str_contains($rawRegE, 'IRE') || $codRegE === 5) {
                continue;
            }
            $cantEgresos++;

            $tipDocE = strtoupper(trim((string)($re['tip_doc'] ?? 'FAC')));
            $sign    = ($tipDocE === 'NCR' || str_contains($tipDocE, 'CRED') || str_contains($tipDocE, 'CRÉD')) ? -1.0 : 1.0;

            $tot = max(0.0, (float)($re['valor_total'] ?? 0));
            $g10 = max(0.0, (float)($re['grav10'] ?? 0));
            $i10 = max(0.0, (float)($re['iva10'] ?? 0));
            $g5  = max(0.0, (float)($re['grav5'] ?? 0));
            $i5  = max(0.0, (float)($re['iva5'] ?? 0));
            $ex  = max(0.0, (float)($re['exentas'] ?? 0));

            [$b10, ] = self::extraerBaseImponibleEImpuesto($g10, $i10, 10);
            [$b5,  ] = self::extraerBaseImponibleEImpuesto($g5,  $i5,  5);
            $sinIva  = $b10 + $b5 + $ex;

            if ($tot > 0 && $sinIva <= 0) {
                $totIva = max(0.0, (float)($re['tot_iva'] ?? 0));
                $sinIva = $totIva > 0 ? max(0.0, $tot - $totIva) : round($tot / 1.1);
            }

            $egrTotalSinIva += $sign * $sinIva;
            $egrTotalConIva += $sign * $tot;

            $codTe  = trim((string)($re['tip_egreso'] ?? ''));
            $codCg  = trim((string)($re['clasificacion_gasto'] ?? '1'));
            if (is_numeric($codCg)) {
                $codCg = (string)(int)round((float)$codCg);
            }
            $descTe = $mapTiposEgreso[$codTe] ?? '';
            $descCg = $mapClasifGasto[$codCg] ?? '';
            $obs    = strtoupper(trim((string)($re['observacion'] ?? '')));
            $texto  = strtoupper($codTe . ' ' . $descTe . ' ' . $codCg . ' ' . $descCg . ' ' . $obs);

            $esPersonal = ($codCg === '3' || $codCg === '4' || str_contains($descCg, 'PERSONAL') || str_contains($descCg, 'FAMILIAR'));
            $esSoloIrp  = ($rawRegE === '2' || $rawRegE === 'IRP' || $codRegE === 2);

            if ($modoEgresos === 'CON_IVA') {
                $montoElegido = $tot;
            } elseif ($modoEgresos === 'MIXTO') {
                $montoElegido = ($esSoloIrp || $esPersonal) ? $tot : $sinIva;
            } else {
                $montoElegido = $sinIva;
            }

            // Determinar casilla de Rubro 2 según tipo de egreso y clasificación
            if (str_contains($texto, 'DONAC') || str_contains($texto, 'DIEZMO')) {
                $casTarget = '21';
                $sumC21 += $sign * $montoElegido;
            } elseif (str_contains($texto, 'PATRONAL')) {
                $casTarget = '19';
                $sumC19 += $sign * $montoElegido;
            } elseif (str_contains($texto, 'PRIVADO') && str_contains($texto, 'SEGURIDAD')) {
                $casTarget = '20';
                $sumC20 += $sign * $montoElegido;
            } elseif (str_contains($texto, 'AUTOVEH') || str_contains($texto, 'RODADO')) {
                $casTarget = '18';
                $sumC18 += $sign * $montoElegido;
            } elseif (str_contains($texto, 'INMUEBLE') && str_contains($texto, 'VIVIENDA') && str_contains($texto, 'CONTADO')) {
                $casTarget = '23';
                $sumC23 += $sign * $montoElegido;
            } elseif (str_contains($texto, 'EXTERIOR')) {
                $casTarget = '17';
                $sumC17 += $sign * $montoElegido;
            } elseif ($esPersonal && $modoEgresos === 'SEPARAR_PERSONALES') {
                $casTarget = '16';
                $sumC16 += $sign * $montoElegido;
            } else {
                // Por defecto (y tal como declara el contador en el ejemplo adjunto), va a Casilla 15
                $casTarget = '15';
                $sumC15 += $sign * $montoElegido;
            }

            $porIncisoResumen[$casTarget]['cantidad']++;
            $porIncisoResumen[$casTarget]['sinIva'] += $sign * $sinIva;
            $porIncisoResumen[$casTarget]['conIva'] += $sign * $tot;

            $rucP = trim((string)($re['ruc_proveedor'] ?? 'S/RUC'));
            $nomP = trim((string)($re['nom_proveedor'] ?? 'PROVEEDOR'));
            $pKey = $rucP ?: $nomP;
            if (!isset($proveedoresResumen[$pKey])) {
                $proveedoresResumen[$pKey] = [
                    'ruc'      => $rucP,
                    'nombre'   => $nomP,
                    'cantidad' => 0,
                    'sinIva'   => 0.0,
                    'conIva'   => 0.0,
                ];
            }
            $proveedoresResumen[$pKey]['cantidad']++;
            $proveedoresResumen[$pKey]['sinIva'] += $sign * $sinIva;
            $proveedoresResumen[$pKey]['conIva'] += $sign * $tot;

            if ($tipDocE === '') $tipDocE = 'FAC';
            if (!isset($mapCompEgr[$tipDocE])) {
                $mapCompEgr[$tipDocE] = [
                    'operacion' => 'EGRESO',
                    'codigo'    => $tipDocE,
                    'nombre'    => self::resolveNombreTipoComprobante($tipDocE, $mapTiposComp),
                    'cantidad'  => 0,
                    'importe'   => 0.0,
                    'deducible' => 0.0,
                ];
            }
            $mapCompEgr[$tipDocE]['cantidad']++;
            $mapCompEgr[$tipDocE]['importe']   += $sign * $tot;
            $mapCompEgr[$tipDocE]['deducible'] += $sign * $sinIva;
        }

        $ingBrutoRound   = (int)round($ingBrutoTotal);
        $ingGravRound    = max(0, (int)round($ingGravados));
        $egrConIvaRound  = (int)round($egrTotalConIva);
        $egrSinIvaRound  = (int)round($egrTotalSinIva);

        $porTipoComprobanteIng = array_values(array_map(function (array $it) use ($ingBrutoRound, $ingGravRound): array {
            $imp = (int)round($it['importe']);
            $ded = (int)round($it['deducible']);
            return [
                'operacion'    => 'INGRESO',
                'codigo'       => $it['codigo'],
                'nombre'       => $it['nombre'],
                'cantidad'     => $it['cantidad'],
                'importe'      => $imp,
                'deducible'    => $ded,
                'pctImporte'   => $ingBrutoRound > 0 ? round(($imp / $ingBrutoRound) * 100, 1) : 0.0,
                'pctDeducible' => $ingGravRound > 0 ? round(($ded / $ingGravRound) * 100, 1) : 0.0,
            ];
        }, $mapCompIng));
        usort($porTipoComprobanteIng, fn($a, $b) => $b['importe'] <=> $a['importe']);

        $porTipoComprobanteEgr = array_values(array_map(function (array $it) use ($egrConIvaRound, $egrSinIvaRound): array {
            $imp = (int)round($it['importe']);
            $ded = (int)round($it['deducible']);
            return [
                'operacion'    => 'EGRESO',
                'codigo'       => $it['codigo'],
                'nombre'       => $it['nombre'],
                'cantidad'     => $it['cantidad'],
                'importe'      => $imp,
                'deducible'    => $ded,
                'pctImporte'   => $egrConIvaRound > 0 ? round(($imp / $egrConIvaRound) * 100, 1) : 0.0,
                'pctDeducible' => $egrSinIvaRound > 0 ? round(($ded / $egrSinIvaRound) * 100, 1) : 0.0,
            ];
        }, $mapCompEgr));
        usort($porTipoComprobanteEgr, fn($a, $b) => $b['importe'] <=> $a['importe']);

        $topProveedores = array_values($proveedoresResumen);
        usort($topProveedores, fn($a, $b) => $b['sinIva'] <=> $a['sinIva']);
        $topProveedores = array_slice($topProveedores, 0, 15);

        // Rubro 2 (Casillas 15 a 24)
        $c15 = max(0, (int)round($sumC15));
        $c16 = max(0, (int)round($sumC16));
        $c17 = max(0, (int)round($sumC17));
        $c18 = max(0, (int)round($sumC18));
        $c19 = max(0, (int)round($sumC19));
        $c20 = max(0, (int)round($sumC20));
        $topeDonaciones = $c14 > 0 ? (int)round($c14 * 0.05) : (int)round($sumC21);
        $c21 = max(0, min((int)round($sumC21), $topeDonaciones));
        $c22 = $c15 + $c16 + $c17 + $c18 + $c19 + $c20 + $c21;
        $c23 = max(0, (int)round($sumC23));
        $c24 = $c22 + $c23;

        // Rubro 3 (Casillas 25 a 32)
        $c25 = $c14;
        $c26 = min($c22, $c25);
        $c27 = max(0, $c25 - $c26);
        $c28 = min($c23, $c27);
        $c29 = max(0, $c27 - $c28);
        $c30 = 0; // Préstamos/financiaciones o remanente últimos 5 ejercicios (Casilla 102 Anexo)
        $excesoInmueble = max(0, $c23 - $c28);
        $c31 = max(0, $excesoInmueble - $c30);
        $c32 = min($excesoInmueble, $c30);

        // Rubro 4 (Casillas 33 a 39)
        $c33 = $c32;
        $c34 = (int)(Database::queryForValue(
            "SELECT c39 FROM co_formulario_515 WHERE id_propietario = ? AND cod_empresa = ? AND anio = ? LIMIT 1",
            [$idProp, $codEmpresa, $anio - 1]
        ) ?? 0);
        $c35 = $c33 + $c34;
        $c36 = 0;
        $c37 = max(0, $c35 - $c36);
        $c38 = min(max(0, $c34 - $c36), (int)round($c29 * 0.20));
        $c39 = max(0, $c37 - $c38);

        // Rubro 5 (Casillas 40 a 51)
        $c45 = max(0, $c29 - $c38);
        $c46 = (int)round(min($c45, 50000000) * 0.08);
        $c47 = (int)round(max(0, min($c45, 150000000) - 50000000) * 0.09);
        $c48 = (int)round(max(0, $c45 - 150000000) * 0.10);

        $c40 = (int)(Database::queryForValue(
            "SELECT c44 FROM co_formulario_515 WHERE id_propietario = ? AND cod_empresa = ? AND anio = ? LIMIT 1",
            [$idProp, $codEmpresa, $anio - 1]
        ) ?? 0);

        // Retenciones IRP sufridas en el ejercicio
        $cantRetIrp = 0;
        $c41        = 0;
        try {
            $rowRetIrp = Database::queryForList(
                "SELECT COUNT(*) AS cant_ret,
                        COALESCE(SUM(COALESCE(importe_retenido, 0) * COALESCE(NULLIF(tasa_cambio, 0), 1)), 0) AS total_ret
                 FROM co_retenciones_sufridas
                 WHERE id_propietario = ? AND cod_empresa = ?
                   AND fec_retencion >= ? AND fec_retencion <= ?
                   AND COALESCE(anulada, 'N') <> 'S'
                   AND COALESCE(estado, 'A') <> 'I'
                   AND (UPPER(COALESCE(observacion, '')) LIKE '%IRP%' OR UPPER(COALESCE(observacion, '')) LIKE '%RENTA%' OR UPPER(COALESCE(form_145, '')) LIKE '%IRP%')",
                [$idProp, $codEmpresa, $startDateStr . ' 00:00:00', $endDateStr . ' 23:59:59']
            );
            $cantRetIrp = (int)($rowRetIrp[0]['cant_ret'] ?? 0);
            $c41        = max(0, (int)round((float)($rowRetIrp[0]['total_ret'] ?? 0)));
        } catch (\Throwable $ignored) {}
        $c42 = 0;
        $c49 = 0;

        $c43 = $c40 + $c41 + $c42;
        $c50 = $c46 + $c47 + $c48 + $c49;
        $c44 = ($c43 > $c50) ? ($c43 - $c50) : 0;
        $c51 = ($c50 > $c43) ? ($c50 - $c43) : 0;

        $form = [
            'COD_EMPRESA'      => $codEmpresa,
            'ANIO'             => $anio,
            'TIPO_DECLARACION' => 'O',
            'NUM_ORDEN'        => '',
            'NUM_ORDEN_RECT'   => '0',
            'CARACTER_CESE'    => 'N',
            'MODO_EGRESOS'     => $modoEgresos,
            // Rubro 1
            'C10' => $c10, 'C12' => $c12, 'C11' => $c11, 'C13' => $c13, 'C14' => $c14,
            // Rubro 2
            'C15' => $c15, 'C16' => $c16, 'C17' => $c17, 'C18' => $c18,
            'C19' => $c19, 'C20' => $c20, 'C21' => $c21, 'C22' => $c22,
            'C23' => $c23, 'C24' => $c24,
            // Rubro 3
            'C25' => $c25, 'C26' => $c26, 'C27' => $c27, 'C28' => $c28,
            'C29' => $c29, 'C30' => $c30, 'C31' => $c31, 'C32' => $c32,
            // Rubro 4
            'C33' => $c33, 'C34' => $c34, 'C35' => $c35, 'C36' => $c36,
            'C37' => $c37, 'C38' => $c38, 'C39' => $c39,
            // Rubro 5
            'C45' => $c45, 'C46' => $c46, 'C47' => $c47, 'C48' => $c48,
            'C40' => $c40, 'C41' => $c41, 'C42' => $c42, 'C49' => $c49,
            'C43' => $c43, 'C50' => $c50, 'C44' => $c44, 'C51' => $c51,
        ];

        $resumenLibros = [
            'anio'            => $anio,
            'cantIngresos'    => $cantIngresos,
            'cantFacturas'    => $cantFacturas,
            'cantLiqSalario'  => $cantLiqSalario,
            'ingBrutoTotal'   => (int)round($ingBrutoTotal),
            'ingExonerados'   => $c10,
            'ingGravados'     => $c14,
            'cantEgresos'     => $cantEgresos,
            'egrTotalSinIva'  => (int)round($egrTotalSinIva),
            'egrTotalConIva'  => (int)round($egrTotalConIva),
            'egrDeducibles'   => $c24,
            'rentaNeta'       => $c45,
            'impuestoTotal'   => $c50,
            'cantRetIrp'      => $cantRetIrp,
            'totalRetIrp'     => $c41,
            'porInciso'             => array_values($porIncisoResumen),
            'topProveedores'        => $topProveedores,
            'porTipoComprobanteIng' => $porTipoComprobanteIng,
            'porTipoComprobanteEgr' => $porTipoComprobanteEgr,
        ];

        return [
            'status'          => 'calculated',
            'data'            => $form,
            'calculadoLibros' => $form,
            'resumenLibros'   => $resumenLibros,
            'aniosConDatos'   => $aniosConDatos,
            'empresa'         => self::fetchForm120EmpresaInfo($codEmpresa),
        ];
    }

    public static function apiFormulario515Guardar(): void {
        if (!self::requireAuth()) return;
        self::ensureFormulario515Table();
        $idProp     = TenantContext::idPropietario();
        $codEmpresa = TenantContext::empresa();
        $payload    = self::getInput();

        $anio         = (int)($payload['anio'] ?? date('Y'));
        $tipoDecl     = trim((string)($payload['tipoDeclaracion'] ?? 'O')) ?: 'O';
        $numOrden     = trim((string)($payload['numOrden'] ?? ''));
        $numRect      = trim((string)($payload['numOrdenRect'] ?? '0'));
        $caracterCese = trim((string)($payload['caracterCese'] ?? 'N')) ?: 'N';
        $modoEgresos  = trim((string)($payload['modoEgresos'] ?? 'SIN_IVA')) ?: 'SIN_IVA';

        $allFields = [
            'c10','c12','c11','c13','c14',
            'c15','c16','c17','c18','c19','c20','c21','c22','c23','c24',
            'c25','c26','c27','c28','c29','c30','c31','c32',
            'c33','c34','c35','c36','c37','c38','c39',
            'c45','c46','c47','c48','c40','c41','c42','c49','c43','c50','c44','c51'
        ];

        try {
            $exists = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM co_formulario_515 WHERE id_propietario = ? AND cod_empresa = ? AND anio = ?",
                [$idProp, $codEmpresa, $anio]
            );

            if ($exists > 0) {
                $sets   = ['tipo_declaracion = ?', 'num_orden = ?', 'num_orden_rect = ?', 'caracter_cese = ?', 'modo_egresos = ?'];
                $params = [$tipoDecl, $numOrden, $numRect, $caracterCese, $modoEgresos];
                foreach ($allFields as $f) {
                    $sets[]   = "{$f} = ?";
                    $params[] = (int)round((float)($payload[$f] ?? $payload[strtoupper($f)] ?? 0));
                }
                $params[] = $idProp;
                $params[] = $codEmpresa;
                $params[] = $anio;

                Database::update(
                    "UPDATE co_formulario_515 SET " . implode(', ', $sets) . " WHERE id_propietario = ? AND cod_empresa = ? AND anio = ?",
                    $params
                );
            } else {
                $cols   = ['id_propietario', 'cod_empresa', 'anio', 'tipo_declaracion', 'num_orden', 'num_orden_rect', 'caracter_cese', 'modo_egresos'];
                $params = [$idProp, $codEmpresa, $anio, $tipoDecl, $numOrden, $numRect, $caracterCese, $modoEgresos];
                foreach ($allFields as $f) {
                    $cols[]   = $f;
                    $params[] = (int)round((float)($payload[$f] ?? $payload[strtoupper($f)] ?? 0));
                }
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                Database::update(
                    "INSERT INTO co_formulario_515 (" . implode(', ', $cols) . ") VALUES ({$placeholders})",
                    $params
                );
            }

            View::json(['status' => 'success', 'message' => "Declaración Jurada Formulario N° 515 (IRP Versión 1 - Ejercicio {$anio}) guardada correctamente."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar Formulario 515: ' . $e->getMessage()]);
        }
    }

    public static function printFormulario515(): void {
        if (!self::requireAuth(false)) return;
        self::ensureFormulario515Table();
        $idProp      = TenantContext::idPropietario();
        $codEmpresa  = TenantContext::empresa();
        $anio        = (int)($_GET['anio'] ?? date('Y'));
        $modoEgresos = strtoupper(trim((string)($_GET['modoEgresos'] ?? 'SIN_IVA')));
        $origen      = strtolower(trim((string)($_GET['origen'] ?? '')));

        $calc     = self::calcularFormulario515Auto($idProp, $codEmpresa, $anio, $modoEgresos);
        $autoData = $calc['data'];

        $rows = Database::queryForListUpper(
            "SELECT * FROM co_formulario_515 WHERE id_propietario = ? AND cod_empresa = ? AND anio = ? LIMIT 1",
            [$idProp, $codEmpresa, $anio]
        );
        if (!empty($rows) && $origen !== 'calc') {
            $saved    = $rows[0];
            foreach ($autoData as $k => $v) {
                if (!array_key_exists($k, $saved) || $saved[$k] === null) {
                    $saved[$k] = $v;
                }
            }
            $savedMov = (int)($saved['C14'] ?? 0) + (int)($saved['C24'] ?? 0) + (int)($saved['C41'] ?? 0);
            $autoMov  = (int)($autoData['C14'] ?? 0) + (int)($autoData['C24'] ?? 0) + (int)($autoData['C41'] ?? 0);
            $data     = ($savedMov === 0 && $autoMov > 0) ? $autoData : $saved;
        } else {
            $data = $autoData;
        }

        View::render('formulario_515_print', [
            'f'       => $data,
            'resumen' => $calc['resumenLibros'],
            'empresa' => self::fetchForm120EmpresaInfo($codEmpresa),
            'anio'    => $anio,
        ]);
    }

    public static function exportarXlsFormulario515(): void {
        if (!self::requireAuth(false)) return;
        self::ensureFormulario515Table();
        $idProp      = TenantContext::idPropietario();
        $codEmpresa  = TenantContext::empresa();
        $anio        = (int)($_GET['anio'] ?? date('Y'));
        $modoEgresos = strtoupper(trim((string)($_GET['modoEgresos'] ?? 'SIN_IVA')));

        $empData         = self::fetchForm120EmpresaInfo($codEmpresa);
        $rucNum          = $empData['rucNum'] ?? '5555';
        $rucDv           = $empData['rucDv'] ?? '7';
        $primerApellido  = $empData['primer_apellido'] ?? $empData['razon_social'] ?? '';
        $segundoApellido = $empData['segundo_apellido'] ?? '';
        $nombres         = $empData['nombres'] ?? '';

        $calc     = self::calcularFormulario515Auto($idProp, $codEmpresa, $anio, $modoEgresos);
        $autoData = $calc['data'] ?? [];

        $rows = Database::queryForListUpper(
            "SELECT * FROM co_formulario_515 WHERE id_propietario = ? AND cod_empresa = ? AND anio = ? LIMIT 1",
            [$idProp, $codEmpresa, $anio]
        );
        if (!empty($rows)) {
            $saved    = $rows[0];
            $savedMov = (int)($saved['C14'] ?? 0) + (int)($saved['C24'] ?? 0);
            $autoMov  = (int)($autoData['C14'] ?? 0) + (int)($autoData['C24'] ?? 0);
            $d        = ($savedMov === 0 && $autoMov > 0) ? $autoData : $saved;
        } else {
            $d = $autoData;
        }
        $v = fn(string $k): int => (int)round((float)($d[$k] ?? $d[strtolower($k)] ?? 0));

        $lines = [
            "IMPUESTO A LA RENTA PERSONAL - RENTAS DERIVADAS DE LA PRESTACIÓN DE SERVICIOS PERSONALES - VERSIÓN 1 (FORMULARIO 515),,PARA LLENAR LEA EL INSTRUCTIVO DISPONIBLE EN LA WEB,,,,LOS IMPORTES SE CONSIGNARÁN SIN CÉNTIMOS",
            "Número de Orden:,\"{$d['NUM_ORDEN']}\",RUC:,{$rucNum},DV:,{$rucDv},Periodo / Ejercicio Fiscal:,{$anio}",
            "Razón Social/Primer Apellido:,\"{$primerApellido}\",Segundo Apellido:,\"{$segundoApellido}\",Nombres:,\"{$nombres}\",,",
            ",,,,,,,",
            "INC.,RUBRO 1 - INGRESOS OBTENIDOS POR LA PRESTACIÓN DE SERVICIOS PERSONALES,CAS.,EXONERADOS -I-,CAS.,GRAVADOS -II-,,",
            "a,\"Ingresos del ejercicio fiscal por la prestación de servicios personales, conforme a lo señalado en el Art. 62 de la Ley N° 6.380/2019\",10,{$v('C10')},12,{$v('C12')},,",
            "b,\"Ingresos del ejercicio fiscal en carácter de padres, tutores o curadores por las rentas que obtengan los hijos bajo su patria potestad o las personas bajo su tutela o curatela, conforme al Art. 4° del Anexo del Decreto N° 3.184/2019\",11,{$v('C11')},13,{$v('C13')},,",
            "c,TOTAL INGRESOS BRUTOS GRAVADOS PERCIBIDOS EN EL EJERCICIO FISCAL,,,14,{$v('C14')},,",
            ",,,,,,,",
            "INC.,RUBRO 2 - EGRESOS DEDUCIBLES DEL EJERCICIO FISCAL,,,CAS.,IMPORTE,,",
            "a,Egresos directamente relacionados con la actividad gravada,,,15,{$v('C15')},,",
            "b,\"Egresos personales y a favor de familiares a cargo realizados en el país (con excepción de los egresos señalados en los incisos d e i del presente rubro)\",,,16,{$v('C16')},,",
            "c,Egresos personales y a favor de familiares a cargo realizados en el exterior destinados exclusivamente a la salud y a la educación,,,17,{$v('C17')},,",
            "d,Egreso personal por la adquisición de un autovehículo cada 3 (tres) años,,,18,{$v('C18')},,",
            "e,Aporte patronal a favor del trabajador realizado al Sistema de Seguridad Social creado o admitido por Ley o Decreto-Ley,,,19,{$v('C19')},,",
            "f,Aporte a un Sistema privado de seguridad social realizado por el prestador de servicio personal independiente,,,20,{$v('C20')},,",
            "g,\"Cuotas, aportes, diezmos y donaciones realizadas a favor del Estado y demás asociaciones (hasta el 5% de la renta bruta gravada)\",,,21,{$v('C21')},,",
            "h,EGRESOS DEDUCIDOS EN EL EJERCICIO FISCAL (Sumatoria de las casillas 15 al 21),,,22,{$v('C22')},,",
            "i,EGRESO PERSONAL POR LA ADQUISICIÓN AL CONTADO DE UN INMUEBLE PARA LA VIVIENDA CADA 5 (CINCO) AÑOS,,,23,{$v('C23')},,",
            "j,TOTAL DE EGRESOS DEDUCIBLES EN EL EJERCICIO FISCAL (Suma de las casillas 22 y 23),,,24,{$v('C24')},,",
            ",,,,,,,",
            "INC.,RUBRO 3 - RESULTADO OBTENIDO LUEGO DE APLICAR LAS DEDUCCIONES ADMITIDAS PARA LA LIQUIDACIÓN DEL IMPUESTO,,,CAS.,IMPORTE,,",
            "a,Ingresos brutos gravados del ejercicio fiscal (Casilla 14 del Rubro 1),,,25,{$v('C25')},,",
            "b,\"Menos: Egresos deducidos del ejercicio fiscal (Casilla 22, limitado hasta Casilla 25)\",,,26,{$v('C26')},,",
            "c,Resultado después de descontar los Egresos Deducibles (Diferencia entre Casillas 25 y 26),,,27,{$v('C27')},,",
            "d,\"Menos: Egreso personal por la adquisición al contado de un inmueble para la vivienda cada 5 (cinco) años (Casilla 23, hasta Casilla 27)\",,,28,{$v('C28')},,",
            "e,SUBTOTAL RENTA NETA IMPONIBLE ANTES DE CONSIDERAR EL SALDO ARRASTRABLE DE PÉRDIDAS DE EJERCICIOS ANTERIORES (Casillas 27 - 28),,,29,{$v('C29')},,",
            "f,Monto total o porción de los préstamos/financiaciones obtenidos y utilizados en el ejercicio o el remanente de los ingresos gravados de los 5 últimos ejercicios,,,30,{$v('C30')},,",
            "g,Saldo de egresos por la adquisición de un inmueble para la vivienda no arrastrable (Pérdida fiscal no arrastrable),,,31,{$v('C31')},,",
            "h,\"Pérdida Fiscal por adquisición de un inmueble, arrastrable al siguiente ejercicio fiscal\",,,32,{$v('C32')},,",
            ",,,,,,,",
            "INC.,RUBRO 4 - PÉRDIDA FISCAL ARRASTRABLE POR LA ADQUISICIÓN DE UN INMUEBLE PARA LA VIVIENDA,,,CAS.,IMPORTE,,",
            "a,\"Pérdida Fiscal arrastrable por la adquisición de un inmueble para la vivienda, producida en el ejercicio fiscal que se liquida (Casilla 32)\",,,33,{$v('C33')},,",
            "b,\"Más: Pérdida Fiscal arrastrable por la adquisición de un inmueble para la vivienda, proveniente del ejercicio fiscal anterior\",,,34,{$v('C34')},,",
            "c,Acumulado de Pérdida Fiscal por la adquisición de inmueble (Casillas 33 + 34),,,35,{$v('C35')},,",
            "d,Menos: Porción de Pérdida Fiscal arrastrable que debe excluirse por haber superado los 5 años de arrastre,,,36,{$v('C36')},,",
            "e,Saldo de Pérdida Fiscal arrastrable susceptible de ser compensado en la liquidación del impuesto (Casillas 35 - 36),,,37,{$v('C37')},,",
            "f,\"Menos: Porción de Pérdida Fiscal proveniente de ejercicios fiscales anteriores que se compensa con la renta neta (limitada al 20% de Casilla 29)\",,,38,{$v('C38')},,",
            "g,Saldo de Pérdida Fiscal arrastrable susceptible de ser trasladado al ejercicio siguiente (Casillas 37 - 38),,,39,{$v('C39')},,",
            ",,,,,,,",
            "INC.,RUBRO 5 - DETERMINACIÓN DEL IMPUESTO Y LIQUIDACIÓN FINAL,CAS.,CONTRIBUYENTE -I-,CAS.,FISCO -II-,,",
            "a,RENTA NETA IMPONIBLE (Diferencia entre las casillas 29 y 38),,,45,{$v('C45')},,",
            "b,IMPUESTO DETERMINADO A LA TASA DEL 8% Por la porción de la Renta Neta hasta G. 50.000.000,,,46,{$v('C46')},,",
            "c,IMPUESTO DETERMINADO A LA TASA DEL 9% Por la porción de la Renta Neta desde G. 50.000.001 hasta G. 150.000.000,,,47,{$v('C47')},,",
            "d,IMPUESTO DETERMINADO A LA TASA DEL 10% Por la porción de la Renta Neta mayor a G. 150.000.000,,,48,{$v('C48')},,",
            "e,Saldo a favor del contribuyente del ejercicio fiscal anterior (Casilla 44 del ejercicio anterior),40,{$v('C40')},,,,",
            "f,RETENCIONES: Monto de las retenciones computables a favor del contribuyente,41,{$v('C41')},,,,",
            "g,PERCEPCIONES: Monto de las percepciones computables a favor del contribuyente,42,{$v('C42')},,,,",
            "h,Multa por presentar la Declaración Jurada con posterioridad al vencimiento,,,49,{$v('C49')},,",
            "i,SUBTOTAL (Columna I: Sumatoria de las casillas 40 al 42); (Columna II: Sumatoria de las casillas 46 al 49),43,{$v('C43')},50,{$v('C50')},,",
            "j,\"SALDO A FAVOR DEL CONTRIBUYENTE (Diferencia entre las casillas 43 y 50, cuando la casilla 43 sea mayor)\",44,{$v('C44')},,,,",
            "k,\"SALDO A INGRESAR A FAVOR DEL FISCO (Diferencia entre las casillas 50 y 43, cuando la casilla 50 sea mayor)\",,,51,{$v('C51')},,",
        ];

        $filename = sprintf('FORM515_V1_%s_%04d.csv', $rucNum, $anio);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
        exit;
    }

    // =========================================================================
    // SALDOS DE FACTURAS CRÉDITO (COSALEGR)
    // =========================================================================
    public static function pageSaldosFacturasCredito(): void {
        if (!self::requireAuth(false)) return;
        $codEmpresa = TenantContext::empresa();
        $ejercicio  = EjercicioService::getEjercicio();
        $empData    = self::fetchEmpresaInfo($codEmpresa, $ejercicio);
        $rango      = EjercicioService::getRango($codEmpresa, $ejercicio);
        $def        = EjercicioService::defaultMes($rango);

        self::renderFormPage('saldos_facturas_credito', [
            'nomForma'      => 'COSALEGR',
            'tituloForma'   => 'Saldos de Facturas Crédito (Egresos)',
            'empresa'       => $empData,
            'defFechaDesde' => $def[0],
            'defFechaHasta' => $def[1],
            'ejerInicio'    => $rango[0],
            'ejerFinal'     => $rango[1],
            'ejercicio'     => $ejercicio,
        ]);
    }

    public static function apiSaldosFacturasCreditoData(): void {
        if (!self::requireAuth()) return;
        $idProp  = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();

        $fechaDesde      = trim((string)($_GET['fechaDesde'] ?? $_GET['desde'] ?? ''));
        $fechaHasta      = trim((string)($_GET['fechaHasta'] ?? $_GET['hasta'] ?? ''));
        $ejercicio       = trim((string)($_GET['ejercicio'] ?? $_GET['anio'] ?? ''));
        $rucProveedor    = trim((string)($_GET['rucProveedor'] ?? $_GET['ruc'] ?? ''));
        $codProveedor    = trim((string)($_GET['codProveedor'] ?? ''));
        $filtroBusqueda  = trim((string)($_GET['search'] ?? $_GET['q'] ?? ''));
        $estadoSaldo     = strtoupper(trim((string)($_GET['estadoSaldo'] ?? $_GET['estado'] ?? 'TODOS')));
        $incluirAnuladas = !empty($_GET['incluirAnuladas']) && $_GET['incluirAnuladas'] !== 'false' && $_GET['incluirAnuladas'] !== '0';

        try {
            // 1. Obtener todas las facturas a crédito de egresos
            $sqlEgr = "SELECT e.id_egreso,
                              e.cod_empresa,
                              COALESCE(e.tip_doc, 'FAC') AS tip_doc,
                              COALESCE(e.nro_timbrado, '') AS nro_timbrado,
                              COALESCE(e.nro_factura, '') AS nro_factura,
                              COALESCE(e.serie, '') AS serie,
                              DATE_FORMAT(e.fec_egreso, '%Y-%m-%d') AS fec_egreso,
                              DATE_FORMAT(e.fec_egreso, '%d/%m/%Y') AS fec_egreso_fmt,
                              COALESCE(e.ruc_proveedor, '') AS ruc_proveedor,
                              COALESCE(e.nom_proveedor, '') AS nom_proveedor,
                              COALESCE(e.cod_proveedor, '') AS cod_proveedor,
                              COALESCE(e.condicion, 'CREDITO') AS condicion,
                              COALESCE(e.cod_moneda, '1') AS cod_moneda,
                              COALESCE(e.tasa_cambio, 1) AS tasa_cambio,
                              COALESCE(e.grav10, 0) AS grav10,
                              COALESCE(e.iva10, 0) AS iva10,
                              COALESCE(e.grav5, 0) AS grav5,
                              COALESCE(e.iva5, 0) AS iva5,
                              COALESCE(e.exentas, 0) AS exentas,
                              COALESCE(e.tot_iva, 0) AS tot_iva,
                              COALESCE(e.valor_total, 0) AS valor_total,
                              CASE WHEN UPPER(COALESCE(e.estado, 'A')) = 'I' THEN 'S' ELSE 'N' END AS anulada,
                              COALESCE(e.observacion, '') AS observacion
                       FROM co_egresos e
                       WHERE e.id_propietario = ?
                         AND e.cod_empresa = ?
                         AND (UPPER(TRIM(e.condicion)) IN ('CRE', 'CREDITO', 'CRÉDITO') OR UPPER(e.condicion) LIKE 'CRE%') ";
            $paramsEgr = [$idProp, $empresa];

            if (!$incluirAnuladas) {
                $sqlEgr .= "AND COALESCE(e.estado, 'A') <> 'I' ";
            }

            if ($fechaDesde !== '' && $fechaHasta !== '') {
                $sqlEgr .= "AND DATE(e.fec_egreso) >= ? AND DATE(e.fec_egreso) <= ? ";
                $paramsEgr[] = $fechaDesde;
                $paramsEgr[] = $fechaHasta;
            } elseif ($fechaDesde !== '') {
                $sqlEgr .= "AND DATE(e.fec_egreso) >= ? ";
                $paramsEgr[] = $fechaDesde;
            } elseif ($fechaHasta !== '') {
                $sqlEgr .= "AND DATE(e.fec_egreso) <= ? ";
                $paramsEgr[] = $fechaHasta;
            } elseif ($ejercicio !== '' && strcasecmp($ejercicio, 'TODOS') !== 0) {
                $sqlEgr .= "AND YEAR(e.fec_egreso) = ? ";
                $paramsEgr[] = (int)$ejercicio;
            }

            if ($rucProveedor !== '') {
                $cleanRuc = str_replace(['-', ' '], '', $rucProveedor);
                $sqlEgr .= "AND (UPPER(TRIM(e.ruc_proveedor)) = UPPER(?) OR UPPER(REPLACE(e.ruc_proveedor, '-', '')) = UPPER(?)) ";
                $paramsEgr[] = $rucProveedor;
                $paramsEgr[] = $cleanRuc;
            } elseif ($codProveedor !== '') {
                $sqlEgr .= "AND TRIM(e.cod_proveedor) = ? ";
                $paramsEgr[] = $codProveedor;
            }

            $sqlEgr .= "ORDER BY e.fec_egreso ASC, e.id_egreso ASC";
            $facturas = Database::queryForListUpper($sqlEgr, $paramsEgr);

            // 2. Obtener todos los detalles de recibos de pago activos para la empresa
            $sqlPagos = "SELECT d.id_detalle,
                                d.id_recibo_pago,
                                d.id_egreso,
                                d.tip_comprobante,
                                COALESCE(d.nro_timbrado, '') AS nro_timbrado,
                                COALESCE(d.nro_factura, '') AS nro_factura,
                                COALESCE(d.serie, '') AS serie_factura,
                                DATE_FORMAT(d.fec_egreso, '%Y-%m-%d') AS fec_egreso,
                                COALESCE(d.valor_total, 0) AS valor_total,
                                COALESCE(d.saldo, 0) AS saldo_det,
                                COALESCE(d.importe, 0) AS importe,
                                COALESCE(p.nro_recibo, '') AS nro_recibo,
                                COALESCE(p.serie, '') AS serie_recibo,
                                DATE_FORMAT(p.fec_recibo, '%Y-%m-%d') AS fec_recibo,
                                DATE_FORMAT(p.fec_recibo, '%d/%m/%Y') AS fec_recibo_fmt,
                                COALESCE(p.ruc_proveedor, '') AS ruc_proveedor,
                                COALESCE(p.nom_proveedor, '') AS nom_proveedor,
                                COALESCE(p.cod_proveedor, '') AS cod_proveedor,
                                COALESCE(p.anulada, 'N') AS recibo_anulado
                         FROM co_recibos_pago_det d
                         JOIN co_recibos_pago p
                           ON p.id_propietario = d.id_propietario AND p.id_recibo_pago = d.id_recibo_pago
                         WHERE d.id_propietario = ?
                           AND d.cod_empresa = ?
                           AND COALESCE(p.anulada, 'N') <> 'S'
                           AND COALESCE(p.estado, 'A') <> 'I'
                           AND COALESCE(d.estado, 'A') <> 'I'
                         ORDER BY p.fec_recibo ASC, d.id_detalle ASC";
            $pagosRows = Database::queryForListUpper($sqlPagos, [$idProp, $empresa]);

            $pagosPorId = [];
            $pagosPorDocRuc = [];
            $pagosPorDocSolo = [];

            foreach ($pagosRows as $p) {
                $idEgr     = (int)($p['ID_EGRESO'] ?? 0);
                $docRaw    = strtoupper(trim(str_replace(['-', ' '], '', (string)($p['NRO_FACTURA'] ?? ''))));
                $rucProv   = strtoupper(trim(str_replace(['-', ' '], '', (string)($p['RUC_PROVEEDOR'] ?? ''))));
                $imp       = (float)($p['IMPORTE'] ?? 0);

                $itemPago = [
                    'idRecibo'       => (int)($p['ID_RECIBO_PAGO'] ?? 0),
                    'nroRecibo'      => (string)($p['NRO_RECIBO'] ?? ''),
                    'serieRecibo'    => (string)($p['SERIE_RECIBO'] ?? ''),
                    'fecRecibo'      => (string)($p['FEC_RECIBO'] ?? ''),
                    'fecReciboFmt'   => (string)($p['FEC_RECIBO_FMT'] ?? ''),
                    'importe'        => $imp,
                    'rucProveedor'   => (string)($p['RUC_PROVEEDOR'] ?? ''),
                    'nomProveedor'   => (string)($p['NOM_PROVEEDOR'] ?? ''),
                ];

                if ($idEgr > 0) {
                    $pagosPorId[$idEgr][] = $itemPago;
                }
                if ($docRaw !== '') {
                    if ($rucProv !== '') {
                        $pagosPorDocRuc[$docRaw . '|' . $rucProv][] = $itemPago;
                    }
                    $pagosPorDocSolo[$docRaw][] = $itemPago;
                }
            }

            // 3. Cruzar cada factura crédito con sus recibos de pago
            $items = [];
            $kpiTotalCredito = 0.0;
            $kpiTotalPagado  = 0.0;
            $kpiTotalSaldo   = 0.0;
            $cantPendientes  = 0;
            $cantParciales   = 0;
            $cantCanceladas  = 0;

            $proveedoresMap = [];

            foreach ($facturas as $f) {
                $idEgr     = (int)($f['ID_EGRESO'] ?? 0);
                $docClean  = strtoupper(trim(str_replace(['-', ' '], '', (string)($f['NRO_FACTURA'] ?? ''))));
                $rucClean  = strtoupper(trim(str_replace(['-', ' '], '', (string)($f['RUC_PROVEEDOR'] ?? ''))));
                $vTotal    = (float)($f['VALOR_TOTAL'] ?? 0);
                if ($vTotal <= 0) {
                    $vTotal = (float)($f['GRAV10'] ?? 0) + (float)($f['GRAV5'] ?? 0) + (float)($f['EXENTAS'] ?? 0);
                }

                // Obtener recibos asociados
                $recibosAsoc = [];
                if ($idEgr > 0 && !empty($pagosPorId[$idEgr])) {
                    $recibosAsoc = $pagosPorId[$idEgr];
                } elseif ($docClean !== '' && $rucClean !== '' && !empty($pagosPorDocRuc[$docClean . '|' . $rucClean])) {
                    $recibosAsoc = $pagosPorDocRuc[$docClean . '|' . $rucClean];
                } elseif ($docClean !== '' && !empty($pagosPorDocSolo[$docClean])) {
                    $recibosAsoc = $pagosPorDocSolo[$docClean];
                }

                $totalPagado = 0.0;
                $recibosDetalles = [];
                $vistosRecibos = [];

                foreach ($recibosAsoc as $r) {
                    $rKey = ($r['idRecibo'] ?: 0) . '_' . ($r['nroRecibo'] ?: '') . '_' . ($r['importe'] ?: 0);
                    if (isset($vistosRecibos[$rKey])) continue;
                    $vistosRecibos[$rKey] = true;

                    $totalPagado += (float)$r['importe'];
                    $recibosDetalles[] = [
                        'idRecibo'     => $r['idRecibo'],
                        'nroRecibo'    => $r['nroRecibo'],
                        'serieRecibo'  => $r['serieRecibo'],
                        'fecRecibo'    => $r['fecRecibo'],
                        'fecReciboFmt' => $r['fecReciboFmt'],
                        'importe'      => $r['importe'],
                    ];
                }

                $anulada = ($f['ANULADA'] ?? 'N') === 'S';
                $saldo = $anulada ? 0.0 : max(0.0, $vTotal - $totalPagado);

                // Estado de saldo
                if ($anulada) {
                    $estadoCod = 'ANULADA';
                    $estadoLabel = 'ANULADA';
                    $badgeClass = 'badge-anulada';
                } elseif ($saldo <= 0.009) {
                    $estadoCod = 'CANCELADO';
                    $estadoLabel = 'PAGADO';
                    $badgeClass = 'badge-pagado';
                    $cantCanceladas++;
                } elseif ($totalPagado > 0.009) {
                    $estadoCod = 'PARCIAL';
                    $estadoLabel = 'PAGO PARCIAL';
                    $badgeClass = 'badge-parcial';
                    $cantParciales++;
                } else {
                    $estadoCod = 'PENDIENTE';
                    $estadoLabel = 'PENDIENTE';
                    $badgeClass = 'badge-pendiente';
                    $cantPendientes++;
                }

                // Filtrar por estadoSaldo si no es 'TODOS'
                if ($estadoSaldo === 'PENDIENTES' && $estadoCod !== 'PENDIENTE' && $estadoCod !== 'PARCIAL') {
                    continue;
                }
                if ($estadoSaldo === 'CANCELADOS' && $estadoCod !== 'CANCELADO') {
                    continue;
                }
                if ($estadoSaldo === 'PARCIALES' && $estadoCod !== 'PARCIAL') {
                    continue;
                }

                // Filtro de texto libre si fue proporcionado
                if ($filtroBusqueda !== '') {
                    $q = strtoupper($filtroBusqueda);
                    $hayMatch = str_contains(strtoupper((string)($f['NRO_FACTURA'] ?? '')), $q) ||
                                str_contains(strtoupper((string)($f['RUC_PROVEEDOR'] ?? '')), $q) ||
                                str_contains(strtoupper((string)($f['NOM_PROVEEDOR'] ?? '')), $q) ||
                                str_contains(strtoupper((string)($f['NRO_TIMBRADO'] ?? '')), $q);
                    if (!$hayMatch) {
                        foreach ($recibosDetalles as $rd) {
                            if (str_contains(strtoupper((string)$rd['nroRecibo']), $q)) {
                                $hayMatch = true;
                                break;
                            }
                        }
                    }
                    if (!$hayMatch) continue;
                }

                $kpiTotalCredito += $vTotal;
                $kpiTotalPagado  += $totalPagado;
                $kpiTotalSaldo   += $saldo;

                // Agrupador por proveedor
                $pKey = $rucClean ?: ($f['NOM_PROVEEDOR'] ?? 'SIN_PROVEEDOR');
                if (!isset($proveedoresMap[$pKey])) {
                    $proveedoresMap[$pKey] = [
                        'ruc'           => (string)($f['RUC_PROVEEDOR'] ?? ''),
                        'nombre'        => (string)($f['NOM_PROVEEDOR'] ?? ''),
                        'codProveedor'  => (string)($f['COD_PROVEEDOR'] ?? ''),
                        'cantFacturas'  => 0,
                        'totalCredito'  => 0.0,
                        'totalPagado'   => 0.0,
                        'saldo'         => 0.0,
                    ];
                }
                $proveedoresMap[$pKey]['cantFacturas']++;
                $proveedoresMap[$pKey]['totalCredito'] += $vTotal;
                $proveedoresMap[$pKey]['totalPagado']  += $totalPagado;
                $proveedoresMap[$pKey]['saldo']        += $saldo;

                $items[] = [
                    'idEgreso'        => $idEgr,
                    'tipDoc'          => (string)($f['TIP_DOC'] ?? 'FAC'),
                    'nroTimbrado'     => (string)($f['NRO_TIMBRADO'] ?? ''),
                    'nroFactura'      => (string)($f['NRO_FACTURA'] ?? ''),
                    'serie'           => (string)($f['SERIE'] ?? ''),
                    'fecEgreso'       => (string)($f['FEC_EGRESO'] ?? ''),
                    'fecEgresoFmt'    => (string)($f['FEC_EGRESO_FMT'] ?? ''),
                    'rucProveedor'    => (string)($f['RUC_PROVEEDOR'] ?? ''),
                    'nomProveedor'    => (string)($f['NOM_PROVEEDOR'] ?? ''),
                    'codProveedor'    => (string)($f['COD_PROVEEDOR'] ?? ''),
                    'condicion'       => (string)($f['CONDICION'] ?? 'CREDITO'),
                    'codMoneda'       => (string)($f['COD_MONEDA'] ?? '1'),
                    'tasaCambio'      => (float)($f['TASA_CAMBIO'] ?? 1),
                    'grav10'          => (float)($f['GRAV10'] ?? 0),
                    'iva10'           => (float)($f['IVA10'] ?? 0),
                    'grav5'           => (float)($f['GRAV5'] ?? 0),
                    'iva5'            => (float)($f['IVA5'] ?? 0),
                    'exentas'         => (float)($f['EXENTAS'] ?? 0),
                    'totIva'          => (float)($f['TOT_IVA'] ?? 0),
                    'valorTotal'      => $vTotal,
                    'totalPagado'     => $totalPagado,
                    'saldo'           => $saldo,
                    'porcentajePago'  => $vTotal > 0 ? min(100, round(($totalPagado / $vTotal) * 100, 1)) : 100,
                    'estadoCod'       => $estadoCod,
                    'estadoLabel'     => $estadoLabel,
                    'badgeClass'      => $badgeClass,
                    'anulada'         => $anulada ? 'S' : 'N',
                    'recibos'         => $recibosDetalles,
                    'cantRecibos'     => count($recibosDetalles),
                ];
            }

            // Ordenar proveedores por mayor saldo pendiente
            $resumenProveedores = array_values($proveedoresMap);
            usort($resumenProveedores, function($a, $b) {
                return $b['saldo'] <=> $a['saldo'];
            });

            View::json([
                'status' => 'success',
                'data'   => [
                    'facturas'            => $items,
                    'resumenProveedores'  => $resumenProveedores,
                    'kpis' => [
                        'totalCredito'   => $kpiTotalCredito,
                        'totalPagado'    => $kpiTotalPagado,
                        'totalSaldo'     => $kpiTotalSaldo,
                        'cantTotal'      => count($items),
                        'cantPendientes' => $cantPendientes,
                        'cantParciales'  => $cantParciales,
                        'cantCanceladas' => $cantCanceladas,
                    ]
                ]
            ]);
        } catch (\Throwable $e) {
            View::json([
                'status'  => 'error',
                'message' => 'Error al calcular saldos de facturas a crédito: ' . $e->getMessage()
            ], 500);
        }
    }
}
