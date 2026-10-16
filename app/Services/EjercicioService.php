<?php
namespace App\Services;

use Core\Database;
use Core\TenantContext;

class EjercicioService {

    public static function getEjercicio(): string {
        return TenantContext::ejercicio();
    }

    /**
     * Retorna [$ini, $fin] en formato 'YYYY-MM-DD' del ejercicio activo de la empresa y propietario
     */
    public static function getRango(string $codEmpresa, string $ejercicio): array {
        $propId = TenantContext::idPropietario();
        $row = Database::queryForMap(
            "SELECT DATE_FORMAT(fec_inicio, '%Y-%m-%d') AS ini, DATE_FORMAT(fec_final, '%Y-%m-%d') AS fin
             FROM co_ejercicios
             WHERE id_propietario = ? AND cod_empresa = ? AND UPPER(cod_ejercicio) = UPPER(?)",
            [$propId, $codEmpresa, $ejercicio]
        );
        if ($row && !empty($row['ini']) && !empty($row['fin'])) {
            return [$row['ini'], $row['fin']];
        }
        $y = (int)preg_replace('/[^0-9]/', '', $ejercicio);
        if ($y < 1990 || $y > 2100) {
            $y = (int)date('Y');
        }
        return [sprintf('%04d-01-01', $y), sprintf('%04d-12-31', $y)];
    }

    public static function clamp(?string $fecha, array $rango, bool $esDesde): string {
        $ini = $rango[0];
        $fin = $rango[1];
        $d = ($fecha !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($fecha)))
            ? trim($fecha)
            : ($esDesde ? $ini : $fin);
        if ($d < $ini) $d = $ini;
        if ($d > $fin) $d = $fin;
        return $d;
    }

    public static function defaultMes(array $rango): array {
        $today = date('Y-m-d');
        $ref = ($today < $rango[0] || $today > $rango[1]) ? $rango[0] : $today;
        $ts = strtotime($ref);
        $first = date('Y-m-01', $ts);
        $last  = date('Y-m-t', $ts);
        return [
            self::clamp($first, $rango, true),
            self::clamp($last, $rango, false)
        ];
    }

    public static function getFirmantes(string $codEmpresa, string $ejercicio): array {
        $propId = TenantContext::idPropietario();
        $res = [
            'repLegal'    => '',
            'nomContador' => '',
            'rucContador' => ''
        ];
        try {
            $row = Database::queryForMap(
                "SELECT p2.nombre AS nom_repres,
                        p3.nombre AS nom_contador,
                        (SELECT MAX(i.numero) FROM ident_personas i WHERE i.id_propietario = e.id_propietario AND TRIM(i.cod_persona) = TRIM(e.cod_per_contador)) AS ruc_contador
                 FROM co_ejercicios e
                 LEFT JOIN personas p2 ON p2.id_propietario = e.id_propietario AND TRIM(p2.cod_persona) = TRIM(e.cod_per_repres_legal)
                 LEFT JOIN personas p3 ON p3.id_propietario = e.id_propietario AND TRIM(p3.cod_persona) = TRIM(e.cod_per_contador)
                 WHERE e.id_propietario = ? AND e.cod_empresa = ? AND UPPER(e.cod_ejercicio) = UPPER(?)",
                [$propId, $codEmpresa, $ejercicio]
            );
            if ($row) {
                $res['repLegal']    = (string)($row['nom_repres'] ?? '');
                $res['nomContador'] = (string)($row['nom_contador'] ?? '');
                $res['rucContador'] = (string)($row['ruc_contador'] ?? '');
            }
        } catch (\Throwable $ignored) {}
        return $res;
    }
}
