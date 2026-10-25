<?php
/**
 * Representación Gráfica Oficial de Documento Electrónico (KuDE - SIFEN v150 DNIT Paraguay)
 * Variables recibidas: $ingreso, $empresa, $timbrado
 */
$ing  = $ingreso ?? [];
$emp  = $empresa ?? [];
$timb = $timbrado ?? [];

$idIngreso   = (int)($ing['id_ingreso'] ?? 0);
$cdcRaw      = preg_replace('/\D/', '', (string)($ing['cdc'] ?? ''));
$cdcFmt      = strlen($cdcRaw) === 44 ? trim(chunk_split($cdcRaw, 4, ' ')) : $cdcRaw;
$qrUrl       = (string)($ing['sifen_qr_url'] ?? 'https://ekuatia.set.gov.py/consultas/');
$estadoSifen = (string)($ing['sifen_estado'] ?? 'EMITIDO');
$protocolo   = (string)($ing['sifen_protocolo'] ?? '-');
$fecFirma    = (string)($ing['sifen_fecha_firma'] ?? ($ing['fec_ingreso'] ?? date('Y-m-d H:i:s')));

$tipDocRaw = strtoupper(trim((string)($ing['tip_doc'] ?? 'FACTURA')));
$tituloDe  = 'FACTURA ELECTRÓNICA';
if (str_contains($tipDocRaw, 'AUTO')) $tituloDe = 'AUTOFACTURA ELECTRÓNICA';
elseif (str_contains($tipDocRaw, 'CRÉD') || str_contains($tipDocRaw, 'CRED')) $tituloDe = 'NOTA DE CRÉDITO ELECTRÓNICA';
elseif (str_contains($tipDocRaw, 'DÉB') || str_contains($tipDocRaw, 'DEB')) $tituloDe = 'NOTA DE DÉBITO ELECTRÓNICA';

$nomEmisor = (string)($emp['descripcion'] ?? 'CONTRIBUYENTE EMISOR');
$rucEmisor = (string)($emp['ruc_empresa'] ?? '80000000-0');
$dirEmisor = (string)($emp['direccion'] ?? 'ASUNCIÓN - PARAGUAY');
$actEmisor = (string)($emp['actividad'] ?? 'SERVICIOS CONTABLES Y COMERCIALES');
$locEmisor = trim(((string)($emp['localidad'] ?? 'ASUNCIÓN')) . ' ' . ((string)($emp['departamento'] ?? '')));

$nroTimbrado = (string)($ing['nro_timbrado'] ?? ($timb['nro_timbrado'] ?? '12345678'));
$fecIniTimb  = !empty($timb['fec_inicio']) ? date('d/m/Y', strtotime((string)$timb['fec_inicio'])) : '01/01/2025';
$nroFactura  = (string)($ing['nro_factura'] ?? '001-001-0000001');
$fecEmision  = !empty($ing['fec_ingreso']) ? date('d/m/Y', strtotime((string)$ing['fec_ingreso'])) : date('d/m/Y');

$condRaw   = strtoupper(trim((string)($ing['condicion'] ?? 'CON')));
$condTexto = ($condRaw === 'CRE' || str_contains($condRaw, 'CRED')) ? 'CRÉDITO' : 'CONTADO';

$monCode   = (string)($ing['cod_moneda'] ?? '1');
$monNombre = ($monCode === '2') ? 'DÓLAR AMERICANO (USD)' : (($monCode === '3') ? 'REAL (BRL)' : 'GUARANÍ (PYG)');
$tasaCamb  = (float)($ing['tasa_cambio'] ?? 1);

$rucCliente = (string)($ing['ruc_cliente'] ?? '44444401-7');
$nomCliente = (string)($ing['nom_cliente'] ?? 'SIN NOMBRE');
$obsConcepto = trim((string)($ing['observacion'] ?? '')) ?: 'VENTA DE MERCADERÍAS / SERVICIOS SEGÚN COMPROBANTE';

$grav10     = (float)($ing['grav10'] ?? 0);
$iva10      = (float)($ing['iva10'] ?? 0);
$grav5      = (float)($ing['grav5'] ?? 0);
$iva5       = (float)($ing['iva5'] ?? 0);
$exentas    = (float)($ing['exentas'] ?? 0);
$totIva     = (float)($ing['tot_iva'] ?? ($iva10 + $iva5));
$valorTotal = (float)($ing['valor_total'] ?? ($grav10 + $grav5 + $exentas));

// Construir filas de detalle a partir de los montos registrados
$itemsDetalle = [];
if ($grav10 > 0) {
    $itemsDetalle[] = [
        'codigo' => '001',
        'cant'   => 1,
        'desc'   => $obsConcepto . ($grav5 > 0 || $exentas > 0 ? ' (GRAVADO 10%)' : ''),
        'precio' => $grav10,
        'exenta' => 0,
        'grav5'  => 0,
        'grav10' => $grav10,
    ];
}
if ($grav5 > 0) {
    $itemsDetalle[] = [
        'codigo' => '002',
        'cant'   => 1,
        'desc'   => $obsConcepto . ' (GRAVADO 5%)',
        'precio' => $grav5,
        'exenta' => 0,
        'grav5'  => $grav5,
        'grav10' => 0,
    ];
}
if ($exentas > 0) {
    $itemsDetalle[] = [
        'codigo' => '003',
        'cant'   => 1,
        'desc'   => $obsConcepto . ' (EXENTO)',
        'precio' => $exentas,
        'exenta' => $exentas,
        'grav5'  => 0,
        'grav10' => 0,
    ];
}
if (empty($itemsDetalle)) {
    $itemsDetalle[] = [
        'codigo' => '001',
        'cant'   => 1,
        'desc'   => $obsConcepto,
        'precio' => $valorTotal,
        'exenta' => 0,
        'grav5'  => 0,
        'grav10' => $valorTotal,
    ];
}

function fmtGs(float $n): string {
    return number_format(round($n), 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>KuDE - <?= htmlspecialchars($tituloDe . ' ' . $nroFactura) ?> | ContaPro</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            background: #e2e8f0;
            color: #0f172a;
            margin: 0;
            padding: 20px;
        }
        .toolbar-no-print {
            max-width: 820px;
            margin: 0 auto 16px auto;
            background: #0f172a;
            color: #fff;
            padding: 12px 20px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.18);
        }
        .toolbar-btn {
            border: none;
            padding: 8px 16px;
            border-radius: 7px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print { background: #16a34a; color: #fff; }
        .btn-xml { background: #0284c7; color: #fff; }
        .btn-close-win { background: #475569; color: #fff; }
        .kude-sheet {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #94a3b8;
            border-radius: 8px;
            padding: 24px 28px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .header-grid {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 16px;
            border: 1.5px solid #1e293b;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 12px;
        }
        .emisor-box h1 {
            margin: 0 0 6px 0;
            font-size: 1.25rem;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
        }
        .emisor-box p {
            margin: 3px 0;
            font-size: 0.8rem;
            color: #334155;
            line-height: 1.35;
        }
        .timbrado-box {
            border-left: 1.5px solid #cbd5e1;
            padding-left: 16px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .timbrado-box .line {
            font-size: 0.82rem;
            margin: 2px 0;
            color: #1e293b;
        }
        .timbrado-box .doc-title {
            margin: 8px 0 4px 0;
            padding: 5px 8px;
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #15803d;
            font-weight: 900;
            font-size: 0.95rem;
            text-align: center;
            border-radius: 4px;
        }
        .timbrado-box .doc-num {
            font-size: 1.15rem;
            font-weight: 900;
            text-align: center;
            color: #0f172a;
            letter-spacing: 1px;
        }
        .receptor-box {
            border: 1.5px solid #1e293b;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 12px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 20px;
            font-size: 0.82rem;
        }
        .receptor-box div strong {
            color: #334155;
        }
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 0.82rem;
        }
        table.items-table th {
            background: #f1f5f9;
            border: 1px solid #1e293b;
            padding: 7px 8px;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.74rem;
            color: #0f172a;
        }
        table.items-table td {
            border: 1px solid #94a3b8;
            padding: 8px;
        }
        table.items-table tfoot td {
            border: 1px solid #1e293b;
            padding: 7px 8px;
            font-weight: 800;
        }
        .qr-footer-grid {
            border: 1.5px solid #1e293b;
            border-radius: 6px;
            padding: 14px 16px;
            display: grid;
            grid-template-columns: 145px 1fr;
            gap: 18px;
            align-items: center;
            background: #f8fafc;
        }
        #qrCodeContainer {
            width: 136px;
            height: 136px;
            background: #fff;
            padding: 6px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cdc-box {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.96rem;
            font-weight: 900;
            background: #ffffff;
            border: 1.5px solid #16a34a;
            color: #0f172a;
            padding: 8px 12px;
            border-radius: 6px;
            letter-spacing: 1px;
            margin: 6px 0;
            text-align: center;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar-no-print { display: none !important; }
            .kude-sheet { box-shadow: none; border: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

    <div class="toolbar-no-print">
        <div style="font-weight: 800; font-size: 0.95rem;">
            KuDE SIFEN v150 — <?= htmlspecialchars($nroFactura) ?>
            <span style="margin-left: 8px; background: #16a34a; padding: 3px 9px; border-radius: 99px; font-size: 0.75rem;">
                <?= htmlspecialchars($estadoSifen) ?>
            </span>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="toolbar-btn btn-print" onclick="window.print()">
                🖨️ Imprimir / Guardar PDF
            </button>
            <a href="/api/sifen/descargar-xml/<?= $idIngreso ?>" class="toolbar-btn btn-xml">
                ⬇️ Descargar XML Firmado
            </a>
            <button type="button" class="toolbar-btn btn-close-win" onclick="window.close()">
                ✖ Cerrar
            </button>
        </div>
    </div>

    <div class="kude-sheet">
        <!-- Encabezado Emisor y Timbrado -->
        <div class="header-grid">
            <div class="emisor-box">
                <h1><?= htmlspecialchars($nomEmisor) ?></h1>
                <p><strong>Actividad Económica:</strong> <?= htmlspecialchars($actEmisor) ?></p>
                <p><strong>Dirección:</strong> <?= htmlspecialchars($dirEmisor) ?></p>
                <p><strong>Ciudad / Dpto.:</strong> <?= htmlspecialchars($locEmisor) ?> - PARAGUAY</p>
            </div>
            <div class="timbrado-box">
                <div class="line"><strong>RUC:</strong> <?= htmlspecialchars($rucEmisor) ?></div>
                <div class="line"><strong>TIMBRADO N°:</strong> <?= htmlspecialchars($nroTimbrado) ?></div>
                <div class="line"><strong>Fecha Inicio Vigencia:</strong> <?= htmlspecialchars($fecIniTimb) ?></div>
                <div class="doc-title"><?= htmlspecialchars($tituloDe) ?></div>
                <div class="doc-num">N° <?= htmlspecialchars($nroFactura) ?></div>
            </div>
        </div>

        <!-- Datos del Receptor -->
        <div class="receptor-box">
            <div><strong>Fecha de Emisión:</strong> <?= htmlspecialchars($fecEmision) ?></div>
            <div><strong>Condición de Venta:</strong> <?= htmlspecialchars($condTexto) ?></div>
            <div><strong>RUC / Doc. Identidad Receptor:</strong> <?= htmlspecialchars($rucCliente) ?></div>
            <div><strong>Moneda:</strong> <?= htmlspecialchars($monNombre) ?><?= $tasaCamb > 1 ? ' (T.C.: ' . fmtGs($tasaCamb) . ')' : '' ?></div>
            <div style="grid-column: 1 / -1;"><strong>Nombre o Razón Social Receptor:</strong> <?= htmlspecialchars($nomCliente) ?></div>
        </div>

        <!-- Tabla de Ítems e Impuestos -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 65px; text-align: center;">Cód.</th>
                    <th style="width: 55px; text-align: center;">Cant.</th>
                    <th style="text-align: left;">Descripción del Producto / Servicio</th>
                    <th style="width: 105px; text-align: right;">Precio Unit.</th>
                    <th style="width: 95px; text-align: right;">Exentas</th>
                    <th style="width: 95px; text-align: right;">Gravadas 5%</th>
                    <th style="width: 105px; text-align: right;">Gravadas 10%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itemsDetalle as $it): ?>
                <tr>
                    <td style="text-align: center;"><?= htmlspecialchars($it['codigo']) ?></td>
                    <td style="text-align: center;"><?= (int)$it['cant'] ?></td>
                    <td><?= htmlspecialchars($it['desc']) ?></td>
                    <td style="text-align: right;"><?= fmtGs($it['precio']) ?></td>
                    <td style="text-align: right;"><?= fmtGs($it['exenta']) ?></td>
                    <td style="text-align: right;"><?= fmtGs($it['grav5']) ?></td>
                    <td style="text-align: right;"><?= fmtGs($it['grav10']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8fafc;">
                    <td colspan="4" style="text-align: right;">SUBTOTALES:</td>
                    <td style="text-align: right;"><?= fmtGs($exentas) ?></td>
                    <td style="text-align: right;"><?= fmtGs($grav5) ?></td>
                    <td style="text-align: right;"><?= fmtGs($grav10) ?></td>
                </tr>
                <tr style="background: #dcfce7; font-size: 0.92rem;">
                    <td colspan="6" style="text-align: right; color: #15803d;">TOTAL DE LA OPERACIÓN (<?= $monCode === '2' ? 'USD' : ($monCode === '3' ? 'BRL' : 'Gs.') ?>):</td>
                    <td style="text-align: right; color: #15803d; font-size: 1rem;"><?= fmtGs($valorTotal) ?></td>
                </tr>
                <tr>
                    <td colspan="2"><strong>LIQUIDACIÓN DEL IVA:</strong></td>
                    <td><strong>IVA (5%):</strong> <?= fmtGs($iva5) ?></td>
                    <td colspan="2"><strong>IVA (10%):</strong> <?= fmtGs($iva10) ?></td>
                    <td colspan="2" style="text-align: right;"><strong>TOTAL IVA: <?= fmtGs($totIva) ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <!-- Recuadro Oficial SIFEN: Código QR + CDC de 44 dígitos -->
        <div class="qr-footer-grid">
            <div id="qrCodeContainer" title="Código QR Oficial SIFEN v150"></div>
            <div>
                <div style="font-size: 0.78rem; color: #334155; line-height: 1.4;">
                    <strong>Consulte la validez de esta Factura Electrónica con el número de CDC impreso abajo en:</strong><br>
                    <a href="https://ekuatia.set.gov.py/consultas/" target="_blank" style="color: #15803d; font-weight: 700;">https://ekuatia.set.gov.py/consultas/</a>
                </div>
                <div class="cdc-box">
                    CDC: <?= htmlspecialchars($cdcFmt ?: 'PENDIENTE DE GENERACIÓN') ?>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.74rem; color: #475569; margin-top: 4px;">
                    <span><strong>Estado SIFEN:</strong> <?= htmlspecialchars($estadoSifen) ?></span>
                    <span><strong>Protocolo:</strong> <?= htmlspecialchars($protocolo) ?></span>
                    <span><strong>Fecha Firma:</strong> <?= htmlspecialchars($fecFirma) ?></span>
                </div>
                <div style="font-size: 0.7rem; color: #64748b; margin-top: 6px; text-transform: uppercase;">
                    ESTE DOCUMENTO ES UNA REPRESENTACIÓN GRÁFICA DE UN DOCUMENTO ELECTRÓNICO (XML) — SIFEN DNIT PARAGUAY
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var qrUrl = <?= json_encode($qrUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            var container = document.getElementById('qrCodeContainer');
            if (typeof QRCode !== 'undefined' && container) {
                new QRCode(container, {
                    text: qrUrl,
                    width: 122,
                    height: 122,
                    colorDark : "#0f172a",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.M
                });
            }
        })();
    </script>
</body>
</html>
