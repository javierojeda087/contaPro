<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>window.CTX = '';</script>
    <title>ContaPro - Acceso al Sistema</title>
    <link rel="icon" type="image/png" href="/images/logo_contapro.png" />
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

        <div id="errorMsgStep1" class="error-msg" style="display: none;">
            <i class="ph ph-warning-circle" style="font-size: 1.2rem;"></i>
            <span id="errorMsgStep1Text"></span>
        </div>

        <form id="loginForm" onsubmit="validarStep1(event)" autocomplete="off">
            <div class="form-group">
                <label for="username">Usuario</label>
                <div class="input-container">
                    <i class="ph ph-user input-icon"></i>
                    <input type="text" id="username" name="username" class="form-control-custom" autocomplete="off" required placeholder="Ingrese su usuario">
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
                <div class="form-group" style="margin-bottom: 1.15rem;">
                    <label for="contribuyente">Contribuyente / Empresa</label>
                    <div class="input-container select-wrapper">
                        <i class="ph ph-buildings input-icon"></i>
                        <select id="contribuyente" class="form-control-custom" onchange="onContribuyenteChange()">
                            <option value="">Cargando contribuyentes...</option>
                        </select>
                        <i class="ph ph-caret-down select-arrow"></i>
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
            errDiv.style.display = 'none';

            const btn = document.getElementById('btnStep1');
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-spinner-gap" style="animation: spin 1s linear infinite;"></i> Verificando...';

            const user = document.getElementById('username').value.trim();
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
                    return;
                }

                notice.style.display = 'none';
                fieldsWrap.style.display = 'block';
                btnText.innerText = 'INGRESAR';

                let html = '<option value="">Seleccionar contribuyente...</option>';
                empresasList.forEach(emp => {
                    const cod = emp.cod_empresa || emp.codEmpresa || emp.COD_EMPRESA || '';
                    const desc = emp.descripcion || emp.DESCRIPCION || '';
                    html += `<option value="${cod}">${cod ? cod + ' - ' : ''}${desc}</option>`;
                });
                select.innerHTML = html;

                let targetEmp = defaultEmpresa;
                if (!targetEmp && empresasList.length > 0) {
                    targetEmp = empresasList[0].cod_empresa || empresasList[0].codEmpresa || empresasList[0].COD_EMPRESA;
                }
                if (targetEmp && select.querySelector(`option[value="${targetEmp}"]`)) {
                    select.value = targetEmp;
                } else if (empresasList.length > 0) {
                    select.value = empresasList[0].cod_empresa || empresasList[0].codEmpresa || empresasList[0].COD_EMPRESA;
                }

                await onContribuyenteChange(defaultSucursal);
            } catch (error) {
                console.error('Error cargando contribuyentes:', error);
            }
        }

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
                    const codEj = ej.codEjercicio || ej.COD_EJERCICIO || ej.cod_ejercicio || '';
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
                    const codSuc = suc.codSucursal || suc.COD_SUCURSAL || suc.cod_sucursal || '';
                    const descSuc = suc.descripcion || suc.DESCRIPCION || '';
                    sucHtml += `<option value="${codSuc}">${codSuc ? codSuc + ' - ' : ''}${descSuc}</option>`;
                });
                sucSelect.innerHTML = sucHtml;

                if (preferredSucursal && sucSelect.querySelector(`option[value="${preferredSucursal}"]`)) {
                    sucSelect.value = preferredSucursal;
                } else if (sucursales.length > 0) {
                    sucSelect.value = sucursales[0].codSucursal || sucursales[0].COD_SUCURSAL || sucursales[0].cod_sucursal;
                }

                const acts = currentEmpresaDetails.actividades || [];
                let actHtml = '';
                if (acts.length) {
                    acts.forEach(a => {
                        actHtml += `<option value="${a.descripcion}" ${a.porDefecto ? 'selected' : ''}>${a.descripcion}</option>`;
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
