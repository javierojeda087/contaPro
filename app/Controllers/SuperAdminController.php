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

        // Asegurar que todos los usuarios creados y propietarios estén en MAYÚSCULAS
        Database::execute(
            "UPDATE `usuarios`
             SET `cod_usuario` = UPPER(TRIM(`cod_usuario`)),
                 `alias`       = UPPER(TRIM(COALESCE(NULLIF(TRIM(`alias`), ''), `cod_usuario`))),
                 `nombre`      = CASE WHEN `nombre` IS NOT NULL THEN UPPER(TRIM(`nombre`)) ELSE NULL END,
                 `apellido`    = CASE WHEN `apellido` IS NOT NULL THEN UPPER(TRIM(`apellido`)) ELSE NULL END",
            true
        );
        Database::execute(
            "UPDATE `propietarios` SET `nombre_propietario` = UPPER(TRIM(`nombre_propietario`)) WHERE `nombre_propietario` IS NOT NULL",
            true
        );

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
                    COALESCE(NULLIF(TRIM(p.email), ''), (
                        SELECT u.e_mail FROM usuarios u
                        WHERE u.id_propietario = p.id_propietario AND u.e_mail IS NOT NULL AND TRIM(u.e_mail) <> ''
                        ORDER BY u.es_admin DESC, u.cod_usuario ASC LIMIT 1
                    )) AS email,
                    COALESCE(pl.nombre_plan, 'Plan Básico') AS nombre_plan,
                    COALESCE(pl.cod_grupo, 'CONTA') AS cod_grupo_plan,
                    COALESCE(pl.precio_mensual, 0) AS precio_mensual,
                    (SELECT COUNT(*) FROM empresas e WHERE e.id_propietario = p.id_propietario) AS usados_contribuyentes,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.id_propietario = p.id_propietario) AS usados_usuarios,
                    (SELECT GROUP_CONCAT(UPPER(TRIM(u.cod_usuario)) SEPARATOR ', ') FROM usuarios u WHERE u.id_propietario = p.id_propietario) AS usuarios_lista,
                    (SELECT UPPER(TRIM(u.cod_usuario)) FROM usuarios u WHERE u.id_propietario = p.id_propietario ORDER BY u.es_admin DESC LIMIT 1) AS primer_usuario
             FROM propietarios p
             LEFT JOIN planes pl ON pl.id_plan = p.id_plan
             ORDER BY p.id_propietario ASC"
        );

        $storage = self::calculateStorageStats();
        $propsStorage = $storage['props'] ?? [];

        $props = [];
        foreach ($propsRaw as $pr) {
            $pId = (int)$pr['id_propietario'];
            $st = $propsStorage[$pId] ?? null;
            $pr['espacio_bytes'] = $st ? $st['bytes'] : 0;
            $pr['espacio_fmt']   = $st ? $st['fmt'] : '0 KB';
            $pr['espacio_pct']   = $st ? $st['pct'] : 0.0;
            $pr['total_filas']   = $st ? $st['filas'] : 0;
            $pr['tablas_usadas'] = $st ? $st['tablas'] : 0;

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
            'storage'      => $storage,
            'config'       => TenantContext::getSystemConfig(true),
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
        $email       = strtolower(trim((string)($_POST['email'] ?? '')));
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
            return ['status' => 'error', 'message' => 'El nombre del propietario es obligatorio (en MAYÚSCULAS).'];
        }

        if ($adminUser !== '' && !preg_match('/^[A-Z0-9_.\-]+$/', $adminUser)) {
            return ['status' => 'error', 'message' => 'El código de usuario debe contener solo letras en MAYÚSCULAS y números, sin espacios.'];
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'message' => 'Debe indicar un correo electrónico válido para el propietario (requerido para recuperación de contraseña).'];
        }

        $dupEmailProp = Database::queryForMap(
            "SELECT id_propietario, nombre_propietario FROM propietarios
             WHERE LOWER(TRIM(COALESCE(email, ''))) = ? AND id_propietario <> ? LIMIT 1",
            [$email, $idProp]
        );
        if ($dupEmailProp) {
            return [
                'status'  => 'error',
                'message' => "El correo '{$email}' ya está vinculado al Propietario #{$dupEmailProp['id_propietario']} ({$dupEmailProp['nombre_propietario']}). Ingrese un correo único para este propietario.",
            ];
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

        // Crear o actualizar usuario administrador del propietario y vincularle el correo del propietario
        Database::execute("ALTER TABLE `usuarios` MODIFY COLUMN `clave` VARCHAR(255) DEFAULT NULL", true);
        if ($adminUser !== '') {
            $grupoUsuarioAdmin = ($adminUser === 'CONTA') ? 'CONADM' : $grupoPlan;
            $uExist = Database::queryForMap(
                "SELECT cod_usuario FROM usuarios WHERE id_propietario = ? AND UPPER(TRIM(cod_usuario)) = ?",
                [$idProp, $adminUser]
            );
            if ($uExist) {
                if ($adminClave !== '') {
                    Database::update(
                        "UPDATE usuarios SET clave = ?, estado = 'A', cod_grupo = ?, es_admin = 'S', e_mail = ?, telefono = COALESCE(NULLIF(?, ''), telefono)
                         WHERE id_propietario = ? AND UPPER(TRIM(cod_usuario)) = ?",
                        [AuthController::hashPassword($adminClave), $grupoUsuarioAdmin, $email, $telefono, $idProp, $adminUser]
                    );
                } else {
                    Database::update(
                        "UPDATE usuarios SET cod_grupo = ?, e_mail = ?, telefono = COALESCE(NULLIF(?, ''), telefono)
                         WHERE id_propietario = ? AND UPPER(TRIM(cod_usuario)) = ?",
                        [$grupoUsuarioAdmin, $email, $telefono, $idProp, $adminUser]
                    );
                }
            } else {
                $claveRaw   = $adminClave !== '' ? $adminClave : $adminUser . '123';
                $claveFinal = AuthController::hashPassword($claveRaw);
                Database::update(
                    "INSERT INTO usuarios (id_propietario, cod_usuario, alias, clave, estado, cod_grupo, cod_empresa, cod_sucursal, nombre, e_mail, telefono, es_admin, es_superadmin)
                     VALUES (?, ?, ?, ?, 'A', ?, '1', '01', ?, ?, ?, 'S', 'N')",
                    [$idProp, $adminUser, $adminUser, $claveFinal, $grupoUsuarioAdmin, $nombre, $email, $telefono ?: null]
                );
            }
        } else {
            // Sincronizar correo con el usuario administrador principal del propietario
            $primaryUser = (string)(Database::queryForValue(
                "SELECT cod_usuario FROM usuarios WHERE id_propietario = ? ORDER BY es_admin DESC, cod_usuario ASC LIMIT 1",
                [$idProp]
            ) ?? '');
            if ($primaryUser !== '') {
                Database::update(
                    "UPDATE usuarios SET e_mail = ?, telefono = COALESCE(NULLIF(?, ''), telefono)
                     WHERE id_propietario = ? AND UPPER(TRIM(cod_usuario)) = ?",
                    [$email, $telefono, $idProp, strtoupper(trim($primaryUser))]
                );
            }
        }

        // Sincronizar y registrar como Persona en el sistema
        try {
            if ($ruc !== '' || $nombre !== '') {
                $tipoDoc = strtoupper(trim((string)($_POST['tipoDocumento'] ?? '')));
                if ($tipoDoc === '') {
                    $tipoDoc = str_contains($ruc, '-') ? 'RUC' : 'CI';
                }
                \App\Services\PersonaService::syncPersonaData(
                    null, $nombre, $ruc, $tipoDoc, null, $telefono, $email, $idProp
                );
                if ($idProp !== 1) {
                    \App\Services\PersonaService::syncPersonaData(
                        null, $nombre, $ruc, $tipoDoc, null, $telefono, $email, 1
                    );
                }
            }
        } catch (\Throwable $ignored) {}

        return [
            'status'  => 'success',
            'message' => 'Propietario #' . $idProp . ' (' . $nombre . ') guardado con correo ' . $email . ' y asignado al grupo ' . $grupoPlan . '.'
        ];
    }

    /**
     * Registra una persona física o jurídica directamente desde el panel SuperAdmin
     */
    public static function savePersona(): array {
        self::checkAccess();
        $payload   = $_POST;
        $tipoDoc   = strtoupper(trim((string)($payload['tipoDocumento'] ?? 'CI'))) ?: 'CI';
        $numero    = trim((string)($payload['numero'] ?? ''));
        $nombre    = mb_strtoupper(trim((string)($payload['nombre'] ?? '')), 'UTF-8');
        $telefono  = trim((string)($payload['telefono'] ?? ''));
        $email     = strtolower(trim((string)($payload['email'] ?? '')));
        $direccion = trim((string)($payload['direccion'] ?? ''));

        if ($numero === '') {
            return ['status' => 'error', 'message' => 'El número de documento es obligatorio.'];
        }
        if ($nombre === '') {
            return ['status' => 'error', 'message' => 'El nombre completo es obligatorio.'];
        }

        try {
            $codPersona = \App\Services\PersonaService::syncPersonaData(
                null, $nombre, $numero, $tipoDoc, $direccion, $telefono, $email, 1
            );
            return [
                'status'     => 'success',
                'codPersona' => $codPersona,
                'nombre'     => $nombre,
                'numero'     => $numero,
                'tipoDoc'    => $tipoDoc,
                'telefono'   => $telefono,
                'email'      => $email,
                'direccion'  => $direccion,
                'message'    => 'Persona registrada correctamente en la base de datos.'
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Error al guardar persona: ' . $e->getMessage()];
        }
    }

    /**
     * Consulta RUC para SuperAdmin en la BD de propietarios, personas y en la DNIT
     */
    public static function consultarRuc(): void {
        self::checkAccess();
        $ruc = trim((string)($_GET['ruc'] ?? ''));
        $idPropExcl = (int)($_GET['idPropietario'] ?? 0);
        $base = str_contains($ruc, '-') ? trim(explode('-', $ruc)[0]) : $ruc;
        $base = preg_replace('/[^0-9]/', '', $base);

        if (strlen($base) < 3) {
            View::json(['status' => 'INVALIDO', 'message' => 'RUC inválido (debe tener al menos 3 dígitos).']);
            return;
        }

        // 1. ¿Ya existe en la tabla de propietarios?
        $prop = Database::queryForMap(
            "SELECT id_propietario, nombre_propietario, ruc, email, telefono
             FROM propietarios
             WHERE UPPER(TRIM(SUBSTRING_INDEX(ruc, '-', 1))) = ?
               AND id_propietario <> ?
             LIMIT 1",
            [$base, $idPropExcl]
        );
        if ($prop) {
            View::json([
                'status'        => 'PROPIETARIO_EXISTE',
                'idPropietario' => (int)$prop['id_propietario'],
                'nombre'        => (string)$prop['nombre_propietario'],
                'ruc'           => (string)($prop['ruc'] ?? $ruc),
                'email'         => (string)($prop['email'] ?? ''),
                'telefono'      => (string)($prop['telefono'] ?? ''),
            ]);
            return;
        }

        // 2. ¿Existe como persona registrada en la BD?
        $per = Database::queryForMap(
            "SELECT p.cod_persona, p.nombre, i.numero,
                    (SELECT num_telefono FROM telef_personas WHERE cod_persona = p.cod_persona AND id_propietario = p.id_propietario ORDER BY CASE WHEN por_defecto = 'S' THEN 0 ELSE 1 END LIMIT 1) AS telefono,
                    (SELECT detalle FROM direc_personas WHERE cod_persona = p.cod_persona AND id_propietario = p.id_propietario ORDER BY CASE WHEN por_defecto = 'S' THEN 0 ELSE 1 END LIMIT 1) AS direccion
             FROM ident_personas i
             JOIN personas p ON p.id_propietario = i.id_propietario AND p.cod_persona = i.cod_persona
             WHERE UPPER(TRIM(SUBSTRING_INDEX(i.numero, '-', 1))) = ?
             ORDER BY CASE WHEN p.id_propietario = 1 THEN 0 ELSE 1 END
             LIMIT 1",
            [$base]
        );
        if ($per) {
            View::json([
                'status'     => 'PERSONA_EXISTE',
                'codPersona' => (string)$per['cod_persona'],
                'ruc'        => (string)$per['numero'],
                'nombre'     => (string)$per['nombre'],
                'telefono'   => (string)($per['telefono'] ?? ''),
                'direccion'  => (string)($per['direccion'] ?? ''),
            ]);
            return;
        }

        // 3. Consultar servicio DNIT (turuc.com.py)
        $url = 'https://turuc.com.py/api/contribuyente?ruc=' . urlencode($base);
        $raw = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
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
                    'timeout'       => 5,
                    'ignore_errors' => true,
                    'header'        => "Accept: application/json\r\nUser-Agent: Mozilla/5.0 ContaPro/1.0\r\n",
                ],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
        }

        if (is_string($raw) && $raw !== '') {
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

    /**
     * Guarda la configuración general de WhatsApp y avisos de suscripción/renovación
     */
    public static function saveConfiguracion(): array {
        self::checkAccess();

        $whatsappSoporte    = trim((string)($_POST['whatsappSoporte'] ?? ''));
        $mensajeRenovacion  = trim((string)($_POST['mensajeRenovacion'] ?? ''));
        $textoBotonWhatsapp = trim((string)($_POST['textoBotonWhatsapp'] ?? ''));
        $aplicarATodos      = strtoupper(trim((string)($_POST['aplicarATodos'] ?? 'S'))) === 'S';

        if ($whatsappSoporte === '' || strlen(preg_replace('/\D+/', '', $whatsappSoporte)) < 6) {
            return ['status' => 'error', 'message' => 'Ingrese un número de WhatsApp válido (ej: +595 982 965 515).'];
        }

        if ($mensajeRenovacion === '') {
            $mensajeRenovacion = 'Contactar al WhatsApp ' . $whatsappSoporte . ' para renovar su suscripción.';
        }
        if ($textoBotonWhatsapp === '') {
            $textoBotonWhatsapp = 'Contactar por WhatsApp para regularizar';
        }

        Database::execute("
            CREATE TABLE IF NOT EXISTS `configuracion_sistema` (
              `clave` VARCHAR(60) NOT NULL,
              `valor` TEXT DEFAULT NULL,
              `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`clave`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);

        $settings = [
            'whatsapp_soporte'     => $whatsappSoporte,
            'mensaje_renovacion'   => $mensajeRenovacion,
            'texto_boton_whatsapp' => $textoBotonWhatsapp,
        ];

        foreach ($settings as $k => $v) {
            Database::update(
                "INSERT INTO configuracion_sistema (clave, valor) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)",
                [$k, $v]
            );
        }

        if ($aplicarATodos) {
            Database::update(
                "UPDATE propietarios SET mensaje_pago = ?",
                [$mensajeRenovacion]
            );
        } else {
            Database::update(
                "UPDATE propietarios SET mensaje_pago = ?
                 WHERE mensaje_pago IS NULL OR TRIM(mensaje_pago) = '' OR mensaje_pago LIKE 'Contactar al WhatsApp %'",
                [$mensajeRenovacion]
            );
        }

        return [
            'status'  => 'success',
            'message' => 'Configuración de WhatsApp (' . $whatsappSoporte . ') y mensaje de renovación actualizados correctamente.',
            'config'  => TenantContext::getSystemConfig(true),
        ];
    }

    /**
     * Calcula estadísticas de almacenamiento de la BD, espacio libre en disco y consumo por propietario.
     */
    public static function calculateStorageStats(): array {
        $storage = [
            'total_db_bytes'  => 0,
            'total_db_fmt'    => '0 MB',
            'data_bytes'      => 0,
            'data_fmt'        => '0 MB',
            'index_bytes'     => 0,
            'index_fmt'       => '0 MB',
            'db_free_bytes'   => 0,
            'db_free_fmt'     => '0 MB',
            'total_tablas'    => 0,
            'disk_free_bytes' => 0,
            'disk_free_fmt'   => 'Desconocido',
            'disk_total_bytes'=> 0,
            'disk_total_fmt'  => 'Desconocido',
            'disk_used_bytes' => 0,
            'disk_used_fmt'   => 'Desconocido',
            'disk_free_pct'   => 0,
            'disk_used_pct'   => 0,
            'top_tablas'      => [],
            'props'           => [],
        ];

        try {
            // 1. Estadísticas globales de la Base de Datos
            $globalStats = Database::queryForList(
                "SELECT 
                    COALESCE(SUM(DATA_LENGTH), 0) AS data_bytes,
                    COALESCE(SUM(INDEX_LENGTH), 0) AS index_bytes,
                    COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0) AS total_bytes,
                    COALESCE(SUM(DATA_FREE), 0) AS free_bytes,
                    COUNT(*) AS total_tablas
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
            );

            if (!empty($globalStats[0])) {
                $row = $globalStats[0];
                $storage['data_bytes']     = (float)($row['data_bytes'] ?? 0);
                $storage['data_fmt']       = self::formatBytes($storage['data_bytes']);
                $storage['index_bytes']    = (float)($row['index_bytes'] ?? 0);
                $storage['index_fmt']      = self::formatBytes($storage['index_bytes']);
                $storage['total_db_bytes'] = (float)($row['total_bytes'] ?? 0);
                $storage['total_db_fmt']   = self::formatBytes($storage['total_db_bytes']);
                $storage['db_free_bytes']  = (float)($row['free_bytes'] ?? 0);
                $storage['db_free_fmt']    = self::formatBytes($storage['db_free_bytes']);
                $storage['total_tablas']   = (int)($row['total_tablas'] ?? 0);
            }

            // Top tablas más pesadas
            try {
                $topTablasRaw = Database::queryForList(
                    "SELECT 
                        TABLE_NAME AS nombre,
                        COALESCE(TABLE_ROWS, 0) AS filas,
                        COALESCE(DATA_LENGTH + INDEX_LENGTH, 0) AS total_bytes
                     FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
                     ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
                     LIMIT 8"
                );
                foreach ($topTablasRaw as $tt) {
                    $b = (float)$tt['total_bytes'];
                    $storage['top_tablas'][] = [
                        'nombre' => $tt['nombre'],
                        'filas'  => (int)$tt['filas'],
                        'bytes'  => $b,
                        'fmt'    => self::formatBytes($b),
                    ];
                }
            } catch (\Throwable $ignored) {}

            // 2. Espacio de disco del servidor
            $diskPath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
            $diskFree = @disk_free_space($diskPath);
            $diskTotal = @disk_total_space($diskPath);
            if (($diskFree === false || $diskFree <= 0) && function_exists('disk_free_space')) {
                $diskFree = @disk_free_space('/');
                $diskTotal = @disk_total_space('/');
            }
            if ($diskTotal && $diskTotal > 0 && $diskFree !== false) {
                $used = max(0, $diskTotal - $diskFree);
                $storage['disk_free_bytes'] = (float)$diskFree;
                $storage['disk_free_fmt']   = self::formatBytes($diskFree);
                $storage['disk_total_bytes']= (float)$diskTotal;
                $storage['disk_total_fmt']  = self::formatBytes($diskTotal);
                $storage['disk_used_bytes'] = (float)$used;
                $storage['disk_used_fmt']   = self::formatBytes($used);
                $storage['disk_free_pct']   = round(($diskFree / $diskTotal) * 100, 1);
                $storage['disk_used_pct']   = round(($used / $diskTotal) * 100, 1);
            }

            // 3. Tablas multitenant con columna id_propietario
            $tablesWithProp = Database::queryForList(
                "SELECT 
                    t.TABLE_NAME,
                    COALESCE(t.DATA_LENGTH + t.INDEX_LENGTH, 0) AS total_bytes
                 FROM information_schema.TABLES t
                 JOIN information_schema.COLUMNS c 
                   ON c.TABLE_SCHEMA = t.TABLE_SCHEMA 
                  AND c.TABLE_NAME = t.TABLE_NAME 
                  AND c.COLUMN_NAME = 'id_propietario'
                 WHERE t.TABLE_SCHEMA = DATABASE() 
                   AND t.TABLE_TYPE = 'BASE TABLE'"
            );

            $propsAccum = [];
            foreach ($tablesWithProp as $t) {
                $tblName = $t['TABLE_NAME'];
                $tblBytes = (float)$t['total_bytes'];
                if ($tblBytes <= 0) continue;

                try {
                    $counts = Database::queryForList(
                        "SELECT id_propietario, COUNT(*) AS cnt 
                         FROM `{$tblName}` 
                         WHERE id_propietario IS NOT NULL 
                         GROUP BY id_propietario"
                    );

                    $sumRows = 0;
                    foreach ($counts as $c) {
                        $sumRows += (int)$c['cnt'];
                    }

                    if ($sumRows > 0) {
                        foreach ($counts as $c) {
                            $pId = (int)$c['id_propietario'];
                            $pCnt = (int)$c['cnt'];
                            if (!isset($propsAccum[$pId])) {
                                $propsAccum[$pId] = [
                                    'bytes'  => 0.0,
                                    'filas'  => 0,
                                    'tablas' => 0,
                                ];
                            }
                            $share = $pCnt / $sumRows;
                            $propsAccum[$pId]['bytes'] += ($tblBytes * $share);
                            $propsAccum[$pId]['filas'] += $pCnt;
                            $propsAccum[$pId]['tablas']++;
                        }
                    }
                } catch (\Throwable $ignored) {
                    continue;
                }
            }

            // Calcular porcentajes relativos
            $totalDb = max(1.0, (float)$storage['total_db_bytes']);
            foreach ($propsAccum as $pId => $st) {
                $b = (float)$st['bytes'];
                $pct = round(($b / $totalDb) * 100, 2);
                $storage['props'][$pId] = [
                    'id_propietario' => $pId,
                    'bytes'          => $b,
                    'fmt'            => self::formatBytes($b),
                    'filas'          => $st['filas'],
                    'tablas'         => $st['tablas'],
                    'pct'            => $pct,
                ];
            }

        } catch (\Throwable $e) {
            error_log("Error calculateStorageStats: " . $e->getMessage());
        }

        return $storage;
    }

    private static function formatBytes(float $bytes, int $precision = 2): string {
        if ($bytes <= 0) return '0 B';
        if ($bytes >= 1073741824) { // GB
            return number_format($bytes / 1073741824, $precision, ',', '.') . ' GB';
        }
        if ($bytes >= 1048576) { // MB
            return number_format($bytes / 1048576, $precision, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) { // KB
            return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        }
        return number_format($bytes, 0, ',', '.') . ' B';
    }
}
