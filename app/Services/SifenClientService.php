<?php
namespace App\Services;

class SifenClientService {

    public static function queryByCdc(?string $cdcInput): array {
        $cdc = preg_replace('/\s+/', '', (string)$cdcInput);
        $resp = [
            'tipoDoc'        => 'No disponible',
            'tipoTimbrado'   => 'Electrónico',
            'nroTimbrado'    => 'No disponible',
            'nroFactura'     => 'No disponible',
            'fechaFactura'   => 'No disponible',
            'nombre'         => 'No disponible',
            'ruc'            => 'No disponible',
            'totalOperacion' => '0',
            'totalIva'       => '0',
            'estadoSifen'    => 'No consultado',
            'moneda'         => 'No disponible'
        ];

        if (strlen($cdc) === 44 && ctype_digit($cdc)) {
            // Decodificación estructural instantánea del CDC de 44 dígitos (estándar SIFEN Paraguay)
            // Posiciones CDC:
            // 0..1 (2): Tipo de Documento (01=Factura Electrónica, 04=Autofactura, 05=Nota Crédito, 06=Nota Débito)
            // 2..9 (8): RUC Emisor (rellenado con ceros a la izquierda)
            // 10 (1): DV RUC Emisor
            // 11..13 (3): Establecimiento
            // 14..16 (3): Punto de Expedición
            // 17..23 (7): Número de Documento
            // 24 (1): Tipo de Contribuyente
            // 25..32 (8): Fecha de Emisión (AAAAMMDD)
            $tiDe = substr($cdc, 0, 2);
            $rucEm = ltrim(substr($cdc, 2, 8), '0');
            $dvEm = substr($cdc, 10, 1);
            $est = substr($cdc, 11, 3);
            $punExp = substr($cdc, 14, 3);
            $numDoc = substr($cdc, 17, 7);
            $fecStr = substr($cdc, 25, 8);

            $tipos = [
                '01' => 'Factura Electrónica',
                '04' => 'Autofactura Electrónica',
                '05' => 'Nota de Crédito Electrónica',
                '06' => 'Nota de Débito Electrónica',
                '07' => 'Nota de Remisión Electrónica'
            ];
            $resp['tipoDoc'] = $tipos[$tiDe] ?? 'Documento Electrónico';
            $resp['ruc'] = $rucEm . '-' . $dvEm;
            $resp['nroFactura'] = $est . '-' . $punExp . '-' . $numDoc;
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $fecStr, $m)) {
                $resp['fechaFactura'] = $m[1] . '-' . $m[2] . '-' . $m[3];
            }

            // Buscar nombre del emisor vía RUC en base local o DNIT
            $dnit = PersonaService::consultarRucDnit($resp['ruc']);
            if (!empty($dnit['found']) && !empty($dnit['razonSocial'])) {
                $resp['nombre'] = $dnit['razonSocial'];
            }
            $resp['estadoSifen'] = 'CDC validado estructuralmente';
        }

        // Si existe certificado .p12 configurado y extensión cURL habilitada, intentar consulta SOAP a SIFEN
        $p12Path = 'C:\\firma\\F1T_99729.p12';
        $p12Pass = 'neR4178&';
        if (function_exists('curl_init') && file_exists($p12Path)) {
            try {
                $xmlRequest = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope" xmlns:xsd="http://ekuatia.set.gov.py/sifen/xsd">'
                    . '<soapenv:Header/><soapenv:Body><xsd:rEnviConsDeRequest><xsd:dId>1</xsd:dId>'
                    . '<xsd:dCDC>' . htmlspecialchars($cdc, ENT_XML1) . '</xsd:dCDC>'
                    . '</xsd:rEnviConsDeRequest></soapenv:Body></soapenv:Envelope>';

                $ch = curl_init('https://sifen.set.gov.py/de/ws/consultas/consulta.wsdl');
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $xmlRequest,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/soap+xml;charset=UTF-8'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 6,
                    CURLOPT_SSLCERT        => $p12Path,
                    CURLOPT_SSLCERTTYPE    => 'P12',
                    CURLOPT_SSLCERTPASSWD  => $p12Pass,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $responseXml = curl_exec($ch);
                curl_close($ch);

                if ($responseXml) {
                    $responseXml = str_replace(['&lt;', '&gt;', '&quot;'], ['<', '>', '"'], $responseXml);
                    $codRes = self::extractXmlTag($responseXml, 'dCodRes');
                    $msgRes = self::extractXmlTag($responseXml, 'dMsgRes');
                    if ($codRes !== '' || $msgRes !== '') {
                        $resp['estadoSifen'] = trim($codRes . ' - ' . $msgRes, ' -');
                    }
                    $numTim = self::extractXmlTag($responseXml, 'dNumTim');
                    if ($numTim !== '') $resp['nroTimbrado'] = $numTim;
                    $nomEmi = self::extractXmlTag($responseXml, 'dNomEmi');
                    if ($nomEmi !== '') $resp['nombre'] = $nomEmi;
                    $totOpe = self::extractXmlTag($responseXml, 'dTotOpe');
                    if ($totOpe !== '' && is_numeric($totOpe)) {
                        $resp['totalOperacion'] = number_format((float)$totOpe, 0, ',', '.');
                    }
                    $totIva = self::extractXmlTag($responseXml, 'dTotIVA');
                    if ($totIva !== '' && is_numeric($totIva)) {
                        $resp['totalIva'] = number_format((float)$totIva, 0, ',', '.');
                    }
                    $mone = self::extractXmlTag($responseXml, 'cMoneOpe') ?: self::extractXmlTag($responseXml, 'dDesMoneOpe');
                    if ($mone !== '') $resp['moneda'] = $mone;
                }
            } catch (\Throwable $ignored) {}
        }

        return $resp;
    }

    private static function extractXmlTag(string $xml, string $tag): string {
        if (preg_match('/<[^>]*?:?' . preg_quote($tag, '/') . '>([^<]*)<\/[^>]*?:?' . preg_quote($tag, '/') . '>/i', $xml, $m)) {
            return trim($m[1]);
        }
        return '';
    }
}
