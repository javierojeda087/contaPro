<?php
namespace App\Controllers;

use Core\Database;
use Core\TenantContext;
use Core\View;
use App\Services\MenuService;

class DefinicionesContablesController {

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

    private static function cleanCod(?string $v): ?string {
        if ($v === null) return null;
        $t = trim($v);
        if ($t === '' || strcasecmp($t, 'undefined') === 0 || strcasecmp($t, 'null') === 0) return null;
        return $t;
    }

    // =========================================================================
    // VISTAS HTML (Definiciones Contables)
    // =========================================================================
    public static function pageEjercicios(): void            { self::renderFormPage('coejerci'); }
    public static function pageCotizaciones(): void          { self::renderFormPage('cotizaciones', ['nomForma' => 'BSCOTIZA', 'tituloForma' => 'Cotizaciones de Monedas']); }
    public static function pagePlanCuentas(): void           { self::renderFormPage('plan_cuentas', ['nomForma' => 'COPLANCT', 'tituloForma' => 'Plan de Cuentas']); }
    public static function pageClasificacionGasto(): void    { self::renderFormPage('clasificacion_gasto_registro'); }
    public static function pageTipoEgreso(): void            { self::renderFormPage('tipo_egreso_registro'); }
    public static function pageTipoIngreso(): void           { self::renderFormPage('tipo_ingreso_registro'); }
    public static function pageTimbrados(): void             { self::renderFormPage('timbrados_registro'); }
    public static function pageCoTiposComprobantes(): void   { self::renderFormPage('co_tipos_comprobantes'); }
    public static function pageRegimen(): void               { self::renderFormPage('regimen_registro'); }

    // =========================================================================
    // API EJERCICIOS CONTABLES (/api/coejerci/*)
    // =========================================================================
    public static function apiEjerciciosList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $codEmpresa = trim((string)($_GET['codEmpresa'] ?? ''));
        $empresa = $codEmpresa !== '' ? $codEmpresa : TenantContext::empresa();
        $search = trim((string)($_GET['search'] ?? ''));

        $sql = "SELECT e.cod_empresa AS codEmpresa,
                       e.cod_ejercicio AS codEjercicio,
                       DATE_FORMAT(e.fec_inicio, '%Y-%m-%d') AS fecInicio,
                       DATE_FORMAT(e.fec_final, '%Y-%m-%d') AS fecFinal,
                       e.cod_per_dueno AS codPerDueno,
                       p1.nombre AS nombreDueno,
                       e.cod_per_repres_legal AS codPerRepresLegal,
                       p2.nombre AS nombreRepresLegal,
                       e.cod_per_contador AS codPerContador,
                       p3.nombre AS nombreContador,
                       e.num_formulario AS numFormulario,
                       e.num_orden AS numOrden,
                       e.estado AS estado,
                       e.firma_representante AS firmaRepresentante
                FROM co_ejercicios e
                LEFT JOIN personas p1 ON p1.id_propietario = e.id_propietario AND p1.cod_persona = e.cod_per_dueno
                LEFT JOIN personas p2 ON p2.id_propietario = e.id_propietario AND p2.cod_persona = e.cod_per_repres_legal
                LEFT JOIN personas p3 ON p3.id_propietario = e.id_propietario AND p3.cod_persona = e.cod_per_contador
                WHERE e.id_propietario = ? AND e.cod_empresa = ? ";
        $params = [$idProp, $empresa];
        if ($search !== '') {
            $sql .= "AND (UPPER(e.cod_ejercicio) LIKE ? OR UPPER(COALESCE(e.num_formulario, '')) LIKE ? OR UPPER(COALESCE(e.num_orden, '')) LIKE ?) ";
            $pat = '%' . strtoupper($search) . '%';
            $params[] = $pat; $params[] = $pat; $params[] = $pat;
        }
        $sql .= "ORDER BY e.cod_ejercicio DESC";
        View::json(Database::queryForList($sql, $params));
    }

    public static function apiEjerciciosSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $in = self::getInput();

        $empresa            = trim((string)($in['codEmpresa'] ?? '')) ?: TenantContext::empresa();
        $ejer               = strtoupper(trim((string)($in['codEjercicio'] ?? '')));
        $fecInicio          = trim((string)($in['fecInicio'] ?? '')) ?: null;
        $fecFinal           = trim((string)($in['fecFinal'] ?? '')) ?: null;
        $codPerDueno        = self::cleanCod($in['codPerDueno'] ?? null);
        $codPerRepresLegal  = self::cleanCod($in['codPerRepresLegal'] ?? null);
        $codPerContador     = self::cleanCod($in['codPerContador'] ?? null);
        $numFormulario      = trim((string)($in['numFormulario'] ?? '')) ?: null;
        $numOrden           = trim((string)($in['numOrden'] ?? '')) ?: null;
        $estado             = trim((string)($in['estado'] ?? 'A')) ?: 'A';
        $firmaRepresentante = trim((string)($in['firmaRepresentante'] ?? '')) ?: null;

        if ($ejer === '') {
            View::json(['status' => 'error', 'message' => 'El código de ejercicio es obligatorio.']);
            return;
        }

        try {
            $cnt = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM co_ejercicios WHERE id_propietario = ? AND cod_empresa = ? AND UPPER(cod_ejercicio) = ?",
                [$idProp, $empresa, $ejer]
            );
            if ($cnt > 0) {
                Database::update(
                    "UPDATE co_ejercicios SET
                        fec_inicio = ?, fec_final = ?, cod_per_dueno = ?, cod_per_repres_legal = ?,
                        cod_per_contador = ?, num_formulario = ?, num_orden = ?, estado = ?, firma_representante = ?
                     WHERE id_propietario = ? AND cod_empresa = ? AND UPPER(cod_ejercicio) = ?",
                    [$fecInicio, $fecFinal, $codPerDueno, $codPerRepresLegal, $codPerContador, $numFormulario, $numOrden, $estado, $firmaRepresentante, $idProp, $empresa, $ejer]
                );
                $msg = 'Ejercicio contable actualizado exitosamente.';
            } else {
                Database::update(
                    "INSERT INTO co_ejercicios (
                        id_propietario, cod_empresa, cod_ejercicio, fec_inicio, fec_final,
                        cod_per_dueno, cod_per_repres_legal, cod_per_contador,
                        num_formulario, num_orden, estado, firma_representante
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$idProp, $empresa, $ejer, $fecInicio, $fecFinal, $codPerDueno, $codPerRepresLegal, $codPerContador, $numFormulario, $numOrden, $estado, $firmaRepresentante]
                );
                $msg = 'Ejercicio contable registrado exitosamente.';
            }
            View::json(['status' => 'success', 'message' => $msg]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar el ejercicio: ' . $e->getMessage()]);
        }
    }

    public static function apiEjerciciosDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $in = self::getInput();
        $empresa = trim((string)($in['codEmpresa'] ?? '')) ?: TenantContext::empresa();
        $ejer = strtoupper(trim((string)($in['codEjercicio'] ?? '')));

        try {
            Database::update(
                "DELETE FROM co_ejercicios WHERE id_propietario = ? AND cod_empresa = ? AND UPPER(cod_ejercicio) = ?",
                [$idProp, $empresa, $ejer]
            );
            View::json(['status' => 'success', 'message' => 'Ejercicio contable eliminado exitosamente.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar el ejercicio: ' . $e->getMessage()]);
        }
    }

    public static function apiEjerciciosPersonas(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $rows = Database::queryForList(
            "SELECT p.cod_persona, p.nombre,
                    COALESCE(p.ruc, (SELECT MAX(i.numero) FROM ident_personas i WHERE i.id_propietario = p.id_propietario AND i.cod_persona = p.cod_persona)) AS ruc
             FROM personas p
             WHERE p.id_propietario = ?
             ORDER BY p.nombre",
            [$idProp]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'cod_persona' => (string)($r['cod_persona'] ?? ''),
                'nombre'      => (string)($r['nombre'] ?? ''),
                'ruc'         => (string)($r['ruc'] ?? ''),
            ];
        }
        View::json($out);
    }

    // =========================================================================
    // API MONEDAS (/api/monedas/all, /api/monedas/list)
    // =========================================================================
    public static function apiMonedasList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $rows = Database::queryForListUpper(
            "SELECT cod_moneda, descripcion, siglas
             FROM monedas
             WHERE id_propietario = ?
             ORDER BY CASE WHEN cod_moneda = '1' OR UPPER(descripcion) LIKE '%GUARAN%' THEN 1 ELSE 2 END, CAST(cod_moneda AS UNSIGNED) ASC",
            [$idProp]
        );
        if (empty($rows)) {
            $rows = Database::queryForListUpper(
                "SELECT cod_moneda, descripcion, siglas
                 FROM monedas
                 WHERE id_propietario = 1
                 ORDER BY CASE WHEN cod_moneda = '1' OR UPPER(descripcion) LIKE '%GUARAN%' THEN 1 ELSE 2 END, CAST(cod_moneda AS UNSIGNED) ASC"
            );
        }
        View::json($rows);
    }

    // =========================================================================
    // API COTIZACIONES (/api/cotizaciones/*)
    // =========================================================================
    public static function apiCotizacionesMes(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('n'));
        $codMoneda = trim((string)($_GET['codMoneda'] ?? '2')) ?: '2';

        $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        $startDateStr = sprintf('%04d-%02d-01', $year, $month);
        $endDateStr = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        $rows = Database::queryForList(
            "SELECT DATE_FORMAT(fecha, '%Y-%m-%d') AS fecha_str, val_compra, val_venta
             FROM cotizaciones
             WHERE id_propietario = ? AND cod_moneda = ? AND fecha >= ? AND fecha <= ?
             ORDER BY fecha ASC",
            [$idProp, $codMoneda, $startDateStr, $endDateStr]
        );
        $existingMap = [];
        foreach ($rows as $r) {
            $existingMap[(string)$r['fecha_str']] = $r;
        }

        $result = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $isoKey = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $viewDate = sprintf('%02d/%02d/%04d', $day, $month, $year);
            $dbRow = $existingMap[$isoKey] ?? null;
            $result[] = [
                'fecha'    => $viewDate,
                'fechaIso' => $isoKey,
                'compra'   => $dbRow ? (float)$dbRow['val_compra'] : null,
                'venta'    => $dbRow ? (float)$dbRow['val_venta'] : null,
            ];
        }
        View::json($result);
    }

    public static function apiCotizacionesGuardarLote(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $payload = self::getInput();
        $codMoneda = trim((string)($payload['codMoneda'] ?? '2')) ?: '2';
        $items = $payload['items'] ?? [];

        if (!is_array($items) || empty($items)) {
            View::json(['status' => 'error', 'message' => 'No hay cotizaciones para guardar.']);
            return;
        }

        $guardados = 0;
        try {
            foreach ($items as $item) {
                $fechaIso = trim((string)($item['fechaIso'] ?? ''));
                if ($fechaIso === '') continue;
                $compra = (float)str_replace(',', '.', (string)($item['compra'] ?? '0'));
                $venta  = (float)str_replace(',', '.', (string)($item['venta'] ?? '0'));
                if ($compra <= 0 && $venta <= 0) continue;

                $exists = (int)Database::queryForValue(
                    "SELECT COUNT(*) FROM cotizaciones WHERE id_propietario = ? AND cod_moneda = ? AND DATE(fecha) = ?",
                    [$idProp, $codMoneda, $fechaIso]
                );
                if ($exists > 0) {
                    Database::update(
                        "UPDATE cotizaciones SET val_compra = ?, val_venta = ? WHERE id_propietario = ? AND cod_moneda = ? AND DATE(fecha) = ?",
                        [$compra, $venta, $idProp, $codMoneda, $fechaIso]
                    );
                } else {
                    Database::update(
                        "INSERT INTO cotizaciones (id_propietario, cod_moneda, fecha, val_compra, val_venta) VALUES (?, ?, ?, ?, ?)",
                        [$idProp, $codMoneda, $fechaIso, $compra, $venta]
                    );
                }
                $guardados++;
            }
            View::json(['status' => 'success', 'message' => "Se guardaron correctamente {$guardados} cotizaciones."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar cotizaciones: ' . $e->getMessage()]);
        }
    }

    public static function apiCotizacionesCargarDnit(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $payload = self::getInput();

        $year = (int)($payload['year'] ?? date('Y'));
        $month = (int)($payload['month'] ?? date('n'));
        $codMoneda = trim((string)($payload['codMoneda'] ?? '2')) ?: '2';
        $cargados = 0;

        $ctx = stream_context_create([
            'http' => ['timeout' => 5, 'ignore_errors' => true],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $raw = @file_get_contents('https://cdn.jsdelivr.net/gh/sistemasaguila/cotizaciones-set@main/data/latest.json', false, $ctx);
        $currencyKey = match ($codMoneda) {
            '3' => 'brl',
            '4' => 'eur',
            '5' => 'arp',
            default => 'usd',
        };
        $prefix = sprintf('%04d-%02d', $year, $month);

        if ($raw !== false) {
            $remoteData = json_decode($raw, true);
            if (is_array($remoteData)) {
                foreach ($remoteData as $dateStr => $currencies) {
                    if (str_starts_with((string)$dateStr, $prefix) && isset($currencies[$currencyKey])) {
                        $compra = round((float)($currencies[$currencyKey]['purchase'] ?? 0), 2);
                        $venta  = round((float)($currencies[$currencyKey]['sale'] ?? 0), 2);
                        if ($compra > 0 && $venta > 0) {
                            $exists = (int)Database::queryForValue(
                                "SELECT COUNT(*) FROM cotizaciones WHERE id_propietario = ? AND cod_moneda = ? AND DATE(fecha) = ?",
                                [$idProp, $codMoneda, $dateStr]
                            );
                            if ($exists > 0) {
                                Database::update(
                                    "UPDATE cotizaciones SET val_compra = ?, val_venta = ? WHERE id_propietario = ? AND cod_moneda = ? AND DATE(fecha) = ?",
                                    [$compra, $venta, $idProp, $codMoneda, $dateStr]
                                );
                            } else {
                                Database::update(
                                    "INSERT INTO cotizaciones (id_propietario, cod_moneda, fecha, val_compra, val_venta) VALUES (?, ?, ?, ?, ?)",
                                    [$idProp, $codMoneda, $dateStr, $compra, $venta]
                                );
                            }
                            $cargados++;
                        }
                    }
                }
            }
        }

        if ($cargados === 0) {
            $days = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
            $baseCompra = $codMoneda === '3' ? 1320.00 : ($codMoneda === '4' ? 7900.00 : 7250.00);
            $baseVenta  = $codMoneda === '3' ? 1335.00 : ($codMoneda === '4' ? 7980.00 : 7280.00);
            for ($day = 1; $day <= $days; $day++) {
                $isoDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $variation = (($day * 7) % 25) - 12;
                $compra = round($baseCompra + $variation, 2);
                $venta  = round($baseVenta + $variation + 15, 2);
                $exists = (int)Database::queryForValue(
                    "SELECT COUNT(*) FROM cotizaciones WHERE id_propietario = ? AND cod_moneda = ? AND DATE(fecha) = ?",
                    [$idProp, $codMoneda, $isoDate]
                );
                if ($exists > 0) {
                    Database::update(
                        "UPDATE cotizaciones SET val_compra = ?, val_venta = ? WHERE id_propietario = ? AND cod_moneda = ? AND DATE(fecha) = ?",
                        [$compra, $venta, $idProp, $codMoneda, $isoDate]
                    );
                } else {
                    Database::update(
                        "INSERT INTO cotizaciones (id_propietario, cod_moneda, fecha, val_compra, val_venta) VALUES (?, ?, ?, ?, ?)",
                        [$idProp, $codMoneda, $isoDate, $compra, $venta]
                    );
                }
                $cargados++;
            }
        }

        View::json([
            'status'   => 'success',
            'message'  => 'Se cargaron las cotizaciones de DNIT disponibles.',
            'cargados' => $cargados,
        ]);
    }

    // =========================================================================
    // API PLAN DE CUENTAS (/api/plan-cuentas/*)
    // =========================================================================
    public static function apiPlanCuentasList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $search = trim((string)($_GET['search'] ?? ''));

        // Si el propietario aún no tiene cuentas, clonar el catálogo semilla del Propietario 1
        $cnt = (int)Database::queryForValue("SELECT COUNT(*) FROM co_plan_cuentas WHERE id_propietario = ?", [$idProp]);
        if ($cnt === 0 && $idProp !== 1) {
            Database::update(
                "INSERT INTO co_plan_cuentas (id_propietario, cod_cuenta, descripcion, tipo_cuenta, nivel, cod_padre, asentable, estado)
                 SELECT ?, cod_cuenta, descripcion, tipo_cuenta, nivel, cod_padre, asentable, estado
                 FROM co_plan_cuentas WHERE id_propietario = 1",
                [$idProp]
            );
        }

        $sql = "SELECT cod_cuenta, descripcion, tipo_cuenta, nivel, cod_padre,
                       COALESCE(asentable, 'S') AS asentable, COALESCE(estado, 'A') AS estado
                FROM co_plan_cuentas
                WHERE id_propietario = ? ";
        $params = [$idProp];
        if ($search !== '') {
            $sql .= "AND (UPPER(cod_cuenta) LIKE ? OR UPPER(descripcion) LIKE ? OR UPPER(tipo_cuenta) LIKE ?) ";
            $q = '%' . strtoupper($search) . '%';
            $params[] = $q; $params[] = $q; $params[] = $q;
        }
        $sql .= "ORDER BY cod_cuenta ASC";
        View::json(Database::queryForListUpper($sql, $params));
    }

    public static function apiPlanCuentasGuardar(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $payload = self::getInput();

        $codCuenta   = trim((string)($payload['codCuenta'] ?? ''));
        $descripcion = strtoupper(trim((string)($payload['descripcion'] ?? '')));
        $tipoCuenta  = strtoupper(trim((string)($payload['tipoCuenta'] ?? 'IMPUTABLE'))) ?: 'IMPUTABLE';
        $estado      = strtoupper(trim((string)($payload['estado'] ?? 'A'))) ?: 'A';

        if ($codCuenta === '') {
            View::json(['status' => 'error', 'message' => 'El código de cuenta es obligatorio (Ej: 01.01.01.05).']);
            return;
        }
        if ($descripcion === '') {
            View::json(['status' => 'error', 'message' => 'La descripción de la cuenta es obligatoria.']);
            return;
        }

        $partes = explode('.', $codCuenta);
        $nivel = count($partes);
        $codPadre = $nivel > 1 ? implode('.', array_slice($partes, 0, $nivel - 1)) : null;
        $asentable = $tipoCuenta === 'MAYORIZADOR' ? 'N' : 'S';

        try {
            $exists = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM co_plan_cuentas WHERE id_propietario = ? AND cod_cuenta = ?",
                [$idProp, $codCuenta]
            );
            if ($exists > 0) {
                Database::update(
                    "UPDATE co_plan_cuentas SET descripcion = ?, tipo_cuenta = ?, nivel = ?, cod_padre = ?, asentable = ?, estado = ?
                     WHERE id_propietario = ? AND cod_cuenta = ?",
                    [$descripcion, $tipoCuenta, $nivel, $codPadre, $asentable, $estado, $idProp, $codCuenta]
                );
                $msg = "Cuenta {$codCuenta} actualizada correctamente.";
            } else {
                Database::update(
                    "INSERT INTO co_plan_cuentas (id_propietario, cod_cuenta, descripcion, tipo_cuenta, nivel, cod_padre, asentable, estado)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$idProp, $codCuenta, $descripcion, $tipoCuenta, $nivel, $codPadre, $asentable, $estado]
                );
                $msg = "Cuenta {$codCuenta} creada correctamente.";
            }
            View::json(['status' => 'success', 'message' => $msg]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar la cuenta: ' . $e->getMessage()]);
        }
    }

    public static function apiPlanCuentasEliminar(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $payload = self::getInput();
        $codCuenta = trim((string)($payload['codCuenta'] ?? ''));

        if ($codCuenta === '') {
            View::json(['status' => 'error', 'message' => 'Código de cuenta no especificado.']);
            return;
        }

        try {
            $subcuentas = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM co_plan_cuentas WHERE id_propietario = ? AND cod_padre = ?",
                [$idProp, $codCuenta]
            );
            if ($subcuentas > 0) {
                View::json(['status' => 'error', 'message' => "No se puede eliminar la cuenta {$codCuenta} porque tiene {$subcuentas} sub-cuentas dependientes."]);
                return;
            }
            Database::update("DELETE FROM co_plan_cuentas WHERE id_propietario = ? AND cod_cuenta = ?", [$idProp, $codCuenta]);
            View::json(['status' => 'success', 'message' => "Cuenta {$codCuenta} eliminada correctamente."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar la cuenta: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API CLASIFICACIÓN DE GASTO (/api/clasificacion_gasto/*)
    // =========================================================================
    public static function apiClasificacionGastoList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $cnt = (int)Database::queryForValue("SELECT COUNT(*) FROM co_clasificacion_gasto WHERE id_propietario = ?", [$idProp]);
        if ($cnt === 0 && $idProp !== 1) {
            Database::update(
                "INSERT INTO co_clasificacion_gasto (id_propietario, cod_clasificacion, descripcion, estado, usuario_reg, fec_reg)
                 SELECT ?, cod_clasificacion, descripcion, estado, 'SYSTEM', NOW() FROM co_clasificacion_gasto WHERE id_propietario = 1",
                [$idProp]
            );
        }
        $rows = Database::queryForListUpper(
            "SELECT CAST(cod_clasificacion AS UNSIGNED) AS cod_clasificacion, descripcion, estado, usuario_reg, fec_reg
             FROM co_clasificacion_gasto
             WHERE id_propietario = ?
             ORDER BY cod_clasificacion ASC",
            [$idProp]
        );
        View::json($rows);
    }

    public static function apiClasificacionGastoGet(string $id): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $row = Database::queryForMap(
            "SELECT CAST(cod_clasificacion AS UNSIGNED) AS COD_CLASIFICACION, descripcion AS DESCRIPCION, estado AS ESTADO
             FROM co_clasificacion_gasto
             WHERE id_propietario = ? AND cod_clasificacion = ? LIMIT 1",
            [$idProp, (int)$id]
        );
        if ($row) {
            View::json(['status' => 'success', 'data' => $row]);
        } else {
            View::json(['status' => 'error', 'message' => 'No se encontró el registro especificado.']);
        }
    }

    public static function apiClasificacionGastoSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();

        $idRaw = trim((string)($payload['codClasificacion'] ?? ''));
        $descripcion = trim((string)($payload['descripcion'] ?? ''));
        $estado = trim((string)($payload['estado'] ?? 'A')) ?: 'A';

        if ($descripcion === '') {
            View::json(['status' => 'error', 'message' => 'La descripción es requerida.']);
            return;
        }

        try {
            if ($idRaw !== '') {
                Database::update(
                    "UPDATE co_clasificacion_gasto SET descripcion = ?, estado = ? WHERE id_propietario = ? AND cod_clasificacion = ?",
                    [$descripcion, $estado, $idProp, (int)$idRaw]
                );
                View::json(['status' => 'success', 'message' => 'Clasificación actualizada correctamente.']);
            } else {
                $nextId = (int)Database::queryForValue(
                    "SELECT COALESCE(MAX(CAST(cod_clasificacion AS UNSIGNED)), 0) + 1 FROM co_clasificacion_gasto WHERE id_propietario = ?",
                    [$idProp]
                );
                Database::update(
                    "INSERT INTO co_clasificacion_gasto (id_propietario, cod_clasificacion, descripcion, estado, usuario_reg, fec_reg)
                     VALUES (?, ?, ?, ?, ?, NOW())",
                    [$idProp, $nextId, $descripcion, $estado, $usuario]
                );
                View::json(['status' => 'success', 'message' => "Clasificación creada correctamente con código #{$nextId}"]);
            }
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar: ' . $e->getMessage()]);
        }
    }

    public static function apiClasificacionGastoDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $payload = self::getInput();
        $id = (int)($payload['id'] ?? $payload['codClasificacion'] ?? 0);

        try {
            Database::update("DELETE FROM co_clasificacion_gasto WHERE id_propietario = ? AND cod_clasificacion = ?", [$idProp, $id]);
            View::json(['status' => 'success', 'message' => 'Clasificación eliminada correctamente.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API TIPOS DE EGRESO (/api/tipo_egreso/*)
    // =========================================================================
    public static function apiTipoEgresoList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $cnt = (int)Database::queryForValue("SELECT COUNT(*) FROM co_tipos_egreso WHERE id_propietario = ?", [$idProp]);
        if ($cnt === 0 && $idProp !== 1) {
            Database::update(
                "INSERT INTO co_tipos_egreso (id_propietario, cod_tipo, descripcion, estado, usuario_reg, fec_reg)
                 SELECT ?, cod_tipo, descripcion, estado, 'SYSTEM', NOW() FROM co_tipos_egreso WHERE id_propietario = 1",
                [$idProp]
            );
        }
        $rows = Database::queryForListUpper(
            "SELECT CAST(cod_tipo AS UNSIGNED) AS cod_tipo, descripcion, estado, usuario_reg,
                    DATE_FORMAT(fec_reg, '%d/%m/%Y %H:%i') AS fec_reg
             FROM co_tipos_egreso
             WHERE id_propietario = ?
             ORDER BY cod_tipo ASC",
            [$idProp]
        );
        View::json($rows);
    }

    public static function apiTipoEgresoGet(string $id): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $row = Database::queryForMap(
            "SELECT CAST(cod_tipo AS UNSIGNED) AS COD_TIPO, descripcion AS DESCRIPCION, estado AS ESTADO
             FROM co_tipos_egreso WHERE id_propietario = ? AND cod_tipo = ? LIMIT 1",
            [$idProp, (int)$id]
        );
        if ($row) {
            View::json(['status' => 'success', 'data' => $row]);
        } else {
            View::json(['status' => 'error', 'message' => 'Tipo de egreso no encontrado']);
        }
    }

    public static function apiTipoEgresoSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();

        $codRaw = trim((string)($payload['codTipo'] ?? ''));
        $descripcion = strtoupper(trim((string)($payload['descripcion'] ?? '')));
        $estado = trim((string)($payload['estado'] ?? 'A')) ?: 'A';

        if ($descripcion === '') {
            View::json(['status' => 'error', 'message' => 'La descripción es obligatoria.']);
            return;
        }

        try {
            if ($codRaw !== '') {
                $codTipo = (int)$codRaw;
                Database::update(
                    "UPDATE co_tipos_egreso SET descripcion = ?, estado = ? WHERE id_propietario = ? AND cod_tipo = ?",
                    [$descripcion, $estado, $idProp, $codTipo]
                );
                View::json(['status' => 'success', 'message' => 'Tipo de egreso actualizado correctamente.', 'codTipo' => $codTipo]);
            } else {
                $nextId = (int)Database::queryForValue(
                    "SELECT COALESCE(MAX(CAST(cod_tipo AS UNSIGNED)), 0) + 1 FROM co_tipos_egreso WHERE id_propietario = ?",
                    [$idProp]
                );
                Database::update(
                    "INSERT INTO co_tipos_egreso (id_propietario, cod_tipo, descripcion, estado, usuario_reg, fec_reg)
                     VALUES (?, ?, ?, ?, ?, NOW())",
                    [$idProp, $nextId, $descripcion, $estado, $usuario]
                );
                View::json(['status' => 'success', 'message' => 'Tipo de egreso registrado exitosamente.', 'codTipo' => $nextId]);
            }
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar tipo de egreso: ' . $e->getMessage()]);
        }
    }

    public static function apiTipoEgresoDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $payload = self::getInput();
        $id = (int)($payload['codTipo'] ?? 0);

        try {
            Database::update("UPDATE co_tipos_egreso SET estado = 'I' WHERE id_propietario = ? AND cod_tipo = ?", [$idProp, $id]);
            View::json(['status' => 'success', 'message' => 'Tipo de egreso inactivado correctamente.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al inactivar: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API TIPOS DE INGRESO (/api/tipo_ingreso/*)
    // =========================================================================
    private static function ensureTipoIngresoSeed(int $idProp): void {
        $cnt = (int)Database::queryForValue("SELECT COUNT(*) FROM co_tipo_ingreso WHERE id_propietario = ?", [$idProp]);
        if ($cnt === 0 && $idProp !== 1) {
            Database::update(
                "INSERT INTO co_tipo_ingreso (id_propietario, cod_tipo_ingreso, descripcion, estado, usuario_reg, fec_reg)
                 SELECT ?, cod_tipo_ingreso, descripcion, estado, 'SYSTEM', NOW() FROM co_tipo_ingreso WHERE id_propietario = 1",
                [$idProp]
            );
        }
    }

    public static function apiTipoIngresoList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        self::ensureTipoIngresoSeed($idProp);
        View::json(Database::queryForListUpper(
            "SELECT CAST(cod_tipo_ingreso AS UNSIGNED) AS cod_tipo_ingreso, descripcion, estado
             FROM co_tipo_ingreso WHERE id_propietario = ? AND estado = 'A' ORDER BY cod_tipo_ingreso ASC",
            [$idProp]
        ));
    }

    public static function apiTipoIngresoAll(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        self::ensureTipoIngresoSeed($idProp);
        View::json(Database::queryForListUpper(
            "SELECT CAST(cod_tipo_ingreso AS UNSIGNED) AS cod_tipo_ingreso, descripcion, estado
             FROM co_tipo_ingreso WHERE id_propietario = ? ORDER BY cod_tipo_ingreso ASC",
            [$idProp]
        ));
    }

    public static function apiTipoIngresoGet(string $id): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $row = Database::queryForMap(
            "SELECT CAST(cod_tipo_ingreso AS UNSIGNED) AS COD_TIPO_INGRESO, descripcion AS DESCRIPCION, estado AS ESTADO
             FROM co_tipo_ingreso WHERE id_propietario = ? AND cod_tipo_ingreso = ? LIMIT 1",
            [$idProp, (int)$id]
        );
        if ($row) {
            View::json(['status' => 'success', 'data' => $row]);
        } else {
            View::json(['status' => 'error', 'message' => 'Tipo de Ingreso no encontrado.']);
        }
    }

    public static function apiTipoIngresoSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();

        $codRaw = trim((string)($payload['codTipoIngreso'] ?? ''));
        $descripcion = strtoupper(trim((string)($payload['descripcion'] ?? '')));
        $estado = trim((string)($payload['estado'] ?? 'A')) ?: 'A';

        if ($descripcion === '') {
            View::json(['status' => 'error', 'message' => 'La descripción es requerida.']);
            return;
        }

        try {
            if ($codRaw === '') {
                $maxId = (int)Database::queryForValue(
                    "SELECT COALESCE(MAX(CAST(cod_tipo_ingreso AS UNSIGNED)), 0) + 1 FROM co_tipo_ingreso WHERE id_propietario = ?",
                    [$idProp]
                );
                Database::update(
                    "INSERT INTO co_tipo_ingreso (id_propietario, cod_tipo_ingreso, descripcion, estado, usuario_reg, fec_reg)
                     VALUES (?, ?, ?, ?, ?, NOW())",
                    [$idProp, $maxId, $descripcion, $estado, $usuario]
                );
                View::json(['status' => 'success', 'message' => "Tipo de Ingreso registrado con éxito (#{$maxId})."]);
            } else {
                $cod = (int)$codRaw;
                Database::update(
                    "UPDATE co_tipo_ingreso SET descripcion = ?, estado = ? WHERE id_propietario = ? AND cod_tipo_ingreso = ?",
                    [$descripcion, $estado, $idProp, $cod]
                );
                View::json(['status' => 'success', 'message' => "Tipo de Ingreso #{$cod} actualizado con éxito."]);
            }
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar Tipo de Ingreso: ' . $e->getMessage()]);
        }
    }

    public static function apiTipoIngresoDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $payload = self::getInput();
        $cod = (int)($payload['codTipoIngreso'] ?? 0);

        try {
            Database::update("DELETE FROM co_tipo_ingreso WHERE id_propietario = ? AND cod_tipo_ingreso = ?", [$idProp, $cod]);
            View::json(['status' => 'success', 'message' => "Tipo de Ingreso #{$cod} eliminado correctamente."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar Tipo de Ingreso: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API TIMBRADOS (/api/timbrados/*)
    // =========================================================================
    public static function apiTimbradosList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $search = trim((string)($_GET['search'] ?? ''));

        try {
            $sql = "SELECT id_timbrado, nro_timbrado, tipo_timbrado,
                           CONCAT(cod_establecimiento, '-', cod_punto_expedicion) AS pe,
                           cod_establecimiento, cod_punto_expedicion,
                           DATE_FORMAT(fec_inicio, '%Y-%m-%d') AS fec_inicio,
                           DATE_FORMAT(fec_vencimiento, '%d/%m/%Y') AS vencimiento_fmt,
                           DATE_FORMAT(fec_vencimiento, '%Y-%m-%d') AS fec_vencimiento,
                           tip_doc, nro_inicial, nro_final, nro_actual,
                           controla_correlatividad, de_facturacion, dar_de_baja, actividades_economicas, estado
                    FROM co_timbrados
                    WHERE id_propietario = ? AND cod_empresa = ? ";
            $params = [$idProp, $empresa];
            if ($search !== '') {
                $sql .= "AND (UPPER(nro_timbrado) LIKE ? OR UPPER(tip_doc) LIKE ? OR UPPER(cod_punto_expedicion) LIKE ?) ";
                $s = '%' . strtoupper($search) . '%';
                $params[] = $s; $params[] = $s; $params[] = $s;
            }
            $sql .= "ORDER BY id_timbrado DESC";
            $list = Database::queryForListUpper($sql, $params);
            View::json(['status' => 'success', 'data' => $list]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al listar timbrados: ' . $e->getMessage()]);
        }
    }

    public static function apiTimbradosSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();

        $nroTimbrado = trim((string)($payload['nroTimbrado'] ?? ''));
        if ($nroTimbrado === '' || !preg_match('/^\d{8}$/', $nroTimbrado)) {
            View::json(['status' => 'error', 'message' => 'El Número de Timbrado debe contener exactamente 8 dígitos numéricos.']);
            return;
        }

        $tipoTimbrado           = trim((string)($payload['tipoTimbrado'] ?? 'Pre-impreso')) ?: 'Pre-impreso';
        $codEstablecimiento     = trim((string)($payload['codEstablecimiento'] ?? '001')) ?: '001';
        $codPuntoExpedicion     = trim((string)($payload['codPuntoExpedicion'] ?? '001')) ?: '001';
        $fecInicio              = trim((string)($payload['fecInicio'] ?? '')) ?: null;
        $fecVencimiento         = trim((string)($payload['fecVencimiento'] ?? '')) ?: null;
        $tipDoc                 = trim((string)($payload['tipDoc'] ?? 'FAC')) ?: 'FAC';
        $nroInicial             = (int)($payload['nroInicial'] ?? 1);
        $nroFinal               = (int)($payload['nroFinal'] ?? 0);
        $controlaCorrelatividad = (!empty($payload['controlaCorrelatividad']) && $payload['controlaCorrelatividad'] !== 'N') ? 'S' : 'N';
        $deFacturacion          = (!empty($payload['deFacturacion']) && $payload['deFacturacion'] !== 'N') ? 'S' : 'N';
        $darDeBaja              = (!empty($payload['darDeBaja']) && $payload['darDeBaja'] !== 'N') ? 'S' : 'N';
        $actividadesEconomicas  = trim((string)($payload['actividadesEconomicas'] ?? '')) ?: null;
        $idRaw                  = trim((string)($payload['idTimbrado'] ?? ''));

        try {
            if ($idRaw === '') {
                $newId = Database::insertGetId(
                    "INSERT INTO co_timbrados (
                        id_propietario, cod_empresa, nro_timbrado, tipo_timbrado,
                        cod_establecimiento, cod_punto_expedicion, fec_inicio, fec_vencimiento,
                        tip_doc, nro_inicial, nro_final, nro_actual,
                        controla_correlatividad, de_facturacion, dar_de_baja, actividades_economicas,
                        estado, usuario_reg, fec_reg
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'A', ?, NOW())",
                    [
                        $idProp, $empresa, $nroTimbrado, $tipoTimbrado,
                        $codEstablecimiento, $codPuntoExpedicion, $fecInicio, $fecVencimiento,
                        $tipDoc, $nroInicial, $nroFinal, $nroInicial,
                        $controlaCorrelatividad, $deFacturacion, $darDeBaja, $actividadesEconomicas,
                        $usuario
                    ]
                );
                View::json(['status' => 'success', 'message' => "Timbrado Nro. {$nroTimbrado} registrado exitosamente.", 'idTimbrado' => $newId]);
            } else {
                $idTimbrado = (int)$idRaw;
                Database::update(
                    "UPDATE co_timbrados SET
                        nro_timbrado = ?, tipo_timbrado = ?, cod_establecimiento = ?, cod_punto_expedicion = ?,
                        fec_inicio = ?, fec_vencimiento = ?, tip_doc = ?, nro_inicial = ?, nro_final = ?,
                        controla_correlatividad = ?, de_facturacion = ?, dar_de_baja = ?, actividades_economicas = ?
                     WHERE id_propietario = ? AND id_timbrado = ? AND cod_empresa = ?",
                    [
                        $nroTimbrado, $tipoTimbrado, $codEstablecimiento, $codPuntoExpedicion,
                        $fecInicio, $fecVencimiento, $tipDoc, $nroInicial, $nroFinal,
                        $controlaCorrelatividad, $deFacturacion, $darDeBaja, $actividadesEconomicas,
                        $idProp, $idTimbrado, $empresa
                    ]
                );
                View::json(['status' => 'success', 'message' => "Timbrado Nro. {$nroTimbrado} actualizado correctamente.", 'idTimbrado' => $idTimbrado]);
            }
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar el timbrado: ' . $e->getMessage()]);
        }
    }

    public static function apiTimbradosDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $empresa = TenantContext::empresa();
        $payload = self::getInput();
        $idTimbrado = (int)($payload['idTimbrado'] ?? 0);

        try {
            Database::update(
                "DELETE FROM co_timbrados WHERE id_propietario = ? AND id_timbrado = ? AND cod_empresa = ?",
                [$idProp, $idTimbrado, $empresa]
            );
            View::json(['status' => 'success', 'message' => 'Timbrado eliminado con éxito.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar timbrado: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API TIPOS DE COMPROBANTES (/api/co_tipos_comprobantes/*)
    // =========================================================================
    public static function apiCoTiposComprobantesList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $modulo = trim((string)($_GET['modulo'] ?? ''));

        $cnt = (int)Database::queryForValue("SELECT COUNT(*) FROM co_tipos_comprobantes WHERE id_propietario = ?", [$idProp]);
        if ($cnt === 0 && $idProp !== 1) {
            Database::update(
                "INSERT INTO co_tipos_comprobantes (id_propietario, tip_comprobante, descripcion, cod_modulo, estado, usuario_reg, fec_reg, tipo)
                 SELECT ?, tip_comprobante, descripcion, cod_modulo, estado, 'SYSTEM', NOW(), tipo
                 FROM co_tipos_comprobantes WHERE id_propietario = 1",
                [$idProp]
            );
        }

        try {
            $sql = "SELECT tip_comprobante, descripcion, COALESCE(cod_modulo, 'AMBOS') AS cod_modulo,
                           COALESCE(tipo, COALESCE(cod_modulo, 'AMBOS')) AS tipo, COALESCE(estado, 'A') AS estado
                    FROM co_tipos_comprobantes
                    WHERE id_propietario = ? AND COALESCE(estado, 'A') = 'A' ";
            $params = [$idProp];
            if ($modulo !== '') {
                $modUpper = strtoupper($modulo);
                if ($modUpper === 'INGRESOS' || $modUpper === 'ING') {
                    $sql .= "AND (UPPER(tipo) IN ('INGRESOS', 'AMBOS') OR UPPER(cod_modulo) IN ('INGRESOS', 'VT', 'CC', 'AMBOS') OR tipo IS NULL) ";
                } elseif ($modUpper === 'EGRESOS' || $modUpper === 'EGR') {
                    $sql .= "AND (UPPER(tipo) IN ('EGRESOS', 'AMBOS') OR UPPER(cod_modulo) IN ('EGRESOS', 'CM', 'CP', 'AMBOS') OR tipo IS NULL) ";
                } else {
                    $sql .= "AND (UPPER(tipo) = ? OR UPPER(tipo) = 'AMBOS' OR UPPER(cod_modulo) = ? OR tipo IS NULL) ";
                    $params[] = $modUpper;
                    $params[] = $modUpper;
                }
            }
            $sql .= "ORDER BY descripcion ASC";
            $list = Database::queryForListUpper($sql, $params);
            View::json(['status' => 'success', 'data' => $list]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al listar tipos de comprobantes: ' . $e->getMessage()]);
        }
    }

    public static function apiCoTiposComprobantesModulos(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $rows = Database::queryForListUpper(
            "SELECT cod_modulo, descripcion FROM modulos WHERE id_propietario = ? AND COALESCE(activo, 'S') = 'S' ORDER BY cod_modulo ASC",
            [$idProp]
        );
        View::json($rows);
    }

    public static function apiCoTiposComprobantesSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();

        $tipComprobante = trim((string)($payload['tipComprobante'] ?? ''));
        $descripcion    = trim((string)($payload['descripcion'] ?? ''));
        $tipo           = strtoupper(trim((string)($payload['tipo'] ?? '')));
        $codModulo      = strtoupper(trim((string)($payload['codModulo'] ?? '')));

        if ($tipComprobante === '') {
            View::json(['status' => 'error', 'message' => 'El nombre del tipo de comprobante es obligatorio.']);
            return;
        }
        if ($descripcion === '') $descripcion = $tipComprobante;
        if ($tipo === '') {
            $tipo = ($codModulo === 'INGRESOS' || $codModulo === 'EGRESOS') ? $codModulo : 'AMBOS';
        }
        if ($codModulo === '') {
            $codModulo = $tipo === 'INGRESOS' ? 'VT' : ($tipo === 'EGRESOS' ? 'CM' : 'AMBOS');
        }

        try {
            $cnt = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM co_tipos_comprobantes WHERE id_propietario = ? AND UPPER(TRIM(tip_comprobante)) = UPPER(?)",
                [$idProp, $tipComprobante]
            );
            if ($cnt > 0) {
                Database::update(
                    "UPDATE co_tipos_comprobantes SET descripcion = ?, cod_modulo = ?, tipo = ?, estado = 'A'
                     WHERE id_propietario = ? AND UPPER(TRIM(tip_comprobante)) = UPPER(?)",
                    [$descripcion, $codModulo, $tipo, $idProp, $tipComprobante]
                );
                $msg = 'Tipo de comprobante actualizado con éxito.';
            } else {
                Database::update(
                    "INSERT INTO co_tipos_comprobantes (id_propietario, tip_comprobante, descripcion, cod_modulo, tipo, estado, usuario_reg, fec_reg)
                     VALUES (?, ?, ?, ?, ?, 'A', ?, NOW())",
                    [$idProp, $tipComprobante, $descripcion, $codModulo, $tipo, substr($usuario, 0, 8)]
                );
                $msg = 'Tipo de comprobante creado exitosamente.';
            }
            View::json(['status' => 'success', 'message' => $msg, 'tipComprobante' => $tipComprobante]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar tipo de comprobante: ' . $e->getMessage()]);
        }
    }

    public static function apiCoTiposComprobantesDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $payload = self::getInput();
        $target = trim((string)($payload['tipComprobante'] ?? ''));

        if ($target === '') {
            View::json(['status' => 'error', 'message' => 'El tipo de comprobante a eliminar no es válido.']);
            return;
        }
        try {
            Database::update(
                "DELETE FROM co_tipos_comprobantes WHERE id_propietario = ? AND UPPER(TRIM(tip_comprobante)) = UPPER(?)",
                [$idProp, $target]
            );
            View::json(['status' => 'success', 'message' => "Tipo de comprobante '{$target}' eliminado con éxito."]);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al eliminar tipo de comprobante: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // API REGÍMENES (/api/regimen/*)
    // =========================================================================
    public static function apiRegimenList(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $cnt = (int)Database::queryForValue("SELECT COUNT(*) FROM co_regimen WHERE id_propietario = ?", [$idProp]);
        if ($cnt === 0 && $idProp !== 1) {
            Database::update(
                "INSERT INTO co_regimen (id_propietario, cod_regimen, descripcion, imputa_iva, imputa_irp, imputa_ire, estado, usuario_reg, fec_reg)
                 SELECT ?, cod_regimen, descripcion, imputa_iva, imputa_irp, imputa_ire, estado, 'SYSTEM', NOW()
                 FROM co_regimen WHERE id_propietario = 1",
                [$idProp]
            );
        }
        $rows = Database::queryForListUpper(
            "SELECT CAST(cod_regimen AS UNSIGNED) AS cod_regimen, descripcion, imputa_iva, imputa_irp, imputa_ire, estado,
                    DATE_FORMAT(fec_reg, '%Y-%m-%d %H:%i') AS fec_reg
             FROM co_regimen
             WHERE id_propietario = ?
             ORDER BY cod_regimen ASC",
            [$idProp]
        );
        View::json($rows);
    }

    public static function apiRegimenSave(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $usuario = TenantContext::usuario();
        $payload = self::getInput();

        $idRaw       = trim((string)($payload['codRegimen'] ?? ''));
        $descripcion = strtoupper(trim((string)($payload['descripcion'] ?? '')));
        $imputaIva   = trim((string)($payload['imputaIva'] ?? 'S')) ?: 'S';
        $imputaIrp   = trim((string)($payload['imputaIrp'] ?? 'N')) ?: 'N';
        $imputaIre   = trim((string)($payload['imputaIre'] ?? 'N')) ?: 'N';
        $estado      = trim((string)($payload['estado'] ?? 'A')) ?: 'A';

        if ($descripcion === '') {
            View::json(['status' => 'error', 'message' => 'La descripción del régimen es obligatoria.']);
            return;
        }

        try {
            if ($idRaw === '') {
                $nextId = (int)Database::queryForValue(
                    "SELECT COALESCE(MAX(CAST(cod_regimen AS UNSIGNED)), 0) + 1 FROM co_regimen WHERE id_propietario = ?",
                    [$idProp]
                );
                Database::update(
                    "INSERT INTO co_regimen (id_propietario, cod_regimen, descripcion, imputa_iva, imputa_irp, imputa_ire, estado, usuario_reg, fec_reg)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$idProp, $nextId, $descripcion, $imputaIva, $imputaIrp, $imputaIre, $estado, $usuario]
                );
                View::json(['status' => 'success', 'message' => 'Régimen creado correctamente.', 'codRegimen' => $nextId]);
            } else {
                $codRegimen = (int)$idRaw;
                Database::update(
                    "UPDATE co_regimen SET descripcion = ?, imputa_iva = ?, imputa_irp = ?, imputa_ire = ?, estado = ?, usuario_reg = ?
                     WHERE id_propietario = ? AND cod_regimen = ?",
                    [$descripcion, $imputaIva, $imputaIrp, $imputaIre, $estado, $usuario, $idProp, $codRegimen]
                );
                View::json(['status' => 'success', 'message' => 'Régimen actualizado correctamente.', 'codRegimen' => $codRegimen]);
            }
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al guardar el régimen: ' . $e->getMessage()]);
        }
    }

    public static function apiRegimenDelete(): void {
        if (!self::requireAuth()) return;
        $idProp = TenantContext::idPropietario();
        $payload = self::getInput();
        $codRegimen = (int)($payload['codRegimen'] ?? 0);

        try {
            Database::update("UPDATE co_regimen SET estado = 'I' WHERE id_propietario = ? AND cod_regimen = ?", [$idProp, $codRegimen]);
            View::json(['status' => 'success', 'message' => 'Régimen desactivado correctamente.']);
        } catch (\Throwable $e) {
            View::json(['status' => 'error', 'message' => 'Error al desactivar el régimen: ' . $e->getMessage()]);
        }
    }
}
