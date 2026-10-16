<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ContaPro | Sistema de Gestión, Facturación y Contabilidad en la Nube</title>
    <link rel="icon" type="image/png" href="/images/logo_contapro.png" />
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
        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
            background: #ffffff;
            line-height: 1.6;
        }

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
            border-bottom-color: #1d8cf8;
        }

        /* Botón Ingresar con desplegable */
        .ingresar-wrapper {
            position: relative;
        }
        .btn-ingresar {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 9px 22px;
            border: 1.5px solid #94a3b8;
            border-radius: 6px;
            background: #ffffff;
            color: #475569;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-ingresar:hover {
            border-color: #16a34a;
            color: #15803d;
            background: #f0fdf4;
        }
        .ingresar-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.16);
            border: 1px solid #e2e8f0;
            width: 240px;
            overflow: hidden;
            z-index: 2000;
        }
        .ingresar-wrapper:hover .ingresar-dropdown,
        .ingresar-dropdown.show {
            display: block;
        }
        .ingresar-dropdown a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 16px;
            text-decoration: none;
            color: #1e293b;
            font-size: 0.9rem;
            font-weight: 600;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
        }
        .ingresar-dropdown a:hover {
            background: #f0fdf4;
            color: #15803d;
        }

        /* Hero Section (Estilo Ábaco) */
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
                linear-gradient(135deg, rgba(16, 61, 118, 0.84) 0%, rgba(21, 94, 160, 0.80) 60%, rgba(21, 128, 61, 0.76) 100%),
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
            background: #2b9af3;
            color: #ffffff;
            font-weight: 600;
            font-size: 1rem;
            padding: 14px 34px;
            border-radius: 6px;
            text-decoration: none;
            box-shadow: 0 10px 25px rgba(43, 154, 243, 0.4);
            transition: all 0.2s;
        }
        .btn-hero:hover {
            background: #1a85de;
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
            background: #eff6ff;
            color: #1d8cf8;
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
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 26px;
        }
        .plan-card {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 18px;
            padding: 32px 28px;
            display: flex;
            flex-direction: column;
            position: relative;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05);
        }
        .plan-card.popular {
            border-color: #1d8cf8;
            box-shadow: 0 18px 40px rgba(29, 140, 248, 0.15);
        }
        .badge-popular {
            position: absolute;
            top: -12px;
            right: 24px;
            background: #1d8cf8;
            color: #fff;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 99px;
            text-transform: uppercase;
        }
        .plan-name {
            font-size: 1.3rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .plan-price {
            font-size: 2rem;
            font-weight: 800;
            color: #15803d;
            margin: 12px 0 18px 0;
        }
        .plan-price span {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 500;
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
            margin-bottom: 10px;
            font-size: 0.92rem;
            color: #334155;
            font-weight: 500;
        }
        .plan-list li i {
            color: #16a34a;
            font-size: 1.15rem;
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
                    <span>Ingresar</span>
                    <i class="ph ph-caret-down"></i>
                </a>
                <div class="ingresar-dropdown">
                    <a href="/login">
                        <i class="ph ph-calculator" style="font-size: 1.25rem; color: #16a34a;"></i>
                        <div>
                            <div>ContaPro Cloud</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Principal (Idéntico al modelo de referencia) -->
    <section id="inicio" class="hero-section">
        <div class="hero-content">
            <h1>Simplifica y mejora los procesos de tu empresa o estudio contable</h1>
            <p>
                Los <strong>sistemas de gestión, facturación e impuestos ContaPro</strong> se adaptan a múltiples rubros y tamaños de empresas y estudios contables en Paraguay, brindando soluciones específicas para cada contribuyente.
            </p>
            <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">
                <a href="#planes" class="btn-hero">Ver soluciones y planes</a>
                <a href="/login" class="btn-hero" style="background: #16a34a; box-shadow: 0 10px 25px rgba(22, 163, 74, 0.4);">
                    <i class="ph ph-sign-in"></i> Ingresar al Sistema
                </a>
            </div>
        </div>
    </section>

    <!-- Sección Sistema Contable -->
    <section id="soluciones" class="section">
        <div class="section-title">
            <h2>Todo lo que tu Estudio Contable necesita en la Nube</h2>
            <p>Multi-Propietario y Multi-Contribuyente con cumplimiento 100% de normativas DNIT / SET Paraguay</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="ph ph-qr-code"></i></div>
                <h3>Integración CDC SIFEN y DNIT</h3>
                <p>Verificación automática de comprobantes electrónicos por CDC en la SET y autocompletado de RUC/Razón Social en línea.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="ph ph-file-zip"></i></div>
                <h3>Exportación RG 90/2021 Marangatu</h3>
                <p>Generación directa del archivo ZIP reglamentario de Compras y Ventas listo para subir al Sistema Marangatu.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="ph ph-Notebook"></i></div>
                <h3>Formulario 120 (IVA V3) y Libros</h3>
                <p>Cálculo automático del Formulario 120 Versión 3, Libro Compras, Libro Ventas, Libro Diario y Libro Mayor.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="ph ph-users-three"></i></div>
                <h3>Arquitectura Multi-Propietario</h3>
                <p>Cada contador o empresa propietaria gestiona únicamente sus propios contribuyentes, asientos y usuarios con total privacidad.</p>
            </div>
        </div>
    </section>

    <!-- Sección Planes Dinámicos -->
    <div id="planes" class="planes-bg">
        <section class="section">
            <div class="section-title">
                <h2>Planes pensados para cada etapa</h2>
                <p>Elige el plan según la cantidad de contribuyentes y usuarios de tu estudio contable</p>
            </div>

            <div class="planes-grid">
                <?php foreach (($planes ?? []) as $idx => $pl):
                    if (($pl['codigo_plan'] ?? '') === 'ILIMITADO') continue;
                    $isPopular = ($pl['codigo_plan'] ?? '') === 'MEDIO';
                ?>
                <div class="plan-card <?= $isPopular ? 'popular' : '' ?>">
                    <?php if ($isPopular): ?>
                        <div class="badge-popular">Más elegido</div>
                    <?php endif; ?>
                    <div class="plan-name"><?= htmlspecialchars($pl['nombre_plan']) ?></div>
                    <div style="font-size: 0.86rem; color: #64748b;"><?= htmlspecialchars($pl['descripcion'] ?? '') ?></div>
                    <div class="plan-price">
                        <?= number_format((float)($pl['precio_mensual'] ?? 0), 0, ',', '.') ?> Gs <span>/ mes</span>
                    </div>
                    <ul class="plan-list">
                        <li><i class="ph ph-check-circle"></i> Hasta <strong><?= (int)$pl['max_contribuyentes'] ?> Contribuyentes / Empresas</strong></li>
                        <li><i class="ph ph-check-circle"></i> Hasta <strong><?= (int)$pl['max_usuarios'] ?> Usuarios</strong> por cuenta</li>
                        <li><i class="ph ph-check-circle"></i> Libros IVA, Diario, Mayor y Form. 120</li>
                        <li><i class="ph ph-check-circle"></i> Exportación RG 90 Marangatu y Consulta CDC</li>
                        <li><i class="ph ph-check-circle"></i> Respaldo en la nube y soporte técnico</li>
                    </ul>
                    <a href="/login" class="btn-hero" style="text-align: center; background: <?= $isPopular ? '#1d8cf8' : '#16a34a' ?>;">
                        Comenzar con <?= htmlspecialchars($pl['nombre_plan']) ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <footer style="background: #0f172a; color: #94a3b8; text-align: center; padding: 32px 20px; font-size: 0.88rem;">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>&copy; <?= date('Y') ?> <strong>ContaPro Paraguay</strong> — Sistema Contable &amp; Impositivo en la Nube.</div>
            <div>Desarrollado por <a href="https://www.josystem.click/" target="_blank" rel="noopener" style="color: #38bdf8; text-decoration: none; font-weight: 700;">JO System Software</a></div>
        </div>
    </footer>

    <a href="https://wa.me/595982965515" target="_blank" rel="noopener" class="float-wa" title="Contactar por WhatsApp"><i class="ph ph-whatsapp-logo"></i></a>

    <script>
        document.querySelectorAll('.nav-menu a').forEach(function (link) {
            link.addEventListener('click', function () {
                document.querySelectorAll('.nav-menu a').forEach(function (el) { el.classList.remove('active'); });
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>
