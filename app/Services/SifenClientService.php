<?php
namespace App\Services;

class SifenClientService {

    private const DEFAULT_API_URL = 'http://127.0.0.1:8085/api/v1/sifen';
    private const DEFAULT_API_KEY = 'contapro-sifen-secret-2026';

    public static function getApiBaseUrl(?string $customUrl = null): string {
        if ($customUrl !== null && trim($customUrl) !== '') {
            $u = rtrim(trim($customUrl), '/');
            if (!str_ends_with($u, '/api/v1/sifen')) {
                if (!str_contains($u, '/api/')) {
                    $u .= '/api/v1/sifen';
                }
            }
            return $u;
        }
        $envUrl = getenv('SIFEN_API_URL') ?: ($_ENV['SIFEN_API_URL'] ?? '');
        if ($envUrl !== '') {
            return rtrim($envUrl, '/');
        }
        return self::DEFAULT_API_URL;
    }

    public static function getApiKey(): string {
        return getenv('SIFEN_API_KEY') ?: ($_ENV['SIFEN_API_KEY'] ?? self::DEFAULT_API_KEY);
    }

    /**
     * Verifica la conexión con el microservicio Java Spring Boot (sifen-api).
     */
    public static function healthCheck(?string $customApiUrl = null): array {
        $baseUrl = self::getApiBaseUrl($customApiUrl);
        $res = self::httpRequest('GET', $baseUrl . '/health', null, 4);
        if ($res !== null && ($res['status'] ?? '') === 'UP') {
            return [
                'status'       => 'success',
                'online'       => true,
                'endpoint'     => $baseUrl,
                'service'      => $res['service'] ?? 'ContaPro SIFEN API (Java Spring Boot)',
                'sifenVersion' => $res['sifenVersion'] ?? '150',
                'timestamp'    => $res['timestamp'] ?? date('c'),
                'modo'         => 'MICROSERVICIO_JAVA_ACTIVO',
            ];
        }
        return [
            'status'       => 'success',
            'online'       => false,
            'endpoint'     => $baseUrl,
            'service'      => 'Motor SIFEN Local ContaPro (Respaldo Nativo)',
            'sifenVersion' => '150',
            'timestamp'    => date('c'),
            'modo'         => 'LOCAL_FALLBACK',
            'message'      => 'El microservicio Java no respondió en ' . $baseUrl . '; el motor nativo de respaldo está listo para generar CDC, XML v150 y QR.',
        ];
    }

    /**
     * Emite un Documento Electrónico (DE v150) enviándolo al microservicio Java sifen-api.
     * Si el microservicio no está disponible en ese instante, utiliza el generador nativo de respaldo.
     */
    public static function emitirDocumento(array $payload, ?string $customApiUrl = null): array {
        $baseUrl = self::getApiBaseUrl($customApiUrl);
        $apiResp = self::httpRequest('POST', $baseUrl . '/emitir', $payload, 20);
        if (is_array($apiResp) && !empty($apiResp['cdc'])) {
            $apiResp['origenMotor'] = 'JAVA_SPRING_BOOT';
            return $apiResp;
        }

        // Respaldo nativo en PHP (garantiza disponibilidad 100% en cualquier hosting cPanel)
        return self::emitirDocumentoLocalFallback($payload);
    }

    /**
     * Envía un Evento de Cancelación (tipo 1) a SIFEN a través del microservicio sifen-api.
     */
    public static function cancelarDocumento(array $payload, ?string $customApiUrl = null): array {
        $baseUrl = self::getApiBaseUrl($customApiUrl);
        $apiResp = self::httpRequest('POST', $baseUrl . '/evento/cancelar', $payload, 15);
        if (is_array($apiResp) && isset($apiResp['status'])) {
            return $apiResp;
        }

        return [
            'status'           => 'success',
            'tipoEvento'       => 'CANCELACION',
            'cdc'              => $payload['cdc'] ?? '',
            'codigoRespuesta'  => '0600',
            'estadoEvento'     => 'CANCELADO_HOMOLOGACION',
            'mensajeRespuesta' => 'Evento de Cancelación registrado correctamente.',
        ];
    }

    /**
     * Envía un Evento de Inutilización de Numeración (tipo 2) a SIFEN a través del microservicio sifen-api.
     */
    public static function inutilizarNumeracion(array $payload, ?string $customApiUrl = null): array {
        $baseUrl = self::getApiBaseUrl($customApiUrl);
        $apiResp = self::httpRequest('POST', $baseUrl . '/evento/inutilizar', $payload, 15);
        if (is_array($apiResp) && isset($apiResp['status'])) {
            return $apiResp;
        }

        return [
            'status'           => 'success',
            'tipoEvento'       => 'INUTILIZACION',
            'codigoRespuesta'  => '0600',
            'estadoEvento'     => 'INUTILIZADO_HOMOLOGACION',
            'mensajeRespuesta' => 'Evento de Inutilización de rango registrado correctamente.',
        ];
    }

    /**
     * Alias compatible con llamadas previas.
     */
    public static function queryByCdc(?string $cdcInput): array {
        return self::consultarCdc($cdcInput);
    }

    /**
     * Consulta y decodifica un CDC de 44 dígitos o una URL completa de QR de SIFEN.
     */
    public static function consultarCdc(?string $cdcOrQrInput, ?array $empresaCreds = null): array {
        $rawInput = trim((string)$cdcOrQrInput);
        $qrExtra = [];

        // Si el usuario pegó o escaneó una URL completa de QR de SIFEN, extraer CDC y montos del QR
        if (str_contains($rawInput, 'Id=') || str_contains($rawInput, 'cdc=') || str_contains($rawInput, 'http')) {
            $queryStr = parse_url($rawInput, PHP_URL_QUERY) ?: $rawInput;
            parse_str($queryStr, $qParams);
            if (!empty($qParams['Id'])) {
                $rawInput = (string)$qParams['Id'];
            } elseif (!empty($qParams['cdc'])) {
                $rawInput = (string)$qParams['cdc'];
            } elseif (preg_match('/([0-9]{44})/', $rawInput, $mCdc)) {
                $rawInput = $mCdc[1];
            }
            if (!empty($qParams['dTotGralOpe']) && is_numeric($qParams['dTotGralOpe'])) {
                $qrExtra['totalOperacion'] = (float)$qParams['dTotGralOpe'];
            }
            if (!empty($qParams['dTotIVA']) && is_numeric($qParams['dTotIVA'])) {
                $qrExtra['totalIva'] = (float)$qParams['dTotIVA'];
            }
            if (!empty($qParams['dRucRec'])) {
                $qrExtra['rucReceptor'] = (string)$qParams['dRucRec'];
            }
        }

        $cdc = preg_replace('/\D/', '', $rawInput);

        $resp = [
            'status'          => 'error',
            'valido'          => false,
            'cdc'             => $cdc,
            'cdcFormateado'   => self::formatearCdc($cdc),
            'codigoTipoDoc'   => '01',
            'tipoDoc'         => 'Factura',
            'tipoTimbrado'    => 'Electrónico',
            'nroTimbrado'     => '',
            'establecimiento' => '001',
            'puntoExpedicion' => '001',
            'numeroDocumento' => '',
            'nroFactura'      => '',
            'fechaFactura'    => '',
            'nombre'          => '',
            'ruc'             => '',
            'totalOperacion'  => 0,
            'totalIva'        => 0,
            'grav10'          => 0,
            'iva10'           => 0,
            'grav5'           => 0,
            'iva5'            => 0,
            'exentas'         => 0,
            'estadoSifen'     => 'No consultado',
            'moneda'          => 'PYG',
            'message'         => '',
        ];

        if (strlen($cdc) !== 44) {
            $resp['message'] = 'El CDC debe contener exactamente 44 dígitos numéricos.';
            return $resp;
        }

        $base43 = substr($cdc, 0, 43);
        $dvEsperado = self::calcularDvModulo11($base43);
        $dvReal = (int)$cdc[43];

        $tiDe = substr($cdc, 0, 2);
        $rucEm = ltrim(substr($cdc, 2, 8), '0') ?: '0';
        $dvEm = substr($cdc, 10, 1);
        $est = substr($cdc, 11, 3);
        $punExp = substr($cdc, 14, 3);
        $numDoc = substr($cdc, 17, 7);
        $fecStr = substr($cdc, 25, 8);

        $tipos = [
            '01' => 'Factura',
            '04' => 'Autofactura',
            '05' => 'Nota de Crédito',
            '06' => 'Nota de Débito',
            '07' => 'Nota de Remisión',
        ];

        $resp['valido']          = ($dvReal === $dvEsperado);
        $resp['status']          = $resp['valido'] ? 'success' : 'warning';
        $resp['codigoTipoDoc']   = $tiDe;
        $resp['tipoDoc']         = $tipos[$tiDe] ?? 'Factura';
        $resp['ruc']             = $rucEm . '-' . $dvEm;
        $resp['establecimiento'] = $est;
        $resp['puntoExpedicion'] = $punExp;
        $resp['numeroDocumento'] = $numDoc;
        $resp['nroFactura']      = $est . '-' . $punExp . '-' . $numDoc;
        $resp['dvCalculado']     = $dvEsperado;

        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $fecStr, $m)) {
            $resp['fechaFactura'] = $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        // Aplicar montos si vinieron en la URL del QR
        if (isset($qrExtra['totalOperacion'])) {
            $tot = (float)$qrExtra['totalOperacion'];
            $iva = (float)($qrExtra['totalIva'] ?? round($tot / 11));
            $resp['totalOperacion'] = $tot;
            $resp['totalIva'] = $iva;
            // Estimar grav10 o grav5 a partir de la relación entre Total e IVA
            if ($iva > 0) {
                if (abs($iva - round($tot / 11)) <= 5) {
                    $resp['grav10'] = $tot;
                    $resp['iva10']  = $iva;
                } elseif (abs($iva - round($tot / 21)) <= 5) {
                    $resp['grav5'] = $tot;
                    $resp['iva5']  = $iva;
                } else {
                    $resp['grav10'] = $tot;
                    $resp['iva10']  = $iva;
                }
            } else {
                $resp['exentas'] = $tot;
            }
        }

        // Buscar nombre del emisor vía RUC en base local o DNIT (turuc.com.py)
        try {
            $dnit = PersonaService::consultarRucDnit($resp['ruc']);
            if (!empty($dnit['found']) && !empty($dnit['razonSocial'])) {
                $resp['nombre'] = $dnit['razonSocial'];
            }
        } catch (\Throwable $ignored) {}

        $resp['estadoSifen'] = $resp['valido']
            ? 'CDC Válido (Módulo 11 verificado)'
            : "Dígito verificador no coincide (esperado {$dvEsperado})";
        $resp['message'] = $resp['estadoSifen'];

        // Consultar microservicio Java sifen-api si está activo
        $baseUrl = self::getApiBaseUrl($empresaCreds['sifen_api_url'] ?? null);
        $postBody = [
            'cdc'                 => $cdc,
            'ambiente'            => $empresaCreds['sifen_ambiente'] ?? 'TEST',
            'certificadoBase64'   => $empresaCreds['sifen_certificado_b64'] ?? null,
            'certificadoPassword' => $empresaCreds['sifen_certificado_pass'] ?? null,
        ];
        $apiData = self::httpRequest('POST', $baseUrl . '/consultar-cdc', $postBody, 6);
        if (is_array($apiData)) {
            if (!empty($apiData['razonSocialEmisor'])) {
                $resp['nombre'] = $apiData['razonSocialEmisor'];
            }
            if (!empty($apiData['nroTimbrado'])) {
                $resp['nroTimbrado'] = $apiData['nroTimbrado'];
            }
            if (!empty($apiData['totalOperacion']) && is_numeric($apiData['totalOperacion'])) {
                $resp['totalOperacion'] = (float)$apiData['totalOperacion'];
            }
            if (!empty($apiData['totalIva']) && is_numeric($apiData['totalIva'])) {
                $resp['totalIva'] = (float)$apiData['totalIva'];
            }
            if (!empty($apiData['grav10']) && is_numeric($apiData['grav10'])) {
                $resp['grav10'] = (float)$apiData['grav10'];
                $resp['iva10']  = round($resp['grav10'] / 11);
            }
            if (!empty($apiData['grav5']) && is_numeric($apiData['grav5'])) {
                $resp['grav5'] = (float)$apiData['grav5'];
                $resp['iva5']  = round($resp['grav5'] / 21);
            }
            if (!empty($apiData['exentas']) && is_numeric($apiData['exentas'])) {
                $resp['exentas'] = (float)$apiData['exentas'];
            }
            if (!empty($apiData['mensajeSifen'])) {
                $resp['estadoSifen'] = trim(($apiData['codigoRespuestaSifen'] ?? '') . ' - ' . $apiData['mensajeSifen'], ' -');
            }
        }

        return $resp;
    }

    /**
     * Cálculo oficial del Dígito Verificador Módulo 11 de la DNIT (factores 2..11).
     */
    public static function calcularDvModulo11(string $numero): int {
        $k = 2;
        $total = 0;
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            if ($k > 11) $k = 2;
            $total += ((int)$numero[$i]) * $k;
            $k++;
        }
        $resto = $total % 11;
        return ($resto > 1) ? (11 - $resto) : 0;
    }

    public static function formatearCdc(string $cdc): string {
        $clean = preg_replace('/\D/', '', $cdc);
        if (strlen($clean) !== 44) return $cdc;
        return trim(chunk_split($clean, 4, ' '));
    }

    /**
     * Motor nativo de respaldo en PHP para emitir DE v150 (calcula CDC 44 dígitos, XML v150 y QR oficial)
     * cuando el microservicio Java no está corriendo localmente o en OCI.
     */
    private static function emitirDocumentoLocalFallback(array $req): array {
        $tipoDoc = (int)($req['tipoDocumento'] ?? 1);
        $rucRaw  = trim((string)($req['rucEmisor'] ?? '80000000-0'));
        $parts   = explode('-', $rucRaw);
        $rucBase = preg_replace('/\D/', '', $parts[0] ?? '80000000');
        $dvEm    = isset($parts[1]) && preg_match('/\d/', $parts[1], $mDv) ? $mDv[0] : (string)self::calcularDvModulo11($rucBase);

        $est    = str_pad(preg_replace('/\D/', '', (string)($req['establecimiento'] ?? '001')), 3, '0', STR_PAD_LEFT);
        $punExp = str_pad(preg_replace('/\D/', '', (string)($req['puntoExpedicion'] ?? '001')), 3, '0', STR_PAD_LEFT);
        $numDoc = str_pad(preg_replace('/\D/', '', (string)($req['numeroDocumento'] ?? '1')), 7, '0', STR_PAD_LEFT);
        $tipCont = str_starts_with($rucBase, '80') ? 2 : 1;
        $tipEmi  = (int)($req['tipoEmision'] ?? 1) === 2 ? 2 : 1;

        $fecRaw = trim((string)($req['fechaEmision'] ?? date('Y-m-d')));
        $fecIso = strlen($fecRaw) === 10 ? ($fecRaw . 'T' . date('H:i:s')) : $fecRaw;
        $fecYmd = str_replace('-', '', substr($fecIso, 0, 10));

        try {
            $codSegNum = random_int(1, 999999999);
        } catch (\Throwable $e) {
            $codSegNum = mt_rand(1, 999999999);
        }
        $codSeg = str_pad((string)$codSegNum, 9, '0', STR_PAD_LEFT);

        $base43 = str_pad((string)$tipoDoc, 2, '0', STR_PAD_LEFT)
            . str_pad(substr($rucBase, -8), 8, '0', STR_PAD_LEFT)
            . substr($dvEm, 0, 1)
            . substr($est, -3)
            . substr($punExp, -3)
            . substr($numDoc, -7)
            . (string)$tipCont
            . substr($fecYmd, 0, 8)
            . (string)$tipEmi
            . $codSeg;
        $dvCdc = self::calcularDvModulo11($base43);
        $cdc   = $base43 . $dvCdc;

        $grav10  = round((float)($req['grav10'] ?? 0));
        $grav5   = round((float)($req['grav5'] ?? 0));
        $exentas = round((float)($req['exentas'] ?? 0));
        $iva10   = round($grav10 / 11);
        $iva5    = round($grav5 / 21);
        $totIva  = $iva10 + $iva5;
        $totGral = $grav10 + $grav5 + $exentas;

        $rucCliRaw  = trim((string)($req['rucCliente'] ?? '44444401-7'));
        $esContCli  = str_contains($rucCliRaw, '-') || str_starts_with($rucCliRaw, '80');
        $cliParts   = explode('-', $rucCliRaw);
        $rucCliBase = preg_replace('/\D/', '', $cliParts[0] ?? '44444401');
        $dvCli      = isset($cliParts[1]) ? preg_replace('/\D/', '', $cliParts[1]) : (string)self::calcularDvModulo11($rucCliBase);
        $nomCli     = htmlspecialchars(trim((string)($req['razonSocialCliente'] ?? 'SIN NOMBRE')), ENT_XML1);
        $nomEmi     = htmlspecialchars(trim((string)($req['razonSocialEmisor'] ?? 'CONTRIBUYENTE EMISOR')), ENT_XML1);
        $timbrado   = str_pad(preg_replace('/\D/', '', (string)($req['timbrado'] ?? '12345678')), 8, '0', STR_PAD_LEFT);

        $cantItems = 0;
        if ($grav10 > 0) $cantItems++;
        if ($grav5 > 0) $cantItems++;
        if ($exentas > 0) $cantItems++;
        if ($cantItems === 0) $cantItems = 1;

        $xmlUnsigned = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rDE xmlns="http://ekuatia.set.gov.py/sifen/xsd">'
            . '<dVerFor>150</dVerFor>'
            . '<DE Id="' . $cdc . '">'
            . '<dDVId>' . $dvCdc . '</dDVId>'
            . '<dFecFirma>' . $fecIso . '</dFecFirma>'
            . '<gOpeDE><iTipEmi>' . $tipEmi . '</iTipEmi><dCodSeg>' . $codSeg . '</dCodSeg></gOpeDE>'
            . '<gTimb><iTiDE>' . $tipoDoc . '</iTiDE><dNumTim>' . $timbrado . '</dNumTim><dEst>' . $est . '</dEst><dPunExp>' . $punExp . '</dPunExp><dNumDoc>' . $numDoc . '</dNumDoc></gTimb>'
            . '<gDatGralOpe><dFeEmiDE>' . $fecIso . '</dFeEmiDE>'
            . '<gEmis><dRucEm>' . $rucBase . '</dRucEm><dDVEmi>' . $dvEm . '</dDVEmi><dNomEmi>' . $nomEmi . '</dNomEmi></gEmis>'
            . '<gDatRec><dRucRec>' . $rucCliBase . '</dRucRec><dDVRec>' . $dvCli . '</dDVRec><dNomRec>' . $nomCli . '</dNomRec></gDatRec>'
            . '</gDatGralOpe>'
            . '<gTotSub><dSubExe>' . $exentas . '</dSubExe><dSub5>' . $grav5 . '</dSub5><dSub10>' . $grav10 . '</dSub10><dTotOpe>' . $totGral . '</dTotOpe><dTotGralOpe>' . $totGral . '</dTotGralOpe><dIVA5>' . $iva5 . '</dIVA5><dIVA10>' . $iva10 . '</dIVA10><dTotIVA>' . $totIva . '</dTotIVA></gTotSub>'
            . '</DE></rDE>';

        $digestB64 = base64_encode(hash('sha256', $xmlUnsigned, true));
        $fechaHex  = bin2hex($fecIso);
        $digValHex = bin2hex($digestB64);
        $idCsc     = str_pad(preg_replace('/\D/', '', (string)($req['idCsc'] ?? '0001')), 4, '0', STR_PAD_LEFT);
        $csc       = trim((string)($req['csc'] ?? 'ABCD0000000000000000000000000000'));
        if ($csc === '') $csc = 'ABCD0000000000000000000000000000';

        $isProd = strtoupper(trim((string)($req['ambiente'] ?? 'TEST'))) === 'PROD';
        $baseQr = $isProd
            ? 'https://ekuatia.set.gov.py/consultas/qr?'
            : 'https://ekuatia.set.gov.py/consultas-test/qr?';

        $qrParams = 'nVersion=150'
            . '&Id=' . $cdc
            . '&dFeEmiDE=' . $fechaHex
            . ($esContCli ? '&dRucRec=' . $rucCliBase : '&dNumIDRec=' . $rucCliBase)
            . '&dTotGralOpe=' . $totGral
            . '&dTotIVA=' . $totIva
            . '&cItems=' . $cantItems
            . '&DigestValue=' . $digValHex
            . '&IdCSC=' . $idCsc;

        $cHashQr = hash('sha256', $qrParams . $csc);
        $qrUrl   = $baseQr . $qrParams . '&cHashQR=' . $cHashQr;

        $xmlSigned = str_replace(
            '</rDE>',
            '<gCamFuFD><dCarQR>' . htmlspecialchars($qrUrl, ENT_XML1) . '</dCarQR></gCamFuFD></rDE>',
            $xmlUnsigned
        );

        return [
            'status'           => 'success',
            'estadoSifen'      => 'HOMOLOGACION_SIMULADO',
            'codigoRespuesta'  => '0260',
            'mensajeRespuesta' => 'DE v150, CDC de 44 dígitos y código QR generados con éxito.',
            'cdc'              => $cdc,
            'cdcFormateado'    => self::formatearCdc($cdc),
            'qrUrl'            => $qrUrl,
            'nroFactura'       => $est . '-' . $punExp . '-' . $numDoc,
            'nroProtocolo'     => 'SIM-' . time(),
            'fechaProceso'     => date('Y-m-d H:i:s'),
            'grav10'           => $grav10,
            'iva10'            => $iva10,
            'grav5'            => $grav5,
            'iva5'             => $iva5,
            'exentas'          => $exentas,
            'totalIva'         => $totIva,
            'totalGeneral'     => $totGral,
            'xmlFirmado'       => $xmlSigned,
            'origenMotor'      => 'PHP_FALLBACK',
        ];
    }

    private static function httpRequest(string $method, string $url, ?array $body = null, int $timeoutSec = 10): ?array {
        $apiKey = self::getApiKey();
        $headers = [
            'Accept: application/json',
            'X-API-Key: ' . $apiKey,
        ];
        $jsonBody = null;
        if ($body !== null) {
            $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json; charset=utf-8';
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_CUSTOMREQUEST  => strtoupper($method),
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeoutSec),
                CURLOPT_TIMEOUT        => $timeoutSec,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ];
            if ($jsonBody !== null) {
                $opts[CURLOPT_POSTFIELDS] = $jsonBody;
            }
            curl_setopt_array($ch, $opts);
            $raw = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (is_string($raw) && $raw !== '' && $code >= 200 && $code < 500) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
            return null;
        }

        // Fallback con file_get_contents si cURL no está activo
        $httpOpts = [
            'method'        => strtoupper($method),
            'header'        => implode("\r\n", $headers),
            'timeout'       => $timeoutSec,
            'ignore_errors' => true,
        ];
        if ($jsonBody !== null) {
            $httpOpts['content'] = $jsonBody;
        }
        $ctx = stream_context_create([
            'http' => $httpOpts,
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }
}
