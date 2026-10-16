<?php
namespace App\Controllers;

use Core\Database;
use Core\TenantContext;
use Core\View;
use App\Services\MenuService;
use App\Services\PersonaService;

class DirectoriosContablesController {

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

    // =========================================================================
    // VISTAS HTML (Directorios Contables)
    // =========================================================================
    public static function pageCoPersonas(): void    { self::renderFormPage('co_personas'); }
    public static function pageCoClientes(): void    { self::renderFormPage('co_clientes'); }
    public static function pageCoProveedores(): void { self::renderFormPage('co_proveedores'); }
    public static function pageCoSucursales(): void  { self::renderFormPage('co_sucursales'); }

    // =========================================================================
    // API CO_PERSONAS (/api/co_personas/*)
    // =========================================================================
    public static function apiCoPersonasList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $search = trim((string)($_GET['search'] ?? ''));

        try {
            $sql = "SELECT cod_persona, nombre, ruc, ci,
                           COALESCE(tipo_documento, 'RUC') AS tipo_documento,
                           direccion, telefono, email,
                           COALESCE(estado, 'A') AS estado
                    FROM personas
                    WHERE id_propietario = ? ";
            $params = [$idProp];
            if ($search !== '') {
                $sql .= "AND (UPPER(nombre) LIKE ? OR cod_persona = ? OR UPPER(COALESCE(ruc, '')) LIKE ? OR UPPER(COALESCE(ci, '')) LIKE ?) ";
                $s = '%' . strtoupper($search) . '%';
                $params[] = $s;
                $params[] = $search;
                $params[] = $s;
                $params[] = $s;
            }
            $sql .= "ORDER BY CAST(cod_persona AS UNSIGNED) DESC, cod_persona DESC";
            $list = Database::queryForListUpper($sql, $params);
            View::json(['status' => 'success', 'data' => $list]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al listar personas: ' . $e->getMessage()]);
        }
    }

    public static function apiCoPersonasSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $payload = self::getInput();

        $codPersona    = trim((string)($payload['codPersona'] ?? ''));
        $nombre        = trim((string)($payload['nombre'] ?? ''));
        $ruc           = trim((string)($payload['ruc'] ?? ''));
        $ci            = trim((string)($payload['ci'] ?? ''));
        $tipoDocumento = trim((string)($payload['tipoDocumento'] ?? 'RUC')) ?: 'RUC';
        $direccion     = trim((string)($payload['direccion'] ?? ''));
        $telefono      = trim((string)($payload['telefono'] ?? ''));
        $email         = trim((string)($payload['email'] ?? ''));

        if ($nombre === '') {
            View::json(['status' => 'error', 'message' => 'El nombre de la persona es obligatorio.']);
            return;
        }

        $docVal = $ruc !== '' ? $ruc : $ci;

        try {
            if ($docVal !== '' && strtoupper($tipoDocumento) !== 'INNOMINADO') {
                $existing = Database::queryForMap(
                    "SELECT p.cod_persona, p.nombre
                     FROM personas p
                     JOIN ident_personas i ON i.id_propietario = p.id_propietario AND i.cod_persona = p.cod_persona
                     WHERE p.id_propietario = ?
                       AND UPPER(TRIM(i.numero)) = UPPER(?)
                       AND (p.cod_persona <> ? OR ? = '')
                     LIMIT 1",
                    [$idProp, $docVal, $codPersona, $codPersona]
                );
                if ($existing) {
                    View::json([
                        'status'  => 'error',
                        'message' => "Operación rechazada: Ya existe una persona registrada con el N° de Documento / RUC {$docVal} (Persona #{$existing['cod_persona']} - {$existing['nombre']}). No se permite duplicar documentos."
                    ]);
                    return;
                }
            }

            $finalCodPersona = PersonaService::syncPersonaData(
                $codPersona !== '' ? $codPersona : null,
                $nombre,
                $docVal,
                $tipoDocumento,
                $direccion,
                $telefono,
                $email
            );

            View::json([
                'status'     => 'success',
                'message'    => "Persona registrada/actualizada correctamente con código {$finalCodPersona}",
                'codPersona' => $finalCodPersona,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar persona: ' . $e->getMessage()]);
        }
    }

    public static function apiCoPersonasDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $payload = self::getInput();
        $codPersona = trim((string)($payload['codPersona'] ?? ''));

        try {
            // Verificar si está usada en clientes, proveedores o usuarios
            $enCli = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM co_clientes WHERE id_propietario = ? AND cod_persona = ?",
                [$idProp, $codPersona]
            );
            $enProv = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM co_proveedores WHERE id_propietario = ? AND cod_persona = ?",
                [$idProp, $codPersona]
            );
            $enUsr = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM usuarios WHERE id_propietario = ? AND cod_persona = ?",
                [$idProp, $codPersona]
            );
            if ($enCli > 0 || $enProv > 0 || $enUsr > 0) {
                $det = '';
                if ($enCli > 0)  $det .= "<li><b>Clientes</b>: {$enCli} registro(s)</li>";
                if ($enProv > 0) $det .= "<li><b>Proveedores</b>: {$enProv} registro(s)</li>";
                if ($enUsr > 0)  $det .= "<li><b>Usuarios</b>: {$enUsr} registro(s)</li>";
                View::json([
                    'status'  => 'error',
                    'message' => "La persona #{$codPersona} está siendo usada en:<ul style='text-align:left'>{$det}</ul>Elimine o reasigne esos registros primero."
                ]);
                return;
            }

            Database::update("DELETE FROM ident_personas WHERE id_propietario = ? AND cod_persona = ?", [$idProp, $codPersona]);
            Database::update("DELETE FROM telef_personas WHERE id_propietario = ? AND cod_persona = ?", [$idProp, $codPersona]);
            Database::update("DELETE FROM direc_personas WHERE id_propietario = ? AND cod_persona = ?", [$idProp, $codPersona]);
            $n = Database::update("DELETE FROM personas WHERE id_propietario = ? AND cod_persona = ?", [$idProp, $codPersona]);

            if ($n === 0) {
                View::json(['status' => 'error', 'message' => "No se encontró la persona #{$codPersona}."]);
                return;
            }
            View::json(['status' => 'success', 'message' => "Persona #{$codPersona} eliminada correctamente."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar persona: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API PERSONAS AUXILIARES (/api/dnit/consultar-ruc, /api/personas/*)
    // =========================================================================
    public static function apiDnitConsultarRuc(): void {
        $ruc = trim((string)($_GET['ruc'] ?? ''));
        if ($ruc === '') {
            View::json(['status' => 'NOT_FOUND']);
            return;
        }
        $rucBase = str_contains($ruc, '-') ? trim(explode('-', $ruc)[0]) : $ruc;
        if (strlen($rucBase) < 3) {
            View::json(['status' => 'NOT_FOUND']);
            return;
        }

        $ctx = stream_context_create([
            'http' => ['timeout' => 4, 'ignore_errors' => true],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $raw = @file_get_contents('https://turuc.com.py/api/contribuyente?ruc=' . urlencode($rucBase), false, $ctx);
        if ($raw !== false) {
            $json = json_decode($raw, true);
            if (!empty($json['data'])) {
                View::json(['status' => 'SUCCESS', 'data' => $json['data']]);
                return;
            }
        }
        View::json(['status' => 'NOT_FOUND']);
    }

    public static function apiPersonasAsientosPredefinidos(string $codPersona): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $cod = (int)$codPersona;

        try {
            $rows = Database::queryForListUpper(
                "SELECT * FROM co_persona_asientos WHERE id_propietario = ? AND cod_persona = ?",
                [$idProp, $cod]
            );
            $contado = [];
            $credito = [];
            foreach ($rows as $r) {
                $cond = strtoupper((string)($r['CONDICION'] ?? ''));
                if ($cond === 'CONTADO') $contado = $r;
                elseif ($cond === 'CREDITO') $credito = $r;
            }
            if (empty($contado)) {
                $contado = [
                    'CTA_IVA10'         => '01.01.03.05.03.01 - IVA CRÉDITO 10%',
                    'CTA_IVA5'          => '01.01.03.05.03.02 - IVA CRÉDITO 05%',
                    'CTA_GRAV10'        => '01.01.04.01.01 - MERCADERÍAS GRAVADAS POR EL IVA AL 10%',
                    'CTA_GRAV5'         => '01.01.04.01.02 - MERCADERÍAS GRAVADAS POR EL IVA AL 5%',
                    'CTA_EXENTAS'       => '01.01.04.01.03 - MERCADERÍAS EXENTAS DEL IVA',
                    'CTA_CONTRAPARTIDA' => '01.01.01.02.01 - CAJA GUARANÍES',
                ];
            }
            if (empty($credito)) {
                $credito = [
                    'CTA_IVA10'         => '01.01.03.05.03.01 - IVA CRÉDITO 10%',
                    'CTA_IVA5'          => '01.01.03.05.03.02 - IVA CRÉDITO 05%',
                    'CTA_GRAV10'        => '01.01.04.01.01 - MERCADERÍAS GRAVADAS POR EL IVA AL 10%',
                    'CTA_GRAV5'         => '01.01.04.01.02 - MERCADERÍAS GRAVADAS POR EL IVA AL 5%',
                    'CTA_EXENTAS'       => '01.01.04.01.03 - MERCADERÍAS EXENTAS DEL IVA',
                    'CTA_CONTRAPARTIDA' => '02.01.01.01 - PROVEEDORES LOCALES [ CORTO PLAZO ]',
                ];
            }
            View::json(['status' => 'success', 'contado' => $contado, 'credito' => $credito]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public static function apiPersonasAsientosPredefinidosByRuc(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $ruc = trim((string)($_GET['ruc'] ?? ''));
        $codPersona = 0;

        if ($ruc !== '') {
            $found = Database::queryForValue(
                "SELECT p.cod_persona FROM personas p
                 WHERE p.id_propietario = ?
                   AND (p.ruc = ? OR p.cod_persona = ? OR EXISTS (
                       SELECT 1 FROM ident_personas i
                       WHERE i.id_propietario = p.id_propietario AND i.cod_persona = p.cod_persona AND i.numero = ?
                   ))
                 LIMIT 1",
                [$idProp, $ruc, $ruc, $ruc]
            );
            if ($found) $codPersona = (int)$found;
        }
        self::apiPersonasAsientosPredefinidos((string)$codPersona);
    }

    public static function apiPersonasGuardarAsientosPredefinidos(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $payload = self::getInput();
        $codPersona = (int)($payload['codPersona'] ?? 0);

        try {
            $contado = $payload['contado'] ?? null;
            if (is_array($contado)) {
                Database::update(
                    "DELETE FROM co_persona_asientos WHERE id_propietario = ? AND cod_persona = ? AND condicion = 'CONTADO'",
                    [$idProp, $codPersona]
                );
                Database::update(
                    "INSERT INTO co_persona_asientos (id_propietario, cod_persona, condicion, cta_iva10, cta_iva5, cta_grav10, cta_grav5, cta_exentas, cta_contrapartida)
                     VALUES (?, ?, 'CONTADO', ?, ?, ?, ?, ?, ?)",
                    [
                        $idProp, $codPersona,
                        $contado['ctaIva10']   ?? '01.01.03.05.03.01 - IVA CRÉDITO 10%',
                        $contado['ctaIva5']    ?? '01.01.03.05.03.02 - IVA CRÉDITO 05%',
                        $contado['ctaGrav10']  ?? '01.01.04.01.01 - MERCADERÍAS GRAVADAS POR EL IVA AL 10%',
                        $contado['ctaGrav5']   ?? '01.01.04.01.02 - MERCADERÍAS GRAVADAS POR EL IVA AL 5%',
                        $contado['ctaExentas'] ?? '01.01.04.01.03 - MERCADERÍAS EXENTAS DEL IVA',
                        $contado['ctaContra']  ?? '01.01.01.02.01 - CAJA GUARANÍES',
                    ]
                );
            }

            $credito = $payload['credito'] ?? null;
            if (is_array($credito)) {
                Database::update(
                    "DELETE FROM co_persona_asientos WHERE id_propietario = ? AND cod_persona = ? AND condicion = 'CREDITO'",
                    [$idProp, $codPersona]
                );
                Database::update(
                    "INSERT INTO co_persona_asientos (id_propietario, cod_persona, condicion, cta_iva10, cta_iva5, cta_grav10, cta_grav5, cta_exentas, cta_contrapartida)
                     VALUES (?, ?, 'CREDITO', ?, ?, ?, ?, ?, ?)",
                    [
                        $idProp, $codPersona,
                        $credito['ctaIva10']   ?? '01.01.03.05.03.01 - IVA CRÉDITO 10%',
                        $credito['ctaIva5']    ?? '01.01.03.05.03.02 - IVA CRÉDITO 05%',
                        $credito['ctaGrav10']  ?? '01.01.04.01.01 - MERCADERÍAS GRAVADAS POR EL IVA AL 10%',
                        $credito['ctaGrav5']   ?? '01.01.04.01.02 - MERCADERÍAS GRAVADAS POR EL IVA AL 5%',
                        $credito['ctaExentas'] ?? '01.01.04.01.03 - MERCADERÍAS EXENTAS DEL IVA',
                        $credito['ctaContra']  ?? '02.01.01.01 - PROVEEDORES LOCALES [ CORTO PLAZO ]',
                    ]
                );
            }

            View::json(['status' => 'success', 'message' => 'Asientos predefinidos guardados correctamente.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // API CO_CLIENTES (/api/co_clientes/*)
    // =========================================================================
    public static function apiCoClientesVerificarRuc(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $ruc = trim((string)($_GET['ruc'] ?? ''));
        $codCliente = trim((string)($_GET['codCliente'] ?? ''));

        if ($ruc === '') {
            View::json(['status' => 'NOT_FOUND_LOCAL']);
            return;
        }
        $rucClean = $ruc;
        $rucNoDash = str_replace(['-', ' '], '', $rucClean);

        // 1. Buscar en co_clientes
        $cli = Database::queryForMap(
            "SELECT cod_cliente, cod_persona, nombre, ruc, COALESCE(tipo_documento, 'RUC') AS tipo_documento, direccion, telefono, email
             FROM co_clientes
             WHERE id_propietario = ? AND cod_empresa = ?
               AND (UPPER(TRIM(ruc)) = UPPER(?) OR UPPER(REPLACE(ruc, '-', '')) = UPPER(?))
               AND (cod_cliente <> ? OR ? = '')
             LIMIT 1",
            [$idProp, $empresa, $rucClean, $rucNoDash, $codCliente, $codCliente]
        );
        if ($cli) {
            View::json([
                'status'        => 'CLIENTE_EXISTE',
                'codCliente'    => (string)$cli['cod_cliente'],
                'codPersona'    => $cli['cod_persona'] !== null ? (string)$cli['cod_persona'] : null,
                'nombre'        => $cli['nombre'],
                'ruc'           => $cli['ruc'],
                'tipoDocumento' => $cli['tipo_documento'],
                'direccion'     => $cli['direccion'],
                'telefono'      => $cli['telefono'],
                'email'         => $cli['email'],
            ]);
            return;
        }

        // 2. Buscar en personas / ident_personas
        $pers = Database::queryForMap(
            "SELECT p.cod_persona, p.nombre, COALESCE(p.direc_electronica, p.email) AS direc_electronica,
                    COALESCE(p.telefono, (SELECT num_telefono FROM telef_personas tp WHERE tp.id_propietario = p.id_propietario AND tp.cod_persona = p.cod_persona LIMIT 1)) AS telefono,
                    COALESCE(p.direccion, (SELECT detalle FROM direc_personas dp WHERE dp.id_propietario = p.id_propietario AND dp.cod_persona = p.cod_persona LIMIT 1)) AS direccion,
                    COALESCE(p.ruc, (SELECT numero FROM ident_personas ip WHERE ip.id_propietario = p.id_propietario AND ip.cod_persona = p.cod_persona LIMIT 1)) AS ruc_ident
             FROM personas p
             WHERE p.id_propietario = ?
               AND (UPPER(TRIM(COALESCE(p.ruc, ''))) = UPPER(?)
                    OR UPPER(REPLACE(COALESCE(p.ruc, ''), '-', '')) = UPPER(?)
                    OR EXISTS (
                        SELECT 1 FROM ident_personas i
                        WHERE i.id_propietario = p.id_propietario AND i.cod_persona = p.cod_persona
                          AND (UPPER(TRIM(i.numero)) = UPPER(?) OR UPPER(REPLACE(i.numero, '-', '')) = UPPER(?))
                    ))
             LIMIT 1",
            [$idProp, $rucClean, $rucNoDash, $rucClean, $rucNoDash]
        );
        if ($pers) {
            View::json([
                'status'        => 'PERSONA_EXISTE',
                'codPersona'    => (string)$pers['cod_persona'],
                'nombre'        => $pers['nombre'],
                'ruc'           => $pers['ruc_ident'] ?: $rucClean,
                'tipoDocumento' => 'RUC',
                'direccion'     => $pers['direccion'],
                'telefono'      => $pers['telefono'],
                'email'         => $pers['direc_electronica'],
            ]);
            return;
        }

        View::json(['status' => 'NOT_FOUND_LOCAL']);
    }

    public static function apiCoClientesList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $search = trim((string)($_GET['search'] ?? ''));

        try {
            $sql = "SELECT cod_cliente, cod_persona, nombre, ruc,
                           COALESCE(tipo_documento, 'RUC') AS tipo_documento,
                           direccion, telefono, email, estado
                    FROM co_clientes
                    WHERE id_propietario = ? AND cod_empresa = ? ";
            $params = [$idProp, $empresa];
            if ($search !== '') {
                $sql .= "AND (UPPER(nombre) LIKE ? OR UPPER(cod_cliente) LIKE ? OR UPPER(COALESCE(cod_persona, '')) LIKE ? OR UPPER(COALESCE(ruc, '')) LIKE ?) ";
                $s = '%' . strtoupper($search) . '%';
                $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
            }
            $sql .= "ORDER BY CAST(cod_cliente AS UNSIGNED) DESC, cod_cliente DESC";
            $list = Database::queryForListUpper($sql, $params);
            View::json(['status' => 'success', 'data' => $list]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al listar clientes: ' . $e->getMessage()]);
        }
    }

    public static function apiCoClientesSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();

        $codCliente    = trim((string)($payload['codCliente'] ?? ''));
        $nombre        = trim((string)($payload['nombre'] ?? ''));
        $ruc           = trim((string)($payload['ruc'] ?? ''));
        $tipoDocumento = trim((string)($payload['tipoDocumento'] ?? 'RUC')) ?: 'RUC';
        $direccion     = trim((string)($payload['direccion'] ?? ''));
        $telefono      = trim((string)($payload['telefono'] ?? ''));
        $email         = trim((string)($payload['email'] ?? ''));

        if ($nombre === '') {
            View::json(['status' => 'error', 'message' => 'El nombre del cliente es obligatorio.']);
            return;
        }

        try {
            if ($ruc !== '' && strtoupper($tipoDocumento) !== 'INNOMINADO') {
                $rucBase = trim(explode('-', strtoupper($ruc))[0]);
                $existing = Database::queryForMap(
                    "SELECT cod_cliente, nombre FROM co_clientes
                     WHERE id_propietario = ? AND cod_empresa = ?
                       AND UPPER(TRIM(SUBSTRING_INDEX(ruc, '-', 1))) = ?
                       AND cod_cliente <> COALESCE(NULLIF(?, ''), '#')
                     LIMIT 1",
                    [$idProp, $empresa, $rucBase, $codCliente]
                );
                if ($existing) {
                    View::json([
                        'status'  => 'error',
                        'message' => "Operación rechazada: Ya existe un cliente registrado con el N° de Documento / RUC {$ruc} (Cliente #{$existing['cod_cliente']} - {$existing['nombre']})."
                    ]);
                    return;
                }
            }

            $existingCodPersona = null;
            if ($codCliente !== '') {
                $existingCodPersona = Database::queryForValue(
                    "SELECT cod_persona FROM co_clientes WHERE id_propietario = ? AND cod_empresa = ? AND cod_cliente = ? LIMIT 1",
                    [$idProp, $empresa, $codCliente]
                );
            }

            $codPersona = PersonaService::syncPersonaData(
                $existingCodPersona ? (string)$existingCodPersona : null,
                $nombre, $ruc, $tipoDocumento, $direccion, $telefono, $email
            );

            if ($codCliente === '') {
                $nextId = (int)Database::queryForValue(
                    "SELECT COALESCE(MAX(CAST(cod_cliente AS UNSIGNED)), 0) + 1 FROM co_clientes WHERE id_propietario = ? AND CAST(cod_cliente AS UNSIGNED) < 3000",
                    [$idProp]
                );
                $codCliente = (string)max(1, $nextId);

                Database::update(
                    "INSERT INTO co_clientes (id_propietario, cod_cliente, cod_empresa, cod_persona, nombre, ruc, tipo_documento, direccion, telefono, email, estado, usuario_reg)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'A', ?)",
                    [$idProp, $codCliente, $empresa, $codPersona, strtoupper($nombre), $ruc, strtoupper($tipoDocumento), $direccion, $telefono, $email, $usuario]
                );
                $msg = "Cliente registrado correctamente con código {$codCliente}";
            } else {
                Database::update(
                    "UPDATE co_clientes SET cod_persona = ?, nombre = ?, ruc = ?, tipo_documento = ?, direccion = ?, telefono = ?, email = ?
                     WHERE id_propietario = ? AND cod_empresa = ? AND cod_cliente = ?",
                    [$codPersona, strtoupper($nombre), $ruc, strtoupper($tipoDocumento), $direccion, $telefono, $email, $idProp, $empresa, $codCliente]
                );
                $msg = "Cliente {$codCliente} actualizado con éxito.";
            }

            View::json([
                'status'     => 'success',
                'message'    => $msg,
                'codCliente' => $codCliente,
                'codPersona' => $codPersona,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar cliente: ' . $e->getMessage()]);
        }
    }

    public static function apiCoClientesDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $payload = self::getInput();
        $codCliente = trim((string)($payload['codCliente'] ?? ''));

        try {
            Database::update(
                "DELETE FROM co_clientes WHERE id_propietario = ? AND cod_empresa = ? AND cod_cliente = ?",
                [$idProp, $empresa, $codCliente]
            );
            View::json(['status' => 'success', 'message' => 'Cliente eliminado con éxito.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar cliente: ' . $e->getMessage()]);
        }
    }

    public static function apiCoClientesAutoRegistrarRuc(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $usuario = TenantContext::usuario();
        $body = self::getInput();

        $ruc = trim((string)($body['ruc'] ?? ''));
        $nombre = trim((string)($body['nombre'] ?? ''));
        if ($ruc === '') {
            View::json(['status' => 'error', 'message' => 'El RUC es obligatorio.']);
            return;
        }

        $rucClean = $ruc;
        $rucNoDash = str_replace(['-', ' '], '', $rucClean);
        $rucBase = str_contains($rucClean, '-') ? trim(explode('-', $rucClean)[0]) : $rucClean;
        $rucPattern = strlen($rucBase) >= 3 ? $rucBase . '-%' : $rucClean;

        $existingCli = Database::queryForMap(
            "SELECT cod_cliente, cod_persona, nombre, ruc FROM co_clientes
             WHERE id_propietario = ? AND cod_empresa = ? AND ruc IS NOT NULL
               AND (UPPER(TRIM(ruc)) = UPPER(?) OR UPPER(REPLACE(ruc, '-', '')) = UPPER(?) OR UPPER(TRIM(ruc)) = UPPER(?) OR UPPER(TRIM(ruc)) LIKE UPPER(?))
             LIMIT 1",
            [$idProp, $empresa, $rucClean, $rucNoDash, $rucBase, $rucPattern]
        );
        if ($existingCli) {
            View::json([
                'status'     => 'CLIENTE_EXISTE',
                'codCliente' => (string)$existingCli['cod_cliente'],
                'codPersona' => $existingCli['cod_persona'] !== null ? (string)$existingCli['cod_persona'] : null,
                'nombre'     => $existingCli['nombre'],
                'ruc'        => $existingCli['ruc'],
                'message'    => 'El cliente ya existe en la base de datos.',
            ]);
            return;
        }

        $pers = Database::queryForMap(
            "SELECT p.cod_persona, p.nombre, COALESCE(p.direc_electronica, p.email) AS email, p.direccion, p.telefono
             FROM personas p
             WHERE p.id_propietario = ?
               AND (UPPER(TRIM(COALESCE(p.ruc, ''))) = UPPER(?) OR UPPER(TRIM(COALESCE(p.ruc, ''))) LIKE UPPER(?)
                    OR EXISTS (SELECT 1 FROM ident_personas i WHERE i.id_propietario = p.id_propietario AND i.cod_persona = p.cod_persona AND (UPPER(TRIM(i.numero)) = UPPER(?) OR UPPER(TRIM(i.numero)) LIKE UPPER(?))))
             LIMIT 1",
            [$idProp, $rucClean, $rucPattern, $rucClean, $rucPattern]
        );

        $codPersonaFound = $pers ? (string)$pers['cod_persona'] : null;
        $nombreFinal = $nombre !== '' ? strtoupper($nombre) : ($pers ? (string)$pers['nombre'] : '');
        if ($nombreFinal === '') {
            View::json(['status' => 'NOT_FOUND_LOCAL', 'message' => 'RUC no encontrado en la BD local.']);
            return;
        }

        $codPersona = PersonaService::syncPersonaData($codPersonaFound, $nombreFinal, $rucClean, 'RUC', $pers['direccion'] ?? null, $pers['telefono'] ?? null, $pers['email'] ?? null);
        $nextId = (int)Database::queryForValue(
            "SELECT COALESCE(MAX(CAST(cod_cliente AS UNSIGNED)), 0) + 1 FROM co_clientes WHERE id_propietario = ? AND CAST(cod_cliente AS UNSIGNED) < 3000",
            [$idProp]
        );
        $codCliente = (string)max(1, $nextId);

        Database::update(
            "INSERT INTO co_clientes (id_propietario, cod_cliente, cod_empresa, cod_persona, nombre, ruc, tipo_documento, direccion, telefono, email, estado, usuario_reg)
             VALUES (?, ?, ?, ?, ?, ?, 'RUC', ?, ?, ?, 'A', ?)",
            [$idProp, $codCliente, $empresa, $codPersona, strtoupper($nombreFinal), $rucClean, $pers['direccion'] ?? null, $pers['telefono'] ?? null, $pers['email'] ?? null, $usuario]
        );

        View::json([
            'status'     => 'CREADO',
            'codCliente' => $codCliente,
            'codPersona' => $codPersona,
            'nombre'     => strtoupper($nombreFinal),
            'ruc'        => $rucClean,
            'message'    => "Registrado exitosamente como Persona y Cliente #{$codCliente}",
        ]);
    }

    // =========================================================================
    // API CO_PROVEEDORES (/api/co_proveedores/*)
    // =========================================================================
    public static function apiCoProveedoresList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $search = trim((string)($_GET['search'] ?? ''));

        try {
            $sql = "SELECT cod_proveedor, cod_persona, nombre, ruc,
                           COALESCE(tipo_documento, 'RUC') AS tipo_documento,
                           direccion, telefono, email, estado
                    FROM co_proveedores
                    WHERE id_propietario = ? AND cod_empresa = ? ";
            $params = [$idProp, $empresa];
            if ($search !== '') {
                $sql .= "AND (UPPER(nombre) LIKE ? OR UPPER(cod_proveedor) LIKE ? OR UPPER(COALESCE(cod_persona, '')) LIKE ? OR UPPER(COALESCE(ruc, '')) LIKE ?) ";
                $s = '%' . strtoupper($search) . '%';
                $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
            }
            $sql .= "ORDER BY CAST(cod_proveedor AS UNSIGNED) DESC, cod_proveedor DESC";
            $list = Database::queryForListUpper($sql, $params);
            View::json(['status' => 'success', 'data' => $list]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al listar proveedores: ' . $e->getMessage()]);
        }
    }

    public static function apiCoProveedoresAutoRegistrarRuc(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $usuario = TenantContext::usuario();
        $body = self::getInput();

        $ruc = trim((string)($body['ruc'] ?? ''));
        $nombreInput = trim((string)($body['nombre'] ?? ''));
        if ($ruc === '') {
            View::json(['status' => 'error', 'message' => 'RUC no proporcionado']);
            return;
        }

        $rucClean = $ruc;
        $rucNoDash = str_replace(['-', ' '], '', $rucClean);
        $rucBase = str_contains($rucClean, '-') ? trim(explode('-', $rucClean)[0]) : $rucClean;
        $rucPattern = strlen($rucBase) >= 3 ? $rucBase . '-%' : $rucClean;

        $prov = Database::queryForMap(
            "SELECT cod_proveedor, cod_persona, nombre, ruc, COALESCE(tipo_documento, 'RUC') AS tipo_documento, direccion, telefono, email
             FROM co_proveedores
             WHERE id_propietario = ? AND cod_empresa = ?
               AND (UPPER(TRIM(cod_proveedor)) = UPPER(?) OR UPPER(TRIM(ruc)) = UPPER(?) OR UPPER(REPLACE(ruc, '-', '')) = UPPER(?) OR UPPER(TRIM(ruc)) = UPPER(?) OR UPPER(TRIM(ruc)) LIKE UPPER(?))
             LIMIT 1",
            [$idProp, $empresa, $rucClean, $rucClean, $rucNoDash, $rucBase, $rucPattern]
        );
        if ($prov) {
            View::json([
                'status'        => 'PROVEEDOR_EXISTE',
                'codProveedor'  => (string)$prov['cod_proveedor'],
                'codPersona'    => $prov['cod_persona'] !== null ? (string)$prov['cod_persona'] : null,
                'nombre'        => $prov['nombre'],
                'ruc'           => $prov['ruc'],
                'tipoDocumento' => $prov['tipo_documento'],
                'direccion'     => $prov['direccion'],
                'telefono'      => $prov['telefono'],
                'email'         => $prov['email'],
            ]);
            return;
        }

        $pers = Database::queryForMap(
            "SELECT p.cod_persona, p.nombre, COALESCE(p.direc_electronica, p.email) AS email, p.direccion, p.telefono
             FROM personas p
             WHERE p.id_propietario = ?
               AND (UPPER(TRIM(COALESCE(p.ruc, ''))) = UPPER(?) OR UPPER(TRIM(COALESCE(p.ruc, ''))) LIKE UPPER(?)
                    OR EXISTS (SELECT 1 FROM ident_personas i WHERE i.id_propietario = p.id_propietario AND i.cod_persona = p.cod_persona AND (UPPER(TRIM(i.numero)) = UPPER(?) OR UPPER(TRIM(i.numero)) LIKE UPPER(?))))
             LIMIT 1",
            [$idProp, $rucClean, $rucPattern, $rucClean, $rucPattern]
        );

        $codPersonaFound = $pers ? (string)$pers['cod_persona'] : null;
        $nombreFinal = $nombreInput !== '' ? strtoupper($nombreInput) : ($pers ? (string)$pers['nombre'] : '');
        if ($nombreFinal === '') {
            View::json(['status' => 'NOT_FOUND_LOCAL']);
            return;
        }

        $codPersona = PersonaService::syncPersonaData($codPersonaFound, $nombreFinal, $rucClean, 'RUC', $pers['direccion'] ?? null, $pers['telefono'] ?? null, $pers['email'] ?? null);
        $nextId = (int)Database::queryForValue(
            "SELECT COALESCE(MAX(CAST(cod_proveedor AS UNSIGNED)), 0) + 1 FROM co_proveedores WHERE id_propietario = ? AND CAST(cod_proveedor AS UNSIGNED) < 3000",
            [$idProp]
        );
        $codProveedor = (string)max(1, $nextId);

        Database::update(
            "INSERT INTO co_proveedores (id_propietario, cod_proveedor, cod_empresa, cod_persona, nombre, ruc, tipo_documento, direccion, telefono, email, estado, usuario_reg)
             VALUES (?, ?, ?, ?, ?, ?, 'RUC', ?, ?, ?, 'A', ?)",
            [$idProp, $codProveedor, $empresa, $codPersona, strtoupper($nombreFinal), $rucClean, $pers['direccion'] ?? null, $pers['telefono'] ?? null, $pers['email'] ?? null, $usuario]
        );

        View::json([
            'status'       => 'CREADO',
            'codProveedor' => $codProveedor,
            'codPersona'   => $codPersona,
            'nombre'       => strtoupper($nombreFinal),
            'ruc'          => $rucClean,
        ]);
    }

    public static function apiCoProveedoresSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();

        $codProveedor  = trim((string)($payload['codProveedor'] ?? ''));
        $nombre        = trim((string)($payload['nombre'] ?? ''));
        $ruc           = trim((string)($payload['ruc'] ?? ''));
        $tipoDocumento = trim((string)($payload['tipoDocumento'] ?? 'RUC')) ?: 'RUC';
        $direccion     = trim((string)($payload['direccion'] ?? ''));
        $telefono      = trim((string)($payload['telefono'] ?? ''));
        $email         = trim((string)($payload['email'] ?? ''));

        if ($nombre === '') {
            View::json(['status' => 'error', 'message' => 'El nombre o Razón Social del proveedor es obligatorio.']);
            return;
        }

        try {
            if ($ruc !== '' && strtoupper($tipoDocumento) !== 'INNOMINADO') {
                $rucBase = trim(explode('-', strtoupper($ruc))[0]);
                $existing = Database::queryForMap(
                    "SELECT cod_proveedor, nombre FROM co_proveedores
                     WHERE id_propietario = ? AND cod_empresa = ?
                       AND UPPER(TRIM(SUBSTRING_INDEX(ruc, '-', 1))) = ?
                       AND cod_proveedor <> COALESCE(NULLIF(?, ''), '#')
                     LIMIT 1",
                    [$idProp, $empresa, $rucBase, $codProveedor]
                );
                if ($existing) {
                    View::json([
                        'status'  => 'error',
                        'message' => "Operación rechazada: Ya existe un proveedor registrado con el N° de Documento / RUC {$ruc} (Proveedor #{$existing['cod_proveedor']} - {$existing['nombre']})."
                    ]);
                    return;
                }
            }

            $existingCodPersona = null;
            if ($codProveedor !== '') {
                $existingCodPersona = Database::queryForValue(
                    "SELECT cod_persona FROM co_proveedores WHERE id_propietario = ? AND cod_empresa = ? AND cod_proveedor = ? LIMIT 1",
                    [$idProp, $empresa, $codProveedor]
                );
            }

            $codPersona = PersonaService::syncPersonaData(
                $existingCodPersona ? (string)$existingCodPersona : null,
                $nombre, $ruc, $tipoDocumento, $direccion, $telefono, $email
            );

            if ($codProveedor === '') {
                $nextId = (int)Database::queryForValue(
                    "SELECT COALESCE(MAX(CAST(cod_proveedor AS UNSIGNED)), 0) + 1 FROM co_proveedores WHERE id_propietario = ? AND CAST(cod_proveedor AS UNSIGNED) < 3000",
                    [$idProp]
                );
                $codProveedor = (string)max(1, $nextId);

                Database::update(
                    "INSERT INTO co_proveedores (id_propietario, cod_proveedor, cod_empresa, cod_persona, nombre, ruc, tipo_documento, direccion, telefono, email, estado, usuario_reg)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'A', ?)",
                    [$idProp, $codProveedor, $empresa, $codPersona, strtoupper($nombre), $ruc, strtoupper($tipoDocumento), $direccion, $telefono, $email, $usuario]
                );
                $msg = "Proveedor registrado correctamente con código {$codProveedor}";
            } else {
                Database::update(
                    "UPDATE co_proveedores SET cod_persona = ?, nombre = ?, ruc = ?, tipo_documento = ?, direccion = ?, telefono = ?, email = ?
                     WHERE id_propietario = ? AND cod_empresa = ? AND cod_proveedor = ?",
                    [$codPersona, strtoupper($nombre), $ruc, strtoupper($tipoDocumento), $direccion, $telefono, $email, $idProp, $empresa, $codProveedor]
                );
                $msg = "Proveedor {$codProveedor} actualizado con éxito.";
            }

            View::json([
                'status'       => 'success',
                'message'      => $msg,
                'codProveedor' => $codProveedor,
                'codPersona'   => $codPersona,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar proveedor: ' . $e->getMessage()]);
        }
    }

    public static function apiCoProveedoresDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $payload = self::getInput();
        $codProveedor = trim((string)($payload['codProveedor'] ?? ''));

        try {
            Database::update(
                "DELETE FROM co_proveedores WHERE id_propietario = ? AND cod_empresa = ? AND cod_proveedor = ?",
                [$idProp, $empresa, $codProveedor]
            );
            View::json(['status' => 'success', 'message' => 'Proveedor eliminado con éxito.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar proveedor: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API CO_SUCURSALES (/api/co_sucursales/*)
    // =========================================================================
    public static function apiCoSucursalesList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $search = trim((string)($_GET['search'] ?? ''));

        try {
            $sql = "SELECT cod_sucursal, descripcion, COALESCE(detalle_dir, direccion) AS direccion,
                           telefono, COALESCE(estado, 'A') AS estado
                    FROM sucursales
                    WHERE id_propietario = ? AND cod_empresa = ? ";
            $params = [$idProp, $empresa];
            if ($search !== '') {
                $sql .= "AND (UPPER(descripcion) LIKE ? OR cod_sucursal = ?) ";
                $params[] = '%' . strtoupper($search) . '%';
                $params[] = $search;
            }
            $sql .= "ORDER BY CAST(cod_sucursal AS UNSIGNED) ASC, cod_sucursal ASC";
            $list = Database::queryForListUpper($sql, $params);
            View::json(['status' => 'success', 'data' => $list]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al listar sucursales: ' . $e->getMessage()]);
        }
    }

    public static function apiCoSucursalesSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $payload = self::getInput();

        $codSucursal = trim((string)($payload['codSucursal'] ?? ''));
        $descripcion = trim((string)($payload['descripcion'] ?? ''));
        $direccion   = trim((string)($payload['direccion'] ?? ''));
        $telefono    = trim((string)($payload['telefono'] ?? ''));

        if ($descripcion === '') {
            View::json(['status' => 'error', 'message' => 'La descripción de la sucursal es obligatoria.']);
            return;
        }

        try {
            if ($codSucursal === '') {
                $nextId = (int)Database::queryForValue(
                    "SELECT COALESCE(MAX(CAST(cod_sucursal AS UNSIGNED)), 0) + 1 FROM sucursales WHERE id_propietario = ? AND cod_empresa = ?",
                    [$idProp, $empresa]
                );
                $codSucursal = (string)max(1, $nextId);

                Database::update(
                    "INSERT INTO sucursales (id_propietario, cod_sucursal, cod_empresa, descripcion, detalle_dir, direccion, telefono, estado)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'A')",
                    [$idProp, $codSucursal, $empresa, strtoupper($descripcion), $direccion, $direccion, $telefono]
                );
                $msg = "Sucursal registrada correctamente con código {$codSucursal}";
            } else {
                Database::update(
                    "UPDATE sucursales SET descripcion = ?, detalle_dir = ?, direccion = ?, telefono = ?
                     WHERE id_propietario = ? AND cod_empresa = ? AND cod_sucursal = ?",
                    [strtoupper($descripcion), $direccion, $direccion, $telefono, $idProp, $empresa, $codSucursal]
                );
                $msg = "Sucursal {$codSucursal} actualizada con éxito.";
            }

            View::json([
                'status'      => 'success',
                'message'     => $msg,
                'codSucursal' => $codSucursal,
            ]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar sucursal: ' . $e->getMessage()]);
        }
    }

    public static function apiCoSucursalesDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $payload = self::getInput();
        $codSucursal = trim((string)($payload['codSucursal'] ?? ''));

        try {
            Database::update(
                "DELETE FROM sucursales WHERE id_propietario = ? AND cod_empresa = ? AND cod_sucursal = ?",
                [$idProp, $empresa, $codSucursal]
            );
            View::json(['status' => 'success', 'message' => 'Sucursal eliminada con éxito.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar sucursal: ' . $e->getMessage()]);
        }
    }
}
