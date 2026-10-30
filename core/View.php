<?php
namespace Core;

use App\Services\MenuService;

class View {

    public static function json(mixed $data, int $statusCode = 200): void {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public static function renderTemplate(string $viewName, array $model = []): void {
        self::render($viewName, $model);
    }

    public static function render(string $viewName, array $model = []): void {
        $viewsDir = __DIR__ . '/../views';

        // Contexto global disponible en todas las vistas
        $usuario       = $_SESSION['usuario'] ?? null;
        $nombrePersona = $_SESSION['nombrePersona'] ?? ($usuario ?: 'Usuario');
        $empresa       = $_SESSION['empresa'] ?? '';
        $empresaDesc   = $_SESSION['empresaDesc'] ?? $empresa;
        $sucursal      = $_SESSION['sucursal'] ?? '';
        $sucursalDesc  = $_SESSION['sucursalDesc'] ?? $sucursal;
        $ejercicio     = $_SESSION['ejercicio'] ?? date('Y');
        $fecInicio     = $_SESSION['fecInicio'] ?? '';
        $fecFinal      = $_SESSION['fecFinal'] ?? '';
        $actividad     = $_SESSION['actividad'] ?? 'COMERCIAL';
        $modulos       = $usuario ? MenuService::getMenuForUsuario($usuario) : [];
        $planSummary   = $usuario ? TenantContext::getPlanSummary() : null;
        $esSuperAdmin  = TenantContext::esSuperAdmin();

        // Si el propietario fue suspendido o venció su período de gracia teniendo sesión abierta (y no es SuperAdmin), cerrar sesión
        if ($usuario && !$esSuperAdmin && !empty($planSummary['bloqueado_por_vencimiento']) && !in_array($viewName, ['login', 'landing'], true)) {
            session_unset();
            session_destroy();
            header('Location: /login');
            exit;
        }

        $model['modulos']       = $modulos;
        $model['planSummary']   = $planSummary;
        $model['esSuperAdmin']  = $esSuperAdmin;
        $model['currentYear']   = (int)date('Y');
        $model['session']       = $_SESSION;

        // 1. Si existe vista nativa PHP (.php), ejecutarla directamente
        $phpFile = $viewsDir . '/' . $viewName . '.php';
        if (is_file($phpFile)) {
            extract($model, EXTR_OVERWRITE);
            require $phpFile;
            return;
        }

        // 2. Si existe plantilla HTML (.html), compilar fragmentos de layout y expresiones Thymeleaf
        $htmlFile = $viewsDir . '/' . $viewName . '.html';
        if (!is_file($htmlFile)) {
            http_response_code(404);
            echo "Vista no encontrada: " . htmlspecialchars($viewName);
            return;
        }

        $html = file_get_contents($htmlFile);

        // Reemplazar fragmento head
        $html = preg_replace_callback(
            '/<head[^>]*th:replace="~\{fragments\/layout\s*::\s*head\(\'([^\']*)\'\)\}"[^>]*>\s*<\/head>/is',
            fn($m) => self::renderHeadFragment($m[1]),
            $html
        );

        // Reemplazar fragmento sidebar
        $html = preg_replace_callback(
            '/<(div|aside)[^>]*th:replace="~\{fragments\/layout\s*::\s*sidebar\}"[^>]*>\s*<\/\1>/is',
            fn($m) => self::renderSidebarFragment($modulos, $planSummary, $esSuperAdmin),
            $html
        );

        // Reemplazar fragmento navbar
        $html = preg_replace_callback(
            '/<(div|header)[^>]*th:replace="~\{fragments\/layout\s*::\s*navbar\(\'([^\']*)\'\)\}"[^>]*>\s*<\/\1>/is',
            fn($m) => self::renderNavbarFragment($m[2], $planSummary, $esSuperAdmin),
            $html
        );

        // Reemplazar fragmento scripts
        $scriptsReplaced = 0;
        $html = preg_replace_callback(
            '/<div[^>]*th:replace="~\{fragments\/layout\s*::\s*scripts\}"[^>]*>\s*<\/div>/is',
            fn($m) => self::renderScriptsFragment(),
            $html,
            -1,
            $scriptsReplaced
        );
        // Si la vista no incluyó explícitamente el fragmento scripts antes de </body>, inyectarlo automáticamente
        if ($scriptsReplaced === 0 && stripos($html, '</body>') !== false) {
            $html = preg_replace('/<\/body>/i', self::renderScriptsFragment() . "\n</body>", $html, 1);
        }

        // Si estamos en contribuyentes.html o usuarios.html, inyectar barra visual del Plan (tope de contribuyentes/usuarios)
        if ($viewName === 'contribuyentes' && $planSummary) {
            $banner = self::renderPlanBanner('contribuyentes', $planSummary);
            $html = preg_replace('/(<div class="content-area"[^>]*>)/i', '$1' . "\n" . $banner, $html, 1);
        } elseif ($viewName === 'usuarios' && $planSummary) {
            $banner = self::renderPlanBanner('usuarios', $planSummary);
            $html = preg_replace('/(<div class="content-area"[^>]*>)/i', '$1' . "\n" . $banner, $html, 1);
        }

        // Inyectar aviso de vencimiento próximo o período de gracia en todas las pantallas si corresponde
        if ($planSummary && !empty($planSummary['mostrar_aviso_pago'])) {
            $subAlert = self::renderSubscriptionAlertBanner($planSummary);
            $html = preg_replace('/(<div class="content-area"[^>]*>)/i', '$1' . "\n" . $subAlert, $html, 1);
        }

        // Reemplazar th:href="@{/...}" y th:src="@{/...}" y th:content="@{/}"
        $html = preg_replace('/\bth:(href|src|content)="\@\{([^}]*)\}"/i', '$1="$2"', $html);

        // Reemplazar /*[[${var}]]*/ defaultVal en bloques JS
        $html = preg_replace_callback(
            '/\/\*\\[\\[\\$\\{([^}]+)\\}\\]\\]\*\/\s*(null|\'\'|"[^"]*"|\'[^\']*\'|[0-9]+)?/m',
            function ($m) use ($model) {
                $val = self::evalExpr(trim($m[1]), $model);
                return json_encode($val, JSON_UNESCAPED_UNICODE);
            },
            $html
        );

        // Reemplazar '[[${var}]]' o "[[${var}]]" directos en bloques JS
        $html = preg_replace_callback(
            '/([\'"])\\[\\[\\$\\{([^}]+)\\}\\]\\]\1/m',
            function ($m) use ($model) {
                $val = (string)self::evalExpr(trim($m[2]), $model);
                return json_encode($val, JSON_UNESCAPED_UNICODE);
            },
            $html
        );

        // Reemplazar [[${var}]] restantes en el HTML
        $html = preg_replace_callback(
            '/\\[\\[\\$\\{([^}]+)\\}\\]\\]/m',
            function ($m) use ($model) {
                $val = (string)self::evalExpr(trim($m[1]), $model);
                return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            },
            $html
        );

        // Reemplazar th:value="${...}", th:min="${...}", th:max="${...}"
        foreach (['value', 'min', 'max'] as $attr) {
            $html = preg_replace_callback(
                '/\bth:' . $attr . '="\$\{([^}]+)\}"/i',
                function ($m) use ($attr, $model) {
                    $val = (string)self::evalExpr(trim($m[1]), $model);
                    return $attr . '="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"';
                },
                $html
            );
        }

        // Reemplazar th:text="${...}" dentro de etiquetas <tag ... th:text="${...}">contenido default</tag>
        $html = preg_replace_callback(
            '/(<([a-zA-Z0-9]+)\b[^>]*?)\s+th:text="\$\{([^}]+)\}"([^>]*>)(.*?)(<\/\2>)/is',
            function ($m) use ($model) {
                $val = (string)self::evalExpr(trim($m[3]), $model);
                return $m[1] . $m[4] . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . $m[6];
            },
            $html
        );

        echo $html;
    }

    public static function renderHeadFragment(string $title): string {
        $layoutFile = __DIR__ . '/../views/fragments/layout.html';
        $raw = file_get_contents($layoutFile);
        if (preg_match('/<head[^>]*>(.*?)<\/head>/is', $raw, $m)) {
            $inner = $m[1];
            $inner = preg_replace('/<title[^>]*>.*?<\/title>/is', '<title>' . htmlspecialchars($title) . ' | ContaPro</title>', $inner);
            $inner = preg_replace('/\bth:(href|src)="\@\{([^}]*)\}"/i', '$1="$2"', $inner);
            return "<head>\n" . $inner . "\n</head>";
        }
        return "<head><meta charset=\"UTF-8\"><title>" . htmlspecialchars($title) . "</title></head>";
    }

    public static function renderSidebarFragment(array $modulos, ?array $planSummary, bool $esSuperAdmin): string {
        ob_start();
        ?>
        <aside id="appSidebar" class="app-sidebar">
            <div class="sidebar-brand" style="padding: 0 0.85rem; justify-content: space-between; gap: 8px;">
                <a href="/menu" class="brand-logo" style="display: flex; align-items: center; justify-content: center; flex: 1; min-width: 0;">
                    <img src="/images/logo_contapro.png" alt="ContaPro - Sistema Contable" style="height: 44px; max-width: 100%; width: auto; object-fit: contain;">
                </a>
                <button type="button" class="sidebar-toggle-btn" onclick="toggleSidebarCollapse()" title="Ocultar menú lateral" style="width: 36px; height: 36px; border: 1px solid #cbd5e1; background: #f8fafc; color: #0f172a; border-radius: 8px; flex-shrink: 0;">
                    <i class="ph ph-list" style="font-size: 1.25rem;"></i>
                </button>
            </div>

            <div style="padding: 10px 12px; background: #334155; border-bottom: 1px solid rgba(255,255,255,0.1); flex-shrink: 0;">
                <div style="position: relative; display: flex; align-items: center;">
                    <input type="text" id="inlineSidebarSearch" placeholder="Buscar..." oninput="onInlineSidebarSearchInput()" style="width: 100%; padding: 8px 32px 8px 12px; border-radius: 4px; border: 1px solid #475569; background: #ffffff; color: #0f172a; font-size: 0.88rem; font-weight: 600; outline: none;" autocomplete="off">
                    <button type="button" id="inlineSidebarSearchClear" onclick="clearInlineSidebarSearch()" style="position: absolute; right: 4px; background: #cbd5e1; border: none; width: 22px; height: 22px; border-radius: 4px; display: none; align-items: center; justify-content: center; cursor: pointer; color: #1e293b; font-weight: bold; font-size: 0.9rem;" title="Limpiar búsqueda">&times;</button>
                </div>
            </div>

            <div class="sidebar-nav-container">
                <div id="inlineSidebarSearchResults" style="display: none; flex-direction: column; gap: 6px;">
                    <div onclick="clearInlineSidebarSearch()" style="padding: 8px 12px; background: #dcfce7; color: #15803d; border-radius: 6px; font-weight: 800; font-size: 0.82rem; cursor: pointer; display: flex; align-items: center; gap: 8px; margin-bottom: 4px; text-transform: uppercase; border: 1px solid #86efac;">
                        <i class="ph ph-house" style="font-size: 1.1rem; color: #15803d;"></i>
                        <span>INICIO</span>
                    </div>
                    <div id="inlineSidebarSearchResultsList" style="display: flex; flex-direction: column; gap: 6px;"></div>
                </div>

                <div id="inlineSidebarAccordionList">
                    <?php foreach ($modulos as $modulo):
                        $descUpper = mb_strtoupper($modulo['descripcion'], 'UTF-8');
                        $iconClass = 'ph-folder';
                        if (str_contains($descUpper, 'CONTABILIDAD')) $iconClass = 'ph-calculator';
                        elseif (str_contains($descUpper, 'ADMINISTRA')) $iconClass = 'ph-buildings';
                    ?>
                    <div class="sidebar-module-group" id="mod-group-<?= htmlspecialchars($modulo['codModulo']) ?>">
                        <div class="sidebar-module-header" onclick="toggleSidebarModule(this)">
                            <div class="sidebar-module-title">
                                <i class="ph <?= $iconClass ?>"></i>
                                <span style="text-transform: uppercase; font-weight: 800;"><?= htmlspecialchars($descUpper) ?></span>
                            </div>
                            <i class="ph ph-caret-down accordion-arrow"></i>
                        </div>

                        <div class="sidebar-module-body">
                            <?php
                            $sections = [
                                'Definiciones' => $modulo['definiciones'] ?? [],
                                'Movimientos'  => $modulo['movimientos'] ?? [],
                                'Consultas'    => $modulo['consultas'] ?? [],
                                'Reportes'     => $modulo['reportes'] ?? [],
                            ];
                            foreach ($sections as $secTitle => $items):
                                if (empty($items)) continue;
                            ?>
                            <div class="sidebar-section section-collapsed">
                                <div class="sidebar-section-title" onclick="toggleSidebarSection(this)">
                                    <span><?= $secTitle ?></span>
                                    <i class="ph ph-caret-down section-arrow"></i>
                                </div>
                                <div class="sidebar-item-container" style="display: flex; flex-direction: column; gap: 2px;">
                                    <?php foreach ($items as $acceso): ?>
                                    <a href="/app/<?= htmlspecialchars($acceso['route']) ?>" class="sidebar-item">
                                        <i class="ph ph-caret-right"></i>
                                        <span><?= htmlspecialchars($acceso['itemMenu']) ?></span>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php echo self::extractSidebarInlineScript(); ?>
        </aside>
        <div id="sidebarMobileBackdrop" class="sidebar-mobile-backdrop" onclick="toggleSidebarCollapse()"></div>
        <?php
        return ob_get_clean();
    }

    public static function renderNavbarFragment(string $pageTitle, ?array $planSummary, bool $esSuperAdmin): string {
        $usuario       = $_SESSION['usuario'] ?? 'Usuario';
        $nombrePersona = $_SESSION['nombrePersona'] ?? $usuario;
        $empresa       = $_SESSION['empresa'] ?? '';
        $empresaDesc   = $_SESSION['empresaDesc'] ?? $empresa;
        $sucursal      = $_SESSION['sucursal'] ?? '';
        $sucursalDesc  = $_SESSION['sucursalDesc'] ?? $sucursal;
        $ejercicio     = $_SESSION['ejercicio'] ?? '';
        $avatarChar    = mb_strtoupper(mb_substr(trim($nombrePersona ?: $usuario), 0, 1, 'UTF-8'), 'UTF-8');
        $roleText      = $empresaDesc . ($sucursalDesc ? ' - ' . $sucursalDesc : '') . ($ejercicio ? ' | Ejercicio: ' . $ejercicio : '');

        $cleanTitle = trim((string)preg_replace('/\s*\([A-Z0-9_]+\)\s*$/', '', $pageTitle));

        ob_start();
        ?>
        <header class="top-navbar">
            <div class="top-navbar-left">
                <div class="navbar-collapsed-brand">
                    <a href="/menu" style="display: flex; align-items: center; text-decoration: none;">
                        <img src="/images/logo_contapro.png" alt="ContaPro" style="height: 40px; width: auto; object-fit: contain;">
                    </a>
                    <button type="button" class="sidebar-toggle-btn" onclick="toggleSidebarCollapse()" title="Mostrar menú lateral" style="width: 36px; height: 36px; border: 1px solid #cbd5e1; background: #f8fafc; color: #0f172a; border-radius: 8px; flex-shrink: 0;">
                        <i class="ph ph-list" style="font-size: 1.25rem;"></i>
                    </button>
                </div>
                <div id="bcpNavbarTicker" class="bcp-navbar-ticker" title="Tipo de Cambio Referencial Fluctuante - Banco Central del Paraguay">
                    <span class="bcp-ticker-badge">
                        <span class="bcp-bill-icon">$</span>
                        <span>BCP &middot; USD/PYG</span>
                    </span>
                    <span class="bcp-rate-item">
                        <span class="bcp-rate-label">COMPRA:</span>
                        <strong class="bcp-rate-val" id="bcpNavbarCompra">GS. ---</strong>
                    </span>
                    <span class="bcp-rate-item">
                        <span class="bcp-rate-label">VENTA:</span>
                        <strong class="bcp-rate-val" id="bcpNavbarVenta">GS. ---</strong>
                    </span>
                    <span class="bcp-rate-time" id="bcpNavbarFechaHora">CARGANDO...</span>
                    <a href="https://www.bcp.gov.py/webapps/web/cotizacion/referencial-fluctuante" target="_blank" rel="noopener noreferrer" class="bcp-detail-btn">
                        VER DETALLE &#8599;
                    </a>
                </div>
                <script>
                    (function () {
                        const CACHE_KEY = 'contapro_bcp_ticker_v1';
                        const elC = document.getElementById('bcpNavbarCompra');
                        const elV = document.getElementById('bcpNavbarVenta');
                        const elF = document.getElementById('bcpNavbarFechaHora');
                        if (!elC || !elV) return;
                        function paint(d) {
                            if (!d || !d.compraFormatted || !d.ventaFormatted) return;
                            elC.textContent = 'GS. ' + d.compraFormatted;
                            elV.textContent = 'GS. ' + d.ventaFormatted;
                            if (elF) {
                                const h = d.hora || '13:00';
                                const f = d.fechaText || '';
                                elF.textContent = f ? (h + ' \u00b7 ' + f) : h;
                            }
                        }
                        try {
                            const raw = sessionStorage.getItem(CACHE_KEY);
                            if (raw) {
                                const parsed = JSON.parse(raw);
                                if (parsed && parsed.data) {
                                    paint(parsed.data);
                                    if (parsed.ts && (Date.now() - parsed.ts) < 600000) return;
                                }
                            }
                        } catch (e) {}
                        fetch('/api/cotizaciones/bcp-actual')
                            .then(function (r) { return r.json(); })
                            .then(function (d) {
                                if (d && d.status === 'success') {
                                    paint(d);
                                    try { sessionStorage.setItem(CACHE_KEY, JSON.stringify({ ts: Date.now(), data: d })); } catch (e) {}
                                }
                            })
                            .catch(function () {});
                    })();
                </script>
            </div>

            <div class="user-info">
                <div class="nav-btn-search" style="background: rgba(22, 163, 74, 0.08); border: 1px solid rgba(22, 163, 74, 0.25); padding: 5px 11px; border-radius: 8px; font-size: 0.78rem; color: #15803d; font-weight: 700; display: flex; align-items: center; gap: 5px; cursor: pointer; transition: all 0.2s; white-space: nowrap;" onclick="openGlobalSearchModal()" title="Buscar pantalla (Ctrl+F2)">
                    <i class="ph ph-magnifying-glass" style="font-size: 0.92rem;"></i> <span class="nav-btn-text">Buscar (Ctrl+F2)</span>
                </div>

                <?php if ($ejercicio !== ''): ?>
                <div class="nav-badge-ejercicio" style="background: #f0fdf4; border: 1px solid #86efac; padding: 5px 11px; border-radius: 8px; font-size: 0.78rem; color: #15803d; font-weight: 700; display: flex; align-items: center; gap: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); white-space: nowrap;" title="Ejercicio Fiscal Activo">
                    <i class="ph ph-calendar-blank" style="font-size: 1rem; color: #16a34a;"></i>
                    <span><span class="nav-ejercicio-label">Ejercicio: </span><strong><?= htmlspecialchars($ejercicio) ?></strong></span>
                </div>
                <?php endif; ?>

                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars($nombrePersona) ?></span>
                    <span class="user-role"><?= htmlspecialchars($roleText) ?></span>
                </div>
                <div class="avatar"><?= htmlspecialchars($avatarChar) ?></div>
                <a href="/cambiar-contribuyente" class="btn-logout btn-cambiar-contrib" title="Cambiar Contribuyente"
                   style="color:#15803d; border-color:#bbf7d0; background:#f0fdf4;">
                    <i class="ph ph-buildings"></i> <span class="nav-btn-text">Cambiar Contribuyente</span>
                </a>
                <a href="/logout" class="btn-logout btn-salir" title="Cerrar sesión">
                    <i class="ph ph-sign-out"></i> <span class="nav-btn-text">Salir</span>
                </a>
            </div>
            <?php echo self::extractGlobalSearchModal(); ?>
        </header>
        <?php if ($cleanTitle !== '' && mb_strtolower($cleanTitle, 'UTF-8') !== 'inicio'): ?>
        <div class="page-title-bar">
            <div class="page-title-inner">
                <span class="page-title-accent"></span>
                <h2 class="page-title"><?= htmlspecialchars($cleanTitle) ?></h2>
            </div>
        </div>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    public static function renderScriptsFragment(): string {
        $layoutFile = __DIR__ . '/../views/fragments/layout.html';
        $raw = file_get_contents($layoutFile);
        if (preg_match('/<div th:fragment="scripts">(.*?)<\/div>\s*<\/body>/is', $raw, $m)) {
            return "<div>\n" . $m[1] . "\n</div>";
        }
        return '';
    }

    private static function extractSidebarInlineScript(): string {
        $layoutFile = __DIR__ . '/../views/fragments/layout.html';
        $raw = file_get_contents($layoutFile);
        if (preg_match('/(<script>\s*if\s*\(typeof\s+toggleSidebarModule.*?<\/script>)\s*<\/aside>/is', $raw, $m)) {
            return $m[1];
        }
        return '';
    }

    private static function extractGlobalSearchModal(): string {
        $layoutFile = __DIR__ . '/../views/fragments/layout.html';
        $raw = file_get_contents($layoutFile);
        if (preg_match('/(<!-- Modal Buscador Global.*?<\/div>\s*<\/div>\s*<\/div>)\s*<\/header>/is', $raw, $m)) {
            return $m[1];
        }
        return '';
    }

    private static function renderPlanBanner(string $type, array $plan): string {
        $propName = htmlspecialchars($plan['nombre_propietario'] ?? '');
        $planName = htmlspecialchars($plan['nombre_plan'] ?? 'Plan Básico');
        if ($type === 'contribuyentes') {
            $used = (int)$plan['usados_contribuyentes'];
            $max  = (int)$plan['max_contribuyentes'];
            $pct  = $max > 0 ? min(100, round(($used / $max) * 100)) : 100;
            $color = $used >= $max ? '#ef4444' : '#16a34a';
            $label = "Contribuyentes registrados: <strong>$used de $max</strong> permitidos en su <strong>$planName</strong>";
        } else {
            $used = (int)$plan['usados_usuarios'];
            $max  = (int)$plan['max_usuarios'];
            $pct  = $max > 0 ? min(100, round(($used / $max) * 100)) : 100;
            $color = $used >= $max ? '#ef4444' : '#16a34a';
            $label = "Usuarios registrados: <strong>$used de $max</strong> permitidos en su <strong>$planName</strong>";
        }

        return <<<HTML
        <div style="background: #ffffff; border: 1px solid #cbd5e1; border-left: 5px solid {$color}; border-radius: 12px; padding: 12px 20px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: #f0fdf4; color: {$color}; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                    <i class="ph ph-shield-check"></i>
                </div>
                <div>
                    <div style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Propietario: {$propName}</div>
                    <div style="font-size: 0.9rem; color: #0f172a;">{$label}</div>
                </div>
            </div>
            <div style="min-width: 180px;">
                <div style="height: 8px; background: #e2e8f0; border-radius: 99px; overflow: hidden;">
                    <div style="width: {$pct}%; height: 100%; background: {$color}; border-radius: 99px;"></div>
                </div>
            </div>
        </div>
HTML;
    }

    public static function renderSubscriptionAlertBanner(array $plan): string {
        if (empty($plan['mostrar_aviso_pago'])) {
            return '';
        }
        $isDanger  = ($plan['nivel_aviso_pago'] ?? 'warning') === 'danger';
        $bg        = $isDanger ? '#fef2f2' : '#fffbeb';
        $border    = $isDanger ? '#ef4444' : '#f59e0b';
        $textColor = $isDanger ? '#991b1b' : '#92400e';
        $iconBg    = $isDanger ? '#fee2e2' : '#fef3c7';
        $iconColor = $isDanger ? '#dc2626' : '#d97706';
        $btnBg     = $isDanger ? '#dc2626' : '#d97706';

        $titulo    = htmlspecialchars((string)($plan['titulo_aviso_pago'] ?? 'Aviso de Suscripción'));
        $texto     = htmlspecialchars((string)($plan['texto_aviso_pago'] ?? ''));
        $planNom   = (string)($plan['nombre_plan'] ?? 'Plan');
        $propNom   = (string)($plan['nombre_propietario'] ?? '');
        $vencFmt   = (string)($plan['fec_vencimiento_fmt'] ?? '');
        $precioGs  = number_format((float)($plan['precio_mensual'] ?? 0), 0, ',', '.');

        if (!empty($plan['whatsapp_url'])) {
            $waUrl = htmlspecialchars((string)$plan['whatsapp_url']);
        } else {
            $sysCfg = TenantContext::getSystemConfig();
            $waPhone = $sysCfg['whatsapp_soporte'] ?? '+595 982 965 515';
            $waText = "¡Hola! Deseo renovar mi suscripción de ContaPro"
                    . ($propNom !== '' ? " (Cuenta: {$propNom})" : "")
                    . " - {$planNom} (Gs. {$precioGs}/mes, vencimiento: {$vencFmt}).";
            $waUrl  = htmlspecialchars(TenantContext::formatWhatsappLink($waPhone, $waText));
        }

        return <<<HTML
        <div style="width: 100%; max-width: 1200px; background: {$bg}; border: 1px solid {$border}; border-left: 6px solid {$border}; border-radius: 12px; padding: 12px 18px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
            <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 260px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: {$iconBg}; color: {$iconColor}; display: flex; align-items: center; justify-content: center; font-size: 1.45rem; flex-shrink: 0;">
                    <i class="ph ph-warning-circle"></i>
                </div>
                <div>
                    <div style="font-size: 0.9rem; font-weight: 800; color: {$textColor};">{$titulo}</div>
                    <div style="font-size: 0.82rem; color: {$textColor}; opacity: 0.92;">{$texto}</div>
                </div>
            </div>
            <a href="{$waUrl}" target="_blank" rel="noopener" style="background: {$btnBg}; color: #ffffff; text-decoration: none; border: none; border-radius: 8px; padding: 8px 14px; font-size: 0.8rem; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.12);">
                <i class="ph ph-whatsapp-logo" style="font-size: 1.05rem;"></i> Contactar para Renovar
            </a>
        </div>
HTML;
    }

    private static function evalExpr(string $expr, array $model) {
        if ($expr === 'currentYear') return (int)date('Y');
        if ($expr === 'currentYear - 1') return (int)date('Y') - 1;
        if ($expr === 'session.empresaDesc') return $_SESSION['empresaDesc'] ?? ($_SESSION['empresa'] ?? '');
        if ($expr === 'session.ejercicio') return $_SESSION['ejercicio'] ?? date('Y');
        if (str_contains($expr, 'session.usuario')) return $_SESSION['usuario'] ?? 'USUARIO';
        if (str_contains($expr, 'contribuyenteNombre')) {
            return $model['contribuyenteNombre'] ?? ($model['nomEmpresa'] ?? ($model['empresaDesc'] ?? ($_SESSION['empresaDesc'] ?? '')));
        }
        if ($expr === 'empresa.rucNum + \'-\' + empresa.rucDv') {
            $emp = $model['empresa'] ?? [];
            return ($emp['rucNum'] ?? '') . '-' . ($emp['rucDv'] ?? '');
        }
        if ($expr === 'empresa.razon_social') {
            return $model['empresa']['razon_social'] ?? '';
        }
        if ($expr === 'empresaRuc + \' - \' + empresaNombre') {
            return ($model['empresaRuc'] ?? '') . ' - ' . ($model['empresaNombre'] ?? '');
        }
        if (str_starts_with($expr, '#strings.substring(currentDate')) {
            return substr((string)($model['currentDate'] ?? date('Y-m-d')), 0, 4);
        }
        return $model[$expr] ?? '';
    }
}
