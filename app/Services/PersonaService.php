<?php
namespace App\Services;

use Core\Database;
use Core\TenantContext;

class PersonaService {

    public static function syncPersonaData(
        ?string $codPersonaInput,
        ?string $nombre,
        ?string $ruc,
        ?string $tipoDocumento = 'RUC',
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null
    ): ?string {
        if ($nombre === null || trim($nombre) === '') {
            return null;
        }

        $propId = TenantContext::idPropietario();
        $doc = $ruc !== null ? trim($ruc) : '';
        $nom = mb_strtoupper(trim($nombre), 'UTF-8');
        $dir = $direccion !== null ? trim($direccion) : '';
        $tel = $telefono !== null ? trim($telefono) : '';
        $mail = $email !== null ? trim($email) : '';
        $codIdent = ($tipoDocumento !== null && trim($tipoDocumento) !== '') ? strtoupper(trim($tipoDocumento)) : 'RUC';

        $finalCodPersona = null;

        // 1. Si vino cod_persona válido, verificar si existe para este propietario
        if ($codPersonaInput !== null && trim($codPersonaInput) !== '') {
            $cleanCod = trim($codPersonaInput);
            $exists = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM personas WHERE id_propietario = ? AND TRIM(cod_persona) = ?",
                [$propId, $cleanCod]
            );
            if ($exists > 0) {
                $finalCodPersona = $cleanCod;
            }
        }

        // 2. Buscar por RUC / CI en ident_personas o personas de este propietario
        if ($finalCodPersona === null && $doc !== '') {
            $docBase = strpos($doc, '-') !== false ? trim(explode('-', $doc)[0]) : $doc;
            $found = Database::queryForValue(
                "SELECT p.cod_persona
                 FROM personas p
                 INNER JOIN ident_personas i ON i.id_propietario = p.id_propietario AND TRIM(i.cod_persona) = TRIM(p.cod_persona)
                 WHERE p.id_propietario = ?
                   AND (UPPER(TRIM(i.numero)) = UPPER(?) OR UPPER(SUBSTRING_INDEX(TRIM(i.numero), '-', 1)) = UPPER(?))
                 LIMIT 1",
                [$propId, $doc, $docBase]
            );
            if ($found !== null && $found !== '') {
                $finalCodPersona = (string)$found;
            }
        }

        // 3. Crear nueva persona si no existe
        if ($finalCodPersona === null) {
            $maxCod = (int)Database::queryForValue(
                "SELECT COALESCE(MAX(CAST(cod_persona AS UNSIGNED)), 0)
                 FROM personas
                 WHERE id_propietario = ? AND CAST(cod_persona AS UNSIGNED) < 3000",
                [$propId]
            );
            $nextId = max(1, $maxCod + 1);
            while (true) {
                $taken = (int)Database::queryForValue(
                    "SELECT COUNT(*) FROM personas WHERE id_propietario = ? AND TRIM(cod_persona) = ?",
                    [$propId, (string)$nextId]
                );
                if ($taken === 0) break;
                $nextId++;
            }
            $finalCodPersona = (string)$nextId;

            Database::update("DELETE FROM ident_personas WHERE id_propietario = ? AND TRIM(cod_persona) = ?", [$propId, $finalCodPersona]);
            Database::update("DELETE FROM telef_personas WHERE id_propietario = ? AND TRIM(cod_persona) = ?", [$propId, $finalCodPersona]);
            Database::update("DELETE FROM direc_personas WHERE id_propietario = ? AND TRIM(cod_persona) = ?", [$propId, $finalCodPersona]);

            $esFisica = (strpos($doc, '-') !== false && str_starts_with($doc, '800')) ? 'N' : 'S';

            Database::update(
                "INSERT INTO personas (id_propietario, cod_persona, nombre, direc_electronica, es_fisica, fec_alta)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$propId, $finalCodPersona, $nom, $mail, $esFisica]
            );
        } else {
            Database::update(
                "UPDATE personas SET nombre = ?, direc_electronica = COALESCE(NULLIF(?, ''), direc_electronica)
                 WHERE id_propietario = ? AND TRIM(cod_persona) = ?",
                [$nom, $mail, $propId, $finalCodPersona]
            );
        }

        // Actualizar columnas directas en personas si existen en el esquema
        try {
            Database::update(
                "UPDATE personas
                 SET ruc = COALESCE(NULLIF(?, ''), ruc),
                     direccion = COALESCE(NULLIF(?, ''), direccion),
                     telefono = COALESCE(NULLIF(?, ''), telefono),
                     email = COALESCE(NULLIF(?, ''), email),
                     tipo_documento = COALESCE(NULLIF(?, ''), tipo_documento),
                     estado = COALESCE(estado, 'A')
                 WHERE id_propietario = ? AND TRIM(cod_persona) = ?",
                [$doc, $dir, $tel, $mail, $codIdent, $propId, $finalCodPersona]
            );
        } catch (\Throwable $ignored) {}

        // 4. Sincronizar ident_personas (cod_ident es VARCHAR(5), y la tabla usa observacion)
        if ($doc !== '') {
            $codIdentShort = ($codIdent === 'CEDULA' || $codIdent === 'CI') ? 'CI' : substr($codIdent, 0, 5);
            $cntIdent = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM ident_personas WHERE id_propietario = ? AND TRIM(cod_persona) = ?",
                [$propId, $finalCodPersona]
            );
            if ($cntIdent > 0) {
                Database::update(
                    "UPDATE ident_personas SET numero = ?, cod_ident = ? WHERE id_propietario = ? AND TRIM(cod_persona) = ?",
                    [$doc, $codIdentShort, $propId, $finalCodPersona]
                );
            } else {
                Database::update(
                    "INSERT INTO ident_personas (id_propietario, cod_persona, cod_ident, numero, observacion)
                     VALUES (?, ?, ?, ?, 'Registro')",
                    [$propId, $finalCodPersona, $codIdentShort, $doc]
                );
            }
        }

        // 5. Sincronizar telef_personas
        if ($tel !== '') {
            $cntTel = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM telef_personas WHERE id_propietario = ? AND TRIM(cod_persona) = ?",
                [$propId, $finalCodPersona]
            );
            if ($cntTel > 0) {
                Database::update(
                    "UPDATE telef_personas SET num_telefono = ? WHERE id_propietario = ? AND TRIM(cod_persona) = ?",
                    [$tel, $propId, $finalCodPersona]
                );
            } else {
                Database::update(
                    "INSERT INTO telef_personas (id_propietario, cod_persona, codigo_area, num_telefono, tip_telefono, tel_ubicacion, por_defecto)
                     VALUES (?, ?, '+595', ?, 'M', 'C', 'S')",
                    [$propId, $finalCodPersona, $tel]
                );
            }
        }

        // 6. Sincronizar direc_personas
        if ($dir !== '') {
            $cntDir = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM direc_personas WHERE id_propietario = ? AND TRIM(cod_persona) = ?",
                [$propId, $finalCodPersona]
            );
            if ($cntDir > 0) {
                Database::update(
                    "UPDATE direc_personas SET detalle = ? WHERE id_propietario = ? AND TRIM(cod_persona) = ?",
                    [$dir, $propId, $finalCodPersona]
                );
            } else {
                Database::update(
                    "INSERT INTO direc_personas (id_propietario, cod_persona, cod_direccion, detalle, tip_direccion, por_defecto)
                     VALUES (?, ?, 1, ?, 'C', 'S')",
                    [$propId, $finalCodPersona, $dir]
                );
            }
        }

        return $finalCodPersona;
    }

    /**
     * Consulta un RUC o CI en la base local del propietario o vía API pública turuc.com.py (DNIT)
     */
    public static function consultarRucDnit(string $rucInput): array {
        $propId = TenantContext::idPropietario();
        $clean = strtoupper(trim($rucInput));
        $base = strpos($clean, '-') !== false ? trim(explode('-', $clean)[0]) : $clean;
        $baseDigits = preg_replace('/[^0-9]/', '', $base);

        if (strlen($baseDigits) < 3) {
            return ['status' => 'not_found', 'found' => false, 'message' => 'RUC inválido'];
        }

        // 1. Buscar en personas locales del propietario
        $local = Database::queryForMap(
            "SELECT p.cod_persona, p.nombre, i.numero AS ruc,
                    (SELECT MAX(d.detalle) FROM direc_personas d WHERE d.id_propietario = p.id_propietario AND TRIM(d.cod_persona) = TRIM(p.cod_persona)) AS direccion,
                    (SELECT MAX(t.num_telefono) FROM telef_personas t WHERE t.id_propietario = p.id_propietario AND TRIM(t.cod_persona) = TRIM(p.cod_persona)) AS telefono
             FROM personas p
             JOIN ident_personas i ON i.id_propietario = p.id_propietario AND TRIM(i.cod_persona) = TRIM(p.cod_persona)
             WHERE p.id_propietario = ?
               AND (UPPER(TRIM(i.numero)) = ? OR SUBSTRING_INDEX(UPPER(TRIM(i.numero)), '-', 1) = ?)
             LIMIT 1",
            [$propId, $clean, $baseDigits]
        );

        if ($local) {
            $fullRuc = $local['ruc'] ?? ($baseDigits . '-' . self::calcularDvParaguay($baseDigits));
            $parts = explode('-', $fullRuc);
            return [
                'status'      => 'success',
                'found'       => true,
                'source'      => 'LOCAL',
                'codPersona'  => $local['cod_persona'],
                'razonSocial' => $local['nombre'],
                'nombre'      => $local['nombre'],
                'ruc'         => $fullRuc,
                'dv'          => $parts[1] ?? self::calcularDvParaguay($baseDigits),
                'direccion'   => $local['direccion'] ?? '',
                'telefono'    => $local['telefono'] ?? '',
                'estado'      => 'ACTIVO'
            ];
        }

        // 2. Consultar API externa turuc.com.py
        $dvCalc = self::calcularDvParaguay($baseDigits);
        $url = "https://turuc.com.py/api/contribuyente?ruc=" . urlencode($baseDigits);
        $raw = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ContaPro/1.0',
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
        }
        if ($raw === false || $raw === '') {
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'GET',
                    'timeout' => 5,
                    'header'  => "Accept: application/json\r\nUser-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) ContaPro/1.0\r\n"
                ],
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false
                ]
            ]);
            $raw = @file_get_contents($url, false, $ctx);
        }
        if (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            $data = $json['data'] ?? $json;
            $razon = $data['razonSocial'] ?? $data['nombre'] ?? null;
            if ($razon) {
                $dv = (string)($data['dv'] ?? $dvCalc);
                return [
                    'status'      => 'success',
                    'found'       => true,
                    'source'      => 'DNIT',
                    'razonSocial' => mb_strtoupper(trim($razon), 'UTF-8'),
                    'nombre'      => mb_strtoupper(trim($razon), 'UTF-8'),
                    'ruc'         => $baseDigits . '-' . $dv,
                    'dv'          => $dv,
                    'estado'      => $data['estado'] ?? 'ACTIVO'
                ];
            }
        }

        return [
            'status'  => 'not_found',
            'found'   => false,
            'ruc'     => $baseDigits . '-' . $dvCalc,
            'dv'      => (string)$dvCalc,
            'message' => 'No encontrado en DNIT'
        ];
    }

    public static function calcularDvParaguay(string $rucSinDv): int {
        $digits = preg_replace('/[^0-9]/', '', $rucSinDv);
        if ($digits === '') return 0;
        $baseMax = 11;
        $k = 2;
        $total = 0;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            if ($k > $baseMax) $k = 2;
            $total += ((int)$digits[$i]) * $k;
            $k++;
        }
        $resto = $total % 11;
        return $resto > 1 ? 11 - $resto : 0;
    }
}
