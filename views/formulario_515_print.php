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
$numOrden     = trim((string)($f['NUM_ORDEN'] ?? $f['num_orden'] ?? ''));
$numOrdenRect = trim((string)($f['NUM_ORDEN_RECT'] ?? $f['num_orden_rect'] ?? '0'));
if ($numOrdenRect === '') {
    $numOrdenRect = '0';
}
$esCese = strtoupper(trim((string)($f['CARACTER_CESE'] ?? $f['caracter_cese'] ?? 'N'))) === 'S';

$anioStr = str_pad((string)($anio ?? date('Y')), 4, '0', STR_PAD_LEFT);
$aDigits = str_split(substr($anioStr, -4));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/images/favicon_cp.png?v=2" />
    <title>Formulario 515 - Impuesto a la Renta Personal (Versión 1) | ContaPro</title>
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
            padding: 2.5px 4px;
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
            width: 175px;
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
        .irp-title {
            font-size: 8.5px;
            line-height: 1.15;
            margin-top: 4px;
        }
        .irp-num {
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
            width: 18px;
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
            background: #e3ecef;
            border: 1px solid #5b828e;
            padding: 3.5px 6px;
            margin: 4px 0 6px 0;
        }
        .col-inc {
            width: 28px;
            text-align: center !important;
        }
        .col-cas {
            width: 24px;
            text-align: center;
            font-size: 8.5px;
            color: #111;
            background: #f4f8f9;
            font-weight: bold;
        }
        .col-val {
            width: 125px;
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
            Formulario N° 515 - Versión 1 (IRP Servicios Personales) | RUC: <?= htmlspecialchars($rucNum . '-' . $rucDv) ?> | Ejercicio: <?= htmlspecialchars($anioStr) ?>
        </div>
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir Formulario 515 V1</button>
    </div>

    <div class="page">
        <div class="norm-title">DECLARACIÓN JURADA <?= $esOriginal ? 'ORIGINAL' : 'RECTIFICATIVA' ?></div>

        <div class="meta-line">
            <span><strong>Formulario:</strong>515 V1</span>
            <span><strong>Contribuyente:</strong> <?= htmlspecialchars($rucNum) ?></span>
            <span><strong>Fecha:</strong> <?= date('d/m/Y H:i') ?></span>
            <span><strong>Presentado por:</strong> INTER</span>
        </div>

        <!-- ENCABEZADO OFICIAL V1 -->
        <table class="form-tbl">
            <colgroup>
                <col style="width: 175px;">
                <col style="width: 24px;">
                <col style="width: 24px;">
                <col style="width: 230px;">
                <col style="width: 24px;">
                <col style="width: 140px;">
                <col style="width: 120px;">
                <col style="width: 45px;">
            </colgroup>
            <tbody>
                <tr>
                    <td rowspan="7" class="logo-cell">
                        <div class="dnit-brand">
                            <span class="dnit-word">DNIT</span>
                            <div class="dnit-sub">Dirección Nacional de<br>Ingresos Tributarios</div>
                        </div>
                        <div class="irp-title">
                            IMPUESTO A LA RENTA PERSONAL<br>
                            RENTAS DERIVADAS DE LA<br>
                            PRESTACIÓN DE SERVICIOS<br>
                            PERSONALES<br>
                            <strong>VERSION 1</strong>
                        </div>
                        <div class="irp-num">515</div>
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
                        <div class="val-strong text-center"><?= htmlspecialchars($numOrden) ?></div>
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
                    <td colspan="3" class="font-bold">Número de Orden de Declaración se rectifica</td>
                </tr>
                <tr>
                    <td class="col-cas">02</td>
                    <td class="text-center"><span class="chk-box <?= $esRectif ? 'checked' : '' ?>"><?= $esRectif ? '✓' : '' ?></span></td>
                    <td colspan="2">Declaración Jurada Rectificativa</td>
                    <td class="col-cas">03</td>
                    <td colspan="2" class="text-right font-bold" style="padding-right: 8px;"><?= htmlspecialchars($numOrdenRect) ?></td>
                </tr>
                <tr>
                    <td class="col-cas">05</td>
                    <td class="text-center"><span class="chk-box <?= $esCese ? 'checked' : '' ?>"><?= $esCese ? '✓' : '' ?></span></td>
                    <td colspan="2" style="font-size: 8.5px;">Declaración Jurada en Carácter de Cese de Actividades, Clausura o Cierre Definitivo</td>
                    <td class="col-cas">04</td>
                    <td colspan="2">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                            <span style="font-size: 8.5px; font-weight: bold;">Periodo / Ejercicio Fiscal</span>
                            <div style="text-align: center;">
                                <div style="font-size: 7.5px;">Año</div>
                                <span class="period-digits">
                                    <span><?= htmlspecialchars($aDigits[0] ?? '2') ?></span>
                                    <span><?= htmlspecialchars($aDigits[1] ?? '0') ?></span>
                                    <span><?= htmlspecialchars($aDigits[2] ?? '2') ?></span>
                                    <span><?= htmlspecialchars($aDigits[3] ?? '5') ?></span>
                                </span>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="banner-contrib">
            PARA PERSONAS FÍSICAS CONTRIBUYENTES DEL IMPUESTO A LA RENTA PERSONAL POR RENTAS DERIVADAS DE LA PRESTACIÓN DE SERVICIOS PERSONALES
        </div>

        <!-- =================================================================== -->
        <!-- RUBRO 1: INGRESOS OBTENIDOS POR LA PRESTACIÓN DE SERVICIOS          -->
        <!-- =================================================================== -->
        <table class="form-tbl">
            <colgroup>
                <col class="col-inc">
                <col>
                <col class="col-cas">
                <col class="col-val">
                <col class="col-cas">
                <col class="col-val">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2" class="th-center">INC.</th>
                    <th rowspan="2">RUBRO 1 - INGRESOS OBTENIDOS POR LA PRESTACIÓN DE SERVICIOS PERSONALES</th>
                    <th colspan="4" class="th-center">IMPORTE</th>
                </tr>
                <tr>
                    <th colspan="2" class="th-center">EXONERADOS<br>-I-</th>
                    <th colspan="2" class="th-center">GRAVADOS<br>-II-</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="col-inc">a</td>
                    <td>Ingresos del ejercicio fiscal por la prestación de servicios personales, conforme a lo señalado en el Art. 62 de la Ley N° 6.380/2019</td>
                    <td class="col-cas">10</td>
                    <td class="col-val"><?= $fmt('C10') ?></td>
                    <td class="col-cas">12</td>
                    <td class="col-val"><?= $fmt('C12') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">b</td>
                    <td>Ingresos del ejercicio fiscal en carácter de padres, tutores o curadores por las rentas que obtengan los hijos bajo su patria potestad o las personas bajo su tutela o curatela, conforme al Art. 4° del Anexo del Decreto N° 3.184/2019</td>
                    <td class="col-cas">11</td>
                    <td class="col-val"><?= $fmt('C11') ?></td>
                    <td class="col-cas">13</td>
                    <td class="col-val"><?= $fmt('C13') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">c</td>
                    <td>TOTAL INGRESOS BRUTOS GRAVADOS PERCIBIDOS EN EL EJERCICIO FISCAL (Suma de las casillas 12 y 13)</td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">14</td>
                    <td class="col-val"><?= $fmt('C14') ?></td>
                </tr>
            </tbody>
        </table>

        <!-- =================================================================== -->
        <!-- RUBRO 2: EGRESOS DEDUCIBLES DEL EJERCICIO FISCAL                    -->
        <!-- =================================================================== -->
        <table class="form-tbl">
            <colgroup>
                <col class="col-inc">
                <col>
                <col class="col-cas">
                <col style="width: 145px;">
            </colgroup>
            <thead>
                <tr>
                    <th class="th-center">INC.</th>
                    <th>RUBRO 2 - EGRESOS DEDUCIBLES DEL EJERCICIO FISCAL</th>
                    <th colspan="2" class="th-center">IMPORTE</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="col-inc">a</td>
                    <td>Egresos directamente relacionados con la actividad gravada</td>
                    <td class="col-cas">15</td>
                    <td class="col-val"><?= $fmt('C15') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">b</td>
                    <td>Egresos personales y a favor de familiares a cargo realizados en el país (con excepción de los egresos señalados en los incisos d e i del presente rubro)</td>
                    <td class="col-cas">16</td>
                    <td class="col-val"><?= $fmt('C16') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">c</td>
                    <td>Egresos personales y a favor de familiares a cargo realizados en el exterior destinados exclusivamente a la salud y a la educación</td>
                    <td class="col-cas">17</td>
                    <td class="col-val"><?= $fmt('C17') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">d</td>
                    <td>Egreso personal por la adquisición de un autovehículo cada 3 (tres) años</td>
                    <td class="col-cas">18</td>
                    <td class="col-val"><?= $fmt('C18') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">e</td>
                    <td>Aporte patronal a favor del trabajador realizado al Sistema de Seguridad Social creado o admitido por Ley o Decreto-Ley</td>
                    <td class="col-cas">19</td>
                    <td class="col-val"><?= $fmt('C19') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">f</td>
                    <td>Aporte a un Sistema privado de seguridad social realizado por el prestador de servicio personal independiente</td>
                    <td class="col-cas">20</td>
                    <td class="col-val"><?= $fmt('C20') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">g</td>
                    <td>Cuotas, aportes, diezmos y donaciones realizadas a favor del Estado y demás asociaciones o entidades civiles reconocidas como de beneficio público por la Administración Tributaria (hasta el 5% de la renta bruta gravada)</td>
                    <td class="col-cas">21</td>
                    <td class="col-val"><?= $fmt('C21') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">h</td>
                    <td>EGRESOS DEDUCIDOS EN EL EJERCICIO FISCAL (Sumatoria de las casillas 15 al 21)</td>
                    <td class="col-cas">22</td>
                    <td class="col-val"><?= $fmt('C22') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">i</td>
                    <td>EGRESO PERSONAL POR LA ADQUISICIÓN AL CONTADO DE UN INMUEBLE PARA LA VIVIENDA CADA 5 (CINCO) AÑOS</td>
                    <td class="col-cas">23</td>
                    <td class="col-val"><?= $fmt('C23') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">j</td>
                    <td>TOTAL DE EGRESOS DEDUCIBLES EN EL EJERCICIO FISCAL (Suma de las casillas 22 y 23)</td>
                    <td class="col-cas">24</td>
                    <td class="col-val"><?= $fmt('C24') ?></td>
                </tr>
            </tbody>
        </table>

        <!-- =================================================================== -->
        <!-- RUBRO 3: RESULTADO OBTENIDO LUEGO DE APLICAR LAS DEDUCCIONES        -->
        <!-- =================================================================== -->
        <table class="form-tbl">
            <colgroup>
                <col class="col-inc">
                <col>
                <col class="col-cas">
                <col style="width: 145px;">
            </colgroup>
            <thead>
                <tr>
                    <th class="th-center">INC.</th>
                    <th>RUBRO 3 - RESULTADO OBTENIDO LUEGO DE APLICAR LAS DEDUCCIONES ADMITIDAS PARA LA LIQUIDACIÓN DEL IMPUESTO</th>
                    <th colspan="2" class="th-center">IMPORTE</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="col-inc">a</td>
                    <td>Ingresos brutos gravados del ejercicio fiscal (proviene de la casilla 14 del Rubro 1)</td>
                    <td class="col-cas">25</td>
                    <td class="col-val"><?= $fmt('C25') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">b</td>
                    <td>Menos: Egresos deducidos del ejercicio fiscal (proviene de la casilla 22 del Rubro 2, limitado hasta el monto de la casilla 25)</td>
                    <td class="col-cas">26</td>
                    <td class="col-val"><?= $fmt('C26') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">c</td>
                    <td>Resultado después de descontar los Egresos Deducibles (Diferencia entre las casillas 25 y 26)</td>
                    <td class="col-cas">27</td>
                    <td class="col-val"><?= $fmt('C27') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">d</td>
                    <td>Menos: Egreso personal por la adquisición al contado de un inmueble para la vivienda cada 5 (cinco) años (proviene de la casilla 23 del Rubro 2, hasta el monto de la casilla 27)</td>
                    <td class="col-cas">28</td>
                    <td class="col-val"><?= $fmt('C28') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">e</td>
                    <td>SUBTOTAL RENTA NETA IMPONIBLE ANTES DE CONSIDERAR EL SALDO ARRASTRABLE DE PÉRDIDAS DE EJERCICIOS ANTERIORES (Diferencia entre las casillas 27 y 28)</td>
                    <td class="col-cas">29</td>
                    <td class="col-val"><?= $fmt('C29') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">f</td>
                    <td>Monto total o porción de los préstamos o financiaciones obtenidos y utilizados en el ejercicio o el remanente de los ingresos gravados de los 5 (cinco) últimos ejercicios (proviene de la casilla 102 del Anexo, cuando el monto consignado en la casilla 23 sea mayor a la casilla 27)</td>
                    <td class="col-cas">30</td>
                    <td class="col-val"><?= $fmt('C30') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">g</td>
                    <td>Saldo de egresos por la adquisición de un inmueble para la vivienda no arrastrable (Pérdida fiscal no arrastrable)</td>
                    <td class="col-cas">31</td>
                    <td class="col-val"><?= $fmt('C31') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">h</td>
                    <td>Pérdida Fiscal por adquisición de un inmueble, arrastrable al siguiente ejercicio fiscal</td>
                    <td class="col-cas">32</td>
                    <td class="col-val"><?= $fmt('C32') ?></td>
                </tr>
            </tbody>
        </table>

        <!-- =================================================================== -->
        <!-- RUBRO 4: PÉRDIDA FISCAL ARRASTRABLE POR ADQUISICIÓN DE INMUEBLE     -->
        <!-- =================================================================== -->
        <table class="form-tbl">
            <colgroup>
                <col class="col-inc">
                <col>
                <col class="col-cas">
                <col style="width: 145px;">
            </colgroup>
            <thead>
                <tr>
                    <th class="th-center">INC.</th>
                    <th>RUBRO 4 - PÉRDIDA FISCAL ARRASTRABLE POR LA ADQUISICIÓN DE UN INMUEBLE PARA LA VIVIENDA</th>
                    <th colspan="2" class="th-center">IMPORTE</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="col-inc">a</td>
                    <td>Pérdida Fiscal arrastrable por la adquisición de un inmueble para la vivienda, producida en el ejercicio fiscal que se liquida (proviene de la casilla 32 del Rubro 3)</td>
                    <td class="col-cas">33</td>
                    <td class="col-val"><?= $fmt('C33') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">b</td>
                    <td>Más: Pérdida Fiscal arrastrable por la adquisición de un inmueble para la vivienda, proveniente del ejercicio fiscal anterior</td>
                    <td class="col-cas">34</td>
                    <td class="col-val"><?= $fmt('C34') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">c</td>
                    <td>Acumulado de Pérdida Fiscal por la adquisición de inmueble (Suma de las casillas 33 y 34)</td>
                    <td class="col-cas">35</td>
                    <td class="col-val"><?= $fmt('C35') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">d</td>
                    <td>Menos: Porción de Pérdida Fiscal arrastrable que debe excluirse por haber superado los 5 (cinco) años de arrastre</td>
                    <td class="col-cas">36</td>
                    <td class="col-val"><?= $fmt('C36') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">e</td>
                    <td>Saldo de Pérdida Fiscal arrastrable susceptible de ser compensado en la liquidación del impuesto (Diferencia entre las casillas 35 y 36)</td>
                    <td class="col-cas">37</td>
                    <td class="col-val"><?= $fmt('C37') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">f</td>
                    <td>Menos: Porción de Pérdida Fiscal proveniente de ejercicios fiscales anteriores que se compensa con la renta neta de la casilla 29 del Rubro 3 (limitada al 20% de la Renta Neta del ejercicio que se liquida)</td>
                    <td class="col-cas">38</td>
                    <td class="col-val"><?= $fmt('C38') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">g</td>
                    <td>Saldo de Pérdida Fiscal arrastrable susceptible de ser trasladado al ejercicio siguiente (Diferencia entre las casillas 37 y 38)</td>
                    <td class="col-cas">39</td>
                    <td class="col-val"><?= $fmt('C39') ?></td>
                </tr>
            </tbody>
        </table>

        <!-- =================================================================== -->
        <!-- RUBRO 5: DETERMINACIÓN DEL IMPUESTO Y LIQUIDACIÓN FINAL             -->
        <!-- =================================================================== -->
        <table class="form-tbl">
            <colgroup>
                <col class="col-inc">
                <col>
                <col class="col-cas">
                <col class="col-val">
                <col class="col-cas">
                <col class="col-val">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2" class="th-center">INC.</th>
                    <th rowspan="2">RUBRO 5 - DETERMINACIÓN DEL IMPUESTO Y LIQUIDACIÓN FINAL</th>
                    <th colspan="4" class="th-center">IMPORTE A FAVOR DEL:</th>
                </tr>
                <tr>
                    <th colspan="2" class="th-center">CONTRIBUYENTE<br>-I-</th>
                    <th colspan="2" class="th-center">FISCO<br>-II-</th>
                </tr>
            </thead>
            <tbody>
                <tr class="row-total">
                    <td class="col-inc">a</td>
                    <td><strong>RENTA NETA IMPONIBLE</strong> (Diferencia entre las casillas 29 del Rubro 3 y 38 del Rubro 4)</td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">45</td>
                    <td class="col-val"><?= $fmt('C45') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">b</td>
                    <td><strong>IMPUESTO DETERMINADO A LA TASA DEL 8%</strong> Por la porción de la Renta Neta hasta G. 50.000.000</td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">46</td>
                    <td class="col-val"><?= $fmt('C46') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">c</td>
                    <td><strong>IMPUESTO DETERMINADO A LA TASA DEL 9%</strong> Por la porción de la Renta Neta desde G. 50.000.001 hasta G. 150.000.000</td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">47</td>
                    <td class="col-val"><?= $fmt('C47') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">d</td>
                    <td><strong>IMPUESTO DETERMINADO A LA TASA DEL 10%</strong> Por la porción de la Renta Neta mayor a G. 150.000.000</td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">48</td>
                    <td class="col-val"><?= $fmt('C48') ?></td>
                </tr>
                <tr>
                    <td class="col-inc">e</td>
                    <td>Saldo a favor del contribuyente del ejercicio fiscal anterior (Proviene de la casilla 44 del ejercicio anterior)</td>
                    <td class="col-cas">40</td>
                    <td class="col-val"><?= $fmt('C40') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">f</td>
                    <td><strong>RETENCIONES:</strong> Monto de las retenciones computables a favor del contribuyente</td>
                    <td class="col-cas">41</td>
                    <td class="col-val"><?= $fmt('C41') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">g</td>
                    <td><strong>PERCEPCIONES:</strong> Monto de las percepciones computables a favor del contribuyente</td>
                    <td class="col-cas">42</td>
                    <td class="col-val"><?= $fmt('C42') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr>
                    <td class="col-inc">h</td>
                    <td>Multa por presentar la Declaración Jurada con posterioridad al vencimiento</td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">49</td>
                    <td class="col-val"><?= $fmt('C49') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">i</td>
                    <td><strong>SUBTOTAL</strong> (Columna I: Sumatoria de las casillas 40 al 42); (Columna II: Sumatoria de las casillas 46 al 49)</td>
                    <td class="col-cas">43</td>
                    <td class="col-val"><?= $fmt('C43') ?></td>
                    <td class="col-cas">50</td>
                    <td class="col-val"><?= $fmt('C50') ?></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">j</td>
                    <td><strong>SALDO A FAVOR DEL CONTRIBUYENTE</strong> (Diferencia entre las casillas 43 y 50, cuando la casilla 43 sea mayor)</td>
                    <td class="col-cas">44</td>
                    <td class="col-val"><?= $fmt('C44') ?></td>
                    <td colspan="2" class="shaded"></td>
                </tr>
                <tr class="row-total">
                    <td class="col-inc">k</td>
                    <td><strong>SALDO A INGRESAR A FAVOR DEL FISCO</strong> (Diferencia entre las casillas 50 y 43, cuando la casilla 50 sea mayor)</td>
                    <td colspan="2" class="shaded"></td>
                    <td class="col-cas">51</td>
                    <td class="col-val"><?= $fmt('C51') ?></td>
                </tr>
            </tbody>
        </table>

        <!-- PIE OFICIAL FORMULARIO 515 V1 -->
        <table class="form-tbl" style="margin-top: 8px; margin-bottom: 4px;">
            <tbody>
                <tr>
                    <td style="padding: 5px 8px; font-size: 9px;">
                        <strong>Estimado Contribuyente:</strong> Le recordamos que los pagos que efectúe, emergentes de esta liquidación, serán imputados en su cuenta corriente conforme a lo señalado en la Ley N° 125/1991, Art. 162.
                    </td>
                </tr>
            </tbody>
        </table>
        <div style="display: flex; justify-content: space-between; font-size: 8.5px; font-weight: bold; padding: 0 2px;">
            <span>(*) Ley N° 7143/2023 «Que crea la Dirección Nacional de Ingresos Tributarios»</span>
            <span>Versión - Noviembre/2020</span>
        </div>
    </div>
</body>
</html>
