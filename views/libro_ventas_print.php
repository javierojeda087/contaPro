<?php
$emp = $empresa ?? [];
$recs = $records ?? [];
$tot = $totales ?? ['grav10'=>0,'grav5'=>0,'iva10'=>0,'iva5'=>0,'exentas'=>0,'total'=>0];
$fmt = fn($n) => number_format((float)($n ?? 0), 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/images/favicon_cp.png?v=2" />
    <title>Impresión Libro Ventas | ContaPro</title>
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000; background: #fff; margin: 0; padding: 10px; }
        .no-print-bar { display: flex; align-items: center; justify-content: space-between; background: #1e293b; color: white; padding: 10px 20px; margin-bottom: 20px; border-radius: 8px; }
        .btn-print { background: #16a34a; color: white; border: none; padding: 8px 18px; border-radius: 6px; font-weight: bold; font-size: 13px; cursor: pointer; }
        .btn-close { background: #64748b; color: white; border: none; padding: 8px 14px; border-radius: 6px; font-weight: bold; font-size: 13px; cursor: pointer; }
        @media print { .no-print-bar { display: none !important; } body { padding: 0; } }
        .report-header-title { text-align: center; margin-bottom: 12px; }
        .report-header-title h1 { margin: 0; font-size: 18px; font-weight: bold; text-transform: uppercase; }
        .report-header-title h2 { margin: 3px 0 0 0; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .top-boxes-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .top-boxes-table td { vertical-align: top; padding: 0 3px; }
        .box-title { font-size: 9px; font-weight: bold; margin-bottom: 2px; }
        .box-subtable { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        .box-subtable th { border: 1px solid #000; background: #fff; font-size: 8.5px; font-weight: bold; text-align: center; padding: 3px 4px; text-transform: uppercase; }
        .box-subtable td { border: 1px solid #000; font-size: 9.5px; text-align: center; padding: 4px; text-transform: uppercase; }
        .report-data-table { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        .report-data-table th { border: 1px solid #000; background: #fff; font-size: 9px; font-weight: bold; text-align: center; padding: 4px 3px; text-transform: uppercase; }
        .report-data-table td { border: 1px solid #000; font-size: 9px; padding: 3px 4px; white-space: nowrap; }
        .report-data-table tfoot td { font-weight: bold; font-size: 9.5px; border-top: 2px solid #000; border-bottom: 2px solid #000; }
        .text-center { text-align: center; } .text-right { text-align: right; } .text-left { text-align: left; }
    </style>
</head>
<body>
    <div class="no-print-bar">
        <div><strong style="font-size: 15px;">Vista Previa de Impresión - Libro Ventas</strong></div>
        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn-print" onclick="window.print()">🖨️ Imprimir</button>
            <button type="button" class="btn-close" onclick="window.close()">Cerrar</button>
        </div>
    </div>

    <div class="report-header-title">
        <h1>LIBRO VENTAS</h1>
        <h2>ACTIVIDAD: <?= htmlspecialchars($emp['actividad'] ?? 'IRE-GENERAL/IRACIS') ?></h2>
    </div>

    <table class="top-boxes-table">
        <tr>
            <td style="width: 32%;">
                <div class="box-title">1. Identificador del Contribuyente</div>
                <table class="box-subtable">
                    <thead><tr><th style="width: 70%;">RAZÓN SOCIAL/NOMBRES Y APELLIDOS</th><th style="width: 30%;">RUC</th></tr></thead>
                    <tbody><tr><td class="text-left"><?= htmlspecialchars($emp['descripcion'] ?? '') ?></td><td><?= htmlspecialchars($emp['rucEmpresa'] ?? '') ?></td></tr></tbody>
                </table>
            </td>
            <td style="width: 18%;">
                <div class="box-title">2. Periodo Fiscal</div>
                <table class="box-subtable">
                    <thead><tr><th style="width: 50%;">DESDE</th><th style="width: 50%;">HASTA</th></tr></thead>
                    <tbody><tr><td><?= htmlspecialchars($fechaDesdeFmt ?? '') ?></td><td><?= htmlspecialchars($fechaHastaFmt ?? '') ?></td></tr></tbody>
                </table>
            </td>
            <td style="width: 20%;">
                <div class="box-title">3. Representante Legal</div>
                <table class="box-subtable">
                    <thead><tr><th>NOMBRES Y APELLIDOS</th></tr></thead>
                    <tbody><tr><td><?= htmlspecialchars($emp['repLegal'] ?? '') ?></td></tr></tbody>
                </table>
            </td>
            <td style="width: 30%;">
                <div class="box-title">4. Identificador del Contador</div>
                <table class="box-subtable">
                    <thead><tr><th style="width: 65%;">NOMBRES Y APELLIDOS</th><th style="width: 35%;">RUC</th></tr></thead>
                    <tbody><tr><td class="text-left"><?= htmlspecialchars($emp['nomContador'] ?? '') ?></td><td><?= htmlspecialchars($emp['rucContador'] ?? '') ?></td></tr></tbody>
                </table>
            </td>
        </tr>
    </table>

    <table class="report-data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 5%;">DIA-MES</th>
                <th rowspan="2" style="width: 11%;">DOCUMENTO</th>
                <th rowspan="2" style="width: 8%;">TIMB.</th>
                <th colspan="3" style="width: 36%;">CLIENTE</th>
                <th rowspan="2" style="width: 4%;">COND</th>
                <th colspan="6" style="width: 36%;">VALORES DE VENTAS</th>
            </tr>
            <tr>
                <th style="width: 25%;">RAZÓN SOCIAL</th>
                <th style="width: 8%;">RUC</th>
                <th style="width: 3%;">DV</th>
                <th style="width: 7%;">GRAV. 10%</th>
                <th style="width: 5%;">GRAV. 5%</th>
                <th style="width: 6%;">IVA 10%</th>
                <th style="width: 5%;">IVA 5%</th>
                <th style="width: 5%;">EXENTAS</th>
                <th style="width: 8%;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recs)): ?>
                <tr><td colspan="13" class="text-center" style="padding: 15px; font-weight: bold; color: #64748b;">No existen registros para el período fiscal indicado.</td></tr>
            <?php else: foreach ($recs as $r): ?>
                <tr style="<?= !empty($r['anulada']) ? 'text-decoration: line-through; background-color: #fee2e2;' : '' ?>">
                    <td class="text-center"><?= htmlspecialchars($r['diaMes'] ?? '') ?></td>
                    <td class="text-left"><?= htmlspecialchars($r['documento'] ?? '') ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['timbrado'] ?? '') ?></td>
                    <td class="text-left"><?= htmlspecialchars($r['razonSocial'] ?? '') ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['rucNum'] ?? '') ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['rucDv'] ?? '') ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['condicion'] ?? '') ?></td>
                    <td class="text-right"><?= $fmt($r['grav10'] ?? 0) ?></td>
                    <td class="text-right"><?= $fmt($r['grav5'] ?? 0) ?></td>
                    <td class="text-right"><?= $fmt($r['iva10'] ?? 0) ?></td>
                    <td class="text-right"><?= $fmt($r['iva5'] ?? 0) ?></td>
                    <td class="text-right"><?= $fmt($r['exentas'] ?? 0) ?></td>
                    <td class="text-right"><?= $fmt($r['total'] ?? 0) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" class="text-left">TOTAL GENERAL</td>
                <td class="text-right"><?= $fmt($tot['grav10'] ?? 0) ?></td>
                <td class="text-right"><?= $fmt($tot['grav5'] ?? 0) ?></td>
                <td class="text-right"><?= $fmt($tot['iva10'] ?? 0) ?></td>
                <td class="text-right"><?= $fmt($tot['iva5'] ?? 0) ?></td>
                <td class="text-right"><?= $fmt($tot['exentas'] ?? 0) ?></td>
                <td class="text-right"><?= $fmt($tot['total'] ?? 0) ?></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
