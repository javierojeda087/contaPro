<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen de Impuesto a la Renta Personal</title>
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            color: #000000;
            background: #e2e8f0;
            margin: 0;
            padding: 58px 0 30px 0;
        }

        .print-toolbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 46px;
            background: #0f172a;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .print-toolbar .btn {
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn-print { background: #0284c7; color: #fff; }
        .btn-excel { background: #16a34a; color: #fff; }
        .btn-close { background: #475569; color: #fff; }

        .sheet {
            width: 273mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 12mm 14mm;
            box-shadow: 0 4px 18px rgba(0,0,0,0.12);
        }

        .report-title {
            text-align: center;
            margin-bottom: 14px;
        }
        .report-title h1 {
            margin: 0;
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 0.3px;
        }

        .header-boxes {
            display: flex;
            gap: 8px;
            align-items: stretch;
            margin-bottom: 18px;
        }
        .box-wrap { display: flex; flex-direction: column; }
        .box-label {
            font-size: 7.5pt;
            margin-bottom: 3px;
        }
        .box-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }
        .box-table th {
            border: 1px solid #000;
            padding: 3px 5px;
            font-size: 7pt;
            font-weight: normal;
            text-align: center;
        }
        .box-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 7.8pt;
            text-align: center;
            height: 22px;
        }

        .section-heading {
            font-size: 9.8pt;
            font-weight: bold;
            margin: 14px 0 6px 0;
        }

        .irp-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            margin-bottom: 14px;
        }
        .irp-table th {
            background-color: #e5e5e5 !important;
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 8.2pt;
            font-weight: bold;
        }
        .irp-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 8.2pt;
            vertical-align: middle;
        }
        .irp-table td.num {
            text-align: right;
        }
        .irp-table tr.bold-row td {
            font-weight: bold;
        }

        @media print {
            body { background: #ffffff; padding: 0; }
            .no-print { display: none !important; }
            .sheet {
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
<?php
    $fmt = fn($n) => number_format((float)($n ?? 0), 0, ',', '.');
    $empRazon    = (string)($empresa['descripcion'] ?? '');
    $empRuc      = (string)($empresa['rucEmpresa'] ?? '');
    $repLegal    = (string)($empresa['repLegal'] ?? '');
    $nomContador = (string)($empresa['nomContador'] ?? '');
    $rucContador = (string)($empresa['rucContador'] ?? '');
    $fDesdeFmt   = (string)($fechaDesdeFmt ?? $fechaDesde ?? '');
    $fHastaFmt   = (string)($fechaHastaFmt ?? $fechaHasta ?? '');
    $anioLbl     = (string)($ejercicio ?? date('Y'));
    $excelUrl    = '/api/reportes/resumen-irp/excel?' . http_build_query([
        'fechaDesde'      => $fechaDesde ?? '',
        'fechaHasta'      => $fechaHasta ?? '',
        'incluirAnulados' => $_GET['incluirAnulados'] ?? 'false',
    ]);
?>

    <div class="print-toolbar no-print">
        <div style="font-weight: 700; font-size: 13px;">
            Vista Previa de Impresión - Resumen IRP (<?= htmlspecialchars($fDesdeFmt) ?> al <?= htmlspecialchars($fHastaFmt) ?>)
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= htmlspecialchars($excelUrl) ?>" class="btn btn-excel">
                <i class="ph ph-file-xls"></i> Descargar Excel
            </a>
            <button type="button" class="btn btn-print" onclick="window.print()">
                <i class="ph ph-printer"></i> Imprimir
            </button>
            <button type="button" class="btn btn-close" onclick="window.close()">
                Cerrar
            </button>
        </div>
    </div>

    <div class="sheet">
        <div class="report-title">
            <h1>RESUMEN DE IMPUESTO A LA RENTA PERSONAL</h1>
        </div>

        <div class="header-boxes">
            <!-- 1. Identificador del Contribuyente -->
            <div class="box-wrap" style="flex: 3.6;">
                <div class="box-label">1. Identificador del Contribuyente</div>
                <table class="box-table">
                    <thead>
                        <tr>
                            <th style="width: 73%;">RAZÓN SOCIAL/NOMBRES Y APELLIDOS</th>
                            <th style="width: 27%;">RUC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: left; padding-left: 6px;"><?= htmlspecialchars($empRazon) ?></td>
                            <td><?= htmlspecialchars($empRuc) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 2. Periodo Fiscal -->
            <div class="box-wrap" style="flex: 1.6;">
                <div class="box-label">2. Periodo Fiscal</div>
                <table class="box-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">DESDE</th>
                            <th style="width: 50%;">HASTA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= htmlspecialchars($fDesdeFmt) ?></td>
                            <td><?= htmlspecialchars($fHastaFmt) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 3. Representante Legal -->
            <div class="box-wrap" style="flex: 2.0;">
                <div class="box-label">3. Representante Legal</div>
                <table class="box-table">
                    <thead>
                        <tr>
                            <th>NOMBRES Y APELLIDOS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= htmlspecialchars($repLegal) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 4. Identificador del Contador -->
            <div class="box-wrap" style="flex: 2.8;">
                <div class="box-label">4. Identificador del Contador</div>
                <table class="box-table">
                    <thead>
                        <tr>
                            <th style="width: 70%;">NOMBRES Y APELLIDOS</th>
                            <th style="width: 30%;">RUC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= htmlspecialchars($nomContador) ?></td>
                            <td><?= htmlspecialchars($rucContador) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabla 1 -->
        <div class="section-heading">Resumen de Ingresos y Egresos en <?= htmlspecialchars($anioLbl) ?></div>
        <table class="irp-table">
            <thead>
                <tr>
                    <th style="width: 58%; text-align: left;">DESCRIPCIÓN</th>
                    <th style="width: 21%; text-align: center;">IMPORTE</th>
                    <th style="width: 21%; text-align: center;">DEDUCIBLES ¹</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total de ingresos</td>
                    <td class="num"><?= $fmt($irp['ingresosImporte'] ?? 0) ?></td>
                    <td class="num"><?= $fmt($irp['ingresosDeducibles'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>Total de egresos</td>
                    <td class="num"><?= $fmt($irp['egresosImporte'] ?? 0) ?></td>
                    <td class="num"><?= $fmt($irp['egresosDeducibles'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>Donaciones, aportes y contribuciones</td>
                    <td class="num"><?= $fmt($irp['donacionesImporte'] ?? 0) ?></td>
                    <td class="num"><?= $fmt($irp['donacionesDeducibles'] ?? 0) ?></td>
                </tr>
                <tr class="bold-row">
                    <td>Saldo al final del periodo</td>
                    <td class="num"><?= $fmt($irp['saldoImporte'] ?? 0) ?></td>
                    <td class="num"><?= $fmt($irp['saldoDeducibles'] ?? 0) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Tabla 2 -->
        <div class="section-heading">Impuesto a la Renta Personal en <?= htmlspecialchars($anioLbl) ?></div>
        <table class="irp-table">
            <thead>
                <tr>
                    <th style="width: 79%; text-align: left;">DESCRIPCIÓN</th>
                    <th style="width: 21%; text-align: center;">IMPORTE IRP</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div style="font-weight: bold;">Monto sobre el que se calcula el impuesto a la renta</div>
                        <div style="font-size: 7.4pt; margin-top: 2px;">Aplica si los ingresos deducibles superan Gs. 80.000.000</div>
                    </td>
                    <td class="num"><?= $fmt($irp['montoBaseIrp'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>
                        <div style="font-weight: bold;">Impuesto al 8%</div>
                        <div style="font-size: 7.4pt; margin-top: 2px;">Aplicable hasta Gs. 50.000.000</div>
                    </td>
                    <td class="num"><?= $fmt($irp['impuesto8'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>
                        <div style="font-weight: bold;">Impuesto al 9%</div>
                        <div style="font-size: 7.4pt; margin-top: 2px;">De Gs. 50.000.001 a Gs. 150.000.000</div>
                    </td>
                    <td class="num"><?= $fmt($irp['impuesto9'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>
                        <div style="font-weight: bold;">Impuesto al 10%</div>
                        <div style="font-size: 7.4pt; margin-top: 2px;">Por ingresos mayores a Gs. 150.000.000</div>
                    </td>
                    <td class="num"><?= $fmt($irp['impuesto10'] ?? 0) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Tabla 3 -->
        <div class="section-heading">Resumen</div>
        <table class="irp-table">
            <thead>
                <tr>
                    <th style="width: 79%; text-align: left;">DESCRIPCIÓN</th>
                    <th style="width: 21%; text-align: center;">IMPORTE</th>
                </tr>
            </thead>
            <tbody>
                <tr class="bold-row">
                    <td>Impuesto a pagar</td>
                    <td class="num"><?= $fmt($irp['impuestoAPagar'] ?? 0) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

</body>
</html>
