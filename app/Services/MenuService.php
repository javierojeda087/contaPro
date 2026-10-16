<?php
namespace App\Services;

use Core\Database;
use Core\RouteHelper;
use Core\TenantContext;

class MenuService {

    private static array $FORMAS_EXCLUIDAS = [
        'PVFACTUR', 'COFACTUR', 'CMORDCOM', 'CMCORDCO', 'CMCONDIC', 'STTRASLA', 'STINVENT',
        'CCRECIBO', 'CCCOBROS', 'CCCOBROA', 'CCCAJASV', 'CCHABCAJ', 'CCCUENTA', 'CCDEPOSI',
        'CCEXTRAC', 'CCLSTREC', 'CCRENDIC', 'CPPAGOSP', 'CPPAGOSA', 'BSGRPUSU',
        'BSTIPCOM', 'BSSERCOM', 'BSGRUPOS', 'BSEMPRES'
    ];

    public static function obtenerMenuPorUsuario(?string $usuario, ?string $grupo = null): array {
        return self::getMenuForUsuario($usuario);
    }

    public static function getMenuForUsuario(?string $usuario): array {
        if (!$usuario || trim($usuario) === '') {
            return [];
        }

        $propId = TenantContext::idPropietario();
        $cleanUser = strtoupper(trim($usuario));

        $sql = "SELECT m.cod_modulo,
                       m.descripcion AS modulo_desc,
                       m.nombre_imagen,
                       a.nom_forma,
                       COALESCE(f.titulo, a.item_menu, a.nom_forma) AS item_menu,
                       COALESCE(f.tipo, 'D') AS tipo,
                       COALESCE(f.titulo, a.item_menu, a.nom_forma) AS titulo
                FROM modulos m
                JOIN accesos_grupos a
                  ON a.id_propietario = m.id_propietario
                 AND UPPER(TRIM(m.cod_modulo)) = UPPER(TRIM(a.cod_modulo))
                JOIN usuarios u
                  ON u.id_propietario = m.id_propietario
                 AND UPPER(TRIM(COALESCE(u.cod_grupo, 'CONTA'))) = UPPER(TRIM(a.cod_grupo))
                LEFT JOIN formas f
                  ON f.id_propietario = m.id_propietario
                 AND UPPER(TRIM(a.nom_forma)) = UPPER(TRIM(f.nom_forma))
                WHERE m.id_propietario = ?
                  AND UPPER(TRIM(u.cod_usuario)) = ?
                  AND (a.mostrar_en_menu IS NULL OR UPPER(TRIM(a.mostrar_en_menu)) IN ('S','1','A'))
                  AND (m.activo IS NULL OR UPPER(TRIM(m.activo)) IN ('S','1','A'))
                ORDER BY m.orden_menu, f.orden_en_menu, a.item_menu";

        $rows = Database::queryForList($sql, [$propId, $cleanUser]);

        // Fallback: si ese propietario aún no tiene filas en accesos_grupos, usar plantilla del propietario 1
        if (empty($rows)) {
            $sqlFallback = "SELECT m.cod_modulo,
                                   m.descripcion AS modulo_desc,
                                   m.nombre_imagen,
                                   a.nom_forma,
                                   COALESCE(f.titulo, a.item_menu, a.nom_forma) AS item_menu,
                                   COALESCE(f.tipo, 'D') AS tipo,
                                   COALESCE(f.titulo, a.item_menu, a.nom_forma) AS titulo
                            FROM modulos m
                            JOIN accesos_grupos a
                              ON a.id_propietario = m.id_propietario
                             AND UPPER(TRIM(m.cod_modulo)) = UPPER(TRIM(a.cod_modulo))
                            LEFT JOIN formas f
                              ON f.id_propietario = m.id_propietario
                             AND UPPER(TRIM(a.nom_forma)) = UPPER(TRIM(f.nom_forma))
                            WHERE m.id_propietario = 1
                              AND UPPER(TRIM(a.cod_grupo)) = 'CONTA'
                              AND (a.mostrar_en_menu IS NULL OR UPPER(TRIM(a.mostrar_en_menu)) IN ('S','1','A'))
                              AND (m.activo IS NULL OR UPPER(TRIM(m.activo)) IN ('S','1','A'))
                            ORDER BY m.orden_menu, f.orden_en_menu, a.item_menu";
            $rows = Database::queryForList($sqlFallback);
        }

        $modulosMap = [];
        foreach ($rows as $row) {
            $codModulo = strtoupper(trim((string)($row['cod_modulo'] ?? '')));
            if ($codModulo === '') continue;
            if ($codModulo === 'ADM') $codModulo = 'AD';

            $nomForma = strtoupper(trim((string)($row['nom_forma'] ?? '')));
            if ($nomForma === '' || in_array($nomForma, self::$FORMAS_EXCLUIDAS, true)) {
                continue;
            }

            $moduloDesc = trim((string)($row['modulo_desc'] ?? $codModulo));
            $nombreImagen = trim((string)($row['nombre_imagen'] ?? 'ph-folder'));
            $itemMenu = trim((string)($row['item_menu'] ?? $nomForma));
            $tipo = strtoupper(trim((string)($row['tipo'] ?? 'D')));
            $titulo = trim((string)($row['titulo'] ?? $itemMenu));

            if (!isset($modulosMap[$codModulo])) {
                $modulosMap[$codModulo] = [
                    'codModulo'    => $codModulo,
                    'descripcion'  => $moduloDesc,
                    'nombreImagen' => $nombreImagen,
                    'definiciones' => [],
                    'movimientos'  => [],
                    'consultas'    => [],
                    'reportes'     => [],
                ];
            }

            $acceso = [
                'nomForma' => $nomForma,
                'itemMenu' => $itemMenu,
                'tipo'     => $tipo,
                'titulo'   => $titulo,
                'route'    => RouteHelper::getRoute($nomForma),
            ];

            switch ($tipo) {
                case 'M':
                    $modulosMap[$codModulo]['movimientos'][] = $acceso;
                    break;
                case 'C':
                    $modulosMap[$codModulo]['consultas'][] = $acceso;
                    break;
                case 'R':
                    $modulosMap[$codModulo]['reportes'][] = $acceso;
                    break;
                default:
                    $modulosMap[$codModulo]['definiciones'][] = $acceso;
                    break;
            }
        }

        // Asegurar acceso a SuperAdmin de Planes y Propietarios si el usuario es SuperAdmin
        if (TenantContext::esSuperAdmin()) {
            if (!isset($modulosMap['AD'])) {
                $modulosMap['AD'] = [
                    'codModulo'    => 'AD',
                    'descripcion'  => 'ADMINISTRACION',
                    'nombreImagen' => 'ph-buildings',
                    'definiciones' => [],
                    'movimientos'  => [],
                    'consultas'    => [],
                    'reportes'     => [],
                ];
            }
            array_unshift($modulosMap['AD']['definiciones'], [
                'nomForma' => 'SUPERADMIN',
                'itemMenu' => 'Propietarios y Planes (SaaS)',
                'tipo'     => 'D',
                'titulo'   => 'Propietarios y Planes (SaaS)',
                'route'    => 'superadmin',
            ]);
        }

        return array_values($modulosMap);
    }
}
