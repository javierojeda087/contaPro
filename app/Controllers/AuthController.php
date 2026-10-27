<?php
namespace App\Controllers;

use Core\Database;
use Core\TenantContext;
use Core\View;

class AuthController {

    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS    = 120;
    private const ATTEMPT_WINDOW_SEC = 300;

    public static function hashPassword(string $plain): string {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public static function isPasswordHash(string $stored): bool {
        return str_starts_with($stored, '$2y$')
            || str_starts_with($stored, '$2a$')
            || str_starts_with($stored, '$2b$')
            || str_starts_with($stored, '$argon2');
    }

    /**
     * Verifica contraseña soportando tanto hashes seguros (bcrypt/argon2) como claves legadas en texto plano.
     * @return array{valid: bool, needsRehash: bool}
     */
    public static function verifyPassword(string $plain, string $stored): array {
        $storedClean = trim(str_replace('"', '', $stored));
        if ($storedClean === '' || $plain === '') {
            return ['valid' => false, 'needsRehash' => false];
        }

        if (self::isPasswordHash($storedClean)) {
            $ok = password_verify($plain, $storedClean);
            return [
                'valid'       => $ok,
                'needsRehash' => $ok && password_needs_rehash($storedClean, PASSWORD_DEFAULT),
            ];
        }

        // Compatibilidad con claves legadas en texto plano y migración transparente a bcrypt
        $ok = ($storedClean === $plain) || (strcasecmp($storedClean, $plain) === 0);
        return [
            'valid'       => $ok,
            'needsRehash' => $ok,
        ];
    }

    private static function getClientIp(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return preg_replace('/[^0-9a-fA-F:.,]/', '', (string)$ip) ?: '0.0.0.0';
    }

    private static function getRateLimitFile(): string {
        $ipHash = hash('sha256', 'contapro_login_' . self::getClientIp());
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cp_rl_' . substr($ipHash, 0, 24) . '.json';
    }

    private static function checkRateLimit(): ?string {
        $now = time();

        // 1. Verificar por sesión
        $sessLock = (int)($_SESSION['_login_lock_until'] ?? 0);
        if ($sessLock > $now) {
            $wait = $sessLock - $now;
            return "Demasiados intentos fallidos. Por seguridad, espere {$wait} segundos antes de volver a intentar.";
        }

        // 2. Verificar por IP
        $file = self::getRateLimitFile();
        if (is_file($file)) {
            $data = json_decode((string)@file_get_contents($file), true);
            if (is_array($data)) {
                $lockUntil = (int)($data['lock_until'] ?? 0);
                if ($lockUntil > $now) {
                    $wait = $lockUntil - $now;
                    return "Demasiados intentos fallidos. Por seguridad, espere {$wait} segundos antes de volver a intentar.";
                }
            }
        }
        return null;
    }

    private static function recordFailedAttempt(): void {
        $now = time();

        // Sesión
        $firstAt = (int)($_SESSION['_login_first_attempt'] ?? 0);
        $count   = (int)($_SESSION['_login_attempts'] ?? 0);
        if ($firstAt === 0 || ($now - $firstAt) > self::ATTEMPT_WINDOW_SEC) {
            $firstAt = $now;
            $count   = 1;
        } else {
            $count++;
        }
        $_SESSION['_login_first_attempt'] = $firstAt;
        $_SESSION['_login_attempts']      = $count;
        if ($count >= self::MAX_LOGIN_ATTEMPTS) {
            $_SESSION['_login_lock_until'] = $now + self::LOCKOUT_SECONDS;
        }

        // Archivo por IP
        $file = self::getRateLimitFile();
        $ipFirst = $now;
        $ipCount = 1;
        $ipLock  = 0;
        if (is_file($file)) {
            $data = json_decode((string)@file_get_contents($file), true);
            if (is_array($data)) {
                $prevFirst = (int)($data['first_at'] ?? 0);
                $prevCount = (int)($data['attempts'] ?? 0);
                if ($prevFirst > 0 && ($now - $prevFirst) <= self::ATTEMPT_WINDOW_SEC) {
                    $ipFirst = $prevFirst;
                    $ipCount = $prevCount + 1;
                }
            }
        }
        if ($ipCount >= self::MAX_LOGIN_ATTEMPTS) {
            $ipLock = $now + self::LOCKOUT_SECONDS;
        }
        @file_put_contents($file, json_encode([
            'first_at'   => $ipFirst,
            'attempts'   => $ipCount,
            'lock_until' => $ipLock,
        ]));
    }

    private static function clearFailedAttempts(): void {
        unset($_SESSION['_login_first_attempt'], $_SESSION['_login_attempts'], $_SESSION['_login_lock_until']);
        $file = self::getRateLimitFile();
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private static function hasPreAuthOrActiveSession(): bool {
        $u = $_SESSION['_verified_usuario'] ?? $_SESSION['cambioUsuario'] ?? $_SESSION['usuario'] ?? '';
        return trim((string)$u) !== '';
    }

    public static function landing(): void {
        try {
            $planes = Database::queryForList("SELECT * FROM planes WHERE estado = 'A' ORDER BY orden, id_plan");
        } catch (\Throwable $e) {
            $planes = [];
        }
        View::render('landing', ['planes' => $planes]);
    }

    public static function showLogin(): void {
        if (!empty($_SESSION['usuario'])) {
            header('Location: /menu');
            exit;
        }
        $model = [
            'cambioUsuario'  => $_SESSION['cambioUsuario'] ?? null,
            'cambioEmpresa'  => $_SESSION['cambioEmpresa'] ?? '',
            'cambioSucursal' => $_SESSION['cambioSucursal'] ?? '',
        ];
        View::render('login', $model);
    }

    public static function verifyCredentials(): array {
        if ($lockMsg = self::checkRateLimit()) {
            return ['status' => 'error', 'message' => $lockMsg];
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = trim((string)($_POST['password'] ?? ''));

        if ($username === '' || $password === '') {
            return ['status' => 'error', 'message' => 'Ingrese usuario y contraseña.'];
        }

        $cleanUser = strtoupper($username);
        $sql = "SELECT u.id_propietario, u.cod_usuario, u.clave, u.estado, u.cod_empresa, u.cod_sucursal AS suc_codi,
                       u.es_superadmin, u.nombre, u.apellido,
                       p.nombre AS nombre_persona,
                       pr.nombre_propietario, pr.estado AS estado_prop, pr.fec_vencimiento,
                       pr.dias_aviso, pr.dias_gracia, pr.mensaje_pago,
                       pr.max_contribuyentes, pr.max_usuarios,
                       pl.nombre_plan, pl.precio_mensual
                FROM usuarios u
                LEFT JOIN propietarios pr ON pr.id_propietario = u.id_propietario
                LEFT JOIN planes pl ON pl.id_plan = pr.id_plan
                LEFT JOIN personas p ON p.id_propietario = u.id_propietario AND TRIM(p.cod_persona) = TRIM(u.cod_persona)
                WHERE UPPER(TRIM(u.cod_usuario)) = ? OR UPPER(TRIM(COALESCE(u.alias, ''))) = ?
                ORDER BY u.id_propietario ASC
                LIMIT 1";

        $uInfo = Database::queryForMap($sql, [$cleanUser, $cleanUser]);
        if (!$uInfo) {
            self::recordFailedAttempt();
            return ['status' => 'error', 'message' => 'Usuario o contraseña incorrectos.'];
        }

        $claveDb = (string)($uInfo['clave'] ?? '');
        $check   = self::verifyPassword($password, $claveDb);
        if (!$check['valid']) {
            self::recordFailedAttempt();
            return ['status' => 'error', 'message' => 'Usuario o contraseña incorrectos.'];
        }

        $propId     = (int)($uInfo['id_propietario'] ?? 1);
        $codUserReal = strtoupper(trim((string)($uInfo['cod_usuario'] ?? $cleanUser)));

        // Si la clave aún estaba en texto plano o requiere actualización de algoritmo, migrar a bcrypt automáticamente
        if ($check['needsRehash']) {
            try {
                Database::execute("ALTER TABLE `usuarios` MODIFY COLUMN `clave` VARCHAR(255) DEFAULT NULL", true);
                Database::update(
                    "UPDATE usuarios SET clave = ? WHERE id_propietario = ? AND UPPER(TRIM(cod_usuario)) = ?",
                    [self::hashPassword($password), $propId, $codUserReal]
                );
            } catch (\Throwable $ignored) {}
        }

        $estado = strtoupper(trim((string)($uInfo['estado'] ?? 'A')));
        if ($estado !== '' && $estado !== 'A') {
            return ['status' => 'error', 'message' => 'El usuario está inactivo.'];
        }

        $esSuper = (strtoupper(trim((string)($uInfo['es_superadmin'] ?? 'N'))) === 'S') || ($propId === 1 && $codUserReal === 'CONTA');

        if (!$esSuper) {
            $subInfo = TenantContext::enrichSubscriptionInfo([
                'estado'          => $uInfo['estado_prop'] ?? 'A',
                'fec_vencimiento' => $uInfo['fec_vencimiento'] ?? null,
                'dias_aviso'      => $uInfo['dias_aviso'] ?? 7,
                'dias_gracia'     => $uInfo['dias_gracia'] ?? 3,
                'nombre_plan'     => $uInfo['nombre_plan'] ?? 'Plan Básico',
                'precio_mensual'  => $uInfo['precio_mensual'] ?? 0,
                'mensaje_pago'    => $uInfo['mensaje_pago'] ?? null,
            ]);

            if (!empty($subInfo['bloqueado_por_vencimiento'])) {
                return [
                    'status'  => 'error',
                    'message' => ($subInfo['titulo_aviso_pago'] ? $subInfo['titulo_aviso_pago'] . ': ' : '') . $subInfo['texto_aviso_pago'],
                ];
            }
        }

        self::clearFailedAttempts();
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }

        $_SESSION['id_propietario']    = $propId;
        $_SESSION['es_superadmin']     = $esSuper ? 'S' : 'N';
        $_SESSION['_verified_usuario'] = $codUserReal;

        $usedContrib = (int)Database::queryForValue("SELECT COUNT(*) FROM empresas WHERE id_propietario = ?", [$propId]);

        $nomPer = trim((string)($uInfo['nombre_persona'] ?? ''));
        if ($nomPer === '') {
            $nomPer = trim(($uInfo['nombre'] ?? '') . ' ' . ($uInfo['apellido'] ?? ''));
        }
        if ($nomPer === '') $nomPer = $codUserReal;

        return [
            'status'               => 'success',
            'username'             => $codUserReal,
            'idPropietario'        => $propId,
            'nombrePropietario'    => $uInfo['nombre_propietario'] ?? ('PROPIETARIO ' . $propId),
            'nombrePlan'           => $uInfo['nombre_plan'] ?? 'Plan Básico',
            'maxContribuyentes'    => (int)($uInfo['max_contribuyentes'] ?? 5),
            'usadosContribuyentes' => $usedContrib,
            'defaultEmpresa'       => (string)($uInfo['cod_empresa'] ?? ''),
            'defaultSucursal'      => (string)($uInfo['suc_codi'] ?? ''),
            'nombrePersona'        => $nomPer,
        ];
    }

    public static function getEmpresasLogin(): array {
        if (!self::hasPreAuthOrActiveSession()) {
            http_response_code(401);
            return [];
        }
        $propId = TenantContext::idPropietario();
        $rows = Database::queryForList(
            "SELECT cod_empresa, descripcion, actividad, ruc_empresa
             FROM empresas
             WHERE id_propietario = ?
             ORDER BY CAST(cod_empresa AS UNSIGNED) ASC, cod_empresa ASC",
            [$propId]
        );
        $out = [];
        foreach ($rows as $m) {
            $out[] = [
                'cod_empresa' => (string)($m['cod_empresa'] ?? ''),
                'descripcion' => (string)($m['descripcion'] ?? ''),
                'actividad'   => (string)($m['actividad'] ?? ''),
                'ruc_empresa' => (string)($m['ruc_empresa'] ?? ''),
            ];
        }
        return $out;
    }

    public static function getEmpresaDetalles(): array {
        if (!self::hasPreAuthOrActiveSession()) {
            http_response_code(401);
            return ['status' => 'error', 'message' => 'Sesión no autorizada.'];
        }
        $propId = TenantContext::idPropietario();
        $emp = trim((string)($_GET['codEmpresa'] ?? ''));

        $ejercicios = [];
        $ejList = Database::queryForList(
            "SELECT cod_ejercicio, DATE_FORMAT(fec_inicio, '%d/%m/%Y') AS fec_ini, DATE_FORMAT(fec_final, '%d/%m/%Y') AS fec_fin
             FROM co_ejercicios
             WHERE id_propietario = ? AND cod_empresa = ? AND (estado IS NULL OR estado = 'A')
             ORDER BY cod_ejercicio DESC",
            [$propId, $emp]
        );
        foreach ($ejList as $row) {
            $ejercicios[] = [
                'codEjercicio' => (string)($row['cod_ejercicio'] ?? ''),
                'fecInicio'    => (string)($row['fec_ini'] ?? ''),
                'fecFinal'     => (string)($row['fec_fin'] ?? ''),
            ];
        }

        $sucursales = [];
        $sucList = Database::queryForList(
            "SELECT cod_sucursal, descripcion, actividad
             FROM sucursales
             WHERE id_propietario = ? AND cod_empresa = ?
             ORDER BY cod_sucursal",
            [$propId, $emp]
        );
        foreach ($sucList as $row) {
            $sucursales[] = [
                'codSucursal' => (string)($row['cod_sucursal'] ?? ''),
                'descripcion' => (string)($row['descripcion'] ?? ''),
                'actividad'   => (string)($row['actividad'] ?? ''),
            ];
        }

        $empRow = Database::queryForMap(
            "SELECT actividad FROM empresas WHERE id_propietario = ? AND cod_empresa = ?",
            [$propId, $emp]
        );
        $actividadEmpresa = (string)($empRow['actividad'] ?? '');

        $actividades = [];
        $actList = Database::queryForList(
            "SELECT descripcion, por_defecto
             FROM co_actividades
             WHERE id_propietario = ? AND cod_empresa = ? AND estado = 'A'
             ORDER BY CASE WHEN por_defecto = 'S' THEN 0 ELSE 1 END, cod_actividad",
            [$propId, $emp]
        );
        foreach ($actList as $row) {
            $actividades[] = [
                'descripcion' => (string)($row['descripcion'] ?? ''),
                'porDefecto'  => ($row['por_defecto'] ?? '') === 'S',
            ];
        }

        return [
            'status'      => 'success',
            'ejercicios'  => $ejercicios,
            'sucursales'  => $sucursales,
            'actividad'   => $actividadEmpresa,
            'actividades' => $actividades,
        ];
    }

    public static function confirmLogin(): array {
        // Exigir usuario previamente verificado en sesión (evita bypass de contraseña en Paso 2)
        $verifiedSessionUser = $_SESSION['_verified_usuario'] ?? $_SESSION['cambioUsuario'] ?? $_SESSION['usuario'] ?? '';
        $cleanUser = strtoupper(trim((string)$verifiedSessionUser));
        if ($cleanUser === '') {
            http_response_code(401);
            return [
                'status'  => 'error',
                'message' => 'Su sesión de verificación expiró. Por favor ingrese usuario y contraseña nuevamente.',
            ];
        }

        $emp    = trim((string)($_POST['codEmpresa'] ?? ''));
        $suc    = trim((string)($_POST['codSucursal'] ?? '01'));
        $ejer   = trim((string)($_POST['codEjercicio'] ?? date('Y')));
        $fecIni = trim((string)($_POST['fecInicio'] ?? ''));
        $fecFin = trim((string)($_POST['fecFinal'] ?? ''));
        $act    = trim((string)($_POST['actividad'] ?? ''));

        $uRow = Database::queryForMap(
            "SELECT u.id_propietario, u.cod_usuario, u.cod_grupo, u.es_superadmin, u.estado, u.nombre, u.apellido, p.nombre AS nombre_persona
             FROM usuarios u
             LEFT JOIN personas p ON p.id_propietario = u.id_propietario AND TRIM(p.cod_persona) = TRIM(u.cod_persona)
             WHERE UPPER(TRIM(u.cod_usuario)) = ?
             ORDER BY u.id_propietario ASC LIMIT 1",
            [$cleanUser]
        );

        if (!$uRow || (strtoupper(trim((string)($uRow['estado'] ?? 'A'))) !== 'A' && strtoupper(trim((string)($uRow['estado'] ?? ''))) !== '')) {
            http_response_code(401);
            return ['status' => 'error', 'message' => 'Usuario no válido o inactivo.'];
        }

        $propId = (int)($uRow['id_propietario'] ?? TenantContext::idPropietario());

        $empDesc = $emp;
        if ($emp !== '') {
            $eRow = Database::queryForMap("SELECT descripcion FROM empresas WHERE id_propietario = ? AND cod_empresa = ?", [$propId, $emp]);
            if (!$eRow) {
                return ['status' => 'error', 'message' => 'El contribuyente seleccionado no pertenece a su cuenta.'];
            }
            if (!empty($eRow['descripcion'])) {
                $empDesc = $eRow['descripcion'];
            }
        } else {
            $emp = '1';
            $empDesc = 'SIN CONTRIBUYENTE (CREAR PRIMERO)';
        }

        $sucDesc = $suc;
        if ($suc !== '') {
            $sRow = Database::queryForMap("SELECT descripcion FROM sucursales WHERE id_propietario = ? AND cod_empresa = ? AND cod_sucursal = ?", [$propId, $emp, $suc]);
            if ($sRow && !empty($sRow['descripcion'])) {
                $sucDesc = $sRow['descripcion'];
            }
        }

        $nombrePer = trim((string)($uRow['nombre_persona'] ?? ''));
        if ($nombrePer === '') {
            $nombrePer = trim(($uRow['nombre'] ?? '') . ' ' . ($uRow['apellido'] ?? ''));
        }
        if ($nombrePer === '') $nombrePer = $cleanUser;

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }

        unset($_SESSION['cambioUsuario'], $_SESSION['cambioEmpresa'], $_SESSION['cambioSucursal'], $_SESSION['_verified_usuario']);

        $_SESSION['id_propietario'] = $propId;
        $_SESSION['es_superadmin']  = strtoupper(trim((string)($uRow['es_superadmin'] ?? 'N')));
        $_SESSION['usuario']        = $cleanUser;
        $_SESSION['grupo']          = strtoupper(trim((string)($uRow['cod_grupo'] ?? 'CONTA'))) ?: 'CONTA';
        $_SESSION['empresa']        = $emp;
        $_SESSION['empresaDesc']    = $empDesc;
        $_SESSION['ejercicio']      = $ejer ?: date('Y');
        $_SESSION['fecInicio']      = $fecIni;
        $_SESSION['fecFinal']       = $fecFin;
        $_SESSION['sucursal']       = $suc ?: '01';
        $_SESSION['sucursalDesc']   = $sucDesc ?: '01 - MATRIZ';
        $_SESSION['actividad']      = $act;
        $_SESSION['nombrePersona']  = $nombrePer;

        // Si el propietario aún no tiene contribuyentes, llevarlo directo a crear su primer contribuyente
        $cntEmp = (int)Database::queryForValue("SELECT COUNT(*) FROM empresas WHERE id_propietario = ?", [$propId]);
        $redirect = $cntEmp === 0 ? '/app/contribuyentes' : '/menu';

        return ['status' => 'success', 'redirect' => $redirect];
    }

    public static function cambiarContribuyente(): void {
        $u = $_SESSION['usuario'] ?? null;
        if (!$u) {
            header('Location: /login');
            exit;
        }
        $e = $_SESSION['empresa'] ?? '';
        $s = $_SESSION['sucursal'] ?? '';
        $propId = $_SESSION['id_propietario'] ?? 1;
        $super = $_SESSION['es_superadmin'] ?? 'N';

        session_unset();
        $_SESSION['id_propietario'] = $propId;
        $_SESSION['es_superadmin']  = $super;
        $_SESSION['cambioUsuario']  = $u;
        $_SESSION['cambioEmpresa']  = $e;
        $_SESSION['cambioSucursal'] = $s;

        header('Location: /login');
        exit;
    }

    /**
     * Cambia el Contribuyente, Ejercicio, Sucursal y Actividad activa en caliente desde cualquier pantalla sin cerrar sesión
     */
    public static function switchContext(): array {
        if (empty($_SESSION['usuario'])) {
            return ['status' => 'error', 'message' => 'Sesión no activa.'];
        }

        $propId = TenantContext::idPropietario();
        $emp    = trim((string)($_POST['codEmpresa'] ?? ''));
        $suc    = trim((string)($_POST['codSucursal'] ?? '01'));
        $ejer   = trim((string)($_POST['codEjercicio'] ?? date('Y')));
        $fecIni = trim((string)($_POST['fecInicio'] ?? ''));
        $fecFin = trim((string)($_POST['fecFinal'] ?? ''));
        $act    = trim((string)($_POST['actividad'] ?? ''));

        if ($emp === '') {
            return ['status' => 'error', 'message' => 'Debe seleccionar un Contribuyente / Empresa.'];
        }

        $eRow = Database::queryForMap(
            "SELECT cod_empresa, descripcion, actividad FROM empresas WHERE id_propietario = ? AND cod_empresa = ?",
            [$propId, $emp]
        );
        if (!$eRow) {
            return ['status' => 'error', 'message' => 'El contribuyente seleccionado no existe o no pertenece a su cuenta.'];
        }

        $empDesc = !empty($eRow['descripcion']) ? (string)$eRow['descripcion'] : $emp;
        if ($act === '' && !empty($eRow['actividad'])) {
            $act = (string)$eRow['actividad'];
        }

        // Si no se enviaron fechas de inicio/fin del ejercicio, buscarlas en co_ejercicios
        if (($fecIni === '' || $fecFin === '') && $ejer !== '') {
            $ejRow = Database::queryForMap(
                "SELECT DATE_FORMAT(fec_inicio, '%d/%m/%Y') AS fec_ini, DATE_FORMAT(fec_final, '%d/%m/%Y') AS fec_fin
                 FROM co_ejercicios
                 WHERE id_propietario = ? AND cod_empresa = ? AND cod_ejercicio = ?
                 LIMIT 1",
                [$propId, $emp, $ejer]
            );
            if ($ejRow) {
                $fecIni = (string)($ejRow['fec_ini'] ?? '');
                $fecFin = (string)($ejRow['fec_fin'] ?? '');
            }
        }

        $sucDesc = $suc;
        if ($suc !== '') {
            $sRow = Database::queryForMap(
                "SELECT descripcion FROM sucursales WHERE id_propietario = ? AND cod_empresa = ? AND cod_sucursal = ?",
                [$propId, $emp, $suc]
            );
            if ($sRow && !empty($sRow['descripcion'])) {
                $sucDesc = (string)$sRow['descripcion'];
            }
        }

        $_SESSION['empresa']      = $emp;
        $_SESSION['empresaDesc']  = $empDesc;
        $_SESSION['ejercicio']    = $ejer ?: date('Y');
        $_SESSION['fecInicio']    = $fecIni;
        $_SESSION['fecFinal']     = $fecFin;
        $_SESSION['sucursal']     = $suc ?: '01';
        $_SESSION['sucursalDesc'] = $sucDesc ?: '01 - MATRIZ';
        $_SESSION['actividad']    = $act ?: 'COMERCIAL';

        return [
            'status'      => 'success',
            'empresa'     => $_SESSION['empresa'],
            'empresaDesc' => $_SESSION['empresaDesc'],
            'ejercicio'   => $_SESSION['ejercicio'],
            'message'     => "Contribuyente activo cambiado a {$empDesc} (Ejercicio {$_SESSION['ejercicio']}).",
        ];
    }

    public static function logout(): void {
        session_unset();
        session_destroy();
        header('Location: /login');
        exit;
    }

    public static function menu(): void {
        if (empty($_SESSION['usuario'])) {
            header('Location: /login');
            exit;
        }
        $modulos = \App\Services\MenuService::obtenerMenuPorUsuario(TenantContext::usuario(), TenantContext::grupo());
        View::render('menu', [
            'modulos'      => $modulos,
            'planSummary'  => TenantContext::getPlanSummary(),
            'esSuperAdmin' => TenantContext::esSuperAdmin(),
        ]);
    }
}

