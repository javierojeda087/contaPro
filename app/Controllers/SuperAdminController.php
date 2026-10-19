<?php
namespace App\Controllers;

use Core\Database;
use Core\TenantContext;
use Core\View;

class SuperAdminController {

    private static function checkAccess(): void {
        if (empty($_SESSION['usuario']) || !TenantContext::esSuperAdmin()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Acceso restringido a SuperAdmin']);
            exit;
        }
    }

    public static function view(): void {
        if (empty($_SESSION['usuario'])) {
            header('Location: /login');
            exit;
        }
        if (!TenantContext::esSuperAdmin()) {
            header('Location: /menu');
            exit;
        }
        View::render('superadmin');
    }

    public static function getData(): array {
        self::checkAccess();

        $planes = Database::queryForList("SELECT * FROM planes ORDER BY orden, id_plan");
        $grupos = Database::queryForList(
            "SELECT UPPER(TRIM(cod_grupo)) AS cod_grupo, MAX(descripcion) AS descripcion
             FROM grupos_usuarios
             WHERE cod_grupo IS NOT NULL AND TRIM(cod_grupo) <> ''
             GROUP BY UPPER(TRIM(cod_grupo))
             ORDER BY CASE
                 WHEN UPPER(TRIM(cod_grupo)) = 'CONTA'  THEN 1
                 WHEN UPPER(TRIM(cod_grupo)) = 'CONTAM' THEN 2
                 WHEN UPPER(TRIM(cod_grupo)) = 'CONTAP' THEN 3
                 WHEN UPPER(TRIM(cod_grupo)) = 'CONADM' THEN 4
                 ELSE 5
             END, UPPER(TRIM(cod_grupo))"
        );

        $propsRaw = Database::queryForList(
            "SELECT p.*,
                    COALESCE(pl.nombre_plan, 'Plan Básico') AS nombre_plan,
                    COALESCE(pl.cod_grupo, 'CONTA') AS cod_grupo_plan,
                    COALESCE(pl.precio_mensual, 0) AS precio_mensual,
                    (SELECT COUNT(*) FROM empresas e WHERE e.id_propietario = p.id_propietario) AS usados_contribuyentes,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.id_propietario = p.id_propietario) AS usados_usuarios,
                    (SELECT GROUP_CONCAT(u.cod_usuario SEPARATOR ', ') FROM usuarios u WHERE u.id_propietario = p.id_propietario) AS usuarios_lista,
                    (SELECT u.cod_usuario FROM usuarios u WHERE u.id_propietario = p.id_propietario ORDER BY u.es_admin DESC LIMIT 1) AS primer_usuario
             FROM propietarios p
             LEFT JOIN planes pl ON pl.id_plan = p.id_plan
             ORDER BY p.id_propietario ASC"
        );

        $props = [];
        foreach ($propsRaw as $pr) {
            $props[] = TenantContext::enrichSubscriptionInfo($pr);
        }

        $pagos = [];
        try {
            $pagos = Database::queryForList(
                "SELECT pg.*, pr.nombre_propietario, COALESCE(pl.nombre_plan, 'Plan') AS nombre_plan
                 FROM pagos_propietarios pg
                 LEFT JOIN propietarios pr ON pr.id_propietario = pg.id_propietario
                 LEFT JOIN planes pl ON pl.id_plan = pg.id_plan
                 ORDER BY pg.id_pago DESC
                 LIMIT 50"
            );
        } catch (\Throwable $ignored) {}

        return [
            'status'       => 'success',
            'planes'       => $planes,
            'grupos'       => $grupos,
            'propietarios' => $props,
            'pagos'        => $pagos,
        ];
    }

    public static function savePlan(): array {
        self::checkAccess();

        $idPlan     = (int)($_POST['idPlan'] ?? 0);
        $codigo     = strtoupper(trim((string)($_POST['codigoPlan'] ?? 'PLAN')));
        $nombre     = trim((string)($_POST['nombrePlan'] ?? ''));
        $codGrupo   = substr(strtoupper(preg_replace('/[^A-Z0-9_]/i', '', trim((string)($_POST['codGrupo'] ?? '')))), 0, 10);
        $maxContrib = max(1, (int)($_POST['maxContribuyentes'] ?? 5));
        $maxUsers   = max(1, (int)($_POST['maxUsuarios'] ?? 2));
        $precioAnt  = max(0, (float)($_POST['precioAnterior'] ?? 0));
        $precio     = max(0, (float)($_POST['precioMensual'] ?? 0));
        $desc       = trim((string)($_POST['descripcion'] ?? ''));

        if ($nombre === '') {
            return ['status' => 'error', 'message' => 'Ingrese el nombre del plan.'];
        }

        if ($codGrupo === '') {
            $codGrupo = TenantContext::obtenerGrupoPorPlan(null, $codigo);
        }

        // Asegurar que el grupo exista en grupos_usuarios y accesos_grupos
        $descGrupo = 'GRUPO CONTABILIDAD (' . strtoupper($nombre) . ')';
        Database::execute(
            "INSERT INTO `grupos_usuarios` (`id_propietario`, `cod_grupo`, `descripcion`, `ind_clave_sin_caducidad`)
             SELECT DISTINCT p.id_prop, '$codGrupo', '$descGrupo', 'S'
             FROM (
                 SELECT `id_propietario` AS id_prop FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop FROM `grupos_usuarios`
                 UNION
                 SELECT 1 AS id_prop
             ) p
             WHERE NOT EXISTS (
                 SELECT 1 FROM `grupos_usuarios` g
                 WHERE g.`id_propietario` = p.id_prop AND UPPER(TRIM(g.`cod_grupo`)) = '$codGrupo'
             )",
            true
        );

        Database::execute(
            "INSERT INTO `accesos_grupos` (
                 `id_propietario`, `cod_grupo`, `cod_modulo`, `nom_forma`,
                 `puede_insertar`, `puede_borrar`, `puede_actualizar`, `puede_consultar`,
                 `item_menu`, `mostrar_en_menu`
             )
             SELECT a.`id_propietario`, '$codGrupo', a.`cod_modulo`, a.`nom_forma`,
                    a.`puede_insertar`, a.`puede_borrar`, a.`puede_actualizar`, a.`puede_consultar`,
                    a.`item_menu`, a.`mostrar_en_menu`
             FROM `accesos_grupos` a
             WHERE UPPER(TRIM(a.`cod_grupo`)) = 'CONTA'
               AND UPPER(TRIM(a.`nom_forma`)) <> 'BSGRPUSU'
               AND NOT EXISTS (
                   SELECT 1 FROM `accesos_grupos` x
                   WHERE x.`id_propietario` = a.`id_propietario`
                     AND UPPER(TRIM(x.`cod_grupo`)) = '$codGrupo'
               )",
            true
        );

        if ($idPlan > 0) {
            Database::update(
                "UPDATE planes SET codigo_plan = ?, nombre_plan = ?, cod_grupo = ?, max_contribuyentes = ?, max_usuarios = ?, precio_anterior = ?, precio_mensual = ?, descripcion = ?
                 WHERE id_plan = ?",
                [$codigo, $nombre, $codGrupo, $maxContrib, $maxUsers, $precioAnt, $precio, $desc, $idPlan]
            );
            // Sincronizar usuarios de los propietarios que tienen este plan asignado (excepto CONTA/CONADM)
            Database::execute(
                "UPDATE usuarios u
                 JOIN propietarios pr ON pr.id_propietario = u.id_propietario
                 SET u.cod_grupo = '$codGrupo'
                 WHERE pr.id_plan = $idPlan
                   AND UPPER(TRIM(u.cod_usuario)) <> 'CONTA'
                   AND UPPER(TRIM(COALESCE(u.cod_grupo, ''))) <> 'CONADM'",
                true
            );
            return ['status' => 'success', 'message' => "Plan actualizado exitosamente (Grupo asignado: {$codGrupo})."];
        } else {
            Database::insertGetId(
                "INSERT INTO planes (codigo_plan, nombre_plan, cod_grupo, max_contribuyentes, max_usuarios, precio_anterior, precio_mensual, descripcion, estado, orden)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'A', 10)",
                [$codigo, $nombre, $codGrupo, $maxContrib, $maxUsers, $precioAnt, $precio, $desc]
            );
            return ['status' => 'success', 'message' => "Nuevo plan registrado exitosamente (Grupo asignado: {$codGrupo})."];
        }
    }

    public static function savePropietario(): array {
        self::checkAccess();

        $idProp      = (int)($_POST['idPropietario'] ?? 0);
        $nombre      = mb_strtoupper(trim((string)($_POST['nombrePropietario'] ?? '')), 'UTF-8');
        $ruc         = trim((string)($_POST['ruc'] ?? ''));
        $email       = trim((string)($_POST['email'] ?? ''));
        $telefono    = trim((string)($_POST['telefono'] ?? ''));
        $idPlan      = max(1, (int)($_POST['idPlan'] ?? 1));
        $maxContrib  = max(1, (int)($_POST['maxContribuyentes'] ?? 5));
        $maxUsers    = max(1, (int)($_POST['maxUsuarios'] ?? 2));
        $fecVenc     = trim((string)($_POST['fecVencimiento'] ?? ''));
        $diasAviso   = max(1, (int)($_POST['diasAviso'] ?? 7));
        $diasGracia  = max(0, (int)($_POST['diasGracia'] ?? 3));
        $mensajePago = trim((string)($_POST['mensajePago'] ?? ''));
        $estado      = strtoupper(trim((string)($_POST['estado'] ?? 'A')));
        $adminUser   = strtoupper(trim((string)($_POST['adminUser'] ?? '')));
        $adminClave  = trim((string)($_POST['adminClave'] ?? ''));

        if ($nombre === '') {
            return ['status' => 'error', 'message' => 'El nombre del propietario es obligatorio.'];
        }

        $grupoPlan = TenantContext::obtenerGrupoPorPlan($idPlan);

        if ($idProp > 0) {
            Database::update(
                "UPDATE propietarios SET nombre_propietario = ?, ruc = ?, email = ?, telefono = ?,
                        id_plan = ?, max_contribuyentes = ?, max_usuarios = ?, fec_vencimiento = ?,
                        dias_aviso = ?, dias_gracia = ?, mensaje_pago = ?, estado = ?
                 WHERE id_propietario = ?",
                [$nombre, $ruc, $email, $telefono, $idPlan, $maxContrib, $maxUsers, $fecVenc ?: null, $diasAviso, $diasGracia, $mensajePago ?: null, $estado, $idProp]
            );
        } else {
            if ($adminUser === '' || $adminClave === '') {
                return ['status' => 'error', 'message' => 'Al crear un nuevo propietario debe indicar su código de usuario y contraseña inicial.'];
            }
            $existsU = (int)Database::queryForValue("SELECT COUNT(*) FROM usuarios WHERE UPPER(TRIM(cod_usuario)) = ?", [$adminUser]);
            if ($existsU > 0) {
                return ['status' => 'error', 'message' => "El código de usuario '$adminUser' ya existe en el sistema. Elija otro alias de login."];
            }

            $idProp = Database::insertGetId(
                "INSERT INTO propietarios (nombre_propietario, ruc, email, telefono, id_plan, max_contribuyentes, max_usuarios, estado, fec_vencimiento, dias_aviso, dias_gracia, mensaje_pago)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$nombre, $ruc, $email, $telefono, $idPlan, $maxContrib, $maxUsers, $estado, $fecVenc ?: null, $diasAviso, $diasGracia, $mensajePago ?: null]
            );
        }

        // Aprovisionar catálogos base (módulos, formas, permisos de grupos, monedas) para este propietario
        TenantContext::provisionNewTenant($idProp);

        // Sincronizar el grupo de todos los usuarios de este propietario según el plan asignado (excepto CONTA principal)
        Database::update(
            "UPDATE usuarios SET cod_grupo = ? WHERE id_propietario = ? AND UPPER(TRIM(cod_usuario)) <> 'CONTA'",
            [$grupoPlan, $idProp]
        );

        // Crear o actualizar usuario administrador del propietario si se indicó
        if ($adminUser !== '') {
            $grupoUsuarioAdmin = ($adminUser === 'CONTA') ? 'CONADM' : $grupoPlan;
            $uExist = Database::queryForMap(
                "SELECT cod_usuario FROM usuarios WHERE id_propietario = ? AND UPPER(TRIM(cod_usuario)) = ?",
                [$idProp, $adminUser]
            );
            if ($uExist) {
                if ($adminClave !== '') {
                    Database::update(
                        "UPDATE usuarios SET clave = ?, estado = 'A', cod_grupo = ?, es_admin = 'S'
                         WHERE id_propietario = ? AND UPPER(TRIM(cod_usuario)) = ?",
                        [AuthController::hashPassword($adminClave), $grupoUsuarioAdmin, $idProp, $adminUser]
                    );
                } else {
                    Database::update(
                        "UPDATE usuarios SET cod_grupo = ? WHERE id_propietario = ? AND UPPER(TRIM(cod_usuario)) = ?",
                        [$grupoUsuarioAdmin, $idProp, $adminUser]
                    );
                }
            } else {
                $claveRaw   = $adminClave !== '' ? $adminClave : $adminUser . '123';
                $claveFinal = AuthController::hashPassword($claveRaw);
                Database::update(
                    "INSERT INTO usuarios (id_propietario, cod_usuario, clave, estado, cod_grupo, cod_empresa, cod_sucursal, nombre, es_admin, es_superadmin)
                     VALUES (?, ?, ?, 'A', ?, '1', '01', ?, 'S', 'N')",
                    [$idProp, $adminUser, $claveFinal, $grupoUsuarioAdmin, $nombre]
                );
            }
        }

        return [
            'status'  => 'success',
            'message' => 'Propietario #' . $idProp . ' (' . $nombre . ') guardado y asignado al grupo ' . $grupoPlan . '.'
        ];
    }

    /**
     * Registra un pago de suscripción, extiende la fecha de vencimiento y reactiva la cuenta del propietario
     */
    public static function renovarSuscripcion(): array {
        self::checkAccess();

        $idProp      = (int)($_POST['idPropietario'] ?? 0);
        $meses       = max(1, (int)($_POST['meses'] ?? 1));
        $monto       = (float)($_POST['monto'] ?? 0);
        $nuevaFec    = trim((string)($_POST['nuevaFechaVencimiento'] ?? ''));
        $comprobante = trim((string)($_POST['comprobante'] ?? ''));
        $observacion = trim((string)($_POST['observacion'] ?? ''));

        if ($idProp <= 0) {
            return ['status' => 'error', 'message' => 'Seleccione un propietario válido.'];
        }

        $prop = Database::queryForMap("SELECT * FROM propietarios WHERE id_propietario = ?", [$idProp]);
        if (!$prop) {
            return ['status' => 'error', 'message' => 'Propietario no encontrado.'];
        }

        $fecAnterior = !empty($prop['fec_vencimiento']) ? substr((string)$prop['fec_vencimiento'], 0, 10) : null;

        if ($nuevaFec === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nuevaFec)) {
            $baseDate = ($fecAnterior && $fecAnterior >= date('Y-m-d')) ? $fecAnterior : date('Y-m-d');
            $dt = new \DateTimeImmutable($baseDate);
            $nuevaFec = $dt->modify("+{$meses} month")->format('Y-m-d');
        }

        Database::insertGetId(
            "INSERT INTO pagos_propietarios (id_propietario, id_plan, fecha_pago, monto, meses_renovados, fec_vencimiento_anterior, fec_vencimiento_nueva, comprobante, observacion, usuario_registro)
             VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)",
            [
                $idProp,
                (int)($prop['id_plan'] ?? 1),
                $monto,
                $meses,
                $fecAnterior,
                $nuevaFec,
                $comprobante ?: null,
                $observacion ?: null,
                TenantContext::usuario(),
            ]
        );

        Database::update(
            "UPDATE propietarios SET fec_vencimiento = ?, fec_ultimo_pago = CURDATE(), estado = 'A' WHERE id_propietario = ?",
            [$nuevaFec, $idProp]
        );

        return [
            'status'  => 'success',
            'message' => 'Pago registrado y suscripción renovada hasta el ' . date('d/m/Y', strtotime($nuevaFec)) . '.',
        ];
    }

    /**
     * Activa o suspende rápidamente un propietario con 1 clic
     */
    public static function toggleEstadoPropietario(): array {
        self::checkAccess();

        $idProp      = (int)($_POST['idPropietario'] ?? 0);
        $nuevoEstado = strtoupper(trim((string)($_POST['estado'] ?? 'A'))) === 'A' ? 'A' : 'I';

        if ($idProp <= 0) {
            return ['status' => 'error', 'message' => 'Propietario inválido.'];
        }
        if ($idProp === 1 && $nuevoEstado !== 'A') {
            return ['status' => 'error', 'message' => 'No se puede suspender al Propietario #1 (SuperAdmin Principal).'];
        }

        Database::update("UPDATE propietarios SET estado = ? WHERE id_propietario = ?", [$nuevoEstado, $idProp]);

        $msg = $nuevoEstado === 'A' ? 'Cuenta de propietario REACTIVADA.' : 'Cuenta de propietario SUSPENDIDA.';
        return ['status' => 'success', 'message' => $msg];
    }
}
