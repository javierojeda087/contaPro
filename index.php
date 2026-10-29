<?php
declare(strict_types=1);

// 1. Servir archivos estáticos directamente cuando se ejecuta con `php -S`
if (PHP_SAPI === 'cli-server') {
    $urlPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (preg_match('#^/(config|core|app/(Controllers|Models|Services|Helpers)|database)/|\.(sql|key|p12|env|md|sh|bak|zip|bat|yml|yaml|log|ini)$#i', $urlPath)) {
        http_response_code(403);
        exit('403 Forbidden');
    }
    $staticFile = __DIR__ . $urlPath;
    if ($urlPath !== '/' && is_file($staticFile)) {
        return false;
    }
}

// 2. Configuración regional, codificación UTF-8 y seguridad de sesión HTTPS
@ini_set('display_errors', '0');
ob_start();
mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Asuncion');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') == 443)
    || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// 3. Autoloader ligero para namespaces Core\* y App\*
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'Core\\' => __DIR__ . '/core/',
        'App\\'  => __DIR__ . '/app/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

use Core\DatabaseMigrator;
use Core\RouteHelper;
use Core\TenantContext;
use Core\View;
use App\Controllers\AuthController;
use App\Controllers\SuperAdminController;
use App\Controllers\AdminController;
use App\Controllers\DirectoriosContablesController;
use App\Controllers\DefinicionesContablesController;
use App\Controllers\MovimientosContablesController;
use App\Controllers\ReportesContablesController;

// 4. Ejecutar migraciones automáticas de BD si hay versión nueva
DatabaseMigrator::runIfNeeded();

// 5. Obtener ruta y método HTTP (compatible con dominio propio y URL temporal de cPanel /~usuario)
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$rawUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePrefix = '';
if (preg_match('#^(/~[a-zA-Z0-9_]+)(/.*)?$#', $rawUri, $m)) {
    $basePrefix = $m[1];
    $rawUri     = $m[2] ?? '/';
}
if (preg_match('#^/index\.php(/.*)?$#', $rawUri, $m2)) {
    $rawUri = $m2[1] ?? '/';
}
$GLOBALS['BASE_PREFIX'] = $basePrefix;
$uri = rtrim($rawUri, '/') ?: '/';

if ($basePrefix !== '') {
    header_register_callback(function () use ($basePrefix): void {
        foreach (headers_list() as $hdr) {
            if (stripos($hdr, 'Location: /') === 0 && stripos($hdr, 'Location: ' . $basePrefix . '/') !== 0) {
                $target = trim(substr($hdr, 9));
                header('Location: ' . $basePrefix . $target, true);
            }
        }
    });
    ob_start(function (string $buf) use ($basePrefix): string {
        // Solo reescribir respuestas HTML
        if (stripos($buf, '<html') === false && stripos($buf, '<!DOCTYPE') === false) {
            return $buf;
        }
        $p = $basePrefix;
        $hook = "<script>(function(){var P='{$p}';window.CTX=P;var oF=window.fetch;if(oF){window.fetch=function(u,o){if(typeof u==='string'&&u.charAt(0)==='/'&&u.indexOf(P+'/')!==0){u=P+u;}return oF.call(this,u,o);};}var oX=XMLHttpRequest.prototype.open;XMLHttpRequest.prototype.open=function(m,u){if(typeof u==='string'&&u.charAt(0)==='/'&&u.indexOf(P+'/')!==0){arguments[1]=P+u;}return oX.apply(this,arguments);};})();</script>";
        $buf = preg_replace('/<head(\b[^>]*)>/i', '<head$1>' . $hook, $buf, 1);
        $buf = preg_replace('#(href|src|action)=(["\'])/(?!~|/)#i', '$1=$2' . $p . '/', $buf);
        $buf = preg_replace('#url\((["\']?)/(?!~|/)#i', 'url($1' . $p . '/', $buf);
        $buf = str_replace(
            ["window.location.href = '/", "window.location.href='/", "window.location.replace('/", "location.href = '/", "location.href='/"],
            ["window.location.href = '{$p}/", "window.location.href='{$p}/", "window.location.replace('{$p}/", "location.href = '{$p}/", "location.href='{$p}/"],
            $buf
        );
        return $buf;
    });
}

// ============================================================================
// RUTAS PÚBLICAS Y DE AUTENTICACIÓN
// ============================================================================
if ($uri === '/' || $uri === '/inicio' || $uri === '/index.php') {
    AuthController::landing();
    exit;
}

if ($uri === '/login') {
    AuthController::showLogin();
    exit;
}

if ($uri === '/api/login/verify' && $method === 'POST') {
    View::json(AuthController::verifyCredentials());
    exit;
}

if ($uri === '/api/login/empresas') {
    View::json(AuthController::getEmpresasLogin());
    exit;
}

if ($uri === '/api/login/empresa-detalles') {
    View::json(AuthController::getEmpresaDetalles());
    exit;
}

if ($uri === '/api/login/confirm' && $method === 'POST') {
    View::json(AuthController::confirmLogin());
    exit;
}

if ($uri === '/cambiar-contribuyente') {
    AuthController::cambiarContribuyente();
    exit;
}

if ($uri === '/api/session/switch-context' && $method === 'POST') {
    View::json(AuthController::switchContext());
    exit;
}

if ($uri === '/logout') {
    AuthController::logout();
    exit;
}

if ($uri === '/menu') {
    AuthController::menu();
    exit;
}

if ($uri === '/api/session/status') {
    View::json([
        'active'  => TenantContext::check(),
        'usuario' => TenantContext::usuario(),
        'empresa' => TenantContext::empresa(),
    ]);
    exit;
}

if ($uri === '/api/session/keep-alive') {
    View::json(['status' => 'ok', 'active' => TenantContext::check()]);
    exit;
}

// ============================================================================
// RUTAS DE SUPERADMIN (CONTROL DE PROPIETARIOS Y PLANES)
// ============================================================================
if ($uri === '/superadmin' || $uri === '/app/SUPERADMIN') {
    SuperAdminController::view();
    exit;
}
if ($uri === '/api/superadmin/data') {
    View::json(SuperAdminController::getData());
    exit;
}
if (($uri === '/api/superadmin/plan/save' || $uri === '/api/superadmin/planes/save') && $method === 'POST') {
    View::json(SuperAdminController::savePlan());
    exit;
}
if (($uri === '/api/superadmin/propietario/save' || $uri === '/api/superadmin/propietarios/save') && $method === 'POST') {
    View::json(SuperAdminController::savePropietario());
    exit;
}
if ($uri === '/api/superadmin/propietario/renovar' && $method === 'POST') {
    View::json(SuperAdminController::renovarSuscripcion());
    exit;
}
if ($uri === '/api/superadmin/propietario/toggle-estado' && $method === 'POST') {
    View::json(SuperAdminController::toggleEstadoPropietario());
    exit;
}
if ($uri === '/api/superadmin/migrar-bd' && $method === 'POST') {
    if (!TenantContext::esSuperAdmin()) {
        View::json(['status' => 'error', 'message' => 'No autorizado'], 403);
        exit;
    }
    DatabaseMigrator::runIfNeeded();
    View::json(['status' => 'success', 'message' => 'Migración de BD verificada y aplicada.']);
    exit;
}

// ============================================================================
// RUTAS DE IMPRESIÓN Y EXPORTACIÓN DIRECTA DE REPORTES
// ============================================================================
if ($uri === '/app/libro-compras/print' || $uri === '/libro_compras/print') {
    ReportesContablesController::printLibroCompras();
    exit;
}
if ($uri === '/api/reportes/libro-compras/excel' || $uri === '/app/libro-compras/excel') {
    ReportesContablesController::exportarExcelLibroCompras();
    exit;
}
if ($uri === '/app/libro-ventas/print' || $uri === '/libro_ventas/print') {
    ReportesContablesController::printLibroVentas();
    exit;
}
if ($uri === '/api/reportes/libro-ventas/excel' || $uri === '/app/libro-ventas/excel') {
    ReportesContablesController::exportarExcelLibroVentas();
    exit;
}
if ($uri === '/app/resumen-irp/print' || $uri === '/resumen_irp/print' || $uri === '/reportes/resumen-irp/imprimir') {
    ReportesContablesController::printResumenIrp();
    exit;
}
if ($uri === '/api/reportes/resumen-irp/excel' || $uri === '/app/resumen-irp/excel' || $uri === '/reportes/resumen-irp/excel') {
    ReportesContablesController::exportarExcelResumenIrp();
    exit;
}
if ($uri === '/app/formulario-120/print' || $uri === '/formulario_120/print') {
    ReportesContablesController::printFormulario120();
    exit;
}
if ($uri === '/api/formulario-120/exportar-xls' || $uri === '/app/formulario-120/exportar-xls') {
    ReportesContablesController::exportarXlsFormulario120();
    exit;
}
if ($uri === '/app/kude/print' || $uri === '/kude/print') {
    MovimientosContablesController::pagePrintKude();
    exit;
}

// ============================================================================
// RUTAS DE VISTAS /app/{forma}
// ============================================================================
if (preg_match('#^/app/([^/]+)$#', $uri, $m)) {
    $slug = $m[1];
    $upper = strtoupper($slug);

    // Resolver por código de forma o por slug amigable
    $formaMap = [
        // Administración (AD)
        'ADCONTRI'              => [AdminController::class, 'pageContribuyentes'],
        'CONTRIBUYENTES'        => [AdminController::class, 'pageContribuyentes'],
        'BSEMPRES'              => [AdminController::class, 'pageEmpresas'],
        'EMPRESAS'              => [AdminController::class, 'pageEmpresas'],
        'COACTIVI'              => [AdminController::class, 'pageActividades'],
        'ACTIVIDADES'           => [AdminController::class, 'pageActividades'],
        'CO_ACTIVIDADES'        => [AdminController::class, 'pageActividades'],
        'BSUSUARI'              => [AdminController::class, 'pageUsuarios'],
        'USUARIOS'              => [AdminController::class, 'pageUsuarios'],
        'BSFORMAS'              => [AdminController::class, 'pageFormas'],
        'FORMAS'                => [AdminController::class, 'pageFormas'],
        'BSGRPUSU'              => [AdminController::class, 'pageAccesosGrupos'],
        'ACCESOS_GRUPOS'        => [AdminController::class, 'pageAccesosGrupos'],
        'ACCESOS-GRUPOS'        => [AdminController::class, 'pageAccesosGrupos'],
        'CAMCLAVE'              => [AdminController::class, 'pageCambiarClave'],
        'CAMBIAR-CLAVE'         => [AdminController::class, 'pageCambiarClave'],
        'CAMBIAR_CLAVE'         => [AdminController::class, 'pageCambiarClave'],
        'ADBACKUP'              => [AdminController::class, 'pageBackup'],
        'BACKUP'                => [AdminController::class, 'pageBackup'],

        // Directorios Contables (CO)
        'COPERSON'              => [DirectoriosContablesController::class, 'pageCoPersonas'],
        'CO-PERSONAS'           => [DirectoriosContablesController::class, 'pageCoPersonas'],
        'CO_PERSONAS'           => [DirectoriosContablesController::class, 'pageCoPersonas'],
        'BSPERSON'              => [DirectoriosContablesController::class, 'pagePersonas'],
        'PERSONAS'              => [DirectoriosContablesController::class, 'pagePersonas'],
        'COCLIENT'              => [DirectoriosContablesController::class, 'pageCoClientes'],
        'CO-CLIENTES'           => [DirectoriosContablesController::class, 'pageCoClientes'],
        'CO_CLIENTES'           => [DirectoriosContablesController::class, 'pageCoClientes'],
        'COPROVEE'              => [DirectoriosContablesController::class, 'pageCoProveedores'],
        'CO-PROVEEDORES'        => [DirectoriosContablesController::class, 'pageCoProveedores'],
        'CO_PROVEEDORES'        => [DirectoriosContablesController::class, 'pageCoProveedores'],
        'COSUCURS'              => [DirectoriosContablesController::class, 'pageCoSucursales'],
        'CO-SUCURSALES'         => [DirectoriosContablesController::class, 'pageCoSucursales'],
        'CO_SUCURSALES'         => [DirectoriosContablesController::class, 'pageCoSucursales'],

        // Definiciones Contables (CO)
        'COEJERCI'              => [DefinicionesContablesController::class, 'pageEjercicios'],
        'EJERCICIOS'            => [DefinicionesContablesController::class, 'pageEjercicios'],
        'EJERCICIOS_FISCALES'   => [DefinicionesContablesController::class, 'pageEjercicios'],
        'BSMONEDA'              => [DefinicionesContablesController::class, 'pageMonedas'],
        'MONEDAS'               => [DefinicionesContablesController::class, 'pageMonedas'],
        'BSCOTIZA'              => [DefinicionesContablesController::class, 'pageCotizaciones'],
        'COTIZACIONES'          => [DefinicionesContablesController::class, 'pageCotizaciones'],
        'COPLANCT'              => [DefinicionesContablesController::class, 'pagePlanCuentas'],
        'PLAN-CUENTAS'          => [DefinicionesContablesController::class, 'pagePlanCuentas'],
        'PLAN_CUENTAS'          => [DefinicionesContablesController::class, 'pagePlanCuentas'],
        'COCLASGA'              => [DefinicionesContablesController::class, 'pageClasificacionGasto'],
        'CLASIFICACION-GASTO'   => [DefinicionesContablesController::class, 'pageClasificacionGasto'],
        'CLASIFICACION_GASTO'   => [DefinicionesContablesController::class, 'pageClasificacionGasto'],
        'COTIPEGR'              => [DefinicionesContablesController::class, 'pageTipoEgreso'],
        'TIPO-EGRESO'           => [DefinicionesContablesController::class, 'pageTipoEgreso'],
        'TIPO_EGRESO'           => [DefinicionesContablesController::class, 'pageTipoEgreso'],
        'COTIPING'              => [DefinicionesContablesController::class, 'pageTipoIngreso'],
        'TIPO-INGRESO'          => [DefinicionesContablesController::class, 'pageTipoIngreso'],
        'TIPO_INGRESO'          => [DefinicionesContablesController::class, 'pageTipoIngreso'],
        'COTIMBRA'              => [DefinicionesContablesController::class, 'pageTimbrados'],
        'TIMBRADOS'             => [DefinicionesContablesController::class, 'pageTimbrados'],
        'COTIPCOM'              => [DefinicionesContablesController::class, 'pageCoTiposComprobantes'],
        'CO-TIPOS-COMPROBANTES' => [DefinicionesContablesController::class, 'pageCoTiposComprobantes'],
        'CO_TIPOS_COMPROBANTES' => [DefinicionesContablesController::class, 'pageCoTiposComprobantes'],
        'COREGIME'              => [DefinicionesContablesController::class, 'pageRegimen'],
        'REGIMEN'               => [DefinicionesContablesController::class, 'pageRegimen'],

        // Movimientos Contables (CO)
        'COEGRES'               => [MovimientosContablesController::class, 'pageEgresos'],
        'EGRESOS'               => [MovimientosContablesController::class, 'pageEgresos'],
        'EGRESOS_REGISTRO'      => [MovimientosContablesController::class, 'pageEgresos'],
        'COINGRES'              => [MovimientosContablesController::class, 'pageIngresos'],
        'INGRESOS'              => [MovimientosContablesController::class, 'pageIngresos'],
        'INGRESOS_REGISTRO'     => [MovimientosContablesController::class, 'pageIngresos'],
        'CORECIBO'              => [MovimientosContablesController::class, 'pageRecibosCobro'],
        'RECIBOS'               => [MovimientosContablesController::class, 'pageRecibosCobro'],
        'RECIBOS-COBRO'         => [MovimientosContablesController::class, 'pageRecibosCobro'],
        'RECIBOS_COBRO'         => [MovimientosContablesController::class, 'pageRecibosCobro'],
        'CORECIBOPAGO'          => [MovimientosContablesController::class, 'pageRecibosPago'],
        'CORECPAG'              => [MovimientosContablesController::class, 'pageRecibosPago'],
        'RECIBOS-PAGO'          => [MovimientosContablesController::class, 'pageRecibosPago'],
        'RECIBOS_PAGO'          => [MovimientosContablesController::class, 'pageRecibosPago'],
        'COASIENT'              => [MovimientosContablesController::class, 'pageAsientos'],
        'COASIEN2'              => [MovimientosContablesController::class, 'pageAsientos'],
        'ASIENTOS'              => [MovimientosContablesController::class, 'pageAsientos'],
        'ASIENTOS-CONTABLES'    => [MovimientosContablesController::class, 'pageAsientos'],

        // Reportes Contables (CO)
        'COLIBCOM'              => [ReportesContablesController::class, 'pageLibroCompras'],
        'LIBRO-COMPRAS'         => [ReportesContablesController::class, 'pageLibroCompras'],
        'LIBRO_COMPRAS'         => [ReportesContablesController::class, 'pageLibroCompras'],
        'COLIBVEN'              => [ReportesContablesController::class, 'pageLibroVentas'],
        'LIBRO-VENTAS'          => [ReportesContablesController::class, 'pageLibroVentas'],
        'LIBRO_VENTAS'          => [ReportesContablesController::class, 'pageLibroVentas'],
        'COLIBDIA'              => [ReportesContablesController::class, 'pageLibroDiario'],
        'LIBRO-DIARIO'          => [ReportesContablesController::class, 'pageLibroDiario'],
        'LIBRO_DIARIO'          => [ReportesContablesController::class, 'pageLibroDiario'],
        'REPORTE-LIBRO-DIARIO'  => [ReportesContablesController::class, 'pageLibroDiario'],
        'COLIBMAY'              => [ReportesContablesController::class, 'pageLibroMayor'],
        'LIBRO-MAYOR'           => [ReportesContablesController::class, 'pageLibroMayor'],
        'LIBRO_MAYOR'           => [ReportesContablesController::class, 'pageLibroMayor'],
        'REPORTE-LIBRO-MAYOR'   => [ReportesContablesController::class, 'pageLibroMayor'],
        'COMARANG'              => [ReportesContablesController::class, 'pageExportarMarangatu'],
        'EXPORTAR-MARANGATU'    => [ReportesContablesController::class, 'pageExportarMarangatu'],
        'EXPORTAR_MARANGATU'    => [ReportesContablesController::class, 'pageExportarMarangatu'],
        'MARANGATU'             => [ReportesContablesController::class, 'pageExportarMarangatu'],
        'COF12000'              => [ReportesContablesController::class, 'pageFormulario120'],
        'FORMULARIO-120'        => [ReportesContablesController::class, 'pageFormulario120'],
        'FORMULARIO_120'        => [ReportesContablesController::class, 'pageFormulario120'],
        'CORESCOM'              => [ReportesContablesController::class, 'pageResumenComparativo'],
        'RESUMEN-COMPARATIVO'   => [ReportesContablesController::class, 'pageResumenComparativo'],
        'RESUMEN_COMPARATIVO'   => [ReportesContablesController::class, 'pageResumenComparativo'],
        'CORESIRP'              => [ReportesContablesController::class, 'pageResumenIrp'],
        'RESUMEN-IRP'           => [ReportesContablesController::class, 'pageResumenIrp'],
        'RESUMEN_IRP'           => [ReportesContablesController::class, 'pageResumenIrp'],
    ];

    if (isset($formaMap[$upper])) {
        call_user_func($formaMap[$upper]);
        exit;
    }

    // Fallback: si coincide con alguna plantilla html existente
    $templateName = RouteHelper::resolveTemplate($upper);
    if ($templateName) {
        if (!TenantContext::check()) {
            header('Location: /login');
            exit;
        }
        $menu = \App\Services\MenuService::obtenerMenuPorUsuario(TenantContext::usuario(), TenantContext::grupo());
        View::renderTemplate($templateName, [
            'usuario'    => TenantContext::usuario(),
            'empresa'    => TenantContext::empresa(),
            'sucursal'   => TenantContext::sucursal(),
            'nomEmpresa' => TenantContext::nomEmpresa(),
            'ejercicio'  => TenantContext::ejercicio(),
            'modulos'    => $menu,
        ]);
        exit;
    }

    // Fallback para formularios creados dinámicamente en la tabla `formas` que aún no tengan vista personalizada
    $formaRow = \Core\Database::queryForRow(
        "SELECT nom_forma, cod_modulo, titulo, descripcion, tipo
         FROM formas
         WHERE UPPER(TRIM(nom_forma)) = ?
         ORDER BY CASE WHEN id_propietario = ? THEN 0 ELSE 1 END, id_propietario ASC
         LIMIT 1",
        [$upper, TenantContext::idPropietario()]
    );
    if ($formaRow) {
        if (!TenantContext::check()) {
            header('Location: /login');
            exit;
        }
        $menu = \App\Services\MenuService::obtenerMenuPorUsuario(TenantContext::usuario(), TenantContext::grupo());
        View::renderTemplate('forma_generica', [
            'usuario'        => TenantContext::usuario(),
            'empresa'        => TenantContext::empresa(),
            'sucursal'       => TenantContext::sucursal(),
            'nomEmpresa'     => TenantContext::nomEmpresa(),
            'ejercicio'      => TenantContext::ejercicio(),
            'modulos'        => $menu,
            'formaNom'       => strtoupper(trim((string)($formaRow['nom_forma'] ?? $upper))),
            'formaTitulo'    => trim((string)($formaRow['titulo'] ?? $upper)),
            'formaDesc'      => trim((string)($formaRow['descripcion'] ?? '')),
            'formaModulo'    => strtoupper(trim((string)($formaRow['cod_modulo'] ?? 'CO'))),
            'formaTipo'      => strtoupper(trim((string)($formaRow['tipo'] ?? 'D'))),
        ]);
        exit;
    }
}

// ============================================================================
// RUTAS API (/api/*)
// ============================================================================

// --- Global Search ---
if ($uri === '/api/global/search') {
    AdminController::apiGlobalSearch();
    exit;
}

// --- Administración: Contribuyentes / Empresas / Actividades / Usuarios / Backup ---
$exactApiRoutes = [
    // Contribuyentes & Empresas
    '/api/contribuyentes/list'          => [AdminController::class, 'apiContribuyentesList'],
    '/api/contribuyentes/verificar-ruc' => [AdminController::class, 'apiContribuyentesVerificarRuc'],
    '/api/contribuyentes/save'          => [AdminController::class, 'apiContribuyentesSave'],
    '/api/contribuyentes/delete'        => [AdminController::class, 'apiContribuyentesDelete'],
    '/api/empresa/info'                 => [AdminController::class, 'apiEmpresaInfo'],
    '/api/empresas/list'                => [AdminController::class, 'apiEmpresasList'],
    '/api/empresas/save'                => [AdminController::class, 'apiEmpresasSave'],
    '/api/empresas/delete'              => [AdminController::class, 'apiEmpresasDelete'],

    // Actividades Económicas
    '/api/actividades/list'             => [AdminController::class, 'apiActividadesList'],
    '/api/actividades/save'             => [AdminController::class, 'apiActividadesSave'],
    '/api/actividades/delete'           => [AdminController::class, 'apiActividadesDelete'],

    // Usuarios & Cambio de Clave
    '/api/usuarios/list'                => [AdminController::class, 'apiUsuariosList'],
    '/api/usuarios/save'                => [AdminController::class, 'apiUsuariosSave'],
    '/api/usuarios/update'              => [AdminController::class, 'apiUsuariosUpdate'],
    '/api/usuarios/toggle-status'       => [AdminController::class, 'apiUsuariosToggleStatus'],
    '/api/usuarios/delete'              => [AdminController::class, 'apiUsuariosDelete'],
    '/api/usuarios/empresas'            => [AdminController::class, 'apiUsuariosEmpresas'],
    '/api/usuarios/personas-search'     => [AdminController::class, 'apiUsuariosPersonasSearch'],
    '/api/usuarios/personas/search'     => [AdminController::class, 'apiUsuariosPersonasSearch'],
    '/api/usuarios/sucursales'          => [AdminController::class, 'apiUsuariosSucursales'],
    '/api/usuarios/grupos'              => [AdminController::class, 'apiUsuariosGrupos'],
    '/api/usuarios/change-password'     => [AdminController::class, 'apiUsuariosChangePassword'],

    // Accesos Grupos y Formas (Exclusivo CONADM)
    '/api/accesos-grupos/data'              => [AdminController::class, 'apiAccesosGruposData'],
    '/api/accesos-grupos/asignar-usuario'   => [AdminController::class, 'apiAccesosGruposAsignarUsuario'],
    '/api/accesos-grupos/guardar-permisos'  => [AdminController::class, 'apiAccesosGruposGuardarPermisos'],
    '/api/accesos-grupos/guardar-grupo'     => [AdminController::class, 'apiAccesosGruposGuardarGrupo'],
    '/api/accesos-grupos/eliminar-grupo'    => [AdminController::class, 'apiAccesosGruposEliminarGrupo'],
    '/api/formas/list'                      => [AdminController::class, 'apiFormasList'],
    '/api/formas/save'                      => [AdminController::class, 'apiFormasSave'],
    '/api/formas/delete'                    => [AdminController::class, 'apiFormasDelete'],

    // Backup
    '/api/backup/opciones'              => [AdminController::class, 'apiBackupOpciones'],
    '/api/backup/generar'               => [AdminController::class, 'apiBackupGenerar'],
    '/api/backup/list'                  => [AdminController::class, 'apiBackupList'],
    '/api/backup/eliminar'              => [AdminController::class, 'apiBackupEliminar'],

    // Directorios Contables: CoPersonas, Personas, DNIT, Asientos Predefinidos, Clientes, Proveedores, Sucursales
    '/api/co-personas/list'                        => [DirectoriosContablesController::class, 'apiCoPersonasList'],
    '/api/co_personas/list'                        => [DirectoriosContablesController::class, 'apiCoPersonasList'],
    '/api/co-personas/save'                        => [DirectoriosContablesController::class, 'apiCoPersonasSave'],
    '/api/co_personas/save'                        => [DirectoriosContablesController::class, 'apiCoPersonasSave'],
    '/api/co-personas/delete'                      => [DirectoriosContablesController::class, 'apiCoPersonasDelete'],
    '/api/co_personas/delete'                      => [DirectoriosContablesController::class, 'apiCoPersonasDelete'],
    '/api/personas/list'                           => [DirectoriosContablesController::class, 'apiCoPersonasList'],
    '/api/personas/save'                           => [DirectoriosContablesController::class, 'apiCoPersonasSave'],
    '/api/personas/delete'                         => [DirectoriosContablesController::class, 'apiCoPersonasDelete'],
    '/api/dnit/consultar-ruc'                      => [DirectoriosContablesController::class, 'apiDnitConsultarRuc'],
    '/api/personas/asientos-predefinidos-by-ruc'   => [DirectoriosContablesController::class, 'apiPersonasAsientosPredefinidosByRuc'],
    '/api/personas/guardar-asientos-predefinidos'  => [DirectoriosContablesController::class, 'apiPersonasGuardarAsientosPredefinidos'],
    '/api/personas/asientos-predefinidos/save'     => [DirectoriosContablesController::class, 'apiPersonasGuardarAsientosPredefinidos'],

    // Clientes
    '/api/co-clientes/list'                        => [DirectoriosContablesController::class, 'apiCoClientesList'],
    '/api/co_clientes/list'                        => [DirectoriosContablesController::class, 'apiCoClientesList'],
    '/api/co-clientes/verificar-ruc'               => [DirectoriosContablesController::class, 'apiCoClientesVerificarRuc'],
    '/api/co_clientes/verificar-ruc'               => [DirectoriosContablesController::class, 'apiCoClientesVerificarRuc'],
    '/api/co-clientes/auto-registrar-ruc'          => [DirectoriosContablesController::class, 'apiCoClientesAutoRegistrarRuc'],
    '/api/co_clientes/auto-registrar-ruc'          => [DirectoriosContablesController::class, 'apiCoClientesAutoRegistrarRuc'],
    '/api/co-clientes/save'                        => [DirectoriosContablesController::class, 'apiCoClientesSave'],
    '/api/co_clientes/save'                        => [DirectoriosContablesController::class, 'apiCoClientesSave'],
    '/api/co-clientes/delete'                      => [DirectoriosContablesController::class, 'apiCoClientesDelete'],
    '/api/co_clientes/delete'                      => [DirectoriosContablesController::class, 'apiCoClientesDelete'],

    // Proveedores
    '/api/co-proveedores/list'                     => [DirectoriosContablesController::class, 'apiCoProveedoresList'],
    '/api/co_proveedores/list'                     => [DirectoriosContablesController::class, 'apiCoProveedoresList'],
    '/api/co-proveedores/auto-registrar-ruc'       => [DirectoriosContablesController::class, 'apiCoProveedoresAutoRegistrarRuc'],
    '/api/co_proveedores/auto-registrar-ruc'       => [DirectoriosContablesController::class, 'apiCoProveedoresAutoRegistrarRuc'],
    '/api/co-proveedores/save'                     => [DirectoriosContablesController::class, 'apiCoProveedoresSave'],
    '/api/co_proveedores/save'                     => [DirectoriosContablesController::class, 'apiCoProveedoresSave'],
    '/api/co-proveedores/delete'                   => [DirectoriosContablesController::class, 'apiCoProveedoresDelete'],
    '/api/co_proveedores/delete'                   => [DirectoriosContablesController::class, 'apiCoProveedoresDelete'],

    // Sucursales
    '/api/co-sucursales/list'                      => [DirectoriosContablesController::class, 'apiCoSucursalesList'],
    '/api/co_sucursales/list'                      => [DirectoriosContablesController::class, 'apiCoSucursalesList'],
    '/api/co-sucursales/save'                      => [DirectoriosContablesController::class, 'apiCoSucursalesSave'],
    '/api/co_sucursales/save'                      => [DirectoriosContablesController::class, 'apiCoSucursalesSave'],
    '/api/co-sucursales/delete'                    => [DirectoriosContablesController::class, 'apiCoSucursalesDelete'],
    '/api/co_sucursales/delete'                    => [DirectoriosContablesController::class, 'apiCoSucursalesDelete'],

    // Definiciones Contables: Ejercicios, Monedas, Cotizaciones, Plan de Cuentas, Clasificación Gasto, Tipo Egreso, Tipo Ingreso, Timbrados, Tipos Comprobantes, Régimen
    '/api/ejercicios/list'                         => [DefinicionesContablesController::class, 'apiEjerciciosList'],
    '/api/coejerci/list'                           => [DefinicionesContablesController::class, 'apiEjerciciosList'],
    '/api/ejercicios/save'                         => [DefinicionesContablesController::class, 'apiEjerciciosSave'],
    '/api/coejerci/save'                           => [DefinicionesContablesController::class, 'apiEjerciciosSave'],
    '/api/ejercicios/delete'                       => [DefinicionesContablesController::class, 'apiEjerciciosDelete'],
    '/api/coejerci/delete'                         => [DefinicionesContablesController::class, 'apiEjerciciosDelete'],
    '/api/ejercicios/personas'                     => [DefinicionesContablesController::class, 'apiEjerciciosPersonas'],
    '/api/coejerci/personas'                       => [DefinicionesContablesController::class, 'apiEjerciciosPersonas'],

    // Monedas y Cotizaciones
    '/api/monedas/list'                            => [DefinicionesContablesController::class, 'apiMonedasList'],
    '/api/monedas/all'                             => [DefinicionesContablesController::class, 'apiMonedasList'],
    '/api/cotizaciones/list'                       => [DefinicionesContablesController::class, 'apiCotizacionesMes'],
    '/api/cotizaciones/mes'                        => [DefinicionesContablesController::class, 'apiCotizacionesMes'],
    '/api/cotizaciones/monedas'                    => [DefinicionesContablesController::class, 'apiMonedasList'],
    '/api/cotizaciones/guardar-lote'               => [DefinicionesContablesController::class, 'apiCotizacionesGuardarLote'],
    '/api/cotizaciones/save'                       => [DefinicionesContablesController::class, 'apiCotizacionesGuardarLote'],
    '/api/cotizaciones/cargar-dnit'                => [DefinicionesContablesController::class, 'apiCotizacionesCargarDnit'],
    '/api/cotizaciones/bcp-online'                 => [DefinicionesContablesController::class, 'apiCotizacionesCargarDnit'],
    '/api/cotizaciones/bcp-actual'                 => [DefinicionesContablesController::class, 'apiCotizacionBcpActual'],

    // Plan de Cuentas
    '/api/plan-cuentas/list'                       => [DefinicionesContablesController::class, 'apiPlanCuentasList'],
    '/api/plan_cuentas/list'                       => [DefinicionesContablesController::class, 'apiPlanCuentasList'],
    '/api/plan-cuentas/save'                       => [DefinicionesContablesController::class, 'apiPlanCuentasGuardar'],
    '/api/plan-cuentas/guardar'                    => [DefinicionesContablesController::class, 'apiPlanCuentasGuardar'],
    '/api/plan_cuentas/guardar'                    => [DefinicionesContablesController::class, 'apiPlanCuentasGuardar'],
    '/api/plan-cuentas/delete'                     => [DefinicionesContablesController::class, 'apiPlanCuentasEliminar'],
    '/api/plan-cuentas/eliminar'                   => [DefinicionesContablesController::class, 'apiPlanCuentasEliminar'],
    '/api/plan_cuentas/eliminar'                   => [DefinicionesContablesController::class, 'apiPlanCuentasEliminar'],

    // Clasificación de Gastos
    '/api/clasificacion-gasto/list'                => [DefinicionesContablesController::class, 'apiClasificacionGastoList'],
    '/api/clasificacion_gasto/list'                => [DefinicionesContablesController::class, 'apiClasificacionGastoList'],
    '/api/clasificacion-gasto/save'                => [DefinicionesContablesController::class, 'apiClasificacionGastoSave'],
    '/api/clasificacion_gasto/save'                => [DefinicionesContablesController::class, 'apiClasificacionGastoSave'],
    '/api/clasificacion-gasto/delete'              => [DefinicionesContablesController::class, 'apiClasificacionGastoDelete'],
    '/api/clasificacion_gasto/delete'              => [DefinicionesContablesController::class, 'apiClasificacionGastoDelete'],

    // Tipos de Egreso
    '/api/tipo-egreso/list'                        => [DefinicionesContablesController::class, 'apiTipoEgresoList'],
    '/api/tipo_egreso/list'                        => [DefinicionesContablesController::class, 'apiTipoEgresoList'],
    '/api/tipo-egreso/save'                        => [DefinicionesContablesController::class, 'apiTipoEgresoSave'],
    '/api/tipo_egreso/save'                        => [DefinicionesContablesController::class, 'apiTipoEgresoSave'],
    '/api/tipo-egreso/delete'                      => [DefinicionesContablesController::class, 'apiTipoEgresoDelete'],
    '/api/tipo_egreso/delete'                      => [DefinicionesContablesController::class, 'apiTipoEgresoDelete'],

    // Tipos de Ingreso
    '/api/tipo-ingreso/list'                       => [DefinicionesContablesController::class, 'apiTipoIngresoList'],
    '/api/tipo_ingreso/list'                       => [DefinicionesContablesController::class, 'apiTipoIngresoList'],
    '/api/tipo-ingreso/all'                        => [DefinicionesContablesController::class, 'apiTipoIngresoAll'],
    '/api/tipo_ingreso/all'                        => [DefinicionesContablesController::class, 'apiTipoIngresoAll'],
    '/api/tipo-ingreso/save'                       => [DefinicionesContablesController::class, 'apiTipoIngresoSave'],
    '/api/tipo_ingreso/save'                       => [DefinicionesContablesController::class, 'apiTipoIngresoSave'],
    '/api/tipo-ingreso/delete'                     => [DefinicionesContablesController::class, 'apiTipoIngresoDelete'],
    '/api/tipo_ingreso/delete'                     => [DefinicionesContablesController::class, 'apiTipoIngresoDelete'],

    // Timbrados
    '/api/timbrados/list'                          => [DefinicionesContablesController::class, 'apiTimbradosList'],
    '/api/timbrados/save'                          => [DefinicionesContablesController::class, 'apiTimbradosSave'],
    '/api/timbrados/delete'                        => [DefinicionesContablesController::class, 'apiTimbradosDelete'],

    // Tipos de Comprobantes
    '/api/co-tipos-comprobantes/list'              => [DefinicionesContablesController::class, 'apiCoTiposComprobantesList'],
    '/api/co_tipos_comprobantes/list'              => [DefinicionesContablesController::class, 'apiCoTiposComprobantesList'],
    '/api/co-tipos-comprobantes/modulos'           => [DefinicionesContablesController::class, 'apiCoTiposComprobantesModulos'],
    '/api/co_tipos_comprobantes/modulos'           => [DefinicionesContablesController::class, 'apiCoTiposComprobantesModulos'],
    '/api/co-tipos-comprobantes/save'              => [DefinicionesContablesController::class, 'apiCoTiposComprobantesSave'],
    '/api/co_tipos_comprobantes/save'              => [DefinicionesContablesController::class, 'apiCoTiposComprobantesSave'],
    '/api/co-tipos-comprobantes/delete'            => [DefinicionesContablesController::class, 'apiCoTiposComprobantesDelete'],
    '/api/co_tipos_comprobantes/delete'            => [DefinicionesContablesController::class, 'apiCoTiposComprobantesDelete'],

    // Régimen Contable
    '/api/regimen/list'                            => [DefinicionesContablesController::class, 'apiRegimenList'],
    '/api/regimen/save'                            => [DefinicionesContablesController::class, 'apiRegimenSave'],
    '/api/regimen/delete'                          => [DefinicionesContablesController::class, 'apiRegimenDelete'],

    // Movimientos Contables: SIFEN, Egresos, Ingresos, Asientos
    '/api/sifen/health'                            => [MovimientosContablesController::class, 'apiSifenHealth'],
    '/api/sifen/emitir'                            => [MovimientosContablesController::class, 'apiSifenEmitir'],
    '/api/sifen/cancelar'                          => [MovimientosContablesController::class, 'apiSifenCancelar'],
    '/api/sifen/consultar-cdc'                     => [MovimientosContablesController::class, 'apiSifenConsultarCdc'],
    '/api/egresos/verificar-cdc'                   => [MovimientosContablesController::class, 'apiVerificarCdc'],
    '/api/egresos/consultar-timbrado'              => [MovimientosContablesController::class, 'apiEgresosConsultarTimbrado'],
    '/api/egresos/list'                            => [MovimientosContablesController::class, 'apiEgresosList'],
    '/api/egresos/save'                            => [MovimientosContablesController::class, 'apiEgresosSave'],
    '/api/egresos/delete'                          => [MovimientosContablesController::class, 'apiEgresosDelete'],
    '/api/egresos/validar-importacion'             => [MovimientosContablesController::class, 'apiEgresosValidarImportacion'],
    '/api/egresos/importar-lote'                   => [MovimientosContablesController::class, 'apiEgresosImportarLote'],
    '/api/ingresos/verificar-cdc'                  => [MovimientosContablesController::class, 'apiVerificarCdc'],
    '/api/ingresos/tipos-documentos'               => [MovimientosContablesController::class, 'apiIngresosTiposDocumentos'],
    '/api/ingresos/list'                           => [MovimientosContablesController::class, 'apiIngresosList'],
    '/api/ingresos/save'                           => [MovimientosContablesController::class, 'apiIngresosSave'],
    '/api/ingresos/delete'                         => [MovimientosContablesController::class, 'apiIngresosDelete'],
    '/api/recibos-cobro/list'                      => [MovimientosContablesController::class, 'apiRecibosCobroList'],
    '/api/recibos_cobro/list'                      => [MovimientosContablesController::class, 'apiRecibosCobroList'],
    '/api/recibos-cobro/save'                      => [MovimientosContablesController::class, 'apiRecibosCobroSave'],
    '/api/recibos_cobro/save'                      => [MovimientosContablesController::class, 'apiRecibosCobroSave'],
    '/api/recibos-cobro/delete'                    => [MovimientosContablesController::class, 'apiRecibosCobroDelete'],
    '/api/recibos_cobro/delete'                    => [MovimientosContablesController::class, 'apiRecibosCobroDelete'],
    '/api/recibos-cobro/facturas-pendientes'       => [MovimientosContablesController::class, 'apiRecibosCobroFacturasPendientes'],
    '/api/recibos_cobro/facturas-pendientes'       => [MovimientosContablesController::class, 'apiRecibosCobroFacturasPendientes'],
    '/api/recibos-cobro/siguiente-nro'             => [MovimientosContablesController::class, 'apiRecibosCobroSiguienteNro'],
    '/api/recibos_cobro/siguiente-nro'             => [MovimientosContablesController::class, 'apiRecibosCobroSiguienteNro'],
    '/api/recibos-pago/list'                       => [MovimientosContablesController::class, 'apiRecibosPagoList'],
    '/api/recibos_pago/list'                       => [MovimientosContablesController::class, 'apiRecibosPagoList'],
    '/api/recibos-pago/save'                       => [MovimientosContablesController::class, 'apiRecibosPagoSave'],
    '/api/recibos_pago/save'                       => [MovimientosContablesController::class, 'apiRecibosPagoSave'],
    '/api/recibos-pago/delete'                     => [MovimientosContablesController::class, 'apiRecibosPagoDelete'],
    '/api/recibos_pago/delete'                     => [MovimientosContablesController::class, 'apiRecibosPagoDelete'],
    '/api/recibos-pago/facturas-pendientes'        => [MovimientosContablesController::class, 'apiRecibosPagoFacturasPendientes'],
    '/api/recibos_pago/facturas-pendientes'        => [MovimientosContablesController::class, 'apiRecibosPagoFacturasPendientes'],
    '/api/recibos-pago/proveedores'                => [MovimientosContablesController::class, 'apiRecibosPagoProveedores'],
    '/api/recibos_pago/proveedores'                => [MovimientosContablesController::class, 'apiRecibosPagoProveedores'],
    '/api/asientos/list'                           => [MovimientosContablesController::class, 'apiAsientosList'],
    '/api/asientos/siguiente-numero'               => [MovimientosContablesController::class, 'apiAsientosSiguienteNumero'],
    '/api/asientos/buscar-cuentas'                 => [MovimientosContablesController::class, 'apiAsientosBuscarCuentas'],
    '/api/asientos/sucursales'                     => [MovimientosContablesController::class, 'apiAsientosSucursales'],
    '/api/asientos/guardar'                        => [MovimientosContablesController::class, 'apiAsientosGuardar'],
    '/api/asientos/anular'                         => [MovimientosContablesController::class, 'apiAsientosAnular'],

    // Reportes Contables: Libro Compras, Libro Ventas, Libro Diario, Libro Mayor, Marangatu, Formulario 120
    '/api/reportes/libro-compras'                  => [ReportesContablesController::class, 'apiLibroComprasData'],
    '/api/reportes/libro-ventas'                   => [ReportesContablesController::class, 'apiLibroVentasData'],
    '/api/libro-diario/datos'                      => [ReportesContablesController::class, 'apiLibroDiarioDatos'],
    '/api/libro-mayor/datos'                       => [ReportesContablesController::class, 'apiLibroMayorDatos'],
    '/api/marangatu/exportar-zip'                  => [ReportesContablesController::class, 'apiMarangatuExportarZip'],
    '/api/formulario-120/obtener'                  => [ReportesContablesController::class, 'apiFormulario120Obtener'],
    '/api/formulario-120/calcular'                 => [ReportesContablesController::class, 'apiFormulario120Calcular'],
    '/api/formulario-120/guardar'                  => [ReportesContablesController::class, 'apiFormulario120Guardar'],
    '/api/reportes/resumen-comparativo'            => [ReportesContablesController::class, 'apiResumenComparativoData'],
    '/api/resumen-comparativo/data'                => [ReportesContablesController::class, 'apiResumenComparativoData'],
    '/api/reportes/resumen-irp'                    => [ReportesContablesController::class, 'apiResumenIrpData'],
    '/api/reportes/resumen-irp/data'               => [ReportesContablesController::class, 'apiResumenIrpData'],
];

if (isset($exactApiRoutes[$uri])) {
    call_user_func($exactApiRoutes[$uri]);
    exit;
}

// --- Rutas API con parámetros dinámicos en el path ---
$dynamicApiRoutes = [
    '#^/api/usuarios/get/(.+)$#'                      => [AdminController::class, 'apiUsuariosGet'],
    '#^/api/usuarios/personas-get/(.+)$#'             => [AdminController::class, 'apiUsuariosPersonasGet'],
    '#^/api/usuarios/personas/get/(.+)$#'             => [AdminController::class, 'apiUsuariosPersonasGet'],
    '#^/api/backup/download/(.+)$#'                   => [AdminController::class, 'apiBackupDownload'],
    '#^/api/personas/asientos-predefinidos/(.+)$#'    => [DirectoriosContablesController::class, 'apiPersonasAsientosPredefinidos'],
    '#^/api/clasificacion[-_]gasto/get/(.+)$#'        => [DefinicionesContablesController::class, 'apiClasificacionGastoGet'],
    '#^/api/tipo[-_]egreso/get/(.+)$#'                => [DefinicionesContablesController::class, 'apiTipoEgresoGet'],
    '#^/api/tipo[-_]ingreso/get/(.+)$#'               => [DefinicionesContablesController::class, 'apiTipoIngresoGet'],
    '#^/api/egresos/get/(.+)$#'                       => [MovimientosContablesController::class, 'apiEgresosGet'],
    '#^/api/ingresos/get/(.+)$#'                      => [MovimientosContablesController::class, 'apiIngresosGet'],
    '#^/api/recibos[-_]cobro/get/(.+)$#'              => [MovimientosContablesController::class, 'apiRecibosCobroGet'],
    '#^/api/recibos[-_]pago/get/(.+)$#'               => [MovimientosContablesController::class, 'apiRecibosPagoGet'],
    '#^/api/sifen/descargar-xml/(.+)$#'               => [MovimientosContablesController::class, 'apiSifenDescargarXml'],
    '#^/api/asientos/obtener/(.+)$#'                  => [MovimientosContablesController::class, 'apiAsientosObtener'],
];

foreach ($dynamicApiRoutes as $pattern => $handler) {
    if (preg_match($pattern, $uri, $matches)) {
        call_user_func($handler, $matches[1]);
        exit;
    }
}

// ============================================================================
// 404 NOT FOUND
// ============================================================================
if (str_starts_with($uri, '/api/')) {
    View::json(['status' => 'error', 'message' => 'Ruta API no encontrada: ' . $uri], 404);
} else {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>404 - ContaPro</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;"><h2>404 - Página no encontrada</h2><p><a href="/">Volver al inicio de ContaPro</a></p></body></html>';
}
