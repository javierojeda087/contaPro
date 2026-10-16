<?php
$emp = $empresa ?? [];
$recs = $records ?? [];
$tot = $totales ?? ['grav10'=>0,'grav5'=>0,'iva10'=>0,'iva5'=>0,'exentas'=>0,'baseImp'=>0,'total'=>0];
$fmt = fn($n) => number_format((float)($n ?? 0), 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LIBRO COMPRAS | ContaPro</title>
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8.5pt; color: #000; background: #fff; margin: 0; padding: 10px; }
        .header-title { text-align: center; font-size: 16pt; font-weight: bold; margin-bottom: 2px; text-transform: uppercase; }
        .header-subtitle { text-align: center; font-size: 9pt; font-weight: bold; margin-bottom: 12px; text-transform: uppercase; }
        .info-grid { display: flex; gap: 10px; margin-bottom: 12px; width: 100%; }
        .info-box { border: 1px solid #000; box-sizing: border-box; background: #fff; }
        .box-1 { flex: 4; } .box-2 { flex: 2; } .box-3 { flex: 2.5; } .box-4 { flex: 3.5; }
        .box-title { font-size: 7.5pt; font-weight: bold; padding: 2px 4px; border-bottom: 1px solid #000; }
        .box-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .box-table th { font-size: 7pt; font-weight: bold; text-align: center; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 2px; text-transform: uppercase; }
        .box-table th:last-child, .box-table td:last-child { border-right: none; }
        .box-table td { font-size: 8pt; text-align: center; padding: 3px 4px; border-right: 1px solid #000; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .report-table { width: 100%; border-collapse: collapse; font-size: 8pt; }
        .report-table th, .report-table td { border: 1px solid #000; padding: 3px 4px; }
        .report-table th { font-weight: bold; text-align: center; text-transform: uppercase; font-size: 7.5pt; }
        .text-center { text-align: center; } .text-right { text-align: right; } .text-left { text-align: left; }
        .row-anulada { text-decoration: line-through; color: #555; }
        .total-row td { font-weight: bold; border-top: 2px solid #000; }
        @media print { .no-print { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" style="background: #0284c7; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 10pt;">
            🖨️ Imprimir / Guardar PDF
        </button>
    </div>

    <div class="header-title">LIBRO COMPRAS</div>
    <div class="header-subtitle">ACTIVIDAD: <?= htmlspecialchars($emp['actividad'] ?? 'IRE-GENERAL/IRACIS') ?></div>

    <div class="info-grid">
        <div class="info-box box-1">
            <div class="box-title">1. Identificador del Contribuyente</div>
            <table class="box-table">
                <thead><tr><th style="width: 70%;">RAZÓN SOCIAL/NOMBRES Y APELLIDOS</th><th style="width: 30%;">RUC</th></tr></thead>
                <tbody><tr><td class="text-left" style="font-weight: bold;"><?= htmlspecialchars($emp['descripcion'] ?? '') ?></td><td><?= htmlspecialchars($emp['rucEmpresa'] ?? '') ?></td></tr></tbody>
            </table>
        </div>
        <div class="info-box box-2">
            <div class="box-title">2. Periodo Fiscal</div>
            <table class="box-table">
                <thead><tr><th>DESDE</th><th>HASTA</th></tr></thead>
                <tbody><tr><td><?= htmlspecialchars($fechaDesdeFmt ?? '') ?></td><td><?= htmlspecialchars($fechaHastaFmt ?? '') ?></td></tr></tbody>
            </table>
        </div>
        <div class="info-box box-3">
            <div class="box-title">3. Representante Legal</div>
            <table class="box-table">
                <thead><tr><th>NOMBRES Y APELLIDOS</th></tr></thead>
                <tbody><tr><td class="text-left"><?= htmlspecialchars($emp['repLegal'] ?? '') ?></td></tr></tbody>
            </table>
        </div>
        <div class="info-box box-4">
            <div class="box-title">4. Identificador del Contador</div>
            <table class="box-table">
                <thead><tr><th style="width: 65%;">NOMBRES Y APELLIDOS</th><th style="width: 35%;">RUC</th></tr></thead>
                <tbody><tr><td class="text-left"><?= htmlspecialchars($emp['nomContador'] ?? '') ?></td><td><?= htmlspecialchars($emp['rucContador'] ?? '') ?></td></tr></tbody>
            </table>
        </div>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 5%;">DIA-MES</th>
                <th rowspan="2" style="width: 11%;">DOCUMENTO</th>
                <th rowspan="2" style="width: 7%;">TIMB.</th>
                <th colspan="3">PROVEEDOR</th>
                <th rowspan="2" style="width: 4%;">COND</th>
                <th colspan="7">VALORES DE COMPRAS</th>
            </tr>
            <tr>
                <th style="width: 20%;">RAZÓN SOCIAL</th>
                <th style="width: 7%;">RUC</th>
                <th style="width: 3%;">DV</th>
                <th style="width: 7%;">GRAV. 10%</th>
                <th style="width: 6%;">GRAV. 5%</th>
                <th style="width: 6%;">IVA 10%</th>
                <th style="width: 6%;">IVA 5%</th>
                <th style="width: 6%;">EXENTAS</th>
                <th style="width: 5%;">BASE IMP.</th>
                <th style="width: 7%;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recs)): ?>
                <tr><td colspan="14" class="text-center" style="padding: 15px; font-style: italic;">No se registraron movimientos de compras en el periodo seleccionado.</td></tr>
            <?php else: foreach ($recs as $r): ?>
                <tr class="<?= !empty($r['anulada']) ? 'row-anulada' : '' ?>">
                    <td class="text-center"><?= htmlspecialchars($r['diaMes'] ?? '') ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['documento'] ?? '') ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['timbrado'] ?? '') ?></td>
                    <td class="text-left"><?= htmlspecialchars($r['razonSocial'] ?? '') ?></td>
                    <td class="text-right"><?= htmlspecialchars($r['rucNum'] ?? '') ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['rucDv'] ?? '') ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['condicion'] ?? '') ?></td>
                    <td class="text-right"><?= $fmt($r['grav10'] ?? 0) ?></td>
                    <td class="text-right"><?= $fmt($r['grav5'] ?? 0) ?></td>
                    <td class="text-right"><?= $fmt($r['iva10'] ?? 0) ?></td>
                    <td class="text-right"><?= $fmt($r['iva5'] ?? 0) ?></td>
                    <td class="text-right"><?= $fmt($r['exentas'] ?? 0) ?></td>
                    <td class="text-right"><?= $fmt($r['baseImp'] ?? 0) ?></td>
                    <td class="text-right" style="font-weight: bold;"><?= $fmt($r['total'] ?? 0) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="7" class="text-left" style="font-weight: bold;">TOTAL GENERAL</td>
                <td class="text-right"><?= $fmt($tot['grav10'] ?? 0) ?></td>
                <td class="text-right"><?= $fmt($tot['grav5'] ?? 0) ?></td>
                <td class="text-right"><?= $fmt($tot['iva10'] ?? 0) ?></td>
                <td class="text-right"><?= $fmt($tot['iva5'] ?? 0) ?></td>
                <td class="text-right"><?= $fmt($tot['exentas'] ?? 0) ?></td>
                <td class="text-right"><?= $fmt($tot['baseImp'] ?? 0) ?></td>
                <td class="text-right" style="font-size: 8.5pt;"><?= $fmt($tot['total'] ?? 0) ?></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
