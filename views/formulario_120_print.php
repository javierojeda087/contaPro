<?php
$emp = $empresa ?? [];
$f = $f ?? [];
$fmt = fn($k) => number_format((float)($f[$k] ?? 0), 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/images/favicon_cp.png?v=2" />
    <title>Formulario 120 - Impuesto al Valor Agregado (Versión 3) | ContaPro</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #000; background: #fff; margin: 0; padding: 15px; }
        .page { width: 960px; margin: 0 auto; border: 1px solid #000; padding: 10px; box-sizing: border-box; }
        .header-title { text-align: center; font-weight: bold; font-size: 16px; text-transform: uppercase; }
        .sub-header { text-align: center; font-size: 10px; margin-top: 2px; margin-bottom: 10px; }
        .box-row { border: 1px solid #000; margin-bottom: 8px; padding: 6px; }
        .rubro-head { background: #e2e2e2; font-weight: bold; padding: 4px 6px; border: 1px solid #000; margin-top: 10px; font-size: 11px; }
        table.data-tbl { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.data-tbl th, table.data-tbl td { border: 1px solid #000; padding: 3px 5px; }
        .text-right { text-align: right; } .text-center { text-align: center; } .font-bold { font-weight: bold; }
        .casilla-num { font-size: 9px; color: #555; margin-left: 2px; }
        @media print { body { padding: 0; } .page { border: none; width: 100%; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 15px; text-align: right; max-width: 960px; margin-left: auto; margin-right: auto;">
        <button onclick="window.print()" style="padding: 8px 20px; background: #0284c7; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">
            🖨️ Imprimir Formulario 120
        </button>
    </div>

    <div class="page">
        <div class="header-title">IMPUESTO AL VALOR AGREGADO - Versión 3</div>
        <div class="sub-header">PARA LLENAR LEA INSTRUCTIVO DISPONIBLE EN LA WEB - LOS IMPORTES SE CONSIGNARÁN SIN CÉNTIMOS</div>

        <div class="box-row">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 50%;"><strong>RUC:</strong> <span><?= htmlspecialchars(($emp['rucNum'] ?? '') . '-' . ($emp['rucDv'] ?? '')) ?></span></td>
                    <td style="width: 50%;"><strong>PERIODO / EJERCICIO FISCAL:</strong> <span><?= htmlspecialchars(($mes ?? '') . '/' . ($anio ?? '')) ?></span></td>
                </tr>
                <tr>
                    <td colspan="2" style="padding-top: 5px;"><strong>RAZÓN SOCIAL:</strong> <span><?= htmlspecialchars($emp['razon_social'] ?? '') ?></span></td>
                </tr>
            </table>
        </div>

        <div class="rubro-head">RUBRO 1 - ENAJENACIÓN DE BIENES Y/O PRESTACIÓN DE SERVICIOS DEL PERIODO</div>
        <table class="data-tbl">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th style="width: 30px;">INC</th>
                    <th>DESCRIPCIÓN</th>
                    <th style="width: 140px;">MONTO IMPONIBLE AL 5%</th>
                    <th style="width: 140px;">MONTO IMPONIBLE AL 10%</th>
                    <th style="width: 140px;">IVA DÉBITO</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center font-bold">a</td>
                    <td>Enajenación de Bienes y Prestación de Servicios gravadas con tasa del 10%</td>
                    <td></td>
                    <td class="text-right"><span><?= $fmt('C10') ?></span> <span class="casilla-num">[10]</span></td>
                    <td class="text-right"><span><?= $fmt('C22') ?></span> <span class="casilla-num">[22]</span></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">b</td>
                    <td>Enajenación de Bienes y Prestación de Servicios gravadas con tasa del 5%</td>
                    <td class="text-right"><span><?= $fmt('C11') ?></span> <span class="casilla-num">[11]</span></td>
                    <td></td>
                    <td class="text-right"><span><?= $fmt('C19') ?></span> <span class="casilla-num">[19]</span></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">c</td>
                    <td>Enajenación de bienes y/o prestación de servicios exonerados o no alcanzado</td>
                    <td></td>
                    <td class="text-right"><span><?= $fmt('C12') ?></span> <span class="casilla-num">[12]</span></td>
                    <td></td>
                </tr>
                <tr class="font-bold" style="background: #f9f9f9;">
                    <td class="text-center">i</td>
                    <td>TOTALES RUBRO 1</td>
                    <td class="text-right"><span><?= $fmt('C21') ?></span> <span class="casilla-num">[21]</span></td>
                    <td class="text-right"><span><?= $fmt('C18') ?></span> <span class="casilla-num">[18]</span></td>
                    <td class="text-right"><span><?= $fmt('C24') ?></span> <span class="casilla-num">[24]</span></td>
                </tr>
            </tbody>
        </table>

        <div class="rubro-head">RUBRO 3 - COMPRAS LOCALES E IMPORTACIONES DEL PERIODO</div>
        <table class="data-tbl">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th style="width: 30px;">INC</th>
                    <th>DESCRIPCIÓN</th>
                    <th style="width: 140px;">MONTO IMPONIBLE AL 5%</th>
                    <th style="width: 140px;">MONTO IMPONIBLE AL 10%</th>
                    <th style="width: 140px;">IVA CRÉDITO</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center font-bold">a</td>
                    <td>Créditos Fiscales atribuidos directamente a operaciones gravadas mercado interno</td>
                    <td class="text-right"><span><?= $fmt('C32') ?></span> <span class="casilla-num">[32]</span></td>
                    <td class="text-right"><span><?= $fmt('C35') ?></span> <span class="casilla-num">[35]</span></td>
                    <td class="text-right"><span><?= $fmt('C38') ?></span> <span class="casilla-num">[38]</span></td>
                </tr>
                <tr class="font-bold" style="background: #f9f9f9;">
                    <td class="text-center">f</td>
                    <td>TOTAL DE CRÉDITO FISCAL PARA OPERACIONES EN MERCADO INTERNO</td>
                    <td></td>
                    <td></td>
                    <td class="text-right"><span><?= $fmt('C43') ?></span> <span class="casilla-num">[43]</span></td>
                </tr>
            </tbody>
        </table>

        <div class="rubro-head">RUBRO 4 – DETERMINACIÓN DEL IMPUESTO</div>
        <table class="data-tbl">
            <tbody>
                <tr>
                    <td class="text-center font-bold" style="width: 30px;">a</td>
                    <td>Débito fiscal (Proviene del Rubro 1)</td>
                    <td class="text-right" style="width: 180px;"><span><?= $fmt('C44') ?></span> <span class="casilla-num">[44]</span></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">b</td>
                    <td>Crédito fiscal (Proviene del Rubro 3)</td>
                    <td class="text-right"><span><?= $fmt('C45') ?></span> <span class="casilla-num">[45]</span></td>
                </tr>
                <tr>
                    <td class="text-center font-bold">c</td>
                    <td>Saldo a favor del contribuyente del período anterior</td>
                    <td class="text-right"><span><?= $fmt('C46') ?></span> <span class="casilla-num">[46]</span></td>
                </tr>
                <tr class="font-bold">
                    <td class="text-center">d</td>
                    <td>SALDO A FAVOR DEL CONTRIBUYENTE (A trasladar al siguiente periodo)</td>
                    <td class="text-right"><span><?= $fmt('C47') ?></span> <span class="casilla-num">[47]</span></td>
                </tr>
                <tr class="font-bold">
                    <td class="text-center">e</td>
                    <td>SALDO A FAVOR DEL FISCO (IMPUESTO DETERMINADO)</td>
                    <td class="text-right"><span><?= $fmt('C48') ?></span> <span class="casilla-num">[48]</span></td>
                </tr>
            </tbody>
        </table>

        <div class="rubro-head">RUBRO 5 – LIQUIDACIÓN Y SALDO DEL IMPUESTO</div>
        <table class="data-tbl">
            <tbody>
                <tr class="font-bold">
                    <td class="text-center" style="width: 30px;">g</td>
                    <td>SALDO DEFINITIVO A PAGAR A FAVOR DEL FISCO</td>
                    <td class="text-right" style="width: 180px;"><span><?= $fmt('C58') ?></span> <span class="casilla-num">[58]</span></td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 30px; display: flex; justify-content: space-between; align-items: flex-end; font-size: 10px;">
            <div style="width: 60%;">
                El que suscribe declara que los datos consignados en el formulario son correctos y verdaderos y han sido confeccionados sin omitir dato alguno.<br><br>
                Lugar: ______________________, Fecha: _____/_____/_________
            </div>
            <div style="width: 35%; text-align: center; border-top: 1px solid #000; padding-top: 5px;">
                Firma del Contribuyente o Representante Legal
            </div>
        </div>
    </div>
</body>
</html>
