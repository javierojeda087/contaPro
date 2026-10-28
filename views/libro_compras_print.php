<?php
$emp = $empresa ?? [];
$recs = $records ?? [];
$tot = $totales ?? [
    'grav10' => 0, 'grav5' => 0, 'iva10' => 0, 'iva5' => 0,
    'exentas' => 0, 'baseImp' => 0, 'total' => 0
];
$desdeFmt = $fechaDesdeFmt ?? '';
$hastaFmt = $fechaHastaFmt ?? '';
$rowsPerPage = 28;

$fmtGs = function ($val): string {
    return number_format((float)round((float)($val ?? 0)), 0, ',', '.');
};

$chunks = !empty($recs) ? array_chunk($recs, $rowsPerPage) : [[]];
$acum = [
    'grav10' => 0.0, 'grav5' => 0.0, 'iva10' => 0.0, 'iva5' => 0.0,
    'exentas' => 0.0, 'baseImp' => 0.0, 'total' => 0.0
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LIBRO COMPRAS</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.5pt;
            color: #000000;
            background: #f1f5f9;
            margin: 0;
            padding: 16px;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .lc-sheet {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.07);
            padding: 24px 28px;
            max-width: 1200px;
            margin: 0 auto 24px auto;
            page-break-after: always;
        }
        .lc-sheet:last-child {
            page-break-after: auto;
            margin-bottom: 0;
        }
        .lc-title {
            text-align: center;
            font-size: 15pt;
            font-weight: normal;
            margin: 0 0 2px 0;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .lc-subtitle {
            text-align: center;
            font-size: 9pt;
            font-weight: normal;
            margin: 0 0 10px 0;
            text-transform: uppercase;
        }
        .lc-hdr-row {
            display: grid;
            grid-template-columns: 3.6fr 1.6fr 2.1fr 3.2fr;
            gap: 10px;
            margin-bottom: 10px;
            align-items: end;
        }
        .lc-box-label {
            font-size: 7.5pt;
            margin-bottom: 2px;
            padding-left: 1px;
        }
        .lc-box-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000000;
            table-layout: fixed;
            font-size: 7.5pt;
        }
        .lc-box-table th {
            border-bottom: 1px solid #000000;
            border-right: 1px solid #000000;
            padding: 2px 4px;
            text-align: center;
            font-weight: normal;
            text-transform: uppercase;
            background: #ffffff;
        }
        .lc-box-table th:last-child {
            border-right: none;
        }
        .lc-box-table td {
            border-right: 1px solid #000000;
            padding: 2.5px 5px;
            height: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .lc-box-table td:last-child {
            border-right: none;
        }

        .lc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.8pt;
        }
        .lc-table thead th {
            background: #e5e7eb;
            border: 1px solid #000000;
            padding: 3px 4px;
            text-align: center;
            font-weight: bold;
            font-size: 7.5pt;
            text-transform: uppercase;
        }
        .lc-table thead tr:first-child th {
            border-top: 1.5px solid #000000;
        }
        .lc-table thead tr:last-child th {
            border-bottom: 1.5px solid #000000;
        }
        .lc-table thead th:first-child {
            border-left: 1.5px solid #000000;
        }
        .lc-table thead th:last-child {
            border-right: 1.5px solid #000000;
        }
        .lc-table tbody tr.lc-data-row:nth-child(odd) {
            background-color: #ffffff;
        }
        .lc-table tbody tr.lc-data-row:nth-child(even) {
            background-color: #f6eae8;
        }
        .lc-table tbody tr.lc-data-row td {
            padding: 2px 4px;
            border: none;
            white-space: nowrap;
        }
        .lc-table tbody tr.lc-row-anulada td {
            text-decoration: line-through;
            color: #7f1d1d;
        }
        .lc-table tbody tr.lc-transporte-row td {
            border: 1px solid #000000;
            background: #ffffff !important;
            padding: 2.5px 4px;
        }
        .lc-table tfoot tr.lc-total-row td.lc-total-label {
            border: 1px solid #000000;
            background: #ffffff;
            padding: 3px 5px;
            text-align: left;
            font-weight: normal;
        }
        .lc-table tfoot tr.lc-total-row td.lc-total-val {
            border: 1.5px solid #000000;
            background: #e5e7eb;
            padding: 3px 4px;
            text-align: right;
            font-weight: bold;
        }
        .no-print-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #1e293b;
            color: white;
            padding: 10px 20px;
            margin: 0 auto 18px auto;
            max-width: 1216px;
            border-radius: 8px;
        }
        @media print {
            .no-print, .no-print-bar { display: none !important; }
            body { background: #ffffff !important; padding: 0 !important; }
            .lc-sheet {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                margin: 0 !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <div><strong style="font-size: 11pt;">Vista Previa de Impresión - Libro Compras</strong></div>
        <div style="display: flex; gap: 10px;">
            <button type="button" onclick="window.print()" style="background: #0284c7; color: white; border: none; padding: 7px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 9.5pt;">
                🖨️ Imprimir / Guardar PDF
            </button>
            <button type="button" onclick="window.close()" style="background: #64748b; color: white; border: none; padding: 7px 14px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 9.5pt;">
                Cerrar
            </button>
        </div>
    </div>

    <?php foreach ($chunks as $pageIdx => $chunk):
        $isFirstPage = ($pageIdx === 0);
        $isLastPage  = ($pageIdx === count($chunks) - 1);
    ?>
    <div class="lc-sheet">
        <div class="lc-title">LIBRO COMPRAS</div>
        <div class="lc-subtitle">ACTIVIDAD: <?= htmlspecialchars((string)($emp['actividad'] ?? 'IVA & IRP')) ?></div>

        <div class="lc-hdr-row">
            <div>
                <div class="lc-box-label">1. Identificador del Contribuyente</div>
                <table class="lc-box-table">
                    <thead>
                        <tr>
                            <th style="width: 72%;">RAZÓN SOCIAL/NOMBRES Y APELLIDOS</th>
                            <th style="width: 28%;">RUC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: left;"><?= htmlspecialchars((string)($emp['descripcion'] ?? '')) ?></td>
                            <td style="text-align: left;"><?= htmlspecialchars((string)($emp['rucEmpresa'] ?? '')) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div>
                <div class="lc-box-label">2. Periodo Fiscal</div>
                <table class="lc-box-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">DESDE</th>
                            <th style="width: 50%;">HASTA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: center;"><?= htmlspecialchars((string)$desdeFmt) ?></td>
                            <td style="text-align: center;"><?= htmlspecialchars((string)$hastaFmt) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div>
                <div class="lc-box-label">3. Representante Legal</div>
                <table class="lc-box-table">
                    <thead>
                        <tr>
                            <th>NOMBRES Y APELLIDOS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: left;"><?= htmlspecialchars((string)($emp['repLegal'] ?? '')) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div>
                <div class="lc-box-label">4. Identificador del Contador</div>
                <table class="lc-box-table">
                    <thead>
                        <tr>
                            <th style="width: 70%;">NOMBRES Y APELLIDOS</th>
                            <th style="width: 30%;">RUC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: left;"><?= htmlspecialchars((string)($emp['nomContador'] ?? '')) ?></td>
                            <td style="text-align: left;"><?= htmlspecialchars((string)($emp['rucContador'] ?? '')) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <table class="lc-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 5.5%;">DIA-MES</th>
                    <th rowspan="2" style="width: 11%;">DOCUMENTO</th>
                    <th rowspan="2" style="width: 6.5%;">TIMB.</th>
                    <th colspan="3">PROVEEDOR</th>
                    <th rowspan="2" style="width: 4.5%;">COND</th>
                    <th colspan="7">VALORES DE COMPRAS</th>
                </tr>
                <tr>
                    <th style="width: 22%;">RAZÓN SOCIAL</th>
                    <th style="width: 6.5%;">RUC</th>
                    <th style="width: 2.5%;">DV</th>
                    <th style="width: 6.5%;">GRAV. 10%</th>
                    <th style="width: 6%;">GRAV. 5%</th>
                    <th style="width: 6%;">IVA 10%</th>
                    <th style="width: 5.5%;">IVA 5%</th>
                    <th style="width: 5.5%;">EXENTAS</th>
                    <th style="width: 5.5%;">BASE IMP.</th>
                    <th style="width: 6.5%;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$isFirstPage): ?>
                <tr class="lc-transporte-row">
                    <td colspan="7" style="text-align: left;">TRANSPORTE</td>
                    <td style="text-align: right;"><?= $fmtGs($acum['grav10']) ?></td>
                    <td style="text-align: right;"><?= $fmtGs($acum['grav5']) ?></td>
                    <td style="text-align: right;"><?= $fmtGs($acum['iva10']) ?></td>
                    <td style="text-align: right;"><?= $fmtGs($acum['iva5']) ?></td>
                    <td style="text-align: right;"><?= $fmtGs($acum['exentas']) ?></td>
                    <td style="text-align: right;"><?= $fmtGs($acum['baseImp']) ?></td>
                    <td style="text-align: right;"><?= $fmtGs($acum['total']) ?></td>
                </tr>
                <?php endif; ?>

                <?php if (empty($chunk)): ?>
                <tr class="lc-data-row">
                    <td colspan="14" style="text-align: center; padding: 14px;">No se registraron movimientos de compras en el periodo seleccionado.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($chunk as $r):
                        $anulada = !empty($r['anulada']);
                        if (!$anulada) {
                            $acum['grav10']  += (float)($r['grav10'] ?? 0);
                            $acum['grav5']   += (float)($r['grav5'] ?? 0);
                            $acum['iva10']   += (float)($r['iva10'] ?? 0);
                            $acum['iva5']    += (float)($r['iva5'] ?? 0);
                            $acum['exentas'] += (float)($r['exentas'] ?? 0);
                            $acum['baseImp'] += (float)($r['baseImp'] ?? 0);
                            $acum['total']   += (float)($r['total'] ?? 0);
                        }
                    ?>
                    <tr class="lc-data-row<?= $anulada ? ' lc-row-anulada' : '' ?>">
                        <td style="text-align: center;"><?= htmlspecialchars((string)($r['diaMes'] ?? '')) ?></td>
                        <td style="text-align: center;"><?= htmlspecialchars((string)($r['documento'] ?? '')) ?></td>
                        <td style="text-align: center;"><?= htmlspecialchars((string)($r['timbrado'] ?? '')) ?></td>
                        <td style="text-align: left;"><?= htmlspecialchars((string)($r['razonSocial'] ?? '')) ?></td>
                        <td style="text-align: left;"><?= htmlspecialchars((string)($r['rucNum'] ?? '')) ?></td>
                        <td style="text-align: center;"><?= htmlspecialchars((string)($r['rucDv'] ?? '')) ?></td>
                        <td style="text-align: center;"><?= htmlspecialchars((string)($r['condicion'] ?? '')) ?></td>
                        <td style="text-align: right;"><?= $fmtGs($r['grav10'] ?? 0) ?></td>
                        <td style="text-align: right;"><?= $fmtGs($r['grav5'] ?? 0) ?></td>
                        <td style="text-align: right;"><?= $fmtGs($r['iva10'] ?? 0) ?></td>
                        <td style="text-align: right;"><?= $fmtGs($r['iva5'] ?? 0) ?></td>
                        <td style="text-align: right;"><?= $fmtGs($r['exentas'] ?? 0) ?></td>
                        <td style="text-align: right;"><?= $fmtGs($r['baseImp'] ?? 0) ?></td>
                        <td style="text-align: right;"><?= $fmtGs($r['total'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if ($isLastPage): ?>
            <tfoot>
                <tr class="lc-total-row">
                    <td colspan="7" class="lc-total-label">TOTAL GENERAL</td>
                    <td class="lc-total-val"><?= $fmtGs($tot['grav10'] ?? 0) ?></td>
                    <td class="lc-total-val"><?= $fmtGs($tot['grav5'] ?? 0) ?></td>
                    <td class="lc-total-val"><?= $fmtGs($tot['iva10'] ?? 0) ?></td>
                    <td class="lc-total-val"><?= $fmtGs($tot['iva5'] ?? 0) ?></td>
                    <td class="lc-total-val"><?= $fmtGs($tot['exentas'] ?? 0) ?></td>
                    <td class="lc-total-val"><?= $fmtGs($tot['baseImp'] ?? 0) ?></td>
                    <td class="lc-total-val"><?= $fmtGs($tot['total'] ?? 0) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php endforeach; ?>

</body>
</html>
