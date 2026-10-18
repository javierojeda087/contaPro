<?php
namespace App\Controllers;

use Core\Database;
use Core\TenantContext;
use Core\View;
use App\Services\MenuService;
use App\Services\BackupService;
use App\Services\PersonaService;

class AdminController {

    private static function requireAuth(bool $json = true): bool {
        if (!TenantContext::check()) {
            if ($json) {
                View::json(['status' => 'error', 'message' => 'No autenticado', 'error' => 'No autenticado'], 401);
            } else {
                header('Location: /login');
            }
            return false;
        }
        return true;
    }

    private static function renderFormPage(string $template, array $extra = []): void {
        if (!self::requireAuth(false)) return;
        $menu = MenuService::obtenerMenuPorUsuario(TenantContext::usuario(), TenantContext::grupo());
        View::renderTemplate($template, array_merge([
            'usuario'    => TenantContext::usuario(),
            'empresa'    => TenantContext::empresa(),
            'sucursal'   => TenantContext::sucursal(),
            'nomEmpresa' => TenantContext::nomEmpresa(),
            'ejercicio'  => TenantContext::ejercicio(),
            'modulos'    => $menu,
        ], $extra));
    }

    private static function getInput(): array {
        $raw = file_get_contents('php://input');
        $json = $raw ? json_decode($raw, true) : null;
        if (is_array($json)) {
            return array_merge($_GET, $_POST, $json);
        }
        return array_merge($_GET, $_POST);
    }

    private static function rucBase(?string $ruc): string {
        if (!$ruc) return '';
        $r = strtoupper(trim($ruc));
        return str_contains($r, '-') ? trim(explode('-', $r)[0]) : $r;
    }

    private static function isConadm(): bool {
        if (!TenantContext::check()) return false;
        $user = strtoupper(trim(TenantContext::usuario()));
        if ($user === 'CONTA') return true;
        $propId = TenantContext::idPropietario();
        $dbGrupo = strtoupper(trim((string)(Database::queryForValue(
            "SELECT cod_grupo FROM usuarios WHERE id_propietario = ? AND UPPER(TRIM(cod_usuario)) = ? LIMIT 1",
            [$propId, $user]
        ) ?? TenantContext::grupo())));
        return $dbGrupo === 'CONADM';
    }

    private static function requireConadm(bool $json = true): bool {
        if (!self::requireAuth($json)) return false;
        if (!self::isConadm()) {
            if ($json) {
                View::json(['status' => 'error', 'message' => 'Acceso restringido exclusivamente al grupo CONADM.'], 403);
            } else {
                header('Location: /menu');
            }
            return false;
        }
        return true;
    }

    // =========================================================================
    // VISTAS HTML (Módulo AD)
    // =========================================================================
    public static function pageContribuyentes(): void { self::renderFormPage('contribuyentes'); }
    public static function pageEmpresas(): void       { self::renderFormPage('empresas'); }
    public static function pageActividades(): void    { self::renderFormPage('co_actividades'); }
    public static function pageUsuarios(): void       { self::renderFormPage('usuarios'); }
    public static function pageAccesosGrupos(): void  {
        if (!self::requireConadm(false)) return;
        self::renderFormPage('accesos_grupos');
    }
    public static function pageCambiarClave(): void   { self::renderFormPage('cambiar_clave'); }
    public static function pageBackup(): void         { self::renderFormPage('backup'); }

    // =========================================================================
    // API CONTRIBUYENTES (/api/contribuyentes/*)
    // =========================================================================
    public static function apiContribuyentesList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $search = trim((string)($_GET['search'] ?? ''));

        try {
            $sql = "SELECT e.cod_empresa, e.descripcion, e.ruc_empresa, e.direccion, e.actividad,
                           e.retiene_iva, e.ind_facturacion_electronica, e.localidad, e.departamento,
                           (SELECT MAX(t.num_telefono)
                            FROM telef_personas t
                            JOIN ident_personas i ON i.id_propietario = t.id_propietario AND i.cod_persona = t.cod_persona
                            WHERE i.id_propietario = e.id_propietario
                              AND UPPER(TRIM(i.numero)) = UPPER(TRIM(e.ruc_empresa))) AS telefono
                    FROM empresas e
                    WHERE e.id_propietario = ? ";
            $params = [$idProp];
            if ($search !== '') {
                $sql .= "AND (UPPER(e.cod_empresa) LIKE ? OR UPPER(e.descripcion) LIKE ? OR UPPER(e.ruc_empresa) LIKE ?) ";
                $pat = '%' . strtoupper($search) . '%';
                $params[] = $pat;
                $params[] = $pat;
                $params[] = $pat;
            }
            $sql .= "ORDER BY CAST(e.cod_empresa AS UNSIGNED) ASC, e.cod_empresa ASC";
            $rows = Database::queryForListUpper($sql, $params);
            View::json(['status' => 'success', 'data' => $rows]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al consultar contribuyentes: ' . $e->getMessage()]);
        }
    }

    public static function apiContribuyentesVerificarRuc(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $ruc = trim((string)($_GET['ruc'] ?? ''));
        $codEmpresa = trim((string)($_GET['codEmpresa'] ?? ''));
        $base = self::rucBase($ruc);

        if (strlen($base) < 3) {
            View::json(['status' => 'INVALIDO']);
            return;
        }

        // 1. ¿Ya es contribuyente de este propietario?
        $emp = Database::queryForMap(
            "SELECT cod_empresa, descripcion FROM empresas
             WHERE id_propietario = ?
               AND UPPER(TRIM(SUBSTRING_INDEX(ruc_empresa, '-', 1))) = ?
               AND cod_empresa <> COALESCE(NULLIF(?, ''), '#')
             LIMIT 1",
            [$idProp, $base, $codEmpresa]
        );
        if ($emp) {
            View::json([
                'status'     => 'CONTRIBUYENTE_EXISTE',
                'codEmpresa' => (string)$emp['cod_empresa'],
                'nombre'     => (string)$emp['descripcion'],
            ]);
            return;
        }

        // 2. ¿Existe como persona en este propietario?
        $per = Database::queryForMap(
            "SELECT i.cod_persona, i.numero, p.nombre
             FROM ident_personas i
             LEFT JOIN personas p ON p.id_propietario = i.id_propietario AND p.cod_persona = i.cod_persona
             WHERE i.id_propietario = ?
               AND UPPER(TRIM(SUBSTRING_INDEX(i.numero, '-', 1))) = ?
             LIMIT 1",
            [$idProp, $base]
        );
        if ($per) {
            $cod = (string)$per['cod_persona'];
            $dir = (string)(Database::queryForValue(
                "SELECT detalle FROM direc_personas WHERE id_propietario = ? AND cod_persona = ? ORDER BY CASE WHEN por_defecto = 'S' THEN 0 ELSE 1 END LIMIT 1",
                [$idProp, $cod]
            ) ?? '');
            $tel = (string)(Database::queryForValue(
                "SELECT num_telefono FROM telef_personas WHERE id_propietario = ? AND cod_persona = ? ORDER BY CASE WHEN por_defecto = 'S' THEN 0 ELSE 1 END LIMIT 1",
                [$idProp, $cod]
            ) ?? '');
            View::json([
                'status'     => 'PERSONA_EXISTE',
                'codPersona' => $cod,
                'ruc'        => (string)$per['numero'],
                'nombre'     => (string)$per['nombre'],
                'direccion'  => $dir,
                'telefono'   => $tel,
            ]);
            return;
        }

        // 3. Consultar turuc.com.py (DNIT)
        $ctx = stream_context_create([
            'http' => ['timeout' => 4, 'ignore_errors' => true],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $raw = @file_get_contents('https://turuc.com.py/api/contribuyente?ruc=' . urlencode($base), false, $ctx);
        if ($raw !== false) {
            $json = json_decode($raw, true);
            $d = $json['data'] ?? null;
            if (is_array($d) && !empty($d['razonSocial'])) {
                $rucFull = !empty($d['ruc']) ? (string)$d['ruc'] : ($base . (isset($d['dv']) ? '-' . $d['dv'] : ''));
                View::json([
                    'status' => 'DNIT',
                    'ruc'    => $rucFull,
                    'nombre' => trim((string)$d['razonSocial']),
                    'estado' => (string)($d['estado'] ?? ''),
                ]);
                return;
            }
        }

        View::json(['status' => 'NO_ENCONTRADO']);
    }

    public static function apiContribuyentesSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $payload = self::getInput();

        $codEmpresa        = trim((string)($payload['codEmpresa'] ?? $payload['cod_empresa'] ?? ''));
        $descripcion       = strtoupper(trim((string)($payload['descripcion'] ?? '')));
        $rucEmpresa        = trim((string)($payload['rucEmpresa'] ?? $payload['ruc_empresa'] ?? ''));
        $direccion         = trim((string)($payload['direccion'] ?? ''));
        $actividad         = trim((string)($payload['actividad'] ?? ''));
        $localidad         = trim((string)($payload['localidad'] ?? ''));
        $departamento      = trim((string)($payload['departamento'] ?? ''));
        $retieneIva        = (!empty($payload['retieneIva']) && $payload['retieneIva'] !== 'N') ? 'S' : 'N';
        $indFactElectronica = (!empty($payload['indFacturacionElectronica']) && $payload['indFacturacionElectronica'] !== 'N') ? 'S' : 'N';
        $telefono          = trim((string)($payload['telefono'] ?? ''));
        $codPersonaPayload = trim((string)($payload['codPersona'] ?? ''));

        if ($descripcion === '') {
            View::json(['status' => 'error', 'message' => 'La Razón Social o Nombre del Contribuyente es obligatorio.']);
            return;
        }
        if ($rucEmpresa === '' || $direccion === '' || $telefono === '') {
            View::json(['status' => 'error', 'message' => 'RUC, Dirección y Teléfono son obligatorios.']);
            return;
        }

        // Validar RUC duplicado en el propietario
        $dup = (int)Database::queryForValue(
            "SELECT COUNT(*) FROM empresas
             WHERE id_propietario = ?
               AND UPPER(TRIM(SUBSTRING_INDEX(ruc_empresa, '-', 1))) = ?
               AND cod_empresa <> COALESCE(NULLIF(?, ''), '#')",
            [$idProp, self::rucBase($rucEmpresa), $codEmpresa]
        );
        if ($dup > 0) {
            View::json(['status' => 'error', 'message' => "Ya existe un contribuyente con el RUC {$rucEmpresa}."]);
            return;
        }

        try {
            $esNuevo = ($codEmpresa === '');
            if (!$esNuevo) {
                $cnt = (int)Database::queryForValue(
                    "SELECT COUNT(*) FROM empresas WHERE id_propietario = ? AND cod_empresa = ?",
                    [$idProp, $codEmpresa]
                );
                $esNuevo = ($cnt === 0);
            }

            if ($esNuevo) {
                // CONTROL DE LÍMITE DE CONTRIBUYENTES SEGÚN EL PLAN DEL PROPIETARIO
                $plan = TenantContext::getPlanSummary();
                if ($plan['usados_contribuyentes'] >= $plan['max_contribuyentes']) {
                    View::json([
                        'status'  => 'error',
                        'message' => "Límite de plan alcanzado: Su plan actual ({$plan['nombre_plan']}) permite hasta {$plan['max_contribuyentes']} contribuyente(s) y ya tiene {$plan['usados_contribuyentes']} registrado(s). Contacte al SuperAdmin de ContaPro para ampliar su plan.",
                    ]);
                    return;
                }

                if ($codEmpresa === '') {
                    $codEmpresa = (string)Database::queryForValue(
                        "SELECT COALESCE(MAX(CAST(cod_empresa AS UNSIGNED)), 0) + 1 FROM empresas WHERE id_propietario = ?",
                        [$idProp]
                    );
                }

                Database::update(
                    "INSERT INTO empresas (
                        id_propietario, cod_empresa, descripcion, ruc_empresa, direccion,
                        actividad, localidad, departamento, retiene_iva, ind_facturacion_electronica
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$idProp, $codEmpresa, $descripcion, $rucEmpresa, $direccion, $actividad, $localidad, $departamento, $retieneIva, $indFactElectronica]
                );

                // Crear sucursal 1 por defecto y ejercicio actual si no existen
                $cntSuc = (int)Database::queryForValue(
                    "SELECT COUNT(*) FROM sucursales WHERE id_propietario = ? AND cod_empresa = ?",
                    [$idProp, $codEmpresa]
                );
                if ($cntSuc === 0) {
                    Database::update(
                        "INSERT INTO sucursales (id_propietario, cod_empresa, cod_sucursal, descripcion, direccion, detalle_dir, telefono, estado)
                         VALUES (?, ?, '1', 'MATRIZ', ?, ?, ?, 'A')",
                        [$idProp, $codEmpresa, $direccion, $direccion, $telefono]
                    );
                }

                // Clonar plan de cuentas base desde propietario 1 si el propietario aún no tiene plan de cuentas
                $cntPlan = (int)Database::queryForValue(
                    "SELECT COUNT(*) FROM co_plan_cuentas WHERE id_propietario = ?",
                    [$idProp]
                );
                if ($cntPlan === 0) {
                    Database::update(
                        "INSERT INTO co_plan_cuentas (id_propietario, cod_cuenta, descripcion, tipo_cuenta, nivel, cod_padre, asentable, estado)
                         SELECT ?, cod_cuenta, descripcion, tipo_cuenta, nivel, cod_padre, asentable, estado
                         FROM co_plan_cuentas
                         WHERE id_propietario = 1",
                        [$idProp]
                    );
                }

                // Activar en sesión si el propietario no tenía empresa activa
                if (TenantContext::empresa() === '' || TenantContext::empresa() === '0') {
                    $_SESSION['empresa']    = $codEmpresa;
                    $_SESSION['nomEmpresa'] = $descripcion;
                    $_SESSION['sucursal']   = '1';
                }

                $msg = "Contribuyente registrado con éxito con código #{$codEmpresa}";
            } else {
                Database::update(
                    "UPDATE empresas SET
                        descripcion = ?, ruc_empresa = ?, direccion = ?, actividad = ?,
                        localidad = ?, departamento = ?, retiene_iva = ?, ind_facturacion_electronica = ?
                     WHERE id_propietario = ? AND cod_empresa = ?",
                    [$descripcion, $rucEmpresa, $direccion, $actividad, $localidad, $departamento, $retieneIva, $indFactElectronica, $idProp, $codEmpresa]
                );
                if (TenantContext::empresa() === $codEmpresa) {
                    $_SESSION['nomEmpresa'] = $descripcion;
                }
                $msg = "Contribuyente #{$codEmpresa} actualizado correctamente.";
            }

            // Sincronizar con Personas
            $codPersona = null;
            try {
                $codPersonaExistente = $codPersonaPayload !== '' ? $codPersonaPayload : null;
                if (!$codPersonaExistente && $rucEmpresa !== '') {
                    $found = Database::queryForValue(
                        "SELECT cod_persona FROM ident_personas
                         WHERE id_propietario = ? AND UPPER(TRIM(SUBSTRING_INDEX(numero, '-', 1))) = ? LIMIT 1",
                        [$idProp, self::rucBase($rucEmpresa)]
                    );
                    if ($found) $codPersonaExistente = (string)$found;
                }
                $codPersona = PersonaService::syncPersonaData(
                    $codPersonaExistente, $descripcion, $rucEmpresa, 'RUC', $direccion, $telefono, null
                );
            } catch (\Throwable $ignored) {}

            View::json([
                'status'     => 'success',
                'message'    => $msg,
                'codEmpresa' => $codEmpresa,
                'codPersona' => $codPersona,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar contribuyente: ' . $e->getMessage()]);
        }
    }

    public static function apiContribuyentesDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $payload = self::getInput();
        $codEmpresa = trim((string)($payload['codEmpresa'] ?? $payload['cod_empresa'] ?? ''));

        try {
            $tieneMov = (int)Database::queryForValue(
                "SELECT (SELECT COUNT(*) FROM co_egresos WHERE id_propietario = ? AND cod_empresa = ?) +
                        (SELECT COUNT(*) FROM co_ingresos WHERE id_propietario = ? AND cod_empresa = ?) +
                        (SELECT COUNT(*) FROM co_asientos_cab WHERE id_propietario = ? AND cod_empresa = ?)",
                [$idProp, $codEmpresa, $idProp, $codEmpresa, $idProp, $codEmpresa]
            );
            if ($tieneMov > 0) {
                View::json([
                    'status'  => 'error',
                    'message' => "No se puede eliminar el contribuyente #{$codEmpresa} porque ya registra movimientos contables. Elimine esos registros primero."
                ]);
                return;
            }

            Database::update("DELETE FROM sucursales WHERE id_propietario = ? AND cod_empresa = ?", [$idProp, $codEmpresa]);
            Database::update("DELETE FROM empresas WHERE id_propietario = ? AND cod_empresa = ?", [$idProp, $codEmpresa]);

            View::json(['status' => 'success', 'message' => 'Contribuyente eliminado con éxito.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar contribuyente: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API EMPRESAS (/api/empresa/info, /api/empresas/*)
    // =========================================================================
    public static function apiEmpresaInfo(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $emp = TenantContext::empresa();
        $row = Database::queryForMap(
            "SELECT cod_empresa, descripcion, ruc_empresa, direccion
             FROM empresas
             WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
            [$idProp, $emp]
        );
        View::json($row ?: []);
    }

    public static function apiEmpresasList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $search = trim((string)($_GET['search'] ?? ''));

        $sql = "SELECT cod_empresa AS codEmpresa, descripcion, ruc_empresa AS rucEmpresa,
                       direccion, cod_moneda_origen AS codMonedaOrigen, cod_per_juridica AS codPerJuridica,
                       titulo_reportes AS tituloReportes, actividad, nro_patronal AS nroPatronal,
                       localidad, departamento, retiene_iva AS retieneIva,
                       ind_retiene_iva AS indRetieneIva,
                       ind_facturacion_electronica AS indFacturacionElectronica, ramo
                FROM empresas
                WHERE id_propietario = ? ";
        $params = [$idProp];
        if ($search !== '') {
            $sql .= "AND (UPPER(cod_empresa) LIKE ? OR UPPER(descripcion) LIKE ? OR UPPER(ruc_empresa) LIKE ?) ";
            $p = '%' . strtoupper($search) . '%';
            $params[] = $p; $params[] = $p; $params[] = $p;
        }
        $sql .= "ORDER BY CAST(cod_empresa AS UNSIGNED), cod_empresa";
        View::json(Database::queryForList($sql, $params));
    }

    public static function apiEmpresasSave(): void {
        self::apiContribuyentesSave();
    }

    public static function apiEmpresasDelete(): void {
        self::apiContribuyentesDelete();
    }

    // =========================================================================
    // API ACTIVIDADES ECONÓMICAS (/api/actividades/*)
    // =========================================================================
    public static function apiActividadesList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $codEmpresa = trim((string)($_GET['codEmpresa'] ?? ''));
        $soloActivas = trim((string)($_GET['soloActivas'] ?? ''));
        $emp = $codEmpresa !== '' ? $codEmpresa : TenantContext::empresa();

        try {
            $sql = "SELECT cod_actividad, descripcion, estado, por_defecto
                    FROM co_actividades
                    WHERE id_propietario = ? AND cod_empresa = ? "
                 . ($soloActivas === 'S' ? "AND estado = 'A' " : "")
                 . "ORDER BY CASE WHEN por_defecto = 'S' THEN 0 ELSE 1 END, cod_actividad";
            $rows = Database::queryForList($sql, [$idProp, $emp]);
            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'codActividad' => (string)($r['cod_actividad'] ?? ''),
                    'descripcion'  => (string)($r['descripcion'] ?? ''),
                    'estado'       => (string)($r['estado'] ?? 'A'),
                    'porDefecto'   => (string)($r['por_defecto'] ?? 'N'),
                ];
            }
            View::json(['status' => 'success', 'data' => $out, 'empresa' => $emp]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public static function apiActividadesSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $emp = TenantContext::empresa();
        $p = self::getInput();

        $cod    = trim((string)($p['codActividad'] ?? ''));
        $desc   = strtoupper(trim((string)($p['descripcion'] ?? '')));
        $estado = (isset($p['activo']) && ($p['activo'] === false || $p['activo'] === 'false')) ? 'I' : 'A';
        $def    = (!empty($p['porDefecto']) && $p['porDefecto'] !== 'false' && $p['porDefecto'] !== 'N') ? 'S' : 'N';

        if ($desc === '') {
            View::json(['status' => 'error', 'message' => 'La descripción es obligatoria.']);
            return;
        }

        try {
            $dup = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM co_actividades
                 WHERE id_propietario = ? AND cod_empresa = ? AND UPPER(TRIM(descripcion)) = ?
                   AND CAST(cod_actividad AS CHAR) <> COALESCE(NULLIF(?, ''), '#')",
                [$idProp, $emp, $desc, $cod]
            );
            if ($dup > 0) {
                View::json(['status' => 'error', 'message' => "Ya existe la actividad \"{$desc}\"."]);
                return;
            }

            if ($def === 'S') {
                Database::update(
                    "UPDATE co_actividades SET por_defecto = 'N' WHERE id_propietario = ? AND cod_empresa = ?",
                    [$idProp, $emp]
                );
            }

            if ($cod === '') {
                $next = (int)Database::queryForValue(
                    "SELECT COALESCE(MAX(cod_actividad), 0) + 1 FROM co_actividades WHERE id_propietario = ? AND cod_empresa = ?",
                    [$idProp, $emp]
                );
                Database::update(
                    "INSERT INTO co_actividades (id_propietario, cod_empresa, cod_actividad, descripcion, estado, por_defecto)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$idProp, $emp, $next, $desc, $estado, $def]
                );
                View::json(['status' => 'success', 'message' => "Actividad #{$next} registrada."]);
            } else {
                Database::update(
                    "UPDATE co_actividades SET descripcion = ?, estado = ?, por_defecto = ?
                     WHERE id_propietario = ? AND cod_empresa = ? AND cod_actividad = ?",
                    [$desc, $estado, $def, $idProp, $emp, (int)$cod]
                );
                View::json(['status' => 'success', 'message' => "Actividad #{$cod} actualizada."]);
            }
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar: ' . $e->getMessage()]);
        }
    }

    public static function apiActividadesDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $emp = TenantContext::empresa();
        $p = self::getInput();
        $cod = (int)($p['codActividad'] ?? 0);

        try {
            Database::update(
                "DELETE FROM co_actividades WHERE id_propietario = ? AND cod_empresa = ? AND cod_actividad = ?",
                [$idProp, $emp, $cod]
            );
            View::json(['status' => 'success', 'message' => 'Actividad eliminada.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API USUARIOS Y CAMBIO DE CLAVE (/api/usuarios/*)
    // =========================================================================
    public static function apiUsuariosList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $sql = "SELECT u.cod_usuario AS codUsuario,
                       COALESCE(u.alias, u.cod_usuario) AS alias,
                       COALESCE(u.ci, p.ci) AS ci,
                       COALESCE(u.nombre, p.nombre) AS nombre,
                       u.apellido AS apellido,
                       COALESCE(u.telefono, p.telefono, (
                           SELECT tp.num_telefono FROM telef_personas tp
                           WHERE tp.id_propietario = u.id_propietario AND tp.cod_persona = u.cod_persona LIMIT 1
                       )) AS telefono,
                       u.sexo AS sexo,
                       u.es_admin AS esAdmin,
                       u.fec_nacimiento AS fecNacimiento,
                       u.cod_persona AS codPersona,
                       p.nombre AS nombrePersona,
                       u.cod_grupo AS codGrupo,
                       g.descripcion AS descGrupo,
                       u.cod_empresa AS codEmpresa,
                       e.descripcion AS descEmpresa,
                       u.cod_sucursal AS codSucursal,
                       s.descripcion AS descSucursal,
                       u.estado AS estado,
                       COALESCE(u.e_mail, p.email) AS email
                FROM usuarios u
                LEFT JOIN personas p ON p.id_propietario = u.id_propietario AND p.cod_persona = u.cod_persona
                LEFT JOIN grupos_usuarios g ON g.id_propietario = u.id_propietario AND g.cod_grupo = u.cod_grupo
                LEFT JOIN empresas e ON e.id_propietario = u.id_propietario AND e.cod_empresa = u.cod_empresa
                LEFT JOIN sucursales s ON s.id_propietario = u.id_propietario AND s.cod_empresa = u.cod_empresa AND s.cod_sucursal = u.cod_sucursal
                WHERE u.id_propietario = ?
                ORDER BY u.cod_usuario";
        View::json(Database::queryForList($sql, [$idProp]));
    }

    public static function apiUsuariosGet(string $codUsuario): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $key = strtoupper(trim(urldecode($codUsuario)));
        $sql = "SELECT u.cod_usuario AS codUsuario,
                       COALESCE(u.alias, u.cod_usuario) AS alias,
                       COALESCE(u.ci, p.ci) AS ci,
                       COALESCE(u.nombre, p.nombre) AS nombre,
                       u.apellido AS apellido,
                       COALESCE(u.telefono, p.telefono) AS telefono,
                       u.sexo AS sexo,
                       u.es_admin AS esAdmin,
                       u.fec_nacimiento AS fecNacimiento,
                       u.cod_persona AS codPersona,
                       p.nombre AS nombrePersona,
                       u.cod_grupo AS codGrupo,
                       u.cod_empresa AS codEmpresa,
                       u.cod_sucursal AS codSucursal,
                       u.estado AS estado,
                       COALESCE(u.e_mail, p.email) AS email
                FROM usuarios u
                LEFT JOIN personas p ON p.id_propietario = u.id_propietario AND p.cod_persona = u.cod_persona
                WHERE u.id_propietario = ? AND (UPPER(u.cod_usuario) = ? OR UPPER(u.alias) = ?)
                LIMIT 1";
        $row = Database::queryForMap($sql, [$idProp, $key, $key]);
        if ($row) {
            View::json(['status' => 'success', 'data' => $row]);
        } else {
            View::json(['status' => 'error', 'message' => 'Usuario no encontrado.']);
        }
    }

    public static function apiUsuariosSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $in = self::getInput();

        $codUsuario    = trim((string)($in['codUsuario'] ?? ''));
        $alias         = trim((string)($in['alias'] ?? ''));
        $ci            = trim((string)($in['ci'] ?? ''));
        $nombre        = trim((string)($in['nombre'] ?? ''));
        $apellido      = trim((string)($in['apellido'] ?? ''));
        $email         = trim((string)($in['email'] ?? ''));
        $telefono      = trim((string)($in['telefono'] ?? ''));
        $sexo          = trim((string)($in['sexo'] ?? ''));
        $esAdmin       = trim((string)($in['esAdmin'] ?? 'N'));
        $fecNacimiento = trim((string)($in['fecNacimiento'] ?? ''));
        $clave         = trim((string)($in['clave'] ?? ''));
        $codPersona    = trim((string)($in['codPersona'] ?? ''));
        $codEmpresa    = trim((string)($in['codEmpresa'] ?? TenantContext::empresa())) ?: '1';
        $codSucursal   = trim((string)($in['codSucursal'] ?? '1')) ?: '1';
        $estado        = strtoupper(trim((string)($in['estado'] ?? 'A'))) ?: 'A';

        $userCode = $alias !== '' ? $alias : $codUsuario;
        if ($userCode === '') {
            View::json(['status' => 'error', 'message' => 'El Alias o Código de Usuario es obligatorio.']);
            return;
        }
        $userCodeUp = strtoupper($userCode);
        // Regla: todos los usuarios al crear pertenecen al grupo CONTA (excepto el usuario CONTA que pertenece a CONADM)
        $codGrupo   = ($userCodeUp === 'CONTA') ? 'CONADM' : 'CONTA';
        $rawClave   = $clave !== '' ? $clave : '123456';
        $finalClave = AuthController::hashPassword($rawClave);

        // Verificar que el código de usuario no exista globalmente (para evitar colisión en login)
        $cnt = (int)Database::queryForValue(
            "SELECT COUNT(*) FROM usuarios WHERE UPPER(cod_usuario) = ? OR UPPER(alias) = ?",
            [$userCodeUp, $userCodeUp]
        );
        if ($cnt > 0) {
            View::json(['status' => 'error', 'message' => 'El usuario o alias ya está en uso. Elija otro código.']);
            return;
        }

        // CONTROL DE LÍMITE DE USUARIOS SEGÚN EL PLAN DEL PROPIETARIO
        $plan = TenantContext::getPlanSummary();
        if ($plan['usados_usuarios'] >= $plan['max_usuarios']) {
            View::json([
                'status'  => 'error',
                'message' => "Límite de plan alcanzado: Su plan actual ({$plan['nombre_plan']}) permite hasta {$plan['max_usuarios']} usuario(s) y ya tiene {$plan['usados_usuarios']} registrado(s). Contacte al SuperAdmin de ContaPro para ampliar su plan.",
            ]);
            return;
        }

        try {
            $fullNom = trim($nombre . ' ' . $apellido);
            $assignedPersona = $codPersona !== '' ? $codPersona : PersonaService::syncPersonaData(null, $fullNom ?: $userCodeUp, $ci, 'CI', null, $telefono, $email);

            Database::update(
                "INSERT INTO usuarios (
                    id_propietario, cod_usuario, alias, ci, nombre, apellido, e_mail,
                    telefono, sexo, es_admin, fec_nacimiento, cod_persona, clave,
                    cod_grupo, cod_empresa, cod_sucursal, estado
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $idProp, $userCodeUp, $alias !== '' ? strtoupper($alias) : $userCodeUp,
                    $ci ?: null, $nombre ?: null, $apellido ?: null, $email ?: null,
                    $telefono ?: null, $sexo ?: null,
                    ($esAdmin === 'true' || strtoupper($esAdmin) === 'S') ? 'S' : 'N',
                    $fecNacimiento ?: null, $assignedPersona, $finalClave,
                    $codGrupo, $codEmpresa, $codSucursal, $estado
                ]
            );

            View::json(['status' => 'success', 'message' => 'Usuario guardado exitosamente en el grupo CONTA.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al insertar usuario: ' . $e->getMessage()]);
        }
    }

    public static function apiUsuariosUpdate(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $in = self::getInput();

        $key           = strtoupper(trim((string)($in['codUsuario'] ?? '')));
        $alias         = trim((string)($in['alias'] ?? ''));
        $ci            = trim((string)($in['ci'] ?? ''));
        $nombre        = trim((string)($in['nombre'] ?? ''));
        $apellido      = trim((string)($in['apellido'] ?? ''));
        $email         = trim((string)($in['email'] ?? ''));
        $telefono      = trim((string)($in['telefono'] ?? ''));
        $sexo          = trim((string)($in['sexo'] ?? ''));
        $esAdmin       = trim((string)($in['esAdmin'] ?? 'N'));
        $fecNacimiento = trim((string)($in['fecNacimiento'] ?? ''));
        $clave         = trim((string)($in['clave'] ?? ''));
        $codPersona    = trim((string)($in['codPersona'] ?? ''));
        $codGrupo      = strtoupper(trim((string)($in['codGrupo'] ?? 'CONTA'))) ?: 'CONTA';
        if ($key === 'CONTA' || strtoupper($alias) === 'CONTA') {
            $codGrupo = 'CONADM';
        }
        $codEmpresa    = trim((string)($in['codEmpresa'] ?? TenantContext::empresa())) ?: '1';
        $codSucursal   = trim((string)($in['codSucursal'] ?? '1')) ?: '1';
        $estado        = strtoupper(trim((string)($in['estado'] ?? 'A'))) ?: 'A';

        try {
            if ($codPersona === '') {
                $codPersona = (string)(Database::queryForValue(
                    "SELECT cod_persona FROM usuarios WHERE id_propietario = ? AND (UPPER(cod_usuario) = ? OR UPPER(alias) = ?) LIMIT 1",
                    [$idProp, $key, $key]
                ) ?? '');
            }

            Database::update(
                "UPDATE usuarios SET
                    alias = ?, ci = ?, nombre = ?, apellido = ?, e_mail = ?, telefono = ?,
                    sexo = ?, es_admin = ?, fec_nacimiento = ?, cod_persona = ?, estado = ?,
                    cod_grupo = ?, cod_empresa = ?, cod_sucursal = ?
                 WHERE id_propietario = ? AND (UPPER(cod_usuario) = ? OR UPPER(alias) = ?)",
                [
                    $alias !== '' ? strtoupper($alias) : $key,
                    $ci ?: null, $nombre ?: null, $apellido ?: null, $email ?: null, $telefono ?: null,
                    $sexo ?: null, ($esAdmin === 'true' || strtoupper($esAdmin) === 'S') ? 'S' : 'N',
                    $fecNacimiento ?: null, $codPersona ?: null, $estado,
                    $codGrupo, $codEmpresa, $codSucursal,
                    $idProp, $key, $key
                ]
            );

            if ($clave !== '') {
                Database::update(
                    "UPDATE usuarios SET clave = ? WHERE id_propietario = ? AND (UPPER(cod_usuario) = ? OR UPPER(alias) = ?)",
                    [AuthController::hashPassword($clave), $idProp, $key, $key]
                );
            }

            View::json(['status' => 'success', 'message' => 'Usuario actualizado exitosamente.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al actualizar usuario: ' . $e->getMessage()]);
        }
    }

    public static function apiUsuariosToggleStatus(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $in = self::getInput();
        $key = strtoupper(trim((string)($in['codUsuario'] ?? '')));

        $current = Database::queryForValue(
            "SELECT estado FROM usuarios WHERE id_propietario = ? AND (UPPER(cod_usuario) = ? OR UPPER(alias) = ?) LIMIT 1",
            [$idProp, $key, $key]
        );
        if ($current === null) {
            View::json(['status' => 'error', 'message' => 'Usuario no encontrado.']);
            return;
        }
        $newStatus = strtoupper((string)$current) === 'A' ? 'I' : 'A';
        Database::update(
            "UPDATE usuarios SET estado = ? WHERE id_propietario = ? AND (UPPER(cod_usuario) = ? OR UPPER(alias) = ?)",
            [$newStatus, $idProp, $key, $key]
        );
        View::json([
            'status'      => 'success',
            'message'     => $newStatus === 'A' ? 'Usuario habilitado exitosamente.' : 'Usuario deshabilitado exitosamente.',
            'nuevoEstado' => $newStatus,
        ]);
    }

    public static function apiUsuariosDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $in = self::getInput();
        $key = strtoupper(trim((string)($in['codUsuario'] ?? '')));

        if (strtoupper(TenantContext::usuario()) === $key) {
            View::json(['status' => 'error', 'message' => 'No puede eliminar su propio usuario con sesión activa.']);
            return;
        }

        try {
            Database::update(
                "DELETE FROM usuarios WHERE id_propietario = ? AND (UPPER(cod_usuario) = ? OR UPPER(alias) = ?)",
                [$idProp, $key, $key]
            );
            View::json(['status' => 'success', 'message' => 'Usuario eliminado exitosamente.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'No se pudo eliminar el usuario: ' . $e->getMessage()]);
        }
    }

    public static function apiUsuariosEmpresas(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        View::json(Database::queryForListDual(
            "SELECT cod_empresa, descripcion FROM empresas WHERE id_propietario = ? ORDER BY descripcion",
            [$idProp]
        ));
    }

    public static function apiUsuariosPersonasSearch(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $q = '%' . strtoupper(trim((string)($_GET['q'] ?? ''))) . '%';
        $sql = "SELECT p.cod_persona AS codPersona, p.nombre AS nombre,
                       COALESCE(p.ci, p.ruc, (
                           SELECT i.numero FROM ident_personas i
                           WHERE i.id_propietario = p.id_propietario AND i.cod_persona = p.cod_persona LIMIT 1
                       )) AS documento,
                       COALESCE(p.telefono, (
                           SELECT tp.num_telefono FROM telef_personas tp
                           WHERE tp.id_propietario = p.id_propietario AND tp.cod_persona = p.cod_persona LIMIT 1
                       )) AS telefono,
                       COALESCE(p.direc_electronica, p.email) AS email,
                       p.sexo AS sexo
                FROM personas p
                WHERE p.id_propietario = ?
                  AND (UPPER(p.nombre) LIKE ? OR UPPER(COALESCE(p.ruc, '')) LIKE ? OR UPPER(COALESCE(p.ci, '')) LIKE ? OR p.cod_persona LIKE ?)
                ORDER BY p.nombre
                LIMIT 20";
        View::json(Database::queryForList($sql, [$idProp, $q, $q, $q, $q]));
    }

    public static function apiUsuariosPersonasGet(string $codPersona): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $cod = trim(urldecode($codPersona));
        $sql = "SELECT p.cod_persona AS codPersona, p.nombre AS nombre,
                       COALESCE(p.ci, p.ruc, (
                           SELECT i.numero FROM ident_personas i
                           WHERE i.id_propietario = p.id_propietario AND i.cod_persona = p.cod_persona LIMIT 1
                       )) AS documento,
                       COALESCE(p.telefono, (
                           SELECT tp.num_telefono FROM telef_personas tp
                           WHERE tp.id_propietario = p.id_propietario AND tp.cod_persona = p.cod_persona LIMIT 1
                       )) AS telefono,
                       COALESCE(p.direc_electronica, p.email) AS email,
                       p.sexo AS sexo
                FROM personas p
                WHERE p.id_propietario = ? AND p.cod_persona = ?
                LIMIT 1";
        View::json(Database::queryForMap($sql, [$idProp, $cod]) ?: []);
    }

    public static function apiUsuariosSucursales(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $codEmpresa = trim((string)($_GET['codEmpresa'] ?? TenantContext::empresa()));
        View::json(Database::queryForListDual(
            "SELECT cod_sucursal, descripcion FROM sucursales WHERE id_propietario = ? AND cod_empresa = ? ORDER BY cod_sucursal",
            [$idProp, $codEmpresa]
        ));
    }

    public static function apiUsuariosGrupos(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $rows = Database::queryForList(
            "SELECT cod_grupo AS codGrupo, descripcion FROM grupos_usuarios WHERE id_propietario = ? ORDER BY cod_grupo",
            [$idProp]
        );
        if (empty($rows)) {
            $rows = [
                ['codGrupo' => 'CONADM', 'descripcion' => 'ADMINISTRADOR CONTABLE'],
                ['codGrupo' => 'CONTA',  'descripcion' => 'GRUPO CONTABILIDAD'],
            ];
        }
        View::json($rows);
    }

    public static function apiUsuariosChangePassword(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $in = self::getInput();

        $currentPassword = trim((string)($in['currentPassword'] ?? $in['claveActual'] ?? ''));
        $newPassword     = trim((string)($in['newPassword'] ?? $in['claveNueva'] ?? ''));
        $confirmPassword = trim((string)($in['confirmPassword'] ?? $in['confirmarClave'] ?? ''));
        $userClean       = strtoupper(TenantContext::usuario());

        if ($currentPassword === '') {
            View::json(['status' => 'error', 'message' => 'Debe ingresar su contraseña actual.']);
            return;
        }
        if ($newPassword === '') {
            View::json(['status' => 'error', 'message' => 'Debe ingresar la nueva contraseña.']);
            return;
        }
        if ($newPassword !== $confirmPassword) {
            View::json(['status' => 'error', 'message' => 'Las nuevas contraseñas no coinciden.']);
            return;
        }

        $row = Database::queryForMap(
            "SELECT clave FROM usuarios WHERE id_propietario = ? AND (UPPER(cod_usuario) = ? OR UPPER(alias) = ?) LIMIT 1",
            [$idProp, $userClean, $userClean]
        );
        if (!$row) {
            View::json(['status' => 'error', 'message' => 'Usuario no encontrado.']);
            return;
        }
        $claveDb = (string)($row['clave'] ?? '');
        $check   = AuthController::verifyPassword($currentPassword, $claveDb);
        if (!$check['valid']) {
            View::json(['status' => 'error', 'message' => 'La contraseña actual es incorrecta.']);
            return;
        }

        Database::update(
            "UPDATE usuarios SET clave = ? WHERE id_propietario = ? AND (UPPER(cod_usuario) = ? OR UPPER(alias) = ?)",
            [AuthController::hashPassword($newPassword), $idProp, $userClean, $userClean]
        );
        View::json(['status' => 'success', 'message' => 'Contraseña modificada exitosamente.']);
    }

    // =========================================================================
    // API BACKUP (/api/backup/*)
    // =========================================================================
    public static function apiBackupGenerar(): void {
        if (!self::requireAuth()) return;
        try {
            $res = BackupService::generateBackup();
            View::json([
                'status'  => 'success',
                'message' => 'Copia de seguridad generada con éxito.',
                'data'    => $res,
            ]);
        } catch (\Throwable $e) {
            View::json([
                'status'  => 'error',
                'message' => 'Error al generar la copia de seguridad: ' . $e->getMessage(),
            ], 500);
        }
    }

    public static function apiBackupList(): void {
        if (!self::requireAuth()) return;
        View::json(BackupService::listBackups());
    }

    public static function apiBackupDownload(string $fileName): void {
        if (!self::requireAuth(false)) return;
        try {
            $path = BackupService::getBackupFile(urldecode($fileName));
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            http_response_code(404);
            echo 'Archivo no encontrado.';
            exit;
        }
    }

    public static function apiBackupEliminar(): void {
        if (!self::requireAuth()) return;
        $in = self::getInput();
        $fileName = trim((string)($in['fileName'] ?? ''));
        try {
            $deleted = BackupService::deleteBackup($fileName);
            if ($deleted) {
                View::json(['status' => 'success', 'message' => 'Archivo de copia de seguridad eliminado.']);
            } else {
                View::json(['status' => 'error', 'message' => 'No se pudo eliminar el archivo.'], 400);
            }
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar el backup: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API ACCESOS GRUPOS (/api/accesos-grupos/*) - EXCLUSIVO GRUPO CONADM
    // =========================================================================
    public static function apiAccesosGruposData(): void {
        if (!self::requireConadm()) return;

        try {
            // 1. Todos los usuarios creados en el sistema
            $usuarios = Database::queryForList(
                "SELECT u.id_propietario AS idPropietario,
                        COALESCE(pr.nombre_propietario, CONCAT('PROPIETARIO #', u.id_propietario)) AS nombrePropietario,
                        u.cod_usuario AS codUsuario,
                        COALESCE(u.alias, u.cod_usuario) AS alias,
                        COALESCE(p.nombre, NULLIF(TRIM(CONCAT_WS(' ', u.nombre, u.apellido)), ''), u.cod_usuario) AS nombrePersona,
                        COALESCE(u.ci, p.ci, '') AS ci,
                        COALESCE(u.e_mail, p.email, '') AS email,
                        COALESCE(u.telefono, p.telefono, '') AS telefono,
                        CASE
                            WHEN UPPER(TRIM(u.cod_usuario)) = 'CONTA' THEN 'CONADM'
                            ELSE COALESCE(NULLIF(UPPER(TRIM(u.cod_grupo)), ''), 'CONTA')
                        END AS codGrupo,
                        COALESCE(g.descripcion, u.cod_grupo, 'GRUPO CONTABILIDAD') AS descGrupo,
                        COALESCE(u.estado, 'A') AS estado,
                        COALESCE(u.es_admin, 'N') AS esAdmin,
                        COALESCE(u.es_superadmin, 'N') AS esSuperAdmin
                 FROM usuarios u
                 LEFT JOIN propietarios pr ON pr.id_propietario = u.id_propietario
                 LEFT JOIN personas p ON p.id_propietario = u.id_propietario AND p.cod_persona = u.cod_persona
                 LEFT JOIN grupos_usuarios g ON g.id_propietario = u.id_propietario AND UPPER(TRIM(g.cod_grupo)) = UPPER(TRIM(u.cod_grupo))
                 ORDER BY u.id_propietario ASC, u.cod_usuario ASC"
            );

            // 2. Grupos disponibles (plantilla Propietario 1)
            $grupos = Database::queryForList(
                "SELECT UPPER(TRIM(cod_grupo)) AS codGrupo,
                        MAX(descripcion) AS descripcion,
                        MAX(COALESCE(ind_clave_sin_caducidad, 'N')) AS indClaveSinCaducidad
                 FROM grupos_usuarios
                 WHERE cod_grupo IS NOT NULL AND TRIM(cod_grupo) <> ''
                 GROUP BY UPPER(TRIM(cod_grupo))
                 ORDER BY CASE
                     WHEN UPPER(TRIM(cod_grupo)) = 'CONADM' THEN 1
                     WHEN UPPER(TRIM(cod_grupo)) = 'CONTA' THEN 2
                     ELSE 3
                 END, UPPER(TRIM(cod_grupo))"
            );

            // 3. Catálogo de pantallas/formas activas
            $formas = Database::queryForList(
                "SELECT UPPER(TRIM(f.cod_modulo)) AS codModulo,
                        UPPER(TRIM(f.nom_forma)) AS nomForma,
                        COALESCE(MAX(f.titulo), MAX(f.nom_forma)) AS titulo,
                        COALESCE(MAX(f.tipo), 'D') AS tipo,
                        COALESCE(MAX(f.orden_en_menu), 99) AS ordenMenu
                 FROM formas f
                 WHERE f.id_propietario = 1
                   AND f.nom_forma IS NOT NULL AND TRIM(f.nom_forma) <> ''
                 GROUP BY UPPER(TRIM(f.cod_modulo)), UPPER(TRIM(f.nom_forma))
                 ORDER BY CASE WHEN UPPER(TRIM(f.cod_modulo)) = 'AD' THEN 1 ELSE 2 END, ordenMenu, titulo"
            );

            // 4. Permisos actuales por grupo en accesos_grupos (plantilla Propietario 1)
            $accesos = Database::queryForList(
                "SELECT UPPER(TRIM(cod_grupo)) AS codGrupo,
                        UPPER(TRIM(cod_modulo)) AS codModulo,
                        UPPER(TRIM(nom_forma)) AS nomForma,
                        COALESCE(puede_insertar, 'S') AS puedeInsertar,
                        COALESCE(puede_borrar, 'S') AS puedeBorrar,
                        COALESCE(puede_actualizar, 'S') AS puedeActualizar,
                        COALESCE(puede_consultar, 'S') AS puedeConsultar,
                        COALESCE(item_menu, nom_forma) AS itemMenu,
                        COALESCE(mostrar_en_menu, 'S') AS mostrarEnMenu
                 FROM accesos_grupos
                 WHERE id_propietario = 1"
            );

            View::json([
                'status'   => 'success',
                'usuarios' => $usuarios,
                'grupos'   => $grupos,
                'formas'   => $formas,
                'accesos'  => $accesos,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al obtener datos de Accesos Grupos: ' . $e->getMessage()]);
        }
    }

    public static function apiAccesosGruposAsignarUsuario(): void {
        if (!self::requireConadm()) return;
        $in = self::getInput();

        $idProp     = (int)($in['idPropietario'] ?? TenantContext::idPropietario());
        $codUsuario = strtoupper(trim((string)($in['codUsuario'] ?? '')));
        $codGrupo   = strtoupper(trim((string)($in['codGrupo'] ?? 'CONTA')));

        if ($codUsuario === '' || $codGrupo === '') {
            View::json(['status' => 'error', 'message' => 'Debe indicar el usuario y el grupo.']);
            return;
        }

        // Regla: El usuario CONTA debe pertenecer siempre al grupo CONADM
        if ($codUsuario === 'CONTA' && $codGrupo !== 'CONADM') {
            View::json([
                'status'  => 'error',
                'message' => 'El usuario CONTA debe pertenecer obligatoriamente al grupo CONADM.',
            ]);
            return;
        }

        try {
            // Asegurar que el grupo exista para ese propietario en grupos_usuarios y accesos_grupos
            $existeGrp = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM grupos_usuarios WHERE id_propietario = ? AND UPPER(TRIM(cod_grupo)) = ?",
                [$idProp, $codGrupo]
            );
            if ($existeGrp === 0) {
                $descGrp = (string)(Database::queryForValue(
                    "SELECT descripcion FROM grupos_usuarios WHERE UPPER(TRIM(cod_grupo)) = ? LIMIT 1",
                    [$codGrupo]
                ) ?? $codGrupo);
                Database::update(
                    "INSERT INTO grupos_usuarios (id_propietario, cod_grupo, descripcion, ind_clave_sin_caducidad) VALUES (?, ?, ?, 'S')",
                    [$idProp, $codGrupo, $descGrp]
                );
            }

            if ($idProp !== 1) {
                Database::update(
                    "INSERT INTO accesos_grupos (
                         id_propietario, cod_grupo, cod_modulo, nom_forma,
                         puede_insertar, puede_borrar, puede_actualizar, puede_consultar,
                         item_menu, mostrar_en_menu
                     )
                     SELECT ?, a.cod_grupo, a.cod_modulo, a.nom_forma,
                            a.puede_insertar, a.puede_borrar, a.puede_actualizar, a.puede_consultar,
                            a.item_menu, a.mostrar_en_menu
                     FROM accesos_grupos a
                     WHERE a.id_propietario = 1
                       AND UPPER(TRIM(a.cod_grupo)) = ?
                       AND NOT EXISTS (
                           SELECT 1 FROM accesos_grupos x
                           WHERE x.id_propietario = ?
                             AND UPPER(TRIM(x.cod_grupo)) = ?
                             AND UPPER(TRIM(x.nom_forma)) = UPPER(TRIM(a.nom_forma))
                       )",
                    [$idProp, $codGrupo, $idProp, $codGrupo]
                );
            }

            Database::update(
                "UPDATE usuarios SET cod_grupo = ? WHERE id_propietario = ? AND (UPPER(TRIM(cod_usuario)) = ? OR UPPER(TRIM(alias)) = ?)",
                [$codGrupo, $idProp, $codUsuario, $codUsuario]
            );

            if ($idProp === TenantContext::idPropietario() && strtoupper(TenantContext::usuario()) === $codUsuario) {
                $_SESSION['grupo'] = $codGrupo;
            }

            View::json([
                'status'  => 'success',
                'message' => "El usuario {$codUsuario} fue asignado al grupo {$codGrupo} correctamente.",
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al actualizar grupo del usuario: ' . $e->getMessage()]);
        }
    }

    public static function apiAccesosGruposGuardarPermisos(): void {
        if (!self::requireConadm()) return;
        $in = self::getInput();

        $codGrupo = strtoupper(trim((string)($in['codGrupo'] ?? '')));
        $permisos = $in['permisos'] ?? [];
        if (is_string($permisos)) {
            $permisos = json_decode($permisos, true) ?: [];
        }

        if ($codGrupo === '' || !is_array($permisos)) {
            View::json(['status' => 'error', 'message' => 'Datos de permisos inválidos.']);
            return;
        }

        try {
            // Obtener todos los propietarios para sincronizar la plantilla de accesos del grupo
            $propRows = Database::queryForList(
                "SELECT DISTINCT id_prop FROM (
                     SELECT id_propietario AS id_prop FROM propietarios
                     UNION
                     SELECT id_propietario AS id_prop FROM grupos_usuarios
                     UNION
                     SELECT 1 AS id_prop
                 ) t"
            );

            Database::update("DELETE FROM accesos_grupos WHERE UPPER(TRIM(cod_grupo)) = ?", [$codGrupo]);

            foreach ($permisos as $p) {
                $nomForma  = strtoupper(trim((string)($p['nomForma'] ?? '')));
                $codModulo = strtoupper(trim((string)($p['codModulo'] ?? 'CO')));
                $itemMenu  = trim((string)($p['itemMenu'] ?? $nomForma));
                if ($nomForma === '') continue;

                // Regla estricta: solo CONADM puede tener acceso a BSGRPUSU (Accesos Grupos)
                if ($nomForma === 'BSGRPUSU' && $codGrupo !== 'CONADM') {
                    continue;
                }

                $mostrar    = (!empty($p['mostrarEnMenu']) && $p['mostrarEnMenu'] !== 'N' && $p['mostrarEnMenu'] !== false) ? 'S' : 'N';
                $insertar   = (!empty($p['puedeInsertar']) && $p['puedeInsertar'] !== 'N' && $p['puedeInsertar'] !== false) ? 'S' : 'N';
                $actualizar = (!empty($p['puedeActualizar']) && $p['puedeActualizar'] !== 'N' && $p['puedeActualizar'] !== false) ? 'S' : 'N';
                $borrar     = (!empty($p['puedeBorrar']) && $p['puedeBorrar'] !== 'N' && $p['puedeBorrar'] !== false) ? 'S' : 'N';
                $consultar  = (!empty($p['puedeConsultar']) && $p['puedeConsultar'] !== 'N' && $p['puedeConsultar'] !== false) ? 'S' : 'N';

                if ($mostrar === 'N' && $insertar === 'N' && $actualizar === 'N' && $borrar === 'N' && $consultar === 'N') {
                    continue;
                }

                foreach ($propRows as $pr) {
                    $pid = (int)($pr['id_prop'] ?? 1);
                    Database::update(
                        "INSERT INTO accesos_grupos (
                             id_propietario, cod_grupo, cod_modulo, nom_forma,
                             puede_insertar, puede_borrar, puede_actualizar, puede_consultar,
                             item_menu, mostrar_en_menu
                         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$pid, $codGrupo, $codModulo, $nomForma, $insertar, $borrar, $actualizar, $consultar, $itemMenu, $mostrar]
                    );
                }
            }

            // Si es CONADM, garantizar que siempre mantenga acceso a BSGRPUSU
            if ($codGrupo === 'CONADM') {
                foreach ($propRows as $pr) {
                    $pid = (int)($pr['id_prop'] ?? 1);
                    $has = (int)Database::queryForValue(
                        "SELECT COUNT(*) FROM accesos_grupos WHERE id_propietario = ? AND UPPER(TRIM(cod_grupo)) = 'CONADM' AND UPPER(TRIM(nom_forma)) = 'BSGRPUSU'",
                        [$pid]
                    );
                    if ($has === 0) {
                        Database::update(
                            "INSERT INTO accesos_grupos (id_propietario, cod_grupo, cod_modulo, nom_forma, puede_insertar, puede_borrar, puede_actualizar, puede_consultar, item_menu, mostrar_en_menu)
                             VALUES (?, 'CONADM', 'AD', 'BSGRPUSU', 'S', 'S', 'S', 'S', 'Accesos Grupos', 'S')",
                            [$pid]
                        );
                    }
                }
            }

            View::json([
                'status'  => 'success',
                'message' => "Permisos del grupo {$codGrupo} actualizados correctamente.",
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar permisos: ' . $e->getMessage()]);
        }
    }

    public static function apiAccesosGruposGuardarGrupo(): void {
        if (!self::requireConadm()) return;
        $in = self::getInput();

        $codGrupo    = substr(strtoupper(preg_replace('/[^A-Z0-9_]/i', '', trim((string)($in['codGrupo'] ?? '')))), 0, 6);
        $descripcion = strtoupper(trim((string)($in['descripcion'] ?? '')));

        if ($codGrupo === '' || $descripcion === '') {
            View::json(['status' => 'error', 'message' => 'Código de grupo (máx. 6 caracteres) y descripción son obligatorios.']);
            return;
        }

        try {
            $propRows = Database::queryForList(
                "SELECT DISTINCT id_prop FROM (
                     SELECT id_propietario AS id_prop FROM propietarios
                     UNION
                     SELECT id_propietario AS id_prop FROM grupos_usuarios
                     UNION
                     SELECT 1 AS id_prop
                 ) t"
            );

            foreach ($propRows as $pr) {
                $pid = (int)($pr['id_prop'] ?? 1);
                $cnt = (int)Database::queryForValue(
                    "SELECT COUNT(*) FROM grupos_usuarios WHERE id_propietario = ? AND UPPER(TRIM(cod_grupo)) = ?",
                    [$pid, $codGrupo]
                );
                if ($cnt > 0) {
                    Database::update(
                        "UPDATE grupos_usuarios SET descripcion = ? WHERE id_propietario = ? AND UPPER(TRIM(cod_grupo)) = ?",
                        [$descripcion, $pid, $codGrupo]
                    );
                } else {
                    Database::update(
                        "INSERT INTO grupos_usuarios (id_propietario, cod_grupo, descripcion, ind_clave_sin_caducidad) VALUES (?, ?, ?, 'S')",
                        [$pid, $codGrupo, $descripcion]
                    );
                    // Copiar accesos base desde CONTA (sin BSGRPUSU)
                    Database::execute(
                        "INSERT INTO accesos_grupos (id_propietario, cod_grupo, cod_modulo, nom_forma, puede_insertar, puede_borrar, puede_actualizar, puede_consultar, item_menu, mostrar_en_menu)
                         SELECT id_propietario, '$codGrupo', cod_modulo, nom_forma, puede_insertar, puede_borrar, puede_actualizar, puede_consultar, item_menu, mostrar_en_menu
                         FROM accesos_grupos
                         WHERE id_propietario = $pid AND UPPER(TRIM(cod_grupo)) = 'CONTA' AND UPPER(TRIM(nom_forma)) <> 'BSGRPUSU'",
                        true
                    );
                }
            }

            View::json(['status' => 'success', 'message' => "Grupo {$codGrupo} ({$descripcion}) guardado correctamente."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar grupo: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API GLOBAL SEARCH (/api/global/search)
    // =========================================================================
    public static function apiGlobalSearch(): void {
        if (!self::requireAuth()) return;
        $q = strtoupper(trim((string)($_GET['q'] ?? '')));
        if ($q === '') {
            View::json([]);
            return;
        }
        $pat = '%' . $q . '%';
        $extraFilter = self::isConadm() ? "" : " AND UPPER(TRIM(f.nom_forma)) <> 'BSGRPUSU' ";
        $sql = "SELECT DISTINCT f.nom_forma AS nomForma,
                       COALESCE(f.titulo, a.item_menu, f.nom_forma) AS titulo,
                       f.cod_modulo AS codModulo
                FROM formas f
                LEFT JOIN accesos_grupos a ON UPPER(TRIM(a.nom_forma)) = UPPER(TRIM(f.nom_forma)) AND a.id_propietario = 1
                WHERE f.id_propietario = 1
                  {$extraFilter}
                  AND (UPPER(f.nom_forma) LIKE ? OR UPPER(COALESCE(f.titulo, '')) LIKE ? OR UPPER(COALESCE(a.item_menu, '')) LIKE ?)
                ORDER BY f.nom_forma
                LIMIT 10";
        View::json(Database::queryForList($sql, [$pat, $pat, $pat]));
    }
}

