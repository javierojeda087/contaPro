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

        $data     = self::procesarReporteLibroCompras($codEmpresa, $ejercicio, $fechaDesde, $fechaHasta, $codProveedor, $condicion, $incluirAnulados);
        $emp      = $data['empresa'] ?? [];
        $records  = $data['records'] ?? [];
        $tot      = $data['totales'] ?? [];
        $desdeFmt = self::formatDateDmy($fechaDesde);
        $hastaFmt = self::formatDateDmy($fechaHasta);

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
                .title { font-size: 14pt; font-weight: bold; text-align: center; }
                .subtitle { font-size: 9.5pt; font-weight: bold; text-align: center; }
                .box-hdr { border: 1px solid #000000; background-color: #f8fafc; font-weight: bold; font-size: 8pt; text-align: center; }
                .box-val { border: 1px solid #000000; font-size: 8.5pt; }
                .tbl-hdr { border: 1px solid #000000; background-color: #e5e7eb; font-weight: bold; font-size: 8.5pt; text-align: center; }
                .cell-txt { border: 1px solid #d1d5db; mso-number-format:"\@"; }
                .cell-num { border: 1px solid #d1d5db; text-align: right; mso-number-format:"\#\,\#\#0"; }
                .tot-lbl { border: 1.5px solid #000000; font-weight: bold; background-color: #ffffff; }
                .tot-val { border: 1.5px solid #000000; font-weight: bold; background-color: #e5e7eb; text-align: right; mso-number-format:"\#\,\#\#0"; }
            </style>
        </head>
        <body>
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
                <tr><td colspan="14" class="title">LIBRO COMPRAS</td></tr>
                <tr><td colspan="14" class="subtitle">ACTIVIDAD: <?= htmlspecialchars((string)($emp['actividad'] ?? 'IRE-GENERAL/IRACIS')) ?></td></tr>
                <tr><td colspan="14"></td></tr>
                <tr>
                    <td colspan="4" style="font-size:8pt; font-weight:bold;">1. Identificador del Contribuyente</td>
                    <td colspan="3" style="font-size:8pt; font-weight:bold;">2. Periodo Fiscal</td>
                    <td colspan="3" style="font-size:8pt; font-weight:bold;">3. Representante Legal</td>
                    <td colspan="4" style="font-size:8pt; font-weight:bold;">4. Identificador del Contador</td>
                </tr>
                <tr>
                    <td colspan="3" class="box-hdr">RAZÓN SOCIAL/NOMBRES Y APELLIDOS</td>
                    <td colspan="1" class="box-hdr">RUC</td>
                    <td colspan="1" class="box-hdr">DESDE</td>
                    <td colspan="2" class="box-hdr">HASTA</td>
                    <td colspan="3" class="box-hdr">NOMBRES Y APELLIDOS</td>
                    <td colspan="3" class="box-hdr">NOMBRES Y APELLIDOS</td>
                    <td colspan="1" class="box-hdr">RUC</td>
                </tr>
                <tr>
                    <td colspan="3" class="box-val"><?= htmlspecialchars((string)($emp['descripcion'] ?? '')) ?></td>
                    <td colspan="1" class="box-val" style="mso-number-format:'\@';"><?= htmlspecialchars((string)($emp['rucEmpresa'] ?? '')) ?></td>
                    <td colspan="1" class="box-val" style="text-align:center; mso-number-format:'\@';"><?= htmlspecialchars($desdeFmt) ?></td>
                    <td colspan="2" class="box-val" style="text-align:center; mso-number-format:'\@';"><?= htmlspecialchars($hastaFmt) ?></td>
                    <td colspan="3" class="box-val"><?= htmlspecialchars((string)($emp['repLegal'] ?? '')) ?></td>
                    <td colspan="3" class="box-val"><?= htmlspecialchars((string)($emp['nomContador'] ?? '')) ?></td>
                    <td colspan="1" class="box-val" style="mso-number-format:'\@';"><?= htmlspecialchars((string)($emp['rucContador'] ?? '')) ?></td>
                </tr>
                <tr><td colspan="14"></td></tr>
                <tr>
                    <th rowspan="2" class="tbl-hdr">DIA-MES</th>
                    <th rowspan="2" class="tbl-hdr">DOCUMENTO</th>
                    <th rowspan="2" class="tbl-hdr">TIMB.</th>
                    <th colspan="3" class="tbl-hdr">PROVEEDOR</th>
                    <th rowspan="2" class="tbl-hdr">COND</th>
                    <th colspan="7" class="tbl-hdr">VALORES DE COMPRAS</th>
                </tr>
                <tr>
                    <th class="tbl-hdr">RAZÓN SOCIAL</th>
                    <th class="tbl-hdr">RUC</th>
                    <th class="tbl-hdr">DV</th>
                    <th class="tbl-hdr">GRAV. 10%</th>
                    <th class="tbl-hdr">GRAV. 5%</th>
                    <th class="tbl-hdr">IVA 10%</th>
                    <th class="tbl-hdr">IVA 5%</th>
                    <th class="tbl-hdr">EXENTAS</th>
                    <th class="tbl-hdr">BASE IMP.</th>
                    <th class="tbl-hdr">TOTAL</th>
                </tr>
                <?php if (empty($records)): ?>
                <tr>
                    <td colspan="14" style="text-align:center; padding:10px; border:1px solid #000;">No se registraron movimientos de compras en el periodo seleccionado.</td>
                </tr>
                <?php else: foreach ($records as $idx => $r):
                    $bg = ($idx % 2 === 1) ? 'background-color:#f6eae8;' : 'background-color:#ffffff;';
                    $anulStyle = !empty($r['anulada']) ? 'text-decoration:line-through; color:#7f1d1d;' : '';
                ?>
                <tr style="<?= $bg . $anulStyle ?>">
                    <td class="cell-txt" style="text-align:center;"><?= htmlspecialchars((string)($r['diaMes'] ?? '')) ?></td>
                    <td class="cell-txt" style="text-align:center;"><?= htmlspecialchars((string)($r['documento'] ?? '')) ?></td>
                    <td class="cell-txt" style="text-align:center;"><?= htmlspecialchars((string)($r['timbrado'] ?? '')) ?></td>
                    <td class="cell-txt"><?= htmlspecialchars((string)($r['razonSocial'] ?? '')) ?></td>
                    <td class="cell-txt"><?= htmlspecialchars((string)($r['rucNum'] ?? '')) ?></td>
                    <td class="cell-txt" style="text-align:center;"><?= htmlspecialchars((string)($r['rucDv'] ?? '')) ?></td>
                    <td class="cell-txt" style="text-align:center;"><?= htmlspecialchars((string)($r['condicion'] ?? '')) ?></td>
                    <td class="cell-num"><?= (int)round((float)($r['grav10'] ?? 0)) ?></td>
                    <td class="cell-num"><?= (int)round((float)($r['grav5'] ?? 0)) ?></td>
                    <td class="cell-num"><?= (int)round((float)($r['iva10'] ?? 0)) ?></td>
                    <td class="cell-num"><?= (int)round((float)($r['iva5'] ?? 0)) ?></td>
                    <td class="cell-num"><?= (int)round((float)($r['exentas'] ?? 0)) ?></td>
                    <td class="cell-num"><?= (int)round((float)($r['baseImp'] ?? 0)) ?></td>
                    <td class="cell-num"><?= (int)round((float)($r['total'] ?? 0)) ?></td>
                </tr>
                <?php endforeach; endif; ?>
                <tr>
                    <td colspan="7" class="tot-lbl">TOTAL GENERAL</td>
                    <td class="tot-val"><?= (int)round((float)($tot['grav10'] ?? 0)) ?></td>
                    <td class="tot-val"><?= (int)round((float)($tot['grav5'] ?? 0)) ?></td>
                    <td class="tot-val"><?= (int)round((float)($tot['iva10'] ?? 0)) ?></td>
                    <td class="tot-val"><?= (int)round((float)($tot['iva5'] ?? 0)) ?></td>
                    <td class="tot-val"><?= (int)round((float)($tot['exentas'] ?? 0)) ?></td>
                    <td class="tot-val"><?= (int)round((float)($tot['baseImp'] ?? 0)) ?></td>
                    <td class="tot-val"><?= (int)round((float)($tot['total'] ?? 0)) ?></td>
                </tr>
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

        $data     = self::procesarReporteLibroVentas($codEmpresa, $ejercicio, $fechaDesde, $fechaHasta, $codCliente, $condicion, $incluirAnulados);
        $emp      = $data['empresa'] ?? [];
        $records  = $data['records'] ?? [];
        $tot      = $data['totales'] ?? [];
        $desdeFmt = self::formatDateDmy($fechaDesde);
        $hastaFmt = self::formatDateDmy($fechaHasta);

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
                .title { font-size: 14pt; font-weight: bold; text-align: center; }
                .subtitle { font-size: 9.5pt; font-weight: bold; text-align: center; }
                .box-hdr { border: 1px solid #000000; background-color: #f8fafc; font-weight: bold; font-size: 8pt; text-align: center; }
                .box-val { border: 1px solid #000000; font-size: 8.5pt; }
                .tbl-hdr { border: 1px solid #000000; background-color: #e5e7eb; font-weight: bold; font-size: 8.5pt; text-align: center; }
                .cell-txt { border: 1px solid #d1d5db; mso-number-format:"\@"; }
                .cell-num { border: 1px solid #d1d5db; text-align: right; mso-number-format:"\#\,\#\#0"; }
                .tot-lbl { border: 1.5px solid #000000; font-weight: bold; background-color: #ffffff; }
                .tot-val { border: 1.5px solid #000000; font-weight: bold; background-color: #e5e7eb; text-align: right; mso-number-format:"\#\,\#\#0"; }
            </style>
        </head>
        <body>
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
                <tr><td colspan="13" class="title">LIBRO VENTAS</td></tr>
                <tr><td colspan="13" class="subtitle">ACTIVIDAD: <?= htmlspecialchars((string)($emp['actividad'] ?? 'IRE-GENERAL/IRACIS')) ?></td></tr>
                <tr><td colspan="13"></td></tr>
                <tr>
                    <td colspan="4" style="font-size:8pt; font-weight:bold;">1. Identificador del Contribuyente</td>
                    <td colspan="3" style="font-size:8pt; font-weight:bold;">2. Periodo Fiscal</td>
                    <td colspan="3" style="font-size:8pt; font-weight:bold;">3. Representante Legal</td>
                    <td colspan="3" style="font-size:8pt; font-weight:bold;">4. Identificador del Contador</td>
                </tr>
                <tr>
                    <td colspan="3" class="box-hdr">RAZÓN SOCIAL/NOMBRES Y APELLIDOS</td>
                    <td colspan="1" class="box-hdr">RUC</td>
                    <td colspan="1" class="box-hdr">DESDE</td>
                    <td colspan="2" class="box-hdr">HASTA</td>
                    <td colspan="3" class="box-hdr">NOMBRES Y APELLIDOS</td>
                    <td colspan="2" class="box-hdr">NOMBRES Y APELLIDOS</td>
                    <td colspan="1" class="box-hdr">RUC</td>
                </tr>
                <tr>
                    <td colspan="3" class="box-val"><?= htmlspecialchars((string)($emp['descripcion'] ?? '')) ?></td>
                    <td colspan="1" class="box-val" style="mso-number-format:'\@';"><?= htmlspecialchars((string)($emp['rucEmpresa'] ?? '')) ?></td>
                    <td colspan="1" class="box-val" style="text-align:center; mso-number-format:'\@';"><?= htmlspecialchars($desdeFmt) ?></td>
                    <td colspan="2" class="box-val" style="text-align:center; mso-number-format:'\@';"><?= htmlspecialchars($hastaFmt) ?></td>
                    <td colspan="3" class="box-val"><?= htmlspecialchars((string)($emp['repLegal'] ?? '')) ?></td>
                    <td colspan="2" class="box-val"><?= htmlspecialchars((string)($emp['nomContador'] ?? '')) ?></td>
                    <td colspan="1" class="box-val" style="mso-number-format:'\@';"><?= htmlspecialchars((string)($emp['rucContador'] ?? '')) ?></td>
                </tr>
                <tr><td colspan="13"></td></tr>
                <tr>
                    <th rowspan="2" class="tbl-hdr">DIA-MES</th>
                    <th rowspan="2" class="tbl-hdr">DOCUMENTO</th>
                    <th rowspan="2" class="tbl-hdr">TIMB.</th>
                    <th colspan="3" class="tbl-hdr">CLIENTE</th>
                    <th rowspan="2" class="tbl-hdr">COND</th>
                    <th colspan="6" class="tbl-hdr">VALORES DE VENTAS</th>
                </tr>
                <tr>
                    <th class="tbl-hdr">RAZÓN SOCIAL</th>
                    <th class="tbl-hdr">RUC</th>
                    <th class="tbl-hdr">DV</th>
                    <th class="tbl-hdr">GRAV. 10%</th>
                    <th class="tbl-hdr">GRAV. 5%</th>
                    <th class="tbl-hdr">IVA 10%</th>
                    <th class="tbl-hdr">IVA 5%</th>
                    <th class="tbl-hdr">EXENTAS</th>
                    <th class="tbl-hdr">TOTAL</th>
                </tr>
                <?php if (empty($records)): ?>
                <tr>
                    <td colspan="13" style="text-align:center; padding:10px; border:1px solid #000;">No se registraron movimientos de ventas en el periodo seleccionado.</td>
                </tr>
                <?php else: foreach ($records as $idx => $r):
                    $bg = ($idx % 2 === 1) ? 'background-color:#f6eae8;' : 'background-color:#ffffff;';
                    $anulStyle = !empty($r['anulada']) ? 'text-decoration:line-through; color:#7f1d1d;' : '';
                ?>
                <tr style="<?= $bg . $anulStyle ?>">
                    <td class="cell-txt" style="text-align:center;"><?= htmlspecialchars((string)($r['diaMes'] ?? '')) ?></td>
                    <td class="cell-txt" style="text-align:center;"><?= htmlspecialchars((string)($r['documento'] ?? '')) ?></td>
                    <td class="cell-txt" style="text-align:center;"><?= htmlspecialchars((string)($r['timbrado'] ?? '')) ?></td>
                    <td class="cell-txt"><?= htmlspecialchars((string)($r['razonSocial'] ?? '')) ?></td>
                    <td class="cell-txt"><?= htmlspecialchars((string)($r['rucNum'] ?? '')) ?></td>
                    <td class="cell-txt" style="text-align:center;"><?= htmlspecialchars((string)($r['rucDv'] ?? '')) ?></td>
                    <td class="cell-txt" style="text-align:center;"><?= htmlspecialchars((string)($r['condicion'] ?? '')) ?></td>
                    <td class="cell-num"><?= (int)round((float)($r['grav10'] ?? 0)) ?></td>
                    <td class="cell-num"><?= (int)round((float)($r['grav5'] ?? 0)) ?></td>
                    <td class="cell-num"><?= (int)round((float)($r['iva10'] ?? 0)) ?></td>
                    <td class="cell-num"><?= (int)round((float)($r['iva5'] ?? 0)) ?></td>
                    <td class="cell-num"><?= (int)round((float)($r['exentas'] ?? 0)) ?></td>
                    <td class="cell-num"><?= (int)round((float)($r['total'] ?? 0)) ?></td>
                </tr>
                <?php endforeach; endif; ?>
                <tr>
                    <td colspan="7" class="tot-lbl">TOTAL GENERAL</td>
                    <td class="tot-val"><?= (int)round((float)($tot['grav10'] ?? 0)) ?></td>
                    <td class="tot-val"><?= (int)round((float)($tot['grav5'] ?? 0)) ?></td>
                    <td class="tot-val"><?= (int)round((float)($tot['iva10'] ?? 0)) ?></td>
                    <td class="tot-val"><?= (int)round((float)($tot['iva5'] ?? 0)) ?></td>
                    <td class="tot-val"><?= (int)round((float)($tot['exentas'] ?? 0)) ?></td>
                    <td class="tot-val"><?= (int)round((float)($tot['total'] ?? 0)) ?></td>
                </tr>
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
        if (((int)($saved['C62'] ?? 0) === 0) && ((int)($saved['C40'] ?? 0) > 0)) {
            $saved['C62'] = (int)$saved['C40'];
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
            $savedMov = (int)($saved['C10'] ?? 0) + (int)($saved['C151'] ?? 0) + (int)($saved['C12'] ?? 0) + (int)($saved['C32'] ?? 0) + (int)($saved['C35'] ?? 0) + (int)($saved['C62'] ?? 0);
            $autoMov  = (int)($autoData['C10'] ?? 0) + (int)($autoData['C151'] ?? 0) + (int)($autoData['C12'] ?? 0) + (int)($autoData['C32'] ?? 0) + (int)($autoData['C35'] ?? 0) + (int)($autoData['C62'] ?? 0);

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
                 ) p
                 WHERE anio IS NOT NULL AND mes IS NOT NULL
                 GROUP BY anio, mes
                 ORDER BY anio DESC, mes DESC",
                [$idProp, $codEmpresa, $idProp, $codEmpresa]
            );
        } catch (\Throwable $ignored) {}

        // 1. Ventas del periodo desde co_ingresos
        $rowsVentas = Database::queryForList(
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
               AND COALESCE(estado, 'A') <> 'I'
             ORDER BY fec_ingreso ASC, id_ingreso ASC",
            [$idProp, $codEmpresa, $startDateStr, $endDateStr]
        );

        $vBase10 = 0.0; $vIva10 = 0.0;
        $vBase5  = 0.0; $vIva5  = 0.0;
        $vExenta = 0.0;
        $vNcrBase10 = 0.0; $vNcrIva10 = 0.0;
        $vNcrBase5  = 0.0; $vNcrIva5  = 0.0;
        $vNcrExenta = 0.0;
        $vBrutoTotal = 0.0;

        foreach ($rowsVentas as $rv) {
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

        // 2. Rubro 2 (Versión 4): Acumulado de los últimos 6 meses (incluyendo el periodo que se declara)
        $sixMonthsStart = date('Y-m-01', strtotime("-5 months", strtotime($startDateStr)));
        $rowsVentas6M = Database::queryForList(
            "SELECT tip_doc, COALESCE(grav10, 0) AS grav10, COALESCE(iva10, 0) AS iva10,
                    COALESCE(grav5, 0) AS grav5, COALESCE(iva5, 0) AS iva5, COALESCE(exentas, 0) AS exentas
             FROM co_ingresos
             WHERE id_propietario = ? AND cod_empresa = ?
               AND fec_ingreso >= ? AND fec_ingreso <= ?
               AND COALESCE(anulada, 'N') <> 'S'
               AND COALESCE(estado, 'A') <> 'I'",
            [$idProp, $codEmpresa, $sixMonthsStart, $endDateStr]
        );
        $acumGrav6M = 0.0;
        $acumEx6M   = 0.0;
        foreach ($rowsVentas6M as $rv6) {
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

            // Determinar régimen (1 = IRP & IVA, 2 = IRP, 3 = IVA, 4 = G. CAPITAL)
            $rawReg  = strtoupper(trim((string)($rc['regimen'] ?? $rc['cod_regimen'] ?? '1')));
            $codReg  = (int)($rc['cod_regimen'] ?? 1);
            if ($rawReg === '2' || $rawReg === 'IRP' || $codReg === 2) {
                $regKey = '2';
            } elseif ($rawReg === '3' || $rawReg === 'IVA' || $codReg === 3) {
                $regKey = '3';
            } elseif ($rawReg === '4' || str_contains($rawReg, 'CAPITAL') || $codReg === 4) {
                $regKey = '4';
            } else {
                $regKey = '1';
            }

            // Determinar si clasificacion_gasto es personal/familiar (3 o 4) o relacionada a la actividad (1 o 2)
            $clasG = trim((string)($rc['clasificacion_gasto'] ?? '1'));
            if (is_numeric($clasG)) {
                $clasG = (string)(int)round((float)$clasG);
            }
            $esGastoPersonal = ($clasG === '3' || $clasG === '4' || stripos($clasG, 'PERSONAL') !== false || stripos($clasG, 'FAMILIAR') !== false);

            // Imputa IVA si el régimen es 1 (IRP & IVA) o 3 (IVA) y no es gasto personal
            $imputaIva = ($regKey === '1' || $regKey === '3') && !$esGastoPersonal;

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
            $esProrrateo = str_contains($f120Modo, 'PRORRAT') || str_contains($f120Modo, 'INDIST');

            if ($imputaIva) {
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
                    $compExentasDirecto += $sign * $exRaw;
                }
            } else {
                $cantCompSoloIrp++;
                $montoNoImputaIva += $sign * $totRaw;
                $noIvaBase10 += $sign * $b10;
                $noIva10     += $sign * $i10;
                $noIvaBase5  += $sign * $b5;
                $noIva5      += $sign * $i5;
                $compExentasDirecto += $sign * $exRaw;
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
        $c52  = 0; // Retenciones computables
        $c169 = 0; // Percepciones computables
        $c56  = 0; // Multa por contravención
        $c53  = $c51 + $c52 + $c169;
        $c57  = $c55 + $c56;
        $c54  = ($c53 > $c57) ? ($c53 - $c57) : 0;
        $c58  = ($c57 > $c53) ? ($c57 - $c53) : 0;

        // 6. Información de las Compras vinculadas a operaciones exoneradas o no alcanzadas (Rubro 6 Versión 4)
        $c59  = max(0, (int)round($noIvaBase10));
        $c65  = max(0, (int)round($noIva10));
        $c60  = max(0, (int)round($noIvaBase5));
        $c66  = max(0, (int)round($noIva5));
        $c61  = max(0, (int)round($compExentasIndistinto));
        $c62  = max(0, (int)round($compExentasDirecto));
        $c63  = 0;
        $c64  = max(0, $c65 + $c66 + max(0, $c39 - $c164)); // IVA Costo o Gasto deducible en IRE o IRP
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

        $rows = Database::queryForListUpper(
            "SELECT * FROM co_formulario_120 WHERE id_propietario = ? AND cod_empresa = ? AND mes = ? AND anio = ? LIMIT 1",
            [$idProp, $codEmpresa, $mes, $anio]
        );
        if (!empty($rows)) {
            $saved    = self::normalizarSavedForm120V4($rows[0], $autoData);
            $savedMov = (int)($saved['C10'] ?? 0) + (int)($saved['C151'] ?? 0) + (int)($saved['C12'] ?? 0) + (int)($saved['C32'] ?? 0) + (int)($saved['C35'] ?? 0) + (int)($saved['C62'] ?? 0);
            $autoMov  = (int)($autoData['C10'] ?? 0) + (int)($autoData['C151'] ?? 0) + (int)($autoData['C12'] ?? 0) + (int)($autoData['C32'] ?? 0) + (int)($autoData['C35'] ?? 0) + (int)($autoData['C62'] ?? 0);
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
}
