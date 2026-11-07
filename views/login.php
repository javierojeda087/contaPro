<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>window.CTX = '';</script>
    <title>ContaPro - Acceso al Sistema</title>
    <link rel="icon" type="image/png" href="/images/favicon_cp.png?v=2" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #16a34a;
            --primary-hover: #15803d;
            --card-bg: rgba(255, 255, 255, 0.90);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #cbd5e1;
        }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background:
                radial-gradient(circle at 50% 45%, rgba(240, 253, 244, 0.25) 0%, rgba(16, 185, 129, 0.18) 100%),
                url('/images/login_bg_futuristic.jpg?v=1') center/cover no-repeat fixed;
            color: var(--text-main); 
            margin: 0; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            padding: 1.5rem;
            box-sizing: border-box;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(16, 185, 129, 0.07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(16, 185, 129, 0.07) 1px, transparent 1px);
            background-size: 38px 38px;
            mask-image: radial-gradient(circle at 50% 50%, rgba(0,0,0,0.65) 10%, transparent 80%);
            pointer-events: none;
            z-index: 0;
        }
        .ambient-glow {
            position: absolute;
            width: 480px;
            height: 480px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.28) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
            filter: blur(20px);
            pointer-events: none;
            z-index: 0;
        }
        .glow-1 { top: -120px; left: -100px; }
        .glow-2 { bottom: -120px; right: -100px; }
        .login-card { 
            position: relative;
            z-index: 1;
            background: var(--card-bg); 
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: 24px; 
            padding: 2.5rem 2.3rem; 
            box-shadow: 0 25px 60px -12px rgba(6, 95, 70, 0.24), 0 0 0 1.5px rgba(255, 255, 255, 0.85), 0 0 35px rgba(16, 185, 129, 0.15); 
            width: 100%; 
            max-width: 460px; 
            text-align: center; 
        }
        .card-header-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 1rem auto;
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1px solid #86efac;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #15803d;
            font-size: 1.8rem;
        }
        .login-card h1, .login-card h2 { 
            font-size: 1.5rem; 
            font-weight: 800; 
            color: #0f172a; 
            margin: 0 0 0.35rem 0; 
        }
        .card-subtitle {
            font-size: 0.88rem;
            color: #64748b;
            font-weight: 500;
            margin: 0 0 1.25rem 0;
        }
        .form-group { 
            display: flex; 
            flex-direction: column; 
            text-align: left; 
            margin-bottom: 1.1rem; 
        }
        .form-group label { 
            font-size: 0.72rem; 
            font-weight: 800; 
            color: #475569; 
            text-transform: uppercase; 
            letter-spacing: 0.06em; 
            margin-bottom: 0.45rem; 
        }
        .input-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-container i.input-icon {
            position: absolute;
            left: 14px;
            color: #15803d;
            font-size: 1.2rem;
            pointer-events: none;
        }
        .input-container input, .input-container select {
            padding-left: 42px !important;
        }
        .form-control-custom { 
            width: 100%;
            box-sizing: border-box;
            padding: 0.78rem 1rem; 
            border: 1.5px solid #cbd5e1; 
            border-radius: 12px; 
            font-family: inherit; 
            font-size: 0.92rem; 
            font-weight: 600;
            outline: none; 
            background: #ffffff;
            color: #0f172a;
            appearance: none;
        }
        .form-control-custom:focus { 
            border-color: #15803d; 
            box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.20); 
        }
        .select-wrapper { position: relative; width: 100%; }
        .select-wrapper i.select-arrow {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1rem;
            pointer-events: none;
        }
        .field-label-float { position: relative; }
        .field-label-float span {
            position: absolute;
            top: -9px;
            left: 12px;
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #86efac;
            padding: 1px 7px;
            font-size: 0.65rem;
            font-weight: 800;
            border-radius: 6px;
            z-index: 2;
        }
        .btn-login { 
            background: linear-gradient(135deg, #15803d, #16a34a); 
            color: white; 
            border: none; 
            padding: 0.88rem; 
            border-radius: 14px; 
            font-weight: 800; 
            font-size: 0.95rem; 
            cursor: pointer; 
            width: 100%; 
            margin-top: 1rem; 
            box-shadow: 0 10px 20px -5px rgba(21, 128, 61, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* Buscador Dinámico de Contribuyentes */
        .contrib-item {
            padding: 9px 12px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.15s ease;
            border: 1px solid transparent;
            margin-bottom: 3px;
        }
        .contrib-item:hover, .contrib-item.keyboard-active {
            background: #f0fdf4 !important;
            border-color: #86efac !important;
            transform: translateX(2px);
        }
        .contrib-item.selected {
            background: #ecfdf5;
            border-color: #a7f3d0;
        }
        .contrib-item-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #f1f5f9;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            transition: all 0.15s ease;
        }
        .contrib-item:hover .contrib-item-icon, .contrib-item.keyboard-active .contrib-item-icon {
            background: #dcfce7;
            color: #15803d;
        }
        .contrib-item.selected .contrib-item-icon {
            background: #16a34a;
            color: #ffffff;
        }
        .error-msg { 
            background: #fef2f2; 
            color: #b91c1c; 
            padding: 0.85rem; 
            border-radius: 12px; 
            margin-bottom: 1.15rem; 
            font-size: 0.85rem; 
            font-weight: 600; 
            border: 1px solid #fecaca; 
            display: flex;
            align-items: center;
            gap: 8px;
            text-align: left;
        }
        .tenant-badge {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #15803d;
            border-radius: 12px;
            padding: 8px 14px;
            margin-bottom: 1.15rem;
            font-size: 0.82rem;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <!-- STEP 1: Credential Input Card -->
    <div class="login-card" id="step1Card">
        <div style="margin: 0 auto 1rem auto; text-align: center;">
            <img src="/images/logo_contapro.png" alt="ContaPro - Sistema Contable & ERP" style="max-width: 200px; width: 100%; height: auto; display: block; margin: 0 auto 1.25rem auto;">
        </div>

        <div id="errorMsgStep1" class="error-msg" style="display: none; flex-direction: column; align-items: stretch; gap: 10px;">
            <div style="display: flex; align-items: flex-start; gap: 8px;">
                <i class="ph ph-warning-circle" style="font-size: 1.25rem; flex-shrink: 0; margin-top: 1px;"></i>
                <span id="errorMsgStep1Text" style="flex: 1; line-height: 1.45;"></span>
            </div>
            <a id="errorMsgStep1Whatsapp" href="#" target="_blank" rel="noopener noreferrer" style="display: none; background: linear-gradient(135deg, #25d366, #16a34a); color: #ffffff; text-decoration: none; padding: 9px 14px; border-radius: 10px; font-weight: 800; font-size: 0.84rem; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(37, 211, 102, 0.35); transition: all 0.2s;">
                <i class="ph ph-whatsapp-logo" style="font-size: 1.2rem;"></i>
                <span id="errorMsgStep1WhatsappText">Contactar por WhatsApp para regularizar</span>
            </a>
        </div>

        <form id="loginForm" onsubmit="validarStep1(event)" autocomplete="off">
            <div class="form-group">
                <label for="username">Usuario</label>
                <div class="input-container">
                    <i class="ph ph-user input-icon"></i>
                    <input type="text" id="username" name="username" class="form-control-custom" autocomplete="off" required placeholder="INGRESE SU USUARIO" oninput="this.value = this.value.toUpperCase().replace(/\s+/g, '')" style="text-transform: uppercase;">
                </div>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-container">
                    <i class="ph ph-lock input-icon"></i>
                    <input type="password" id="password" name="password" class="form-control-custom" autocomplete="new-password" required placeholder="Ingrese su contraseña" style="padding-right: 2.8rem;">
                    <button type="button" tabindex="-1" onclick="togglePassVisibility()" style="position: absolute; right: 12px; background: none; border: none; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; justify-content: center; padding: 4px;" title="Mostrar/Ocultar contraseña">
                        <i class="ph ph-eye" id="eyeIcon" style="font-size: 1.2rem; color: #64748b;"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-login" id="btnStep1">
                <i class="ph ph-sign-in" style="font-size: 1.2rem;"></i>
                INGRESAR AL SISTEMA
            </button>
        </form>

        <div style="margin-top: 1.35rem;">
            <a href="/" style="color: #64748b; font-size: 0.84rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                <i class="ph ph-globe"></i> Volver a www.contapro.com.py
            </a>
        </div>
    </div>

    <!-- STEP 2: Seleccionar Contribuyente Card -->
    <div class="login-card" id="step2Card" style="display: none; max-width: 490px;">
        <div class="card-header-icon">
            <i class="ph ph-buildings"></i>
        </div>
        <h2>Seleccionar Contribuyente</h2>
        <p class="card-subtitle">Configura el contexto de trabajo de tu cuenta</p>

        <div id="propietarioBanner" class="tenant-badge" style="display: none;">
            <span><i class="ph ph-user-circle-check"></i> Propietario: <strong id="lblPropietarioNombre">-</strong></span>
            <span id="lblPropietarioPlan" style="background: #16a34a; color: #fff; padding: 2px 8px; border-radius: 99px; font-size: 0.72rem;">Plan</span>
        </div>

        <div id="sinContribuyentesNotice" style="display: none; background: #eff6ff; border: 1px solid #93c5fd; color: #1e40af; padding: 12px 14px; border-radius: 12px; margin-bottom: 1.15rem; font-size: 0.84rem; font-weight: 600; text-align: left;">
            <i class="ph ph-info"></i> Aún no tienes contribuyentes creados en tu cuenta. Haz clic en <strong>INGRESAR Y CREAR CONTRIBUYENTE</strong> para dar de alta tu primera empresa.
        </div>

        <div id="errorMsgStep2" class="error-msg" style="display: none;">
            <i class="ph ph-warning-circle" style="font-size: 1.2rem;"></i>
            <span id="errorMsgStep2Text"></span>
        </div>

        <form id="contribuyenteForm" onsubmit="finalizarLogin(event)" autocomplete="off">
            <div id="step2FieldsWrap">
                <div class="form-group" style="margin-bottom: 1.15rem; position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                        <label for="contribuyenteSearchInput" style="margin: 0; cursor: pointer;">Contribuyente / Empresa</label>
                        <span id="contribuyentesCountBadge" style="font-size: 0.72rem; font-weight: 800; color: #15803d; background: #dcfce7; border: 1px solid #bbf7d0; padding: 1px 8px; border-radius: 99px; display: none;"></span>
                    </div>

                    <!-- Input buscador interactivo -->
                    <div class="input-container" style="position: relative;">
                        <i class="ph ph-buildings input-icon" style="z-index: 2;"></i>
                        <input type="text" id="contribuyenteSearchInput" class="form-control-custom"
                               placeholder="Buscar por nombre, RUC o código..."
                               autocomplete="off"
                               style="padding-right: 68px !important; cursor: pointer; text-overflow: ellipsis; font-weight: 700;"
                               onfocus="abrirDropdownContribuyentes()"
                               onclick="abrirDropdownContribuyentes()"
                               oninput="filtrarContribuyentesEnVivo(this.value)"
                               onkeydown="manejarTeclasContribuyentes(event)">
                        
                        <div style="position: absolute; right: 10px; display: flex; align-items: center; gap: 2px; z-index: 3;">
                            <button type="button" id="btnLimpiarBusquedaContrib" onclick="limpiarBusquedaContribuyente(event)" title="Limpiar búsqueda" style="display: none; background: #f1f5f9; border: 1px solid #cbd5e1; width: 24px; height: 24px; color: #64748b; cursor: pointer; border-radius: 50%; font-size: 0.82rem; align-items: center; justify-content: center;" onmouseover="this.style.background='#fee2e2'; this.style.borderColor='#fca5a5'; this.style.color='#dc2626'" onmouseout="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1'; this.style.color='#64748b'">
                                <i class="ph ph-x"></i>
                            </button>
                            <button type="button" id="btnToggleDropdownContrib" onclick="toggleDropdownContribuyentes(event)" tabindex="-1" style="background: none; border: none; padding: 4px; color: #64748b; cursor: pointer; border-radius: 6px; font-size: 1.15rem; display: flex; align-items: center; justify-content: center;">
                                <i class="ph ph-caret-down" id="caretIconContrib" style="transition: transform 0.2s ease;"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Select original oculto para total compatibilidad hacia atrás con finalizarLogin y scripts existentes -->
                    <select id="contribuyente" style="display: none;" onchange="onContribuyenteChange()">
                        <option value="">Cargando contribuyentes...</option>
                    </select>

                    <!-- Menú desplegable interactivo flotante -->
                    <div id="contribuyenteDropdownList" style="display: none; position: absolute; top: calc(100% + 5px); left: 0; right: 0; background: #ffffff; border: 1.5px solid #16a34a; border-radius: 16px; box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.3), 0 0 0 1px rgba(22, 163, 74, 0.15); max-height: 310px; z-index: 1050; overflow: hidden; flex-direction: column;">
                        
                        <!-- Barra superior de estado -->
                        <div style="padding: 8px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 0.74rem; font-weight: 700; color: #64748b; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                            <span id="dropdownHeaderTitle">Contribuyentes disponibles</span>
                            <span style="font-size: 0.68rem; color: #16a34a; font-weight: 800;"><i class="ph ph-magnifying-glass"></i> Búsqueda en vivo</span>
                        </div>

                        <!-- Lista scrolleable de contribuyentes -->
                        <div id="dropdownItemsContainer" style="overflow-y: auto; flex: 1; padding: 6px; max-height: 260px;">
                            <!-- Opciones inyectadas dinámicamente -->
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 8px; margin-bottom: 1.15rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="ejercicio">Ejercicio</label>
                        <div class="input-container select-wrapper">
                            <select id="ejercicio" class="form-control-custom" onchange="onEjercicioChange()" style="padding-left: 12px !important; font-size: 0.88rem;">
                                <option value="">Ejercicio</option>
                            </select>
                            <i class="ph ph-caret-down select-arrow" style="right: 8px;"></i>
                        </div>
                    </div>
                    <div class="form-group field-label-float" style="margin-bottom: 0;">
                        <span>APERTURA</span>
                        <label style="visibility: hidden;">Apertura</label>
                        <input type="text" id="apertura" placeholder="dd/mm/aaaa" readonly class="form-control-custom" style="padding-left: 8px !important; text-align: center; background: #f1f5f9; color: #15803d; font-size: 0.82rem;">
                    </div>
                    <div class="form-group field-label-float" style="margin-bottom: 0;">
                        <span>CIERRE</span>
                        <label style="visibility: hidden;">Cierre</label>
                        <input type="text" id="cierre" placeholder="dd/mm/aaaa" readonly class="form-control-custom" style="padding-left: 8px !important; text-align: center; background: #f1f5f9; color: #15803d; font-size: 0.82rem;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 1.25rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="actividad">Actividad</label>
                        <div class="input-container select-wrapper">
                            <i class="ph ph-briefcase input-icon"></i>
                            <select id="actividad" class="form-control-custom" style="font-size: 0.88rem;">
                                <option value="">Actividad</option>
                            </select>
                            <i class="ph ph-caret-down select-arrow"></i>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="sucursal">Sucursal</label>
                        <div class="input-container select-wrapper">
                            <i class="ph ph-storefront input-icon"></i>
                            <select id="sucursal" class="form-control-custom" style="font-size: 0.88rem;">
                                <option value="">Sucursal</option>
                            </select>
                            <i class="ph ph-caret-down select-arrow"></i>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-login" id="btnConfirmar">
                <i class="ph ph-sign-in" style="font-size: 1.2rem;"></i>
                <span id="btnConfirmarText">INGRESAR</span>
            </button>

            <div style="margin-top: 1.15rem;">
                <a href="#" onclick="volverStep1(event)" style="color: #64748b; font-size: 0.88rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="ph ph-arrow-left"></i> Cambiar Usuario
                </a>
            </div>
        </form>
    </div>

    <script>
        let verifiedUser = '';
        let empresasList = [];
        let currentEmpresaDetails = null;

        function escapeHtml(val) {
            return String(val ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const cambioUser = <?= json_encode($cambioUsuario ?? null) ?>;
            if (cambioUser) {
                verifiedUser = cambioUser;
                cargarStep2(<?= json_encode($cambioEmpresa ?? '') ?>, <?= json_encode($cambioSucursal ?? '') ?>);
                return;
            }
            setTimeout(() => document.getElementById('username')?.focus(), 100);
        });

        function togglePassVisibility() {
            const passInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                eyeIcon.className = 'ph ph-eye-slash';
            } else {
                passInput.type = 'password';
                eyeIcon.className = 'ph ph-eye';
            }
        }

        async function validarStep1(e) {
            e.preventDefault();
            const errDiv = document.getElementById('errorMsgStep1');
            const errText = document.getElementById('errorMsgStep1Text');
            const errWaBtn = document.getElementById('errorMsgStep1Whatsapp');
            const errWaText = document.getElementById('errorMsgStep1WhatsappText');
            errDiv.style.display = 'none';
            if (errWaBtn) errWaBtn.style.display = 'none';

            const btn = document.getElementById('btnStep1');
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-spinner-gap" style="animation: spin 1s linear infinite;"></i> Verificando...';

            const user = document.getElementById('username').value.trim().toUpperCase();
            document.getElementById('username').value = user;
            const pass = document.getElementById('password').value;

            try {
                const response = await fetch('/api/login/verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ username: user, password: pass })
                });
                const result = await response.json();

                if (result.status === 'success') {
                    verifiedUser = result.username;
                    if (result.nombrePropietario) {
                        document.getElementById('propietarioBanner').style.display = 'flex';
                        document.getElementById('lblPropietarioNombre').innerText = result.nombrePropietario;
                        document.getElementById('lblPropietarioPlan').innerText = (result.nombrePlan || 'Plan') + ' (' + (result.usadosContribuyentes ?? 0) + '/' + (result.maxContribuyentes ?? 5) + ')';
                    }
                    await cargarStep2(result.defaultEmpresa, result.defaultSucursal);
                } else {
                    errText.innerText = result.message || 'Usuario o contraseña incorrectos.';
                    if (errWaBtn && result.whatsappUrl) {
                        errWaBtn.href = result.whatsappUrl;
                        if (errWaText) {
                            const lbl = result.whatsappLabel || 'Contactar por WhatsApp';
                            errWaText.innerText = result.whatsappPhone ? `${lbl} (${result.whatsappPhone})` : lbl;
                        }
                        errWaBtn.style.display = 'inline-flex';
                    }
                    errDiv.style.display = 'flex';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="ph ph-sign-in" style="font-size: 1.2rem;"></i> INGRESAR AL SISTEMA';
                }
            } catch (error) {
                errText.innerText = 'Error de conexión con el servidor.';
                errDiv.style.display = 'flex';
                btn.disabled = false;
                btn.innerHTML = '<i class="ph ph-sign-in" style="font-size: 1.2rem;"></i> INGRESAR AL SISTEMA';
            }
        }

        async function cargarStep2(defaultEmpresa = '', defaultSucursal = '') {
            document.getElementById('step1Card').style.display = 'none';
            document.getElementById('step2Card').style.display = 'block';

            try {
                const res = await fetch('/api/login/empresas');
                if (res.status === 401) {
                    volverStep1();
                    return;
                }
                empresasList = await res.json();

                const select = document.getElementById('contribuyente');
                const notice = document.getElementById('sinContribuyentesNotice');
                const fieldsWrap = document.getElementById('step2FieldsWrap');
                const btnText = document.getElementById('btnConfirmarText');

                if (empresasList.length === 0) {
                    notice.style.display = 'block';
                    fieldsWrap.style.display = 'none';
                    btnText.innerText = 'INGRESAR Y CREAR CONTRIBUYENTE';
                    select.innerHTML = '<option value="NEW">Nuevo Contribuyente</option>';
                    select.value = 'NEW';
                    const searchInput = document.getElementById('contribuyenteSearchInput');
                    if (searchInput) searchInput.value = 'Nuevo Contribuyente';
                    return;
                }

                notice.style.display = 'none';
                fieldsWrap.style.display = 'block';
                btnText.innerText = 'INGRESAR';

                const badgeCount = document.getElementById('contribuyentesCountBadge');
                if (badgeCount) {
                    badgeCount.style.display = 'inline-block';
                    badgeCount.innerText = `${empresasList.length} empresa${empresasList.length === 1 ? '' : 's'}`;
                }

                let html = '<option value="">Seleccionar contribuyente...</option>';
                empresasList.forEach(emp => {
                    const cod = escapeHtml(emp.cod_empresa || emp.codEmpresa || emp.COD_EMPRESA || '');
                    const desc = escapeHtml(emp.descripcion || emp.DESCRIPCION || '');
                    html += `<option value="${cod}">${cod ? cod + ' - ' : ''}${desc}</option>`;
                });
                select.innerHTML = html;

                let targetEmp = defaultEmpresa;
                if (!targetEmp && empresasList.length > 0) {
                    targetEmp = empresasList[0].cod_empresa || empresasList[0].codEmpresa || empresasList[0].COD_EMPRESA;
                }
                const hasOpt = Array.from(select.options).some(o => o.value === String(targetEmp));
                if (targetEmp && hasOpt) {
                    select.value = String(targetEmp);
                } else if (empresasList.length > 0) {
                    select.value = String(empresasList[0].cod_empresa || empresasList[0].codEmpresa || empresasList[0].COD_EMPRESA || '');
                }

                sincronizarInputConSeleccionado();
                renderContribuyentesDropdown(empresasList, '');

                await onContribuyenteChange(defaultSucursal);
            } catch (error) {
                console.error('Error cargando contribuyentes:', error);
            }
        }

        // =========================================================================
        // CONTROLADOR DEL BUSCADOR DINÁMICO DE CONTRIBUYENTES
        // =========================================================================
        let renderedContribuyentesList = [];
        let highlightedContribuyenteIndex = -1;

        function highlightMatch(text, query) {
            if (!text) return '';
            if (!query || !query.trim()) return escapeHtml(text);
            const rawText = String(text);
            const q = query.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp(`(${q})`, 'gi');
            return escapeHtml(rawText).replace(regex, '<mark style="background: #fef08a; color: #854d0e; padding: 0 3px; border-radius: 4px; font-weight: 800;">$1</mark>');
        }

        function sincronizarInputConSeleccionado() {
            const select = document.getElementById('contribuyente');
            const input = document.getElementById('contribuyenteSearchInput');
            if (!select || !input) return;
            const currentVal = select.value;
            const foundEmp = empresasList.find(e => String(e.cod_empresa || e.codEmpresa || e.COD_EMPRESA || '') === String(currentVal));
            if (foundEmp) {
                const cod = (foundEmp.cod_empresa || foundEmp.codEmpresa || foundEmp.COD_EMPRESA || '').trim();
                const desc = (foundEmp.descripcion || foundEmp.DESCRIPCION || '').trim();
                input.value = cod ? `${cod} - ${desc}` : desc;
            } else if (currentVal === 'NEW') {
                input.value = 'Nuevo Contribuyente';
            } else {
                input.value = '';
            }
            const btnLimpiar = document.getElementById('btnLimpiarBusquedaContrib');
            if (btnLimpiar) btnLimpiar.style.display = 'none';
        }

        function renderContribuyentesDropdown(lista, query = '') {
            const container = document.getElementById('dropdownItemsContainer');
            const headerTitle = document.getElementById('dropdownHeaderTitle');
            if (!container) return;

            renderedContribuyentesList = lista || [];
            highlightedContribuyenteIndex = -1;

            if (headerTitle) {
                if (query.trim()) {
                    headerTitle.innerHTML = `Resultados: <b>${renderedContribuyentesList.length}</b> encontrado(s)`;
                } else {
                    headerTitle.innerText = `Todos los contribuyentes (${renderedContribuyentesList.length})`;
                }
            }

            if (renderedContribuyentesList.length === 0) {
                container.innerHTML = `
                    <div style="padding: 24px 16px; text-align: center; color: #64748b;">
                        <i class="ph ph-magnifying-glass" style="font-size: 2.2rem; color: #cbd5e1; display: block; margin-bottom: 8px;"></i>
                        <div style="font-size: 0.88rem; font-weight: 800; color: #334155;">No se encontraron contribuyentes</div>
                        <div style="font-size: 0.74rem; color: #94a3b8; margin-top: 4px;">Intente buscando por nombre, RUC o código</div>
                    </div>`;
                return;
            }

            const currentVal = String(document.getElementById('contribuyente')?.value || '');
            let html = '';

            renderedContribuyentesList.forEach((emp, idx) => {
                const cod = String(emp.cod_empresa || emp.codEmpresa || emp.COD_EMPRESA || '').trim();
                const desc = String(emp.descripcion || emp.DESCRIPCION || '').trim();
                const ruc = String(emp.ruc_empresa || emp.rucEmpresa || emp.RUC_EMPRESA || emp.ruc || '').trim();
                const act = String(emp.actividad || emp.ACTIVIDAD || '').trim();
                const isSelected = cod === currentVal;

                html += `
                    <div class="contrib-item ${isSelected ? 'selected' : ''}" 
                         id="contribOption_${idx}"
                         data-idx="${idx}"
                         data-code="${escapeHtml(cod)}"
                         onclick="seleccionarContribuyenteDirecto('${escapeHtml(cod)}')"
                         onmouseover="onHoverContribOption(${idx})">
                        <div style="display: flex; align-items: center; gap: 10px; width: 100%;">
                            <div class="contrib-item-icon">
                                <i class="ph ph-buildings"></i>
                            </div>
                            <div style="flex: 1; min-width: 0; text-align: left;">
                                <div style="font-size: 0.9rem; font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.25;">
                                    ${highlightMatch(desc, query)}
                                </div>
                                <div style="display: flex; gap: 6px; align-items: center; margin-top: 4px; flex-wrap: wrap;">
                                    <span style="font-size: 0.68rem; font-weight: 800; background: #e2e8f0; color: #334155; padding: 1px 7px; border-radius: 5px;">
                                        Cód: ${highlightMatch(cod, query)}
                                    </span>
                                    ${ruc ? `
                                        <span style="font-size: 0.68rem; font-weight: 800; background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; padding: 1px 7px; border-radius: 5px; display: inline-flex; align-items: center; gap: 3px;">
                                            <i class="ph ph-identification-card"></i> ${highlightMatch(ruc, query)}
                                        </span>
                                    ` : ''}
                                    ${act ? `
                                        <span style="font-size: 0.68rem; font-weight: 600; color: #64748b; max-width: 175px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            ${highlightMatch(act, query)}
                                        </span>
                                    ` : ''}
                                </div>
                            </div>
                            <div style="${isSelected ? 'color: #16a34a;' : 'display: none;'} flex-shrink: 0; padding-left: 6px;">
                                <i class="ph ph-check-circle" style="font-size: 1.3rem;"></i>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function abrirDropdownContribuyentes() {
            const dropdown = document.getElementById('contribuyenteDropdownList');
            const caret = document.getElementById('caretIconContrib');
            if (!dropdown) return;
            dropdown.style.display = 'flex';
            if (caret) caret.style.transform = 'rotate(180deg)';

            const input = document.getElementById('contribuyenteSearchInput');
            if (input) {
                input.select();
                renderContribuyentesDropdown(empresasList, '');
            }
        }

        function cerrarDropdownContribuyentes() {
            const dropdown = document.getElementById('contribuyenteDropdownList');
            const caret = document.getElementById('caretIconContrib');
            if (dropdown) dropdown.style.display = 'none';
            if (caret) caret.style.transform = 'rotate(0deg)';
            sincronizarInputConSeleccionado();
        }

        function toggleDropdownContribuyentes(e) {
            if (e) e.stopPropagation();
            const dropdown = document.getElementById('contribuyenteDropdownList');
            if (dropdown && dropdown.style.display === 'flex') {
                cerrarDropdownContribuyentes();
            } else {
                abrirDropdownContribuyentes();
                document.getElementById('contribuyenteSearchInput')?.focus();
            }
        }

        function filtrarContribuyentesEnVivo(query) {
            const q = String(query || '').trim().toLowerCase();
            const qNoDash = q.replace(/[-\s]/g, '');
            const btnLimpiar = document.getElementById('btnLimpiarBusquedaContrib');

            if (btnLimpiar) {
                btnLimpiar.style.display = query.trim() ? 'flex' : 'none';
            }

            const dropdown = document.getElementById('contribuyenteDropdownList');
            if (dropdown && dropdown.style.display !== 'flex') {
                abrirDropdownContribuyentes();
            }

            if (!q) {
                renderContribuyentesDropdown(empresasList, '');
                return;
            }

            const filtrados = empresasList.filter(emp => {
                const desc = String(emp.descripcion || emp.DESCRIPCION || '').toLowerCase();
                const cod = String(emp.cod_empresa || emp.codEmpresa || emp.COD_EMPRESA || '').toLowerCase();
                const ruc = String(emp.ruc_empresa || emp.rucEmpresa || emp.RUC_EMPRESA || emp.ruc || '').toLowerCase();
                const rucClean = ruc.replace(/[-\s]/g, '');
                const act = String(emp.actividad || emp.ACTIVIDAD || '').toLowerCase();

                return desc.includes(q) ||
                       cod === q ||
                       cod.includes(q) ||
                       ruc.includes(q) ||
                       rucClean.includes(qNoDash) ||
                       act.includes(q);
            });

            renderContribuyentesDropdown(filtrados, query);
            if (filtrados.length > 0) {
                highlightedContribuyenteIndex = 0;
                actualizarHighlightOpcion();
            }
        }

        function onHoverContribOption(idx) {
            highlightedContribuyenteIndex = idx;
            actualizarHighlightOpcion();
        }

        function actualizarHighlightOpcion() {
            document.querySelectorAll('.contrib-item').forEach((el, i) => {
                el.classList.toggle('keyboard-active', i === highlightedContribuyenteIndex);
            });
        }

        function seleccionarContribuyenteDirecto(codEmpresa) {
            const select = document.getElementById('contribuyente');
            if (select) {
                select.value = String(codEmpresa);
            }
            sincronizarInputConSeleccionado();
            cerrarDropdownContribuyentes();
            onContribuyenteChange();
        }

        function limpiarBusquedaContribuyente(e) {
            if (e) e.stopPropagation();
            const input = document.getElementById('contribuyenteSearchInput');
            if (input) {
                input.value = '';
                input.focus();
            }
            renderContribuyentesDropdown(empresasList, '');
            const btnLimpiar = document.getElementById('btnLimpiarBusquedaContrib');
            if (btnLimpiar) btnLimpiar.style.display = 'none';
        }

        function manejarTeclasContribuyentes(e) {
            const dropdown = document.getElementById('contribuyenteDropdownList');
            const isOpen = dropdown && dropdown.style.display === 'flex';

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!isOpen) {
                    abrirDropdownContribuyentes();
                    return;
                }
                if (renderedContribuyentesList.length === 0) return;
                highlightedContribuyenteIndex = Math.min(renderedContribuyentesList.length - 1, highlightedContribuyenteIndex + 1);
                actualizarHighlightOpcion();
                document.getElementById(`contribOption_${highlightedContribuyenteIndex}`)?.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!isOpen) {
                    abrirDropdownContribuyentes();
                    return;
                }
                if (renderedContribuyentesList.length === 0) return;
                highlightedContribuyenteIndex = Math.max(0, highlightedContribuyenteIndex - 1);
                actualizarHighlightOpcion();
                document.getElementById(`contribOption_${highlightedContribuyenteIndex}`)?.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                if (isOpen && highlightedContribuyenteIndex >= 0 && renderedContribuyentesList[highlightedContribuyenteIndex]) {
                    e.preventDefault();
                    const emp = renderedContribuyentesList[highlightedContribuyenteIndex];
                    const cod = emp.cod_empresa || emp.codEmpresa || emp.COD_EMPRESA || '';
                    seleccionarContribuyenteDirecto(cod);
                }
            } else if (e.key === 'Escape') {
                if (isOpen) {
                    e.preventDefault();
                    cerrarDropdownContribuyentes();
                }
            }
        }

        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('contribuyenteDropdownList');
            const input = document.getElementById('contribuyenteSearchInput');
            const btnToggle = document.getElementById('btnToggleDropdownContrib');
            const btnClear = document.getElementById('btnLimpiarBusquedaContrib');
            if (!dropdown) return;
            if (
                !dropdown.contains(e.target) &&
                e.target !== input &&
                (!btnToggle || !btnToggle.contains(e.target)) &&
                (!btnClear || !btnClear.contains(e.target))
            ) {
                if (dropdown.style.display === 'flex') {
                    cerrarDropdownContribuyentes();
                }
            }
        });

        async function onContribuyenteChange(preferredSucursal = '') {
            const codEmpresa = document.getElementById('contribuyente').value;
            const ejSelect = document.getElementById('ejercicio');
            const sucSelect = document.getElementById('sucursal');
            const actSelect = document.getElementById('actividad');
            const aperturaInput = document.getElementById('apertura');
            const cierreInput = document.getElementById('cierre');

            aperturaInput.value = '';
            cierreInput.value = '';

            if (!codEmpresa || codEmpresa === 'NEW') return;

            try {
                const res = await fetch(`/api/login/empresa-detalles?codEmpresa=${encodeURIComponent(codEmpresa)}`);
                currentEmpresaDetails = await res.json();

                const ejercicios = currentEmpresaDetails.ejercicios || [];
                let ejHtml = '<option value="">Ejercicio</option>';
                ejercicios.forEach(ej => {
                    const codEj = escapeHtml(ej.codEjercicio || ej.COD_EJERCICIO || ej.cod_ejercicio || '');
                    ejHtml += `<option value="${codEj}">${codEj}</option>`;
                });
                ejSelect.innerHTML = ejHtml;

                if (ejercicios.length > 0) {
                    const firstCod = ejercicios[0].codEjercicio || ejercicios[0].COD_EJERCICIO || ejercicios[0].cod_ejercicio;
                    ejSelect.value = firstCod;
                    onEjercicioChange();
                } else {
                    const currentYear = new Date().getFullYear().toString();
                    ejSelect.innerHTML = `<option value="${currentYear}">${currentYear}</option>`;
                    ejSelect.value = currentYear;
                    aperturaInput.value = `01/01/${currentYear}`;
                    cierreInput.value = `31/12/${currentYear}`;
                }

                const sucursales = currentEmpresaDetails.sucursales || [];
                let sucHtml = '<option value="">Sucursal</option>';
                sucursales.forEach(suc => {
                    const codSuc = escapeHtml(suc.codSucursal || suc.COD_SUCURSAL || suc.cod_sucursal || '');
                    const descSuc = escapeHtml(suc.descripcion || suc.DESCRIPCION || '');
                    sucHtml += `<option value="${codSuc}">${codSuc ? codSuc + ' - ' : ''}${descSuc}</option>`;
                });
                sucSelect.innerHTML = sucHtml;

                const hasSuc = preferredSucursal && Array.from(sucSelect.options).some(o => o.value === String(preferredSucursal));
                if (hasSuc) {
                    sucSelect.value = String(preferredSucursal);
                } else if (sucursales.length > 0) {
                    sucSelect.value = String(sucursales[0].codSucursal || sucursales[0].COD_SUCURSAL || sucursales[0].cod_sucursal || '');
                }

                const acts = currentEmpresaDetails.actividades || [];
                let actHtml = '';
                if (acts.length) {
                    acts.forEach(a => {
                        const descAct = escapeHtml(a.descripcion || '');
                        actHtml += `<option value="${descAct}" ${a.porDefecto ? 'selected' : ''}>${descAct}</option>`;
                    });
                } else {
                    actHtml = '<option value="">Sin actividades cargadas</option>';
                }
                actSelect.innerHTML = actHtml;
            } catch (error) {
                console.error('Error cargando detalles de empresa:', error);
            }
        }

        function onEjercicioChange() {
            const codEjercicio = document.getElementById('ejercicio').value;
            const aperturaInput = document.getElementById('apertura');
            const cierreInput = document.getElementById('cierre');

            if (!codEjercicio || !currentEmpresaDetails || !currentEmpresaDetails.ejercicios) {
                aperturaInput.value = '';
                cierreInput.value = '';
                return;
            }
            const ejObj = currentEmpresaDetails.ejercicios.find(e => (e.codEjercicio || e.COD_EJERCICIO || e.cod_ejercicio) === codEjercicio);
            if (ejObj) {
                aperturaInput.value = ejObj.fecInicio || ejObj.FEC_INICIO || '';
                cierreInput.value = ejObj.fecFinal || ejObj.FEC_FINAL || '';
            }
        }

        async function finalizarLogin(e) {
            e.preventDefault();
            const errDiv = document.getElementById('errorMsgStep2');
            const errText = document.getElementById('errorMsgStep2Text');
            errDiv.style.display = 'none';

            const codEmpresa = document.getElementById('contribuyente').value;
            const codEjercicio = document.getElementById('ejercicio').value || new Date().getFullYear().toString();
            const fecInicio = document.getElementById('apertura').value || ('01/01/' + codEjercicio);
            const fecFinal = document.getElementById('cierre').value || ('31/12/' + codEjercicio);
            const codSucursal = document.getElementById('sucursal').value || '01';
            const actividad = document.getElementById('actividad').value || '';

            if (!codEmpresa) {
                errText.innerText = 'Debe seleccionar un contribuyente / empresa.';
                errDiv.style.display = 'flex';
                return;
            }

            const btn = document.getElementById('btnConfirmar');
            btn.disabled = true;

            try {
                const params = new URLSearchParams({
                    username: verifiedUser,
                    codEmpresa: codEmpresa === 'NEW' ? '' : codEmpresa,
                    codEjercicio: codEjercicio,
                    fecInicio: fecInicio,
                    fecFinal: fecFinal,
                    codSucursal: codSucursal,
                    actividad: actividad
                });

                const response = await fetch('/api/login/confirm', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params
                });
                const result = await response.json();
                if (result.status === 'success') {
                    window.location.href = result.redirect || '/menu';
                } else {
                    errText.innerText = result.message || 'Error al iniciar sesión.';
                    errDiv.style.display = 'flex';
                    btn.disabled = false;
                }
            } catch (error) {
                errText.innerText = 'Error de conexión con el servidor.';
                errDiv.style.display = 'flex';
                btn.disabled = false;
            }
        }

        function volverStep1(e) {
            if (e) e.preventDefault();
            document.getElementById('step2Card').style.display = 'none';
            document.getElementById('step1Card').style.display = 'block';
            document.getElementById('btnStep1').disabled = false;
            document.getElementById('btnStep1').innerHTML = '<i class="ph ph-sign-in" style="font-size: 1.2rem;"></i> INGRESAR AL SISTEMA';
            document.getElementById('password').value = '';
            document.getElementById('username').focus();
        }
    </script>
</body>
</html>
