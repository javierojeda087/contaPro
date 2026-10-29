<?php
/**
 * Impresión de Recibo de Dinero (Recibo de Cobro - CORECIBO)
 * Variables recibidas: $recibo, $detalles, $empresa, $actividad
 */
$rec  = $recibo ?? [];
$dets = is_array($detalles ?? null) ? $detalles : [];
$emp  = $empresa ?? [];
$act  = trim((string)($actividad ?? ''));

// Formatear Nombre del Emisor (si viene "APELLIDO, NOMBRE" convertir a "Nombre Apellido" tipo Title Case)
$rawNomEmisor = trim((string)($emp['descripcion'] ?? 'Carlos Javier Ojeda Sandoval'));
if (str_contains($rawNomEmisor, ',')) {
    $partsNom = array_map('trim', explode(',', $rawNomEmisor, 2));
    $rawNomEmisor = trim(($partsNom[1] ?? '') . ' ' . ($partsNom[0] ?? ''));
}
$nomEmisorTitle = mb_convert_case(mb_strtolower($rawNomEmisor, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

$rucEmisorRaw = trim((string)($emp['ruc_empresa'] ?? '5567097-0'));
if (str_contains($rucEmisorRaw, '-')) {
    [$rBase, $rDv] = explode('-', $rucEmisorRaw, 2);
    $rucEmisorFmt = trim($rBase) . ' -' . trim($rDv);
} else {
    $rucEmisorFmt = $rucEmisorRaw;
}

if ($act === '' || stripos($act, 'SERVICIOS CONTABLES') !== false) {
    $actDisplay = 'Actividades Profesionales, Cientificas y Tecnicas';
} else {
    $actDisplay = mb_convert_case(mb_strtolower($act, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

// Número corto de recibo (ej. 00092)
$rawNroRecibo = trim((string)($rec['nro_recibo'] ?? '1'));
if (str_contains($rawNroRecibo, '-')) {
    $partesNro = explode('-', $rawNroRecibo);
    $seqNum = (int)end($partesNro);
} else {
    $seqNum = (int)preg_replace('/\D/', '', $rawNroRecibo);
}
if ($seqNum <= 0) $seqNum = 1;
$nroReciboCorto = str_pad((string)$seqNum, 5, '0', STR_PAD_LEFT);

// Fecha en español: "31 DE AGOSTO DEL 2026"
$mesesEs = [
    1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
    5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
    9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'
];
$fecRaw = !empty($rec['fec_recibo']) ? substr((string)$rec['fec_recibo'], 0, 10) : date('Y-m-d');
$tsFec  = strtotime($fecRaw) ?: time();
$diaNum = date('d', $tsFec);
$mesNum = (int)date('n', $tsFec);
$anioNum = date('Y', $tsFec);
$mesNombre = $mesesEs[$mesNum] ?? 'AGOSTO';
$fechaLargaEs = "{$diaNum} DE {$mesNombre} DEL {$anioNum}";

$nomCliente = strtoupper(trim((string)($rec['nom_cliente'] ?? 'CLIENTE')));
$rucCliente = trim((string)($rec['ruc_cliente'] ?? ''));

$codMoneda  = trim((string)($rec['cod_moneda'] ?? '1'));
$simboloMon = ($codMoneda === '2') ? 'US$' : (($codMoneda === '3') ? 'R$' : '₲');
$nombreMonLetras = ($codMoneda === '2') ? 'Dólares Americanos' : (($codMoneda === '3') ? 'Reales' : 'Guaranies');

$valorTotal = (float)($rec['valor_total'] ?? 0);
$valorTotalFmt = number_format($valorTotal, 2, ',', '.');

// Concepto y descripción de la línea principal
$conceptoLinea = strtoupper(trim((string)($rec['concepto'] ?? '')));
if ($conceptoLinea === '') {
    $conceptoLinea = 'HONORARIOS';
}
$descLinea = strtoupper(trim((string)($rec['observacion'] ?? '')));
if ($descLinea === '') {
    $descLinea = "HONORARIO MES DE {$mesNombre} {$anioNum}";
}

// Facturas relacionadas en el detalle
$facturasList = [];
foreach ($dets as $d) {
    $nf = trim((string)($d['nro_factura'] ?? ''));
    if ($nf !== '') {
        $facturasList[] = $nf;
    }
}
$facturasTexto = !empty($facturasList) ? implode(' / ', $facturasList) : '001-001-0000001';

$metodoPago = strtoupper(trim((string)($rec['metodo_pago'] ?? '')));
$nroCheque  = trim((string)($rec['nro_cheque'] ?? ''));
$banco      = strtoupper(trim((string)($rec['banco'] ?? '')));

/**
 * Convierte un número entero a letras en español (formato "Siete Millones seiscientos mil")
 */
if (!function_exists('numeroALetrasReciboPy')) {
    function numeroALetrasReciboPy(float $monto): string {
        $n = (int)round($monto);
        if ($n <= 0) return 'Cero';

        $unidades = ['', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $especiales = [
            10 => 'diez', 11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce',
            15 => 'quince', 16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve',
            20 => 'veinte', 21 => 'veintiún', 22 => 'veintidós', 23 => 'veintitrés', 24 => 'veinticuatro',
            25 => 'veinticinco', 26 => 'veintiséis', 27 => 'veintisiete', 28 => 'veintiocho', 29 => 'veintinueve'
        ];
        $decenas = [
            3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta',
            6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta', 9 => 'noventa'
        ];
        $centenas = [
            1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos',
            5 => 'quinientos', 6 => 'seiscientos', 7 => 'setecientos', 8 => 'ochocientos', 9 => 'novecientos'
        ];

        $convertirMenorMil = function(int $num) use ($unidades, $especiales, $decenas, $centenas): string {
            if ($num === 0) return '';
            if ($num === 100) return 'cien';
            $c = intdiv($num, 100);
            $resto = $num % 100;
            $txt = $c > 0 ? $centenas[$c] : '';
            if ($resto > 0) {
                if ($txt !== '') $txt .= ' ';
                if (isset($especiales[$resto])) {
                    $txt .= $especiales[$resto];
                } elseif ($resto < 10) {
                    $txt .= $unidades[$resto];
                } else {
                    $d = intdiv($resto, 10);
                    $u = $resto % 10;
                    $txt .= $decenas[$d] . ($u > 0 ? ' y ' . $unidades[$u] : '');
                }
            }
            return $txt;
        };

        $partes = [];
        $millones = intdiv($n, 1000000);
        $restoMillones = $n % 1000000;
        $miles = intdiv($restoMillones, 1000);
        $unidadesFinal = $restoMillones % 1000;

        if ($millones > 0) {
            if ($millones === 1) {
                $partes[] = 'Un Millón';
            } else {
                $partes[] = ucfirst($convertirMenorMil($millones)) . ' Millones';
            }
        }
        if ($miles > 0) {
            if ($miles === 1) {
                $partes[] = 'mil';
            } else {
                $partes[] = $convertirMenorMil($miles) . ' mil';
            }
        }
        if ($unidadesFinal > 0) {
            $partes[] = $convertirMenorMil($unidadesFinal);
        }

        $res = implode(' ', $partes);
        return mb_strtoupper(mb_substr($res, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($res, 1, null, 'UTF-8');
    }
}

$montoEnLetras = numeroALetrasReciboPy($valorTotal);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de Dinero N° <?= htmlspecialchars($nroReciboCorto) ?> - <?= htmlspecialchars($nomCliente) ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 14mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            margin: 0;
            padding: 20px 0;
            background: #e2e8f0;
            font-family: 'Calibri', 'Segoe UI', Arial, sans-serif;
            color: #111827;
        }

        /* Barra flotante de acciones (no sale en impresión) */
        .no-print-toolbar {
            max-width: 850px;
            margin: 0 auto 16px auto;
            background: #0f172a;
            color: white;
            padding: 10px 18px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            font-family: 'Segoe UI', sans-serif;
        }
        .no-print-toolbar .hint {
            font-size: 12.5px;
            color: #cbd5e1;
        }
        .no-print-toolbar .btn-act {
            border: none;
            border-radius: 6px;
            padding: 7px 16px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print {
            background: #16a34a;
            color: white;
        }
        .btn-print:hover {
            background: #15803d;
        }
        .btn-close-win {
            background: #334155;
            color: #f8fafc;
        }

        /* Hoja del Recibo */
        .page-sheet {
            width: 850px;
            margin: 0 auto;
            background: #ffffff;
            padding: 28px 34px 32px 34px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14);
        }

        .recibo-container {
            width: 100%;
        }

        /* 1. Franja Superior: Recibo de Dinero */
        .top-title-bar {
            border-top: 1.5px solid #111111;
            border-bottom: 1.5px solid #111111;
            padding: 6px 115px 6px 10px;
            text-align: right;
        }
        .top-title-bar h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 400;
            color: #446990;
            letter-spacing: 0.3px;
        }

        /* 2. Datos del Emisor + No. de Recibo */
        .issuer-bar {
            padding: 10px 4px 8px 4px;
            border-bottom: 2px solid #2b4c6f;
        }
        .issuer-name {
            font-size: 16.5px;
            color: #3a638e;
            font-weight: 400;
            margin-bottom: 3px;
        }
        .issuer-sub-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }
        .issuer-activity-ruc {
            font-size: 14px;
            font-weight: 700;
            color: #234d76;
            letter-spacing: 0.2px;
        }
        .receipt-number-box {
            font-size: 15px;
            color: #3a638e;
            padding-right: 22px;
        }

        /* 3. Recibimos de / RUC / Fecha */
        .client-info-section {
            padding: 16px 4px 14px 4px;
        }
        .client-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 5px;
        }
        .client-left {
            display: flex;
            align-items: baseline;
            gap: 8px;
        }
        .lbl-blue {
            color: #3a638e;
            font-size: 14px;
            width: 112px;
            text-align: right;
            font-weight: 400;
            flex-shrink: 0;
        }
        .val-bold {
            font-size: 13.5px;
            font-weight: 700;
            color: #000000;
        }
        .client-right {
            padding-right: 55px;
            font-size: 14.5px;
        }
        .client-right .ruc-lbl {
            color: #3a638e;
        }
        .client-right .ruc-val {
            color: #111111;
            margin-left: 4px;
        }

        /* 4. Tabla de Conceptos */
        .items-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3px 3px;
            margin-top: 4px;
        }
        .items-grid thead th {
            background-color: #c6e8c8;
            color: #1e6b34;
            font-weight: 700;
            font-size: 14px;
            padding: 4px 8px;
            text-align: center;
        }
        .items-grid tbody td {
            height: 24px;
            padding: 3px 8px;
            font-size: 13px;
            color: #000000;
        }
        .row-white td.col-std {
            background-color: #ffffff;
        }
        .row-shaded td.col-std {
            background-color: #d6e0eb;
        }
        .items-grid tbody td.col-total-linea {
            background-color: #d6e0eb;
        }
        .curr-cell {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            font-size: 13.5px;
        }

        /* 5. Cantidad de Guaraníes en letras + Total General */
        .letters-total-section {
            display: grid;
            grid-template-columns: 1fr 168px;
            gap: 3px;
            align-items: center;
            margin-top: 8px;
        }
        .letters-line {
            font-size: 12px;
            color: #111111;
            padding-left: 4px;
            white-space: nowrap;
            overflow: hidden;
        }
        .shaded-box-empty {
            background-color: #d6e0eb;
            height: 24px;
            margin-right: 3px;
        }
        .total-general-row {
            display: grid;
            grid-template-columns: 1fr 168px;
            gap: 3px;
            align-items: center;
            margin-top: 10px;
        }
        .total-general-box {
            background-color: #d6e0eb;
            height: 24px;
            padding: 2px 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #111111;
            margin-right: 3px;
        }

        /* 6. Fila CONCEPTO: [ FACTURA ] [ 001-001-0000010 ] */
        .concepto-factura-row {
            display: grid;
            grid-template-columns: 112px 210px 235px 1fr;
            gap: 4px;
            align-items: center;
            margin-top: 4px;
            padding-left: 4px;
        }
        .concepto-lbl {
            font-size: 13px;
            font-weight: 700;
            color: #111111;
        }
        .concepto-tipo-box {
            background-color: #d6e0eb;
            height: 23px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: #000000;
        }
        .concepto-nro-box {
            background-color: #d6e0eb;
            height: 23px;
            display: flex;
            align-items: center;
            padding: 0 6px;
            font-size: 12.5px;
            color: #000000;
        }

        /* 7. Tabla Método de Pago / N° de Cheque / Banco */
        .payment-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3px 3px;
            margin-top: 34px;
        }
        .payment-grid thead th {
            background-color: #c6e8c8;
            color: #1e6b34;
            font-weight: 700;
            font-size: 14px;
            padding: 4px 8px;
            text-align: center;
        }
        .payment-grid tbody td {
            height: 25px;
            padding: 3px 10px;
            font-size: 13px;
            text-align: center;
            font-weight: 600;
        }
        .payment-grid tbody td.col-metodo {
            background-color: #f2dcdb;
            width: 38%;
        }
        .payment-grid tbody td.col-cheque {
            background-color: #d6e0eb;
            width: 26%;
        }
        .payment-grid tbody td.col-banco {
            background-color: #d6e0eb;
            width: 36%;
        }

        /* 8. Firmas (Lugar - Fecha y Firma) */
        .signatures-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 56px;
            padding: 0 95px 0 112px;
        }
        .sig-block {
            text-align: center;
        }
        .sig-block.lugar {
            width: 215px;
        }
        .sig-block.firma {
            width: 165px;
        }
        .sig-line {
            border-top: 1.5px solid #111111;
            padding-top: 4px;
            font-size: 12.5px;
            font-weight: 700;
            color: #111111;
        }

        /* 9. Pie: Original - Cliente / Duplicado Archivo Tributario */
        .bottom-copies-bar {
            margin-top: 4px;
            border-top: 1.5px solid #111111;
            border-bottom: 1.5px solid #111111;
            padding: 4px 70px 4px 10px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        .bottom-copies-bar div {
            width: 195px;
            text-align: left;
            font-size: 12.5px;
            font-weight: 700;
            color: #111111;
            line-height: 1.45;
        }

        [contenteditable="true"]:focus {
            outline: 1.5px dashed #0284c7;
            background-color: rgba(224, 242, 254, 0.35);
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .no-print-toolbar {
                display: none !important;
            }
            .page-sheet {
                width: 100%;
                box-shadow: none;
                padding: 6px 4px;
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-toolbar">
        <div>
            <strong>Vista Previa de Impresión — Recibo de Dinero N° <?= htmlspecialchars($nroReciboCorto) ?></strong>
            <div class="hint">Podés hacer clic sobre cualquier texto del recibo (Concepto, Descripción, Método de pago, etc.) para ajustarlo antes de imprimir.</div>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn-act btn-print" onclick="window.print()">
                🖨️ Imprimir / Guardar PDF
            </button>
            <button type="button" class="btn-act btn-close-win" onclick="window.close()">
                ✕ Cerrar
            </button>
        </div>
    </div>

    <div class="page-sheet">
        <div class="recibo-container">

            <!-- 1. TÍTULO SUPERIOR -->
            <div class="top-title-bar">
                <h1 contenteditable="true">Recibo de Dinero</h1>
            </div>

            <!-- 2. EMISOR Y NRO DE RECIBO -->
            <div class="issuer-bar">
                <div class="issuer-name" contenteditable="true"><?= htmlspecialchars($nomEmisorTitle) ?></div>
                <div class="issuer-sub-row">
                    <div class="issuer-activity-ruc" contenteditable="true">
                        <?= htmlspecialchars($actDisplay) ?> &nbsp;-&nbsp; Ruc: <?= htmlspecialchars($rucEmisorFmt) ?>
                    </div>
                    <div class="receipt-number-box" contenteditable="true">No. de Recibo:<?= htmlspecialchars($nroReciboCorto) ?></div>
                </div>
            </div>

            <!-- 3. CLIENTE, RUC Y FECHA -->
            <div class="client-info-section">
                <div class="client-row">
                    <div class="client-left">
                        <span class="lbl-blue">RECIBIMOS DE :</span>
                        <span class="val-bold" contenteditable="true"><?= htmlspecialchars($nomCliente) ?></span>
                    </div>
                    <div class="client-right">
                        <span class="ruc-lbl">RUC :</span>
                        <span class="ruc-val" contenteditable="true"><?= htmlspecialchars($rucCliente) ?></span>
                    </div>
                </div>
                <div class="client-row" style="margin-bottom: 0;">
                    <div class="client-left">
                        <span class="lbl-blue">FECHA:</span>
                        <span class="val-bold" contenteditable="true"><?= htmlspecialchars($fechaLargaEs) ?></span>
                    </div>
                    <div></div>
                </div>
            </div>

            <!-- 4. TABLA DE ÍTEMS (5 FILAS) -->
            <table class="items-grid">
                <thead>
                    <tr>
                        <th style="width: 12%;">Cant.</th>
                        <th style="width: 24%;">Concepto</th>
                        <th style="width: 27%;">Descripción</th>
                        <th style="width: 18%;">Precio por unidad</th>
                        <th style="width: 19%;">Total de línea</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Fila 1 (Con los datos del recibo) -->
                    <tr class="row-white">
                        <td class="col-std" style="text-align: center;" contenteditable="true">1</td>
                        <td class="col-std" style="text-align: center;" contenteditable="true"><?= htmlspecialchars($conceptoLinea) ?></td>
                        <td class="col-std" style="text-align: center;" contenteditable="true"><?= htmlspecialchars($descLinea) ?></td>
                        <td class="col-std">
                            <div class="curr-cell" contenteditable="true">
                                <span><?= htmlspecialchars($simboloMon) ?></span>
                                <span><?= htmlspecialchars($valorTotalFmt) ?></span>
                            </div>
                        </td>
                        <td class="col-total-linea">
                            <div class="curr-cell" contenteditable="true">
                                <span><?= htmlspecialchars($simboloMon) ?></span>
                                <span><?= htmlspecialchars($valorTotalFmt) ?></span>
                            </div>
                        </td>
                    </tr>
                    <!-- Fila 2 -->
                    <tr class="row-shaded">
                        <td class="col-std" style="text-align: center;" contenteditable="true"></td>
                        <td class="col-std" style="text-align: center;" contenteditable="true"></td>
                        <td class="col-std" style="text-align: center;" contenteditable="true"></td>
                        <td class="col-std" contenteditable="true"></td>
                        <td class="col-total-linea" contenteditable="true"></td>
                    </tr>
                    <!-- Fila 3 -->
                    <tr class="row-white">
                        <td class="col-std" style="text-align: center;" contenteditable="true"></td>
                        <td class="col-std" style="text-align: center;" contenteditable="true"></td>
                        <td class="col-std" style="text-align: center;" contenteditable="true"></td>
                        <td class="col-std" contenteditable="true"></td>
                        <td class="col-total-linea" contenteditable="true"></td>
                    </tr>
                    <!-- Fila 4 -->
                    <tr class="row-shaded">
                        <td class="col-std" style="text-align: center;" contenteditable="true"></td>
                        <td class="col-std" style="text-align: center;" contenteditable="true"></td>
                        <td class="col-std" style="text-align: center;" contenteditable="true"></td>
                        <td class="col-std" contenteditable="true"></td>
                        <td class="col-total-linea" contenteditable="true"></td>
                    </tr>
                    <!-- Fila 5 -->
                    <tr class="row-white">
                        <td class="col-std" style="text-align: center;" contenteditable="true"></td>
                        <td class="col-std" style="text-align: center;" contenteditable="true"></td>
                        <td class="col-std" style="text-align: center;" contenteditable="true"></td>
                        <td class="col-std" contenteditable="true"></td>
                        <td class="col-total-linea" contenteditable="true"></td>
                    </tr>
                </tbody>
            </table>

            <!-- 5. LA CANTIDAD DE GUARANÍES EN LETRAS + CASILLA TOTAL -->
            <div class="letters-total-section">
                <div class="letters-line" contenteditable="true">
                    La cantidad de <?= htmlspecialchars($nombreMonLetras) ?>: <?= htmlspecialchars($montoEnLetras) ?> ------------------------------------------------------------
                </div>
                <div class="shaded-box-empty"></div>
            </div>

            <div class="total-general-row">
                <div></div>
                <div class="total-general-box" contenteditable="true">
                    <span><?= htmlspecialchars($simboloMon) ?></span>
                    <span><?= htmlspecialchars($valorTotalFmt) ?></span>
                </div>
            </div>

            <!-- 6. CONCEPTO: [ FACTURA ] [ NRO_FACTURA ] -->
            <div class="concepto-factura-row">
                <div class="concepto-lbl">CONCEPTO:</div>
                <div class="concepto-tipo-box" contenteditable="true">FACTURA</div>
                <div class="concepto-nro-box" contenteditable="true"><?= htmlspecialchars($facturasTexto) ?></div>
                <div></div>
            </div>

            <!-- 7. TABLA MÉTODO DE PAGO / N° DE CHEQUE / BANCO -->
            <table class="payment-grid">
                <thead>
                    <tr>
                        <th>Método de pago</th>
                        <th>N.º de cheque</th>
                        <th>BANCO</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="col-metodo" contenteditable="true"><?= htmlspecialchars($metodoPago) ?></td>
                        <td class="col-cheque" contenteditable="true"><?= htmlspecialchars($nroCheque) ?></td>
                        <td class="col-banco" contenteditable="true"><?= htmlspecialchars($banco) ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- 8. FIRMAS: LUGAR - FECHA | FIRMA -->
            <div class="signatures-row">
                <div class="sig-block lugar">
                    <div class="sig-line">LUGAR - FECHA</div>
                </div>
                <div class="sig-block firma">
                    <div class="sig-line">FIRMA</div>
                </div>
            </div>

            <!-- 9. PIE: ORIGINAL - CLIENTE / DUPLICADO ARCHIVO TRIBUTARIO -->
            <div class="bottom-copies-bar">
                <div contenteditable="true">Original - Cliente</div>
                <div contenteditable="true">Duplicado Archivo Tributario</div>
            </div>

        </div>
    </div>

</body>
</html>
