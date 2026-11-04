<?php
$emp = $empresa ?? [];
$f   = $f ?? [];
$val = fn(string $k): int => (int)round((float)($f[$k] ?? $f[strtolower($k)] ?? 0));
$fmt = fn(string $k): string => number_format($val($k), 0, ',', '.');

$rucNum          = trim((string)($emp['rucNum'] ?? ''));
$rucDv           = trim((string)($emp['rucDv'] ?? '0'));
$razonSocial     = trim((string)($emp['razon_social'] ?? ''));
$primerApellido  = trim((string)($emp['primer_apellido'] ?? $razonSocial));
$segundoApellido = trim((string)($emp['segundo_apellido'] ?? ''));
$nombres         = trim((string)($emp['nombres'] ?? ''));

$tipoDecl     = strtoupper(trim((string)($f['TIPO_DECLARACION'] ?? $f['tipo_declaracion'] ?? 'O')));
$esOriginal   = ($tipoDecl !== 'R');
$esRectif     = ($tipoDecl === 'R');
$numOrdenRect = trim((string)($f['NUM_ORDEN_RECT'] ?? $f['num_orden_rect'] ?? '0'));
if ($numOrdenRect === '') {
    $numOrdenRect = '0';
}
$esCese = strtoupper(trim((string)($f['CARACTER_CESE'] ?? $f['caracter_cese'] ?? 'N'))) === 'S';

$mesStr  = str_pad((string)($mes ?? date('m')), 2, '0', STR_PAD_LEFT);
$anioStr = str_pad((string)($anio ?? date('Y')), 4, '0', STR_PAD_LEFT);
$mDigits = str_split(substr($mesStr, -2));
$aDigits = str_split(substr($anioStr, -4));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/images/favicon_cp.png?v=2" />
    <title>Formulario 120 - Impuesto al Valor Agregado (Versión 4) | ContaPro</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 8mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9.5px;
            color: #000;
            background: #f1f5f9;
            margin: 0;
            padding: 16px;
        }
        .no-print-bar {
            max-width: 920px;
            margin: 0 auto 12px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 14px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .btn-print {
            padding: 7px 18px;
            background: #0284c7;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            font-size: 12px;
            cursor: pointer;
        }
        .btn-print:hover {
            background: #0369a1;
        }
        .page {
            max-width: 920px;
            margin: 0 auto;
            background: #ffffff;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 14px rgba(0,0,0,0.07);
        }
        .norm-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 0.4px;
            margin-bottom: 6px;
        }
        .meta-line {
            display: flex;
            justify-content: space-between;
            font-size: 9.5px;
            margin-bottom: 6px;
            padding: 0 2px;
            color: #111;
        }
        table.form-tbl {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            table-layout: fixed;
        }
        table.form-tbl th,
        table.form-tbl td {
            border: 1px solid #5b828e;
            padding: 2px 4px;
            vertical-align: middle;
            line-height: 1.18;
        }
        table.form-tbl thead th {
            background: #e3ecef;
            font-weight: bold;
            text-align: left;
            font-size: 9px;
        }
        table.form-tbl thead th.th-center {
            text-align: center;
        }
        .logo-cell {
            width: 165px;
            text-align: center;
            vertical-align: middle !important;
            padding: 6px 4px !important;
        }
        .dnit-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            margin-bottom: 4px;
        }
        .dnit-word {
            font-size: 20px;
            font-weight: 900;
            color: #1e3a8a;
            letter-spacing: 0.5px;
            line-height: 1;
        }
        .dnit-sub {
            font-size: 6.5px;
            color: #334155;
            font-weight: bold;
            line-height: 1.05;
            text-align: left;
        }
        .iva-title {
            font-size: 9.5px;
            line-height: 1.15;
            margin-top: 4px;
        }
        .iva-num {
            font-size: 28px;
            font-weight: 900;
            line-height: 1;
            margin-top: 4px;
        }
        .lbl-small {
            font-size: 8.5px;
            color: #222;
            display: block;
            margin-bottom: 1px;
        }
        .val-strong {
            font-size: 10px;
            font-weight: bold;
            color: #000;
            min-height: 13px;
        }
        .chk-box {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid #1e293b;
            text-align: center;
            line-height: 11px;
            font-size: 9px;
            font-weight: bold;
            background: #fff;
        }
        .chk-box.checked {
            background: #0284c7;
            color: #fff;
            border-color: #0284c7;
        }
        .period-digits {
            display: inline-flex;
            border-collapse: collapse;
        }
        .period-digits span {
            display: inline-block;
            width: 16px;
            height: 15px;
            line-height: 14px;
            text-align: center;
            font-weight: bold;
            font-size: 9.5px;
            border: 1px solid #5b828e;
            margin-left: -1px;
            background: #fff;
        }
        .banner-contrib {
            text-align: center;
            font-weight: bold;
            font-size: 9.5px;
            margin: 5px 0 4px 0;
        }
        .col-inc {
            width: 26px;
            text-align: center !important;
        }
        .col-cas {
            width: 22px;
            text-align: center;
            font-size: 8.5px;
            color: #111;
            background: #f4f8f9;
        }
        .col-val {
            width: 108px;
            text-align: right;
            font-weight: bold;
            font-size: 9.5px;
            padding-right: 5px !important;
        }
        .shaded {
            background: repeating-linear-gradient(
                to bottom,
                #d8e2e6,
                #d8e2e6 1.5px,
                #c6d4d9 1.5px,
                #c6d4d9 3px
            ) !important;
        }
        .row-total td {
            font-weight: bold;
            background: #f4f8f9;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .no-print-bar {
                display: none !important;
            }
            .page {
                max-width: 100%;
                border: none;
                box-shadow: none;
                padding: 0;
            }
            table.form-tbl {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="no-print-bar">
        <div style="font-size: 12px; font-weight: bold; color: #334155;">
            Formulario N° 120 - Versión 4 | RUC: <?= htmlspecialchars($rucNum . '-' . $rucDv) ?> | Período: <?= htmlspecialchars($mesStr . '/' . $anioStr) ?>
        </div>
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir Formulario 120 V4</button>
    </div>

    <div class="page">
        <div class="norm-title">DECLARACIÓN JURADA NORMALIZADA</div>

        <div class="meta-line">
            <span><strong>Formulario:</strong> 120 V4</span>
            <span><strong>Contribuyente:</strong> <?= htmlspecialchars($rucNum) ?></span>
            <span><strong>Período:</strong> <?= htmlspecialchars($mesStr . '/' . $anioStr) ?></span>
            <span><strong>Fecha Impresión:</strong> <?= date('d/m/Y H:i') ?></span>
            <span><strong>Sistema:</strong> ContaPro</span>
        </div>

        <!-- ENCABEZADO OFICIAL V4 -->
        <table class="form-tbl">
            <colgroup>
                <col style="width: 165px;">
                <col style="width: 24px;">
                <col style="width: 24px;">
                <col style="width: 235px;">
                <col style="width: 24px;">
                <col style="width: 140px;">
                <col style="width: 125px;">
                <col style="width: 45px;">
            </colgroup>
            <tbody>
                <tr>
                    <td rowspan="8" class="logo-cell">
                        <div class="dnit-brand">
                            <span class="dnit-word">DNIT</span>
                            <div class="dnit-sub">Dirección Nacional de<br>Ingresos Tributarios</div>
                        </div>
                        <div class="iva-title">
                            IMPUESTO AL VALOR<br>
                            AGREGADO VERSION 4<br>
                            <strong>V.4 Enero/2020</strong>
                        </div>
                        <div class="iva-num">120</div>
                    </td>
                    <td colspan="4" class="text-center font-bold" style="font-size: 8px; background: #e3ecef;">
                        PARA LLENAR LEA EL INSTRUCTIVO DISPONIBLE EN LA WEB
                    </td>
                    <td colspan="3" class="text-center font-bold" style="font-size: 8px; background: #e3ecef;">
                        LOS IMPORTES SE CONSIGNARÁN SIN CÉNTIMOS
                    </td>
                </tr>
                <tr>
                    <td colspan="4">
                        <span class="lbl-small">Número de Orden</span>
                        <div class="val-strong text-center"><?= htmlspecialchars((string)($f['NUM_ORDEN'] ?? '')) ?></div>
                    </td>
                    <td colspan="2">
                        <span class="lbl-small">RUC</span>
                        <div class="val-strong text-center"><?= htmlspecialchars($rucNum) ?></div>
                    </td>
                    <td class="text-center">
                        <span class="lbl-small">DV</span>
                        <div class="val-strong text-center"><?= htmlspecialchars($rucDv) ?></div>
                    </td>
                </tr>
                <tr>
                    <td colspan="4">
                        <span class="lbl-small">Razón Social/Primer Apellido</span>
                        <div class="val-strong"><?= htmlspecialchars($primerApellido) ?></div>
                    </td>
                    <td colspan="3">
                        <span class="lbl-small">Segundo Apellido</span>
                        <div class="val-strong"><?= htmlspecialchars($segundoApellido) ?></div>
                    </td>
                </tr>
                <tr>
                    <td colspan="7">
                        <span class="lbl-small">Nombres</span>
                        <div class="val-strong"><?= htmlspecialchars($nombres !== '' ? $nombres : ($segundoApellido === '' ? '' : $razonSocial)) ?></div>
                    </td>
                </tr>
                <tr>
                    <td class="col-cas">01</td>
                    <td class="text-center"><span class="chk-box <?= $esOriginal ? 'checked' : '' ?>"><?= $esOriginal ? '✓' : '' ?></span></td>
                    <td colspan="2">Declaración Jurada Original</td>
                    <td colspan="3" class="text-center font-bold" style="font-size: 8.5px;">Número de Orden de Declaración se rectifica</td>
                </tr>
                <tr>
                    <td class="col-cas">02</td>
                    <td class="text-center"><span class="chk-box <?= $esRectif ? 'checked' : '' ?>"><?= $esRectif ? '✓' : '' ?></span></td>
                    <td colspan="2">Declaración Jurada Rectificativa</td>
                    <td class="col-cas">03</td>
                    <td colspan="2" class="text-right font-bold"><?= htmlspecialchars($numOrdenRect) ?></td>
                </tr>
                <tr>
                    <td rowspan="2" class="col-cas">05</td>
                    <td rowspan="2" class="text-center"><span class="chk-box <?= $esCese ? 'checked' : '' ?>"><?= $esCese ? '✓' : '' ?></span></td>
                    <td rowspan="2" colspan="2" style="font-size: 8.5px;">Declaración Jurada en Carácter de Cese de Actividades, Clausura o Cierre Definitivo</td>
                    <td rowspan="2" class="col-cas">04</td>
                    <td colspan="2" class="text-center font-bold" style="font-size: 8.5px; background: #e3ecef;">Periodo / Ejercicio Fiscal</td>
                </tr>
                <tr>
                    <td colspan="2" class="text-center" style="padding: 2px 4px;">
                        <span style="font-size: 8px; margin-right: 3px;">Mes</span>
                        <span class="period-digits">
                            <span><?= htmlspecialchars($mDigits[0] ?? '0') ?></span>
                            <span><?= htmlspecialchars($mDigits[1] ?? '0') ?></span>
                        </span>
                        <span style="font-size: 8px; margin: 0 3px 0 8px;">Año</span>
                        <span class="period-digits">
                            <span><?= htmlspecialchars($aDigits[0] ?? '2') ?></span>
                            <span><?= htmlspecialchars($aDigits[1] ?? '0') ?></span>
                            <span><?= htmlspecialchars($aDigits[2] ?? '2') ?></span>
                            <span><?= htmlspecialchars($aDigits[3] ?? '6') ?></span>
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="banner-contrib">
            PARA CONTRIBUYENTES QUE REALICEN OPERACIONES GRAVADAS Y EXONERADAS (INCLUIDAS LAS EXPORTACIONES)
        </div>

        <!-- =============================================================== -->
        <!-- RUBRO 1                                                         -->
        <!-- =============================================================== -->
        <table class="form-tbl">
            <colgroup>
                <col class="col-inc">
                <col>
                <col class="col-cas">
                <col class="col-val">
                <col class="col-cas">
                <col class="col-val">
                <col class="col-cas">
                <col class="col-val">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2" class="col-inc">INC.</th>
                    <th rowspan="2">RUBRO 1 - ENAJENACIÓN DE BIENES Y/O PRESTACIÓN DE SERVICIOS DEL PERIODO</th>
                    <th rowspan="2" colspan="2" class="th-center">MONTO IMPONIBLE<br>-I-</th>
                    <th colspan="4" class="th-center">IVA DÉBITO</th>
                </tr>
                <tr>
                    <th colspan="2" class="th-center">AL 5%<br>-II-</th>
                    <th colspan="2" class="th-center">AL 10%<br>-III-</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="col-inc">a</td>
                    <td>Enajenación de bienes y/o prestación de servicios gravados con tasa del 10%</td>
                    <td class="col-cas">10</td>
                    <td class="col-val"><?= $fmt('C10') ?></td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">22</td>
                    <td class="col-val"><?= $fmt('C22') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">b</td>
                    <td>Enajenación de productos agrícolas en estado natural y sus derivados del primer proceso de elaboración o industrialización gravados con tasa del 5%</td>
                    <td class="col-cas">150</td>
                    <td class="col-val"><?= $fmt('C150') ?></td>
                    <td class="col-cas">156</td>
                    <td class="col-val"><?= $fmt('C156') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">c</td>
                    <td>Enajenación de otros bienes y/o prestación de servicios gravados con tasa del 5%</td>
                    <td class="col-cas">151</td>
                    <td class="col-val"><?= $fmt('C151') ?></td>
                    <td class="col-cas">157</td>
                    <td class="col-val"><?= $fmt('C157') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">d</td>
                    <td>Enajenación de bienes, prestación de servicios o ingresos exonerados o no alcanzados por el Impuesto</td>
                    <td class="col-cas">12</td>
                    <td class="col-val"><?= $fmt('C12') ?></td>
                    <td colspan="2" class="shaded"></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">e</td>
                    <td>Exportación de productos agrícolas en estado natural y sus derivados del primer proceso de elaboración o industrialización</td>
                    <td class="col-cas">152</td>
                    <td class="col-val"><?= $fmt('C152') ?></td>
                    <td colspan="2" class="shaded"></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">f</td>
                    <td>Prestación de Servicios de Flete Internacional para la exportación de bienes</td>
                    <td class="col-cas">153</td>
                    <td class="col-val"><?= $fmt('C153') ?></td>
                    <td colspan="2" class="shaded"></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">g</td>
                    <td>Exportación de otros bienes</td>
                    <td class="col-cas">14</td>
                    <td class="col-val"><?= $fmt('C14') ?></td>
                    <td colspan="2" class="shaded"></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">h</td>
                    <td>Ajustes de precios, devoluciones realizadas, descuentos obtenidos y recupero de Impuestos por operaciones incobrables, declaradas a la tasa del 10%</td>
                    <td class="col-cas">15</td>
                    <td class="col-val"><?= $fmt('C15') ?></td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">23</td>
                    <td class="col-val"><?= $fmt('C23') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">i</td>
                    <td>Ajustes de precios, devoluciones realizadas, descuentos obtenidos y recupero de Impuestos por operaciones incobrables, declaradas a la tasa del 5%, correspondientes a la adquisición de productos agrícolas en estado natural y sus derivados del primer proceso de elaboración o industrialización</td>
                    <td class="col-cas">154</td>
                    <td class="col-val"><?= $fmt('C154') ?></td>
                    <td class="col-cas">158</td>
                    <td class="col-val"><?= $fmt('C158') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">j</td>
                    <td>Ajustes de precios, devoluciones realizadas, descuentos obtenidos y recupero de Impuestos por operaciones incobrables, declaradas a la tasa del 5%, correspondientes a la adquisición de otros bienes y servicios</td>
                    <td class="col-cas">155</td>
                    <td class="col-val"><?= $fmt('C155') ?></td>
                    <td class="col-cas">159</td>
                    <td class="col-val"><?= $fmt('C159') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">k</td>
                    <td>Ajustes de precios, devoluciones realizadas, descuentos obtenidos y recupero por operaciones incobrables, exonerados o no alcanzados por el Impuesto</td>
                    <td class="col-cas">17</td>
                    <td class="col-val"><?= $fmt('C17') ?></td>
                    <td colspan="2" class="shaded"></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">l</td>
                    <td>TOTAL (Col. I: Inc. a+b+c+d+e+f+g+h+i+j+k); (Col. II: b+c+i+j); (Col. III: Inc. a+h)</td>
                    <td class="col-cas">18</td>
                    <td class="col-val"><?= $fmt('C18') ?></td>
                    <td class="col-cas">21</td>
                    <td class="col-val"><?= $fmt('C21') ?></td>
                    <td class="col-cas">24</td>
                    <td class="col-val"><?= $fmt('C24') ?></td>
                </tr>
            </tbody>
        </table>

        <!-- =============================================================== -->
        <!-- RUBRO 2                                                         -->
        <!-- =============================================================== -->
        <table class="form-tbl">
            <colgroup>
                <col class="col-inc">
                <col>
                <col class="col-cas">
                <col style="width: 145px;">
            </colgroup>
            <thead>
                <tr>
                    <th class="col-inc">INC.</th>
                    <th>RUBRO 2 - ENAJENACIÓN DE BIENES Y/O PRESTACIÓN DE SERVICIOS DE LOS ÚLTIMOS SEIS (6) MESES (INCLUIDO EL PERIODO QUE SE DECLARA)</th>
                    <th colspan="2" class="th-center">MONTO ACUMULADO</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="col-inc">a</td>
                    <td>Enajenación de bienes y/o prestación de servicios gravados en el mercado interno, con excepción de los productos agrícolas en estado natural</td>
                    <td class="col-cas">160</td>
                    <td class="col-val"><?= $fmt('C160') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">b</td>
                    <td>Enajenación de productos agrícolas en estado natural y sus derivados del primer proceso de elaboración o industrialización en el mercado interno</td>
                    <td class="col-cas">161</td>
                    <td class="col-val"><?= $fmt('C161') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">c</td>
                    <td>Enajenación de bienes y/o prestación de servicios exonerados o no alcanzados</td>
                    <td class="col-cas">26</td>
                    <td class="col-val"><?= $fmt('C26') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">d</td>
                    <td>TOTAL DE OPERACIONES EN EL MERCADO INTERNO (Inc. a+b+c)</td>
                    <td class="col-cas">27</td>
                    <td class="col-val"><?= $fmt('C27') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">e</td>
                    <td>Exportación de productos agrícolas en estado natural y sus derivados del primer proceso de elaboración o industrialización</td>
                    <td class="col-cas">162</td>
                    <td class="col-val"><?= $fmt('C162') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">f</td>
                    <td>Servicios de flete internacional prestados por empresas transportadoras para la exportación de bienes</td>
                    <td class="col-cas">163</td>
                    <td class="col-val"><?= $fmt('C163') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">g</td>
                    <td>Exportación de otros bienes</td>
                    <td class="col-cas">29</td>
                    <td class="col-val"><?= $fmt('C29') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">h</td>
                    <td>TOTAL DE OPERACIONES DE EXPORTACIÓN (Inc. e+f+g)</td>
                    <td class="col-cas">30</td>
                    <td class="col-val"><?= $fmt('C30') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">i</td>
                    <td>TOTAL DE OPERACIONES ACUMULADAS (Inc. d+h)</td>
                    <td class="col-cas">31</td>
                    <td class="col-val"><?= $fmt('C31') ?></td>
                </tr>
            </tbody>
        </table>

        <!-- =============================================================== -->
        <!-- RUBRO 3                                                         -->
        <!-- =============================================================== -->
        <table class="form-tbl">
            <colgroup>
                <col class="col-inc">
                <col>
                <col class="col-cas">
                <col class="col-val">
                <col class="col-cas">
                <col class="col-val">
                <col class="col-cas">
                <col class="col-val">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2" class="col-inc">INC.</th>
                    <th rowspan="2">RUBRO 3 - COMPRAS LOCALES E IMPORTACIONES DEL PERIODO</th>
                    <th colspan="4" class="th-center">MONTO IMPONIBLE</th>
                    <th rowspan="2" colspan="2" class="th-center">IVA CRÉDITO<br>-III-</th>
                </tr>
                <tr>
                    <th colspan="2" class="th-center">AL 5%<br>-I-</th>
                    <th colspan="2" class="th-center">AL 10%<br>-II-</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="col-inc">a</td>
                    <td>IVA Crédito atribuido directamente a operaciones gravadas en el mercado interno</td>
                    <td class="col-cas">32</td>
                    <td class="col-val"><?= $fmt('C32') ?></td>
                    <td class="col-cas">35</td>
                    <td class="col-val"><?= $fmt('C35') ?></td>
                    <td class="col-cas">38</td>
                    <td class="col-val"><?= $fmt('C38') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">b</td>
                    <td>IVA Crédito atribuido indistintamente a operaciones gravadas, exoneradas o no alcanzadas</td>
                    <td class="col-cas">33</td>
                    <td class="col-val"><?= $fmt('C33') ?></td>
                    <td class="col-cas">36</td>
                    <td class="col-val"><?= $fmt('C36') ?></td>
                    <td class="col-cas">39</td>
                    <td class="col-val"><?= $fmt('C39') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">c</td>
                    <td>IVA Crédito atribuido proporcionalmente a operaciones gravadas en el mercado interno<br><strong>Rubro 3, Col. III: Inc. b x (Rubro 2 Inc. a+b/ Inc. d)</strong></td>
                    <td colspan="2" class="shaded"></td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">164</td>
                    <td class="col-val"><?= $fmt('C164') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">d</td>
                    <td>IVA Crédito atribuido proporcionalmente a operaciones gravadas en el mercado interno y a exportación (Proviene de la casilla 216 de la hoja de cálculo)</td>
                    <td colspan="2" class="shaded"></td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">165</td>
                    <td class="col-val"><?= $fmt('C165') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">e</td>
                    <td>IVA Crédito por ajustes de precios, devoluciones y descuentos otorgados, por ventas ya declaradas y operaciones declaradas incobrables</td>
                    <td class="col-cas">34</td>
                    <td class="col-val"><?= $fmt('C34') ?></td>
                    <td class="col-cas">37</td>
                    <td class="col-val"><?= $fmt('C37') ?></td>
                    <td class="col-cas">42</td>
                    <td class="col-val"><?= $fmt('C42') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">f</td>
                    <td>TOTAL DE IVA CRÉDITO PARA OPERACIONES EN EL MERCADO INTERNO (Col. III: Inc. a+c+d+e)</td>
                    <td colspan="2" class="shaded"></td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">43</td>
                    <td class="col-val"><?= $fmt('C43') ?></td>
                </tr>
            </tbody>
        </table>

        <!-- =============================================================== -->
        <!-- RUBRO 4                                                         -->
        <!-- =============================================================== -->
        <table class="form-tbl">
            <colgroup>
                <col class="col-inc">
                <col>
                <col class="col-cas">
                <col style="width: 145px;">
            </colgroup>
            <thead>
                <tr>
                    <th class="col-inc">INC.</th>
                    <th>RUBRO 4 - DETERMINACIÓN DEL IMPUESTO O DEL SALDO TÉCNICO</th>
                    <th colspan="2" class="th-center">MONTO</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="col-inc">a</td>
                    <td>IVA Débito (Proviene del Rubro 1, Col. II y III: Inc. l)</td>
                    <td class="col-cas">44</td>
                    <td class="col-val"><?= $fmt('C44') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">b</td>
                    <td>IVA Crédito (Proviene del Rubro 3, Inc. f)</td>
                    <td class="col-cas">45</td>
                    <td class="col-val"><?= $fmt('C45') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">c</td>
                    <td>Saldo a favor del contribuyente del período anterior por operaciones en el mercado interno (Proviene del Rubro 4, Inc. f; de la declaración jurada del período anterior)</td>
                    <td class="col-cas">46</td>
                    <td class="col-val"><?= $fmt('C46') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">d</td>
                    <td><strong>SALDO A FAVOR DEL CONTRIBUYENTE</strong> cuando el Inc. a sea menor que Inc. b+Inc. c</td>
                    <td class="col-cas">166</td>
                    <td class="col-val"><?= $fmt('C166') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">e</td>
                    <td>Saldo a favor del contribuyente a remitir en beneficio del Fisco (Art. 91 de la Ley). Hasta el monto del Inc. d</td>
                    <td class="col-cas">167</td>
                    <td class="col-val"><?= $fmt('C167') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">f</td>
                    <td><strong>SALDO A FAVOR DEL CONTRIBUYENTE (Monto a trasladar en el Inc. c del presente Rubro, en el siguiente período fiscal), Inc. d - Inc. e</strong></td>
                    <td class="col-cas">47</td>
                    <td class="col-val"><?= $fmt('C47') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">g</td>
                    <td>Saldo a favor del fisco, cuando Inc. a sea mayor que Inc. b+Inc. c</td>
                    <td class="col-cas">48</td>
                    <td class="col-val"><?= $fmt('C48') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">h</td>
                    <td>Monto del IVA Crédito utilizado en el período que se liquida para operaciones gravadas (Proviene de la casilla 148 del Anexo Exportador)</td>
                    <td class="col-cas">49</td>
                    <td class="col-val"><?= $fmt('C49') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">i</td>
                    <td>Deducción conforme al Art. 7° y 8° de la Ley N° 4.962/2013 por incorporación de personas con discapacidad y en concepto de gastos y costos por adecuación del entorno físico, tecnológica, etc. y contratación de facilitadores laborales (No trasladable)</td>
                    <td class="col-cas">168</td>
                    <td class="col-val"><?= $fmt('C168') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">j</td>
                    <td><strong>IMPUESTO DETERMINADO (Inc. g - Inc. h - Inc. i)</strong></td>
                    <td class="col-cas">50</td>
                    <td class="col-val"><?= $fmt('C50') ?></td>
                </tr>
            </tbody>
        </table>

        <!-- =============================================================== -->
        <!-- RUBRO 5                                                         -->
        <!-- =============================================================== -->
        <table class="form-tbl">
            <colgroup>
                <col class="col-inc">
                <col>
                <col class="col-cas">
                <col style="width: 120px;">
                <col class="col-cas">
                <col style="width: 120px;">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2" class="col-inc">INC.</th>
                    <th rowspan="2">RUBRO 5 - IMPUESTO DETERMINADO Y/O SALDO FINANCIERO A FAVOR DEL CONTRIBUYENTE</th>
                    <th colspan="4" class="th-center">MONTO A FAVOR DEL</th>
                </tr>
                <tr>
                    <th colspan="2" class="th-center">CONTRIBUYENTE<br>-I-</th>
                    <th colspan="2" class="th-center">FISCO<br>-II-</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="col-inc">a</td>
                    <td>Impuesto determinado para operaciones gravadas (Proviene del Rubro 4, Inc. j)</td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">55</td>
                    <td class="col-val"><?= $fmt('C55') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">b</td>
                    <td>Saldo a favor del contribuyente del período anterior (Proviene del Rubro 5, Col. I: Inc. g, de la declaración jurada del período anterior)</td>
                    <td class="col-cas">51</td>
                    <td class="col-val"><?= $fmt('C51') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">c</td>
                    <td>Retenciones computables por operaciones gravadas</td>
                    <td class="col-cas">52</td>
                    <td class="col-val"><?= $fmt('C52') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">d</td>
                    <td>Percepciones computables por operaciones gravadas</td>
                    <td class="col-cas">169</td>
                    <td class="col-val"><?= $fmt('C169') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">e</td>
                    <td>Multa por presentar la Declaración Jurada con posterioridad al vencimiento</td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">56</td>
                    <td class="col-val"><?= $fmt('C56') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">f</td>
                    <td><strong>SUBTOTALES: (Col. I: Inc. b+c+d); (Col. II: Inc. a+e)</strong></td>
                    <td class="col-cas">53</td>
                    <td class="col-val"><?= $fmt('C53') ?></td>
                    <td class="col-cas">57</td>
                    <td class="col-val"><?= $fmt('C57') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">g</td>
                    <td><strong>SALDO A FAVOR DEL CONTRIBUYENTE (Monto a trasladar al siguiente período fiscal en el Inc. b del presente Rubro).</strong> Diferencia entre Col. I y II: Inc. f, cuando Col. I sea mayor que Col. II. No trasladable al Rubro 4</td>
                    <td class="col-cas">54</td>
                    <td class="col-val"><?= $fmt('C54') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">h</td>
                    <td><strong>SALDO A PAGAR A FAVOR DEL FISCO (Diferencia entre Col. I y II: Inc. f, cuando Col. II sea mayor que Col. I)</strong></td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">58</td>
                    <td class="col-val"><?= $fmt('C58') ?></td>
                </tr>
            </tbody>
        </table>

        <!-- =============================================================== -->
        <!-- RUBRO 6                                                         -->
        <!-- =============================================================== -->
        <table class="form-tbl">
            <colgroup>
                <col class="col-inc">
                <col>
                <col class="col-cas">
                <col style="width: 120px;">
                <col class="col-cas">
                <col style="width: 120px;">
            </colgroup>
            <thead>
                <tr>
                    <th class="col-inc">INC.</th>
                    <th>RUBRO 6 - INFORMACIÓN DE LAS COMPRAS DEL PERIODO, VINCULADAS A OPERACIONES EXONERADAS O NO ALCANZADAS POR EL IMPUESTO</th>
                    <th colspan="2" class="th-center">MONTO<br>-I-</th>
                    <th colspan="2" class="th-center">IVA<br>-II-</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="col-inc">a</td>
                    <td>Compras con IVA Crédito del 10% relacionadas directamente a operaciones exoneradas o no alcanzadas por el Impuesto</td>
                    <td class="col-cas">59</td>
                    <td class="col-val"><?= $fmt('C59') ?></td>
                    <td class="col-cas">65</td>
                    <td class="col-val"><?= $fmt('C65') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">b</td>
                    <td>Compras con IVA Crédito del 5% relacionadas directamente a operaciones exoneradas o no alcanzadas por el Impuesto</td>
                    <td class="col-cas">60</td>
                    <td class="col-val"><?= $fmt('C60') ?></td>
                    <td class="col-cas">66</td>
                    <td class="col-val"><?= $fmt('C66') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">c</td>
                    <td>Compras exentas relacionadas a operaciones exoneradas, no alcanzadas y a exportaciones</td>
                    <td class="col-cas">61</td>
                    <td class="col-val"><?= $fmt('C61') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">d</td>
                    <td>Compras exentas relacionadas a operaciones exoneradas o no alcanzadas por el Impuesto</td>
                    <td class="col-cas">62</td>
                    <td class="col-val"><?= $fmt('C62') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">e</td>
                    <td>Ajustes de precios, descuentos otorgados y/o devoluciones recibidas, por enajenación de bienes o prestación de servicios exonerados o no alcanzados por el Impuesto</td>
                    <td class="col-cas">63</td>
                    <td class="col-val"><?= $fmt('C63') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">f</td>
                    <td>Impuesto al Valor Agregado - Costo o Gasto deducible en el IRE o IRP</td>
                    <td class="col-cas">64</td>
                    <td class="col-val"><?= $fmt('C64') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">g</td>
                    <td>Impuesto al Valor Agregado - Costo o Gasto por remisión del saldo IVA Crédito al fisco, no deducible en el IRE o IRP</td>
                    <td class="col-cas">170</td>
                    <td class="col-val"><?= $fmt('C170') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
            </tbody>
        </table>

        <!-- PIE DE FIRMA Y CERTIFICACIÓN -->
        <table class="form-tbl" style="margin-top: 6px; margin-bottom: 2px;">
            <tbody>
                <tr>
                    <td colspan="2">El que suscribe <strong><?= htmlspecialchars($razonSocial) ?></strong></td>
                </tr>
                <tr>
                    <td style="width: 50%;">en su carácter de Contribuyente / Representante Legal</td>
                    <td style="width: 50%;">con Documento / RUC No. <strong><?= htmlspecialchars($rucNum . '-' . $rucDv) ?></strong></td>
                </tr>
                <tr>
                    <td colspan="2">declara que los datos consignados en este formulario son correctos, íntegros, verdaderos y han sido confeccionados sin omitir dato alguno.</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 5px 4px;">
                        Lugar: _________________________________________, fecha (dd/mm/Año): _____/_____/_________.
                    </td>
                </tr>
                <tr style="height: 60px;">
                    <td style="vertical-align: bottom; text-align: center; padding-bottom: 4px;">
                        _______________________________________<br>
                        Firma
                    </td>
                    <td style="vertical-align: top; text-align: center; padding-top: 4px;">
                        CERTIFICACIÓN O SELLO DE RECEPCIÓN
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="font-size: 8px;">
                        Estimado Contribuyente: Le recordamos que los pagos que efectúe emergentes de esta declaración, serán imputados en su cuenta corriente conforme a lo señalado en la Ley N.° 125/1991, Art. 162.
                    </td>
                </tr>
            </tbody>
        </table>
        <div style="text-align: right; font-size: 8px; color: #333;">
            (*) Ley N° 7143/2023 «Que crea la Dirección Nacional de Ingresos Tributarios»
        </div>
    </div>
</body>
</html>
