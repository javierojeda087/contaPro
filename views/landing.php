<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ContaPro | Sistema de Gestión, Facturación y Contabilidad en la Nube</title>
    <link rel="icon" type="image/png" href="/images/favicon_cp.png?v=2" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --brand-blue: #1d8cf8;
            --brand-dark: #0f294a;
            --brand-green: #16a34a;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html {
            scroll-behavior: smooth;
            scroll-padding-top: 78px;
        }
        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
            background: #ffffff;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* =========================================================
           ANIMACIONES DE ENTRADA Y REVELADO (HERO, SCROLL Y TABS)
           ========================================================= */
        .anim-item {
            opacity: 0;
            transform: translateY(38px) scale(0.985);
            filter: blur(5px);
            transition:
                opacity 0.85s cubic-bezier(0.22, 1, 0.36, 1),
                transform 0.85s cubic-bezier(0.22, 1, 0.36, 1),
                filter 0.85s cubic-bezier(0.22, 1, 0.36, 1);
            will-change: opacity, transform, filter;
        }
        .anim-item.no-transition {
            transition: none !important;
        }
        .anim-item.is-visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            filter: blur(0);
        }
        .anim-delay-1 { transition-delay: 0.18s; }
        .anim-delay-2 { transition-delay: 0.38s; }
        .anim-delay-3 { transition-delay: 0.54s; }
        .anim-delay-4 { transition-delay: 0.70s; }

        /* Top Navbar (Estilo Ábaco) */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #ffffff;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
            height: 78px;
            display: flex;
            align-items: center;
        }
        .nav-container {
            max-width: 1220px;
            width: 100%;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand-link {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .brand-link img {
            height: 46px;
            width: auto;
            object-fit: contain;
        }
        .nav-menu {
            display: flex;
            align-items: center;
            gap: 32px;
            list-style: none;
        }
        .nav-menu a {
            text-decoration: none;
            color: #475569;
            font-weight: 500;
            font-size: 0.96rem;
            padding: 26px 2px;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }
        .nav-menu a:hover,
        .nav-menu a.active {
            color: #0f172a;
            font-weight: 600;
            border-bottom-color: #16a34a;
        }

        /* Botón Ingresar */
        .ingresar-wrapper {
            position: relative;
        }
        .btn-ingresar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 22px;
            border: 1.5px solid #16a34a;
            border-radius: 6px;
            background: #ffffff;
            color: #15803d;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-ingresar:hover {
            border-color: #15803d;
            color: #ffffff;
            background: #16a34a;
        }

        /* Hero Section */
        .hero-section {
            position: relative;
            min-height: 540px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #ffffff;
            padding: 80px 24px;
            background:
                linear-gradient(135deg, rgba(6, 78, 59, 0.86) 0%, rgba(21, 128, 61, 0.80) 55%, rgba(22, 163, 74, 0.75) 100%),
                url('/images/hero_contapro.jpg?v=2') center/cover no-repeat;
        }
        .hero-content {
            max-width: 820px;
            z-index: 2;
        }
        .hero-content h1 {
            font-size: clamp(2.1rem, 4.2vw, 3.25rem);
            font-weight: 800;
            line-height: 1.18;
            margin-bottom: 22px;
            letter-spacing: -0.02em;
        }
        .hero-content p {
            font-size: 1.08rem;
            color: rgba(255, 255, 255, 0.92);
            max-width: 720px;
            margin: 0 auto 34px auto;
        }
        .btn-hero {
            display: inline-block;
            background: #16a34a;
            color: #ffffff;
            font-weight: 600;
            font-size: 1rem;
            padding: 14px 34px;
            border-radius: 6px;
            text-decoration: none;
            box-shadow: 0 10px 25px rgba(22, 163, 74, 0.35);
            transition: all 0.2s;
        }
        .btn-hero:hover {
            background: #15803d;
            transform: translateY(-2px);
        }

        /* Sección Soluciones */
        .section {
            padding: 72px 24px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .section-title {
            text-align: center;
            margin-bottom: 48px;
        }
        .section-title h2 {
            font-size: 2rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 10px;
        }
        .section-title p {
            color: #64748b;
            font-size: 1.02rem;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(265px, 1fr));
            gap: 24px;
        }
        .feature-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px 24px;
            transition: all 0.2s;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
        }
        .feature-card:hover {
            transform: translateY(-4px);
            border-color: #86efac;
            box-shadow: 0 16px 32px rgba(22, 163, 74, 0.1);
        }
        .feature-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: #f0fdf4;
            color: #16a34a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 16px;
        }
        .feature-card h3 {
            font-size: 1.12rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #0f172a;
        }
        .feature-card p {
            font-size: 0.9rem;
            color: #64748b;
        }

        /* Sección Planes */
        .planes-bg {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        .planes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 28px;
            align-items: stretch;
        }
        .plan-card {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 20px;
            padding: 32px 28px;
            display: flex;
            flex-direction: column;
            position: relative;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 42px rgba(15, 23, 42, 0.1);
            border-color: #94a3b8;
        }
        .plan-card.popular {
            border: 2px solid #16a34a;
            box-shadow: 0 18px 40px rgba(22, 163, 74, 0.16);
        }
        .plan-card.popular:hover {
            border-color: #15803d;
            box-shadow: 0 22px 48px rgba(22, 163, 74, 0.24);
        }
        .badge-popular {
            position: absolute;
            top: -13px;
            right: 24px;
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 5px 14px;
            border-radius: 99px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.35);
        }
        .plan-name {
            font-size: 1.38rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .plan-desc {
            font-size: 0.87rem;
            color: #64748b;
            line-height: 1.45;
            min-height: 40px;
        }
        .plan-price-box {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 20px;
            margin: 16px 0 22px 0;
        }
        .plan-card.has-discount .plan-price-box {
            background: linear-gradient(180deg, #f0fdf4 0%, #f8fafc 100%);
            border-color: #86efac;
        }
        .plan-old-price-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom: 1px dashed #cbd5e1;
            flex-wrap: wrap;
        }
        .plan-old-price {
            display: inline-flex;
            align-items: baseline;
            gap: 6px;
            font-size: 0.92rem;
            color: #475569;
            font-weight: 700;
        }
        .plan-old-price del {
            color: #334155;
            font-size: 1.22rem;
            font-weight: 800;
            text-decoration: line-through;
            text-decoration-thickness: 2px;
            text-decoration-color: #64748b;
            letter-spacing: -0.01em;
        }
        .plan-discount-pill {
            background: #16a34a;
            color: #ffffff;
            font-size: 0.74rem;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 99px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 3px 8px rgba(22, 163, 74, 0.22);
        }
        .plan-now-label {
            display: inline-block;
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #15803d;
            margin-bottom: 3px;
        }
        .plan-price {
            font-size: 2.15rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
            margin: 0;
            letter-spacing: -0.02em;
        }
        .plan-card.has-discount .plan-price {
            color: #15803d;
        }
        .plan-price span {
            font-size: 0.92rem;
            color: #64748b;
            font-weight: 600;
            letter-spacing: normal;
        }
        .plan-savings-note {
            margin-top: 10px;
            background: #dcfce7;
            color: #166534;
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .plan-list {
            list-style: none;
            margin-bottom: 26px;
            flex: 1;
        }
        .plan-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 11px;
            font-size: 0.92rem;
            color: #334155;
            font-weight: 500;
        }
        .plan-list li i {
            color: #16a34a;
            font-size: 1.18rem;
            flex-shrink: 0;
        }

        /* Botón flotante WhatsApp */
        .float-wa {
            position: fixed;
            bottom: 22px;
            right: 22px;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: #25d366;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.9rem;
            box-shadow: 0 8px 20px rgba(37, 211, 102, 0.4);
            text-decoration: none;
            z-index: 999;
        }

        @media (max-width: 768px) {
            .nav-menu { display: none; }
        }
    </style>
</head>
<body>

    <!-- Barra Superior Estilo Ábaco -->
    <header class="site-header">
        <div class="nav-container">
            <a href="/" class="brand-link">
                <img src="/images/logo_contapro.png" alt="ContaPro">
            </a>

            <ul class="nav-menu">
                <li><a href="#inicio" class="active">Inicio</a></li>
                <li><a href="#soluciones">Sistema Contable</a></li>
                <li><a href="#planes">Planes</a></li>
            </ul>

            <div class="ingresar-wrapper">
                <a href="/login" class="btn-ingresar">
                    <i class="ph ph-sign-in" style="font-size: 1.15rem;"></i>
                    <span>Ingresar</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Principal -->
    <section id="inicio" class="hero-section">
        <div class="hero-content">
            <h1 class="anim-item anim-delay-1">Potenciá la gestión contable e impositiva de tus clientes en un solo lugar</h1>
            <p class="anim-item anim-delay-2" style="margin-bottom: 0;">
                Con <strong>ContaPro</strong> centralizás el registro de comprobantes, libros contables, liquidación de impuestos y generación de archivos RG 90 para Marangatu desde la nube, de forma ágil, segura y pensada para estudios contables y empresas de todo el Paraguay.
            </p>
        </div>
    </section>

    <!-- Sección Sistema Contable -->
    <section id="soluciones" class="section">
        <div class="section-title anim-item">
            <h2>Todo lo que tu Estudio Contable necesita en la Nube</h2>
            <p>Gestión de múltiples contribuyentes y empresas con cumplimiento 100% de normativas DNIT Paraguay</p>
        </div>

        <div class="features-grid">
            <div class="feature-card anim-item anim-delay-1">
                <div class="feature-icon"><i class="ph ph-qr-code"></i></div>
                <h3>Integración CDC SIFEN y DNIT</h3>
                <p>Verificación automática de comprobantes electrónicos por CDC y autocompletado de RUC/Razón Social en línea.</p>
            </div>
            <div class="feature-card anim-item anim-delay-2">
                <div class="feature-icon"><i class="ph ph-file-zip"></i></div>
                <h3>Exportación RG 90/2021 Marangatu</h3>
                <p>Generación directa del archivo ZIP reglamentario de Compras y Ventas listo para subir al Sistema Marangatu.</p>
            </div>
            <div class="feature-card anim-item anim-delay-3">
                <div class="feature-icon"><i class="ph ph-notebook"></i></div>
                <h3>Formulario 120 y Libros</h3>
                <p>Cálculo automático del Formulario 120, Libro Compras, Libro Ventas, Libro Diario y Libro Mayor.</p>
            </div>
            <div class="feature-card anim-item anim-delay-4">
                <div class="feature-icon"><i class="ph ph-buildings"></i></div>
                <h3>Múltiples Contribuyentes y Empresas</h3>
                <p>Gestiona varios contribuyentes y empresas desde un solo lugar, manteniendo sus libros, asientos y movimientos organizados de forma independiente.</p>
            </div>
        </div>
    </section>

    <!-- Sección Planes Dinámicos -->
    <div id="planes" class="planes-bg">
        <section class="section">
            <div class="section-title anim-item">
                <h2>Planes pensados para cada etapa</h2>
                <p>Elige el plan según la cantidad de contribuyentes y usuarios de tu estudio contable</p>
            </div>

            <div class="planes-grid">
                <?php
                $planStep = 0;
                foreach (($planes ?? []) as $idx => $pl):
                    if (($pl['codigo_plan'] ?? '') === 'ILIMITADO') continue;
                    $planStep++;
                    $delayClass = 'anim-delay-' . min(4, $planStep);
                    $isPopular = ($pl['codigo_plan'] ?? '') === 'MEDIO';
                    $precioActual = (float)($pl['precio_mensual'] ?? 0);
                    $precioAnt    = (float)($pl['precio_anterior'] ?? 0);
                    $tieneDesc    = ($precioAnt > $precioActual && $precioActual > 0);
                    $ahorroGs     = $tieneDesc ? ($precioAnt - $precioActual) : 0;
                    $pctDesc      = $tieneDesc ? (int)round(($ahorroGs / $precioAnt) * 100) : 0;

                    $precioFmt    = number_format($precioActual, 0, ',', '.');
                    $precioAntFmt = number_format($precioAnt, 0, ',', '.');
                    $ahorroFmt    = number_format($ahorroGs, 0, ',', '.');

                    $waDigitsLanding = \Core\TenantContext::normalizeWhatsappDigits((string)(($sysConfig['whatsapp_soporte'] ?? '') ?: '+595 982 965 515'));
                    $msgPlan = "¡Hola! Estoy interesado en el " . ($pl['nombre_plan'] ?? 'Plan') . " de ContaPro (" . ($tieneDesc ? "Oferta {$precioFmt} Gs/mes, antes {$precioAntFmt} Gs" : "{$precioFmt} Gs/mes") . " - hasta " . (int)$pl['max_contribuyentes'] . " contribuyentes y " . (int)$pl['max_usuarios'] . " usuarios). ¿Me podrían brindar más información para habilitar mi cuenta?";
                    $waPlanUrl = "https://wa.me/{$waDigitsLanding}?text=" . rawurlencode($msgPlan);
                ?>
                <div class="plan-card anim-item <?= $delayClass ?> <?= $isPopular ? 'popular' : '' ?> <?= $tieneDesc ? 'has-discount' : '' ?>">
                    <?php if ($isPopular): ?>
                        <div class="badge-popular">Más elegido</div>
                    <?php endif; ?>

                    <div class="plan-name"><?= htmlspecialchars($pl['nombre_plan']) ?></div>
                    <div class="plan-desc"><?= htmlspecialchars($pl['descripcion'] ?? '') ?></div>

                    <div class="plan-price-box">
                        <?php if ($tieneDesc): ?>
                            <div class="plan-old-price-row">
                                <span class="plan-old-price">Antes: <del><?= $precioAntFmt ?> Gs</del></span>
                                <span class="plan-discount-pill"><i class="ph ph-tag"></i> -<?= $pctDesc ?>% OFF</span>
                            </div>
                            <span class="plan-now-label">Ahora por mes</span>
                        <?php endif; ?>
                        <div class="plan-price">
                            <?= $precioFmt ?> Gs <span>/ mes</span>
                        </div>
                        <?php if ($tieneDesc): ?>
                            <div>
                                <span class="plan-savings-note">
                                    <i class="ph ph-check-circle"></i> Ahorrás <?= $ahorroFmt ?> Gs cada mes
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <ul class="plan-list">
                        <li><i class="ph ph-check-circle"></i> Hasta <strong><?= (int)$pl['max_contribuyentes'] ?> Contribuyentes / Empresas</strong></li>
                        <li><i class="ph ph-check-circle"></i> Hasta <strong><?= (int)$pl['max_usuarios'] ?> Usuarios</strong> por cuenta</li>
                        <li><i class="ph ph-check-circle"></i> Libros IVA, Diario, Mayor y Form. 120</li>
                        <li><i class="ph ph-check-circle"></i> Exportación RG 90 Marangatu y Consulta CDC</li>
                        <li><i class="ph ph-check-circle"></i> Respaldo en la nube y soporte técnico</li>
                    </ul>
                    <a href="<?= htmlspecialchars($waPlanUrl) ?>" target="_blank" rel="noopener" class="btn-hero" style="text-align: center; background: <?= $isPopular ? '#15803d' : '#16a34a' ?>;">
                        Comenzar con <?= htmlspecialchars($pl['nombre_plan']) ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <footer style="background: #0f172a; color: #94a3b8; text-align: center; padding: 32px 20px; font-size: 0.88rem;">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>&copy; <?= date('Y') ?> <strong>ContaPro Paraguay</strong> — Sistema Contable &amp; Impositivo en la Nube.</div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="color: #94a3b8; font-weight: 500;">Desarrollado por</span>
                <a href="https://www.josystem.click/" target="_blank" rel="noopener noreferrer" style="text-decoration: none; display: inline-flex; align-items: center; gap: 10px;">
                    <span style="background: #0099ff; color: #ffffff; padding: 5px 11px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-family: monospace; font-size: 0.95rem; line-height: 1; box-shadow: 0 0 18px rgba(0, 153, 255, 0.75);">&lt;/&gt;</span>
                    <span style="color: #0099ff; font-weight: 800; font-size: 1.25rem; letter-spacing: -0.01em; line-height: 1;">JO System</span>
                    <span style="width: 1.5px; height: 22px; background: #475569; display: inline-block;"></span>
                    <span style="color: #0099ff; font-weight: 800; letter-spacing: 0.06em; font-size: 0.85rem; line-height: 1;">SOFTWARE</span>
                </a>
            </div>
        </div>
    </footer>

    <?php
        $waDigitsFloat = \Core\TenantContext::normalizeWhatsappDigits((string)(($sysConfig['whatsapp_soporte'] ?? '') ?: '+595 982 965 515'));
        $waDefaultMsg = "¡Hola! Estoy visitando la web de ContaPro y me gustaría recibir más información sobre el Sistema Contable en la Nube y sus planes.";
        $waDefaultUrl = "https://wa.me/{$waDigitsFloat}?text=" . rawurlencode($waDefaultMsg);
    ?>
    <a href="<?= htmlspecialchars($waDefaultUrl) ?>" target="_blank" rel="noopener" class="float-wa" title="Contactar por WhatsApp"><i class="ph ph-whatsapp-logo"></i></a>

    <script>
        (function () {
            var navLinks = document.querySelectorAll('.nav-menu a');
            var sectionIds = ['inicio', 'soluciones', 'planes'];
            var isManualNav = false;

            function setActiveNav(id) {
                navLinks.forEach(function (el) {
                    el.classList.toggle('active', el.getAttribute('href') === '#' + id);
                });
            }

            function hideSectionItemsInstant(sectionEl) {
                if (!sectionEl) return;
                var items = sectionEl.querySelectorAll('.anim-item');
                items.forEach(function (item) {
                    item.classList.add('no-transition');
                    item.classList.remove('is-visible');
                });
                // Forzar reflow para que el navegador aplique el estado oculto de inmediato
                void sectionEl.offsetWidth;
                items.forEach(function (item) {
                    item.classList.remove('no-transition');
                });
            }

            function playSectionAnimation(sectionEl, delayMs) {
                if (!sectionEl) return;
                var wait = typeof delayMs === 'number' ? delayMs : 120;
                setTimeout(function () {
                    var items = sectionEl.querySelectorAll('.anim-item');
                    items.forEach(function (item) {
                        item.classList.add('is-visible');
                    });
                }, wait);
            }

            // 1. Al entrar a la página: primero se ve el banner limpio y luego aparece el texto
            window.addEventListener('DOMContentLoaded', function () {
                var heroSec = document.getElementById('inicio');
                hideSectionItemsInstant(heroSec);
                playSectionAnimation(heroSec, 260);
            });

            // 2. Al desplazarse hacia abajo / arriba: revelar cada sección y sincronizar menú
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        var sec = entry.target;
                        if (entry.isIntersecting) {
                            if (!isManualNav) {
                                playSectionAnimation(sec, 80);
                                setActiveNav(sec.id);
                            }
                        } else {
                            // Cuando la sección sale completamente de pantalla, se reinicia para volver a animar al regresar
                            if (entry.intersectionRatio === 0 && !isManualNav) {
                                hideSectionItemsInstant(sec);
                            }
                        }
                    });
                }, {
                    threshold: [0, 0.22]
                });

                sectionIds.forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) observer.observe(el);
                });
            } else {
                document.querySelectorAll('.anim-item').forEach(function (el) {
                    el.classList.add('is-visible');
                });
            }

            // 3. Al cambiar de pestaña en el menú superior (Inicio | Sistema Contable | Planes)
            navLinks.forEach(function (link) {
                link.addEventListener('click', function (e) {
                    var href = this.getAttribute('href') || '';
                    if (href.charAt(0) !== '#') return;
                    var targetId = href.slice(1);
                    var targetSec = document.getElementById(targetId);
                    if (!targetSec) return;

                    e.preventDefault();
                    isManualNav = true;
                    setActiveNav(targetId);

                    // Ocultar texto/tarjetas de la sección destino antes de llegar y animarlos enseguida
                    hideSectionItemsInstant(targetSec);
                    targetSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    playSectionAnimation(targetSec, 320);

                    setTimeout(function () {
                        isManualNav = false;
                    }, 900);
                });
            });
        })();
    </script>
</body>
</html>
