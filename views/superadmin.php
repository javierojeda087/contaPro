<!DOCTYPE html>
<html lang="es">
<?= \Core\View::renderHeadFragment('SuperAdmin - Propietarios, Planes y Suscripciones') ?>
<body style="background: var(--bg-color);">

    <?= \Core\View::renderSidebarFragment($modulos ?? [], $planSummary ?? null, $esSuperAdmin ?? false) ?>

    <main class="main-content">
        <?= \Core\View::renderNavbarFragment('Administración de Propietarios, Planes y Suscripciones (SaaS)', $planSummary ?? null, $esSuperAdmin ?? false) ?>

        <style>
            .sa-wrapper {
                max-width: 1480px;
                margin: 0 auto;
                display: flex;
                flex-direction: column;
                gap: 22px;
                padding-bottom: 36px;
            }

            /* KPI Cards */
            .sa-kpi-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
            }
            @media (max-width: 1100px) {
                .sa-kpi-grid { grid-template-columns: repeat(2, 1fr); }
            }
            @media (max-width: 600px) {
                .sa-kpi-grid { grid-template-columns: 1fr; }
            }
            .sa-kpi-card {
                background: #ffffff;
                border-radius: 16px;
                border: 1px solid #e2e8f0;
                padding: 18px 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 14px;
                box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
                transition: all 0.2s ease;
                cursor: pointer;
                position: relative;
                overflow: hidden;
            }
            .sa-kpi-card::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 5px;
                border-radius: 16px 0 0 16px;
            }
            .sa-kpi-card.kpi-blue::before   { background: #0284c7; }
            .sa-kpi-card.kpi-green::before  { background: #16a34a; }
            .sa-kpi-card.kpi-amber::before  { background: #d97706; }
            .sa-kpi-card.kpi-red::before    { background: #dc2626; }

            .sa-kpi-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
                border-color: #cbd5e1;
            }
            .sa-kpi-card.active-filter {
                box-shadow: 0 0 0 2px #0f172a, 0 8px 20px rgba(15, 23, 42, 0.08);
            }
            .sa-kpi-label {
                font-size: 0.73rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #64748b;
                margin-bottom: 4px;
            }
            .sa-kpi-value {
                font-size: 1.85rem;
                font-weight: 800;
                line-height: 1.1;
                color: #0f172a;
            }
            .sa-kpi-sub {
                font-size: 0.75rem;
                color: #64748b;
                font-weight: 600;
                margin-top: 4px;
            }
            .sa-kpi-icon {
                width: 48px;
                height: 48px;
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.55rem;
                flex-shrink: 0;
            }
            .kpi-blue .sa-kpi-icon  { background: #e0f2fe; color: #0284c7; }
            .kpi-green .sa-kpi-icon { background: #dcfce7; color: #16a34a; }
            .kpi-amber .sa-kpi-icon { background: #fef3c7; color: #d97706; }
            .kpi-red .sa-kpi-icon   { background: #fee2e2; color: #dc2626; }

            /* Top Action & View Switcher Bar */
            .sa-toolbar {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                padding: 10px 16px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
                box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
            }
            .sa-tabs {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }
            .sa-tab-btn {
                border: none;
                background: transparent;
                color: #475569;
                font-weight: 700;
                font-size: 0.83rem;
                padding: 8px 14px;
                border-radius: 10px;
                display: inline-flex;
                align-items: center;
                gap: 7px;
                cursor: pointer;
                transition: all 0.18s ease;
            }
            .sa-tab-btn:hover {
                background: #f1f5f9;
                color: #0f172a;
            }
            .sa-tab-btn.active {
                background: #0f172a;
                color: #ffffff;
                box-shadow: 0 4px 10px rgba(15, 23, 42, 0.15);
            }
            .sa-tab-count {
                font-size: 0.72rem;
                padding: 1px 7px;
                border-radius: 99px;
                background: rgba(100, 116, 139, 0.16);
                color: inherit;
                font-weight: 800;
            }
            .sa-tab-btn.active .sa-tab-count {
                background: rgba(255, 255, 255, 0.2);
                color: #ffffff;
            }

            /* Section Cards */
            .sa-card {
                background: #ffffff;
                border-radius: 16px;
                border: 1px solid #e2e8f0;
                box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
                overflow: hidden;
            }
            .sa-card-header {
                padding: 16px 22px;
                background: #ffffff;
                border-bottom: 1px solid #e2e8f0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 12px;
            }
            .sa-card-title-wrap {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .sa-card-icon {
                width: 38px;
                height: 38px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.25rem;
                flex-shrink: 0;
            }
            .sa-card-title {
                margin: 0;
                font-size: 0.96rem;
                font-weight: 800;
                color: #0f172a;
                letter-spacing: -0.01em;
            }
            .sa-card-subtitle {
                margin: 0;
                font-size: 0.77rem;
                color: #64748b;
                font-weight: 500;
            }

            /* Tables */
            .sa-table {
                width: 100%;
                border-collapse: collapse;
                margin: 0;
            }
            .sa-table thead th {
                background: #f8fafc;
                color: #475569;
                font-size: 0.72rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                padding: 12px 16px;
                border-bottom: 1px solid #e2e8f0;
                white-space: nowrap;
            }
            .sa-table tbody tr {
                border-bottom: 1px solid #f1f5f9;
                transition: background 0.15s ease;
            }
            .sa-table tbody tr:last-child {
                border-bottom: none;
            }
            .sa-table tbody tr:hover {
                background: #f8fafc !important;
            }
            .sa-table tbody td {
                padding: 13px 16px;
                vertical-align: middle;
                font-size: 0.86rem;
                color: #1e293b;
                background: transparent !important;
            }

            /* Soft Badges & Pills */
            .sa-pill {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 0.74rem;
                font-weight: 700;
                line-height: 1.2;
                white-space: nowrap;
            }
            .sa-pill-green  { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
            .sa-pill-blue   { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
            .sa-pill-purple { background: #f3e8ff; color: #6d28d9; border: 1px solid #e9d5ff; }
            .sa-pill-amber  { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
            .sa-pill-red    { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
            .sa-pill-slate  { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }

            .sa-dot {
                width: 7px;
                height: 7px;
                border-radius: 50%;
                display: inline-block;
            }
            .sa-dot-green { background: #16a34a; }
            .sa-dot-amber { background: #d97706; }
            .sa-dot-red   { background: #dc2626; }

            /* Tenant Avatar */
            .sa-tenant-cell {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .sa-tenant-avatar {
                width: 38px;
                height: 38px;
                border-radius: 10px;
                background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
                border: 1px solid #86efac;
                color: #15803d;
                font-weight: 800;
                font-size: 0.88rem;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .sa-tenant-avatar.suspended {
                background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
                border-color: #fca5a5;
                color: #dc2626;
            }

            /* Usage Meter */
            .sa-meter-wrap {
                min-width: 96px;
                display: inline-flex;
                flex-direction: column;
                align-items: center;
                gap: 4px;
            }
            .sa-meter-bar {
                width: 84px;
                height: 6px;
                background: #e2e8f0;
                border-radius: 99px;
                overflow: hidden;
            }
            .sa-meter-fill {
                height: 100%;
                border-radius: 99px;
                transition: width 0.3s ease;
            }

            /* Action Buttons */
            .sa-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                padding: 7px 13px;
                border-radius: 9px;
                font-size: 0.8rem;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.18s ease;
                border: 1px solid transparent;
                text-decoration: none;
                white-space: nowrap;
            }
            .sa-btn-primary {
                background: linear-gradient(135deg, #16a34a, #15803d);
                color: #ffffff;
                box-shadow: 0 2px 8px rgba(22, 163, 74, 0.25);
            }
            .sa-btn-primary:hover {
                background: linear-gradient(135deg, #15803d, #166534);
                transform: translateY(-1px);
                color: #ffffff;
            }
            .sa-btn-blue {
                background: linear-gradient(135deg, #0284c7, #0369a1);
                color: #ffffff;
                box-shadow: 0 2px 8px rgba(2, 132, 199, 0.25);
            }
            .sa-btn-blue:hover {
                background: linear-gradient(135deg, #0369a1, #075985);
                transform: translateY(-1px);
                color: #ffffff;
            }
            .sa-btn-ghost {
                background: #ffffff;
                border-color: #cbd5e1;
                color: #334155;
            }
            .sa-btn-ghost:hover {
                background: #f8fafc;
                border-color: #94a3b8;
                color: #0f172a;
            }
            .sa-btn-icon {
                width: 33px;
                height: 33px;
                padding: 0;
                border-radius: 9px;
                background: #ffffff;
                border: 1px solid #cbd5e1;
                color: #475569;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 1rem;
                cursor: pointer;
                transition: all 0.15s ease;
            }
            .sa-btn-icon:hover {
                background: #f1f5f9;
                color: #0f172a;
                border-color: #94a3b8;
            }
            .sa-btn-icon.danger:hover {
                background: #fef2f2;
                color: #dc2626;
                border-color: #fca5a5;
            }
            .sa-btn-icon.success:hover {
                background: #f0fdf4;
                color: #16a34a;
                border-color: #86efac;
            }

            /* Search Input */
            .sa-search-wrap {
                position: relative;
                min-width: 260px;
            }
            .sa-search-wrap i {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: #64748b;
                font-size: 1rem;
                pointer-events: none;
            }
            .sa-search-input {
                width: 100%;
                padding: 7px 12px 7px 34px;
                border-radius: 9px;
                border: 1px solid #cbd5e1;
                background: #f8fafc;
                font-size: 0.82rem;
                font-weight: 600;
                color: #0f172a;
                outline: none;
                transition: all 0.2s;
            }
            .sa-search-input:focus {
                background: #ffffff;
                border-color: #16a34a;
                box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
            }
        </style>

        <div class="content-area">
            <div class="sa-wrapper">

                <!-- 1. KPIs EJECUTIVOS -->
                <div class="sa-kpi-grid">
                    <div class="sa-kpi-card kpi-blue" id="kpiCardALL" onclick="filtrarPorEstadoKpi('ALL')" title="Clic para mostrar todos los propietarios">
                        <div>
                            <div class="sa-kpi-label">Total Propietarios</div>
                            <div class="sa-kpi-value" id="kpiTotalProp">0</div>
                            <div class="sa-kpi-sub" id="kpiMrrSub">MRR Activo: Gs. 0/mes</div>
                        </div>
                        <div class="sa-kpi-icon">
                            <i class="ph ph-buildings"></i>
                        </div>
                    </div>

                    <div class="sa-kpi-card kpi-green" id="kpiCardAL_DIA" onclick="filtrarPorEstadoKpi('AL_DIA')" title="Clic para filtrar suscripciones al día">
                        <div>
                            <div class="sa-kpi-label">Suscripciones Al Día</div>
                            <div class="sa-kpi-value" id="kpiAlDia" style="color: #16a34a;">0</div>
                            <div class="sa-kpi-sub">Servicio activo y regular</div>
                        </div>
                        <div class="sa-kpi-icon">
                            <i class="ph ph-check-circle"></i>
                        </div>
                    </div>

                    <div class="sa-kpi-card kpi-amber" id="kpiCardPOR_VENCER" onclick="filtrarPorEstadoKpi('POR_VENCER')" title="Clic para filtrar cuentas por vencer o en gracia">
                        <div>
                            <div class="sa-kpi-label">Por Vencer / En Gracia</div>
                            <div class="sa-kpi-value" id="kpiPorVencer" style="color: #d97706;">0</div>
                            <div class="sa-kpi-sub">Requieren gestión de cobro</div>
                        </div>
                        <div class="sa-kpi-icon">
                            <i class="ph ph-clock-countdown"></i>
                        </div>
                    </div>

                    <div class="sa-kpi-card kpi-red" id="kpiCardVENCIDO" onclick="filtrarPorEstadoKpi('VENCIDO')" title="Clic para filtrar cuentas vencidas o suspendidas">
                        <div>
                            <div class="sa-kpi-label">Vencidos / Suspendidos</div>
                            <div class="sa-kpi-value" id="kpiVencidos" style="color: #dc2626;">0</div>
                            <div class="sa-kpi-sub">Acceso restringido al sistema</div>
                        </div>
                        <div class="sa-kpi-icon">
                            <i class="ph ph-warning-octagon"></i>
                        </div>
                    </div>
                </div>

                <!-- 2. BARRA DE NAVEGACIÓN DE SECCIONES Y ACCIONES RÁPIDAS -->
                <div class="sa-toolbar">
                    <div class="sa-tabs">
                        <button type="button" class="sa-tab-btn active" id="tabBtnAll" onclick="cambiarVistaSeccion('all')">
                            <i class="ph ph-squares-four"></i> Vista Completa
                        </button>
                        <button type="button" class="sa-tab-btn" id="tabBtnProp" onclick="cambiarVistaSeccion('propietarios')">
                            <i class="ph ph-users-four"></i> Propietarios <span class="sa-tab-count" id="tabCountProp">0</span>
                        </button>
                        <button type="button" class="sa-tab-btn" id="tabBtnPlanes" onclick="cambiarVistaSeccion('planes')">
                            <i class="ph ph-stack"></i> Planes y Límites <span class="sa-tab-count" id="tabCountPlanes">0</span>
                        </button>
                        <button type="button" class="sa-tab-btn" id="tabBtnPagos" onclick="cambiarVistaSeccion('pagos')">
                            <i class="ph ph-receipt"></i> Historial de Pagos <span class="sa-tab-count" id="tabCountPagos">0</span>
                        </button>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <button type="button" class="sa-btn sa-btn-ghost" onclick="abrirModalPlan()">
                            <i class="ph ph-plus-circle" style="color: #0284c7; font-size: 1rem;"></i> Nuevo Plan
                        </button>
                        <button type="button" class="sa-btn sa-btn-primary" onclick="abrirModalPropietario()">
                            <i class="ph ph-user-plus" style="font-size: 1rem;"></i> Nuevo Propietario
                        </button>
                    </div>
                </div>

                <!-- 3. SECCIÓN PROPIETARIOS / CONTROL DE VENCIMIENTO Y SUSCRIPCIONES -->
                <div class="sa-card" id="secPropietarios">
                    <div class="sa-card-header">
                        <div class="sa-card-title-wrap">
                            <div class="sa-card-icon" style="background: #dcfce7; color: #15803d;">
                                <i class="ph ph-users-four"></i>
                            </div>
                            <div>
                                <h3 class="sa-card-title">Propietarios y Suscripciones Activas</h3>
                                <p class="sa-card-subtitle">Control de estudios contables, topes de uso, fechas de vencimiento y estado de cuenta</p>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <div class="sa-search-wrap">
                                <i class="ph ph-magnifying-glass"></i>
                                <input type="text" id="searchPropietarios" class="sa-search-input" placeholder="Buscar propietario, RUC o usuario..." oninput="renderPropietarios()">
                            </div>
                            <select id="filterEstadoProp" class="form-select form-select-sm fw-bold" style="width: auto; min-width: 165px; border-radius: 9px !important; padding: 7px 32px 7px 12px; font-size: 0.8rem;" onchange="renderPropietarios()">
                                <option value="ALL">Todos los estados</option>
                                <option value="AL_DIA">Al día</option>
                                <option value="POR_VENCER">Por vencer / En gracia</option>
                                <option value="VENCIDO">Vencidos / Suspendidos</option>
                            </select>
                        </div>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="sa-table">
                            <thead>
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Propietario / Estudio</th>
                                    <th>RUC / Contacto</th>
                                    <th>Plan Asignado</th>
                                    <th class="text-center">Contribuyentes</th>
                                    <th class="text-center">Usuarios</th>
                                    <th class="text-center">Vencimiento / Suscripción</th>
                                    <th class="text-center">Estado Cuenta</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaPropietariosBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. SECCIÓN PLANES Y LÍMITES DEL SISTEMA -->
                <div class="sa-card" id="secPlanes">
                    <div class="sa-card-header">
                        <div class="sa-card-title-wrap">
                            <div class="sa-card-icon" style="background: #e0f2fe; color: #0284c7;">
                                <i class="ph ph-stack"></i>
                            </div>
                            <div>
                                <h3 class="sa-card-title">Planes Comerciales y Límites del Sistema</h3>
                                <p class="sa-card-subtitle">Configuración de precios mensuales y topes de contribuyentes/usuarios por categoría</p>
                            </div>
                        </div>

                        <button type="button" class="sa-btn sa-btn-blue" onclick="abrirModalPlan()">
                            <i class="ph ph-plus-circle"></i> Nuevo Plan
                        </button>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="sa-table">
                            <thead>
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Código</th>
                                    <th>Nombre del Plan</th>
                                    <th class="text-center">Máx. Contribuyentes</th>
                                    <th class="text-center">Máx. Usuarios</th>
                                    <th class="text-end">Precio Mensual (Gs)</th>
                                    <th>Descripción</th>
                                    <th class="text-end pe-4">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tablaPlanesBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- 5. SECCIÓN HISTORIAL RECIENTE DE PAGOS Y RENOVACIONES -->
                <div class="sa-card" id="secPagos">
                    <div class="sa-card-header">
                        <div class="sa-card-title-wrap">
                            <div class="sa-card-icon" style="background: #f1f5f9; color: #334155;">
                                <i class="ph ph-receipt"></i>
                            </div>
                            <div>
                                <h3 class="sa-card-title">Historial Reciente de Pagos y Renovaciones</h3>
                                <p class="sa-card-subtitle">Últimos cobros registrados y extensiones de vigencia de suscripciones</p>
                            </div>
                        </div>
                        <span class="sa-pill sa-pill-green" id="totalPagosBadge" style="font-size: 0.8rem; padding: 6px 12px;">
                            <i class="ph ph-TrendUp"></i> Total registrado: Gs. 0
                        </span>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="sa-table">
                            <thead>
                                <tr>
                                    <th class="ps-4"># Pago</th>
                                    <th>Fecha Pago</th>
                                    <th>Propietario</th>
                                    <th>Plan</th>
                                    <th class="text-center">Período</th>
                                    <th class="text-end">Monto (Gs)</th>
                                    <th class="text-center">Venc. Anterior</th>
                                    <th class="text-center">Nuevo Vencimiento</th>
                                    <th class="pe-4">Comprobante / Observación</th>
                                </tr>
                            </thead>
                            <tbody id="tablaPagosBody"></tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- MODAL PLAN -->
    <div class="modal fade" id="modalPlan" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 18px; border: 1px solid #cbd5e1; overflow: hidden;">
                <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 18px 24px;">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 36px; height: 36px; border-radius: 10px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                            <i class="ph ph-stack"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold m-0 text-dark" id="modalPlanTitle" style="font-size: 1rem;">Configurar Plan Comercial</h5>
                            <div class="text-muted" style="font-size: 0.75rem;">Defina límites de uso y tarifa mensual</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 22px 24px;">
                    <input type="hidden" id="planId">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-bold small">Código Plan</label>
                            <input type="text" id="planCodigo" class="form-control text-uppercase fw-bold" placeholder="BASICO">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-bold small">Nombre del Plan</label>
                            <input type="text" id="planNombre" class="form-control fw-bold" placeholder="Plan Básico">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-success">Máximo de Contribuyentes</label>
                            <input type="number" id="planMaxContrib" class="form-control fw-bold" min="1" value="5">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-primary">Máximo de Usuarios</label>
                            <input type="number" id="planMaxUsuarios" class="form-control fw-bold" min="1" value="2">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small">Precio Mensual (Gs)</label>
                            <input type="number" id="planPrecio" class="form-control fw-bold" value="150000">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small">Descripción</label>
                            <input type="text" id="planDescripcion" class="form-control" placeholder="Descripción comercial del plan">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 24px;">
                    <button type="button" class="sa-btn sa-btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="sa-btn sa-btn-blue" onclick="guardarPlan()">
                        <i class="ph ph-check-circle"></i> Guardar Plan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL PROPIETARIO -->
    <div class="modal fade" id="modalPropietario" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: 18px; border: 1px solid #cbd5e1; overflow: hidden;">
                <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 18px 24px;">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 38px; height: 38px; border-radius: 10px; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                            <i class="ph ph-users-four"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold m-0 text-dark" id="modalPropTitle" style="font-size: 1.02rem;">Registrar / Editar Propietario</h5>
                            <div class="text-muted" style="font-size: 0.76rem;">Datos del estudio contable, límites del plan y usuario administrador</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 22px 24px;">
                    <input type="hidden" id="propId">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-bold small">Nombre del Propietario / Estudio Contable *</label>
                            <input type="text" id="propNombre" class="form-control text-uppercase fw-bold" placeholder="EJ: ESTUDIO CONTABLE PEREZ">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold small">RUC / CI</label>
                            <input type="text" id="propRuc" class="form-control fw-bold" placeholder="8000000-1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Correo Electrónico</label>
                            <input type="email" id="propEmail" class="form-control" placeholder="cliente@correo.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Teléfono / WhatsApp</label>
                            <input type="text" id="propTelefono" class="form-control" placeholder="+595 981...">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Plan Comercial</label>
                            <select id="propIdPlan" class="form-select fw-bold" onchange="onSelectPlanProp()"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-success">Tope Contribuyentes</label>
                            <input type="number" id="propMaxContrib" class="form-control fw-bold" min="1" value="5">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-primary">Tope Usuarios</label>
                            <input type="number" id="propMaxUsuarios" class="form-control fw-bold" min="1" value="2">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Fecha Vencimiento</label>
                            <input type="date" id="propFecVenc" class="form-control fw-bold">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small" title="Días antes del vencimiento para mostrar el aviso en pantalla">Días Aviso Previo</label>
                            <input type="number" id="propDiasAviso" class="form-control fw-bold" min="1" max="60" value="7">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small" title="Días de tolerancia después del vencimiento antes de cortar el acceso">Días de Gracia</label>
                            <input type="number" id="propDiasGracia" class="form-control fw-bold" min="0" max="60" value="3">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Estado Cuenta</label>
                            <select id="propEstado" class="form-select fw-bold">
                                <option value="A">ACTIVO</option>
                                <option value="I">SUSPENDIDO</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold small">Mensaje / Datos Bancarios para Aviso de Pago (Opcional)</label>
                            <input type="text" id="propMensajePago" class="form-control" placeholder="Ej: Alias SIPAP: 80012345-6 (Banco Itaú) - Enviar comprobante al WhatsApp 0981...">
                        </div>

                        <div class="col-12">
                            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 14px 16px; margin-top: 4px;">
                                <div class="fw-bold text-success small mb-2 d-flex align-items-center gap-1">
                                    <i class="ph ph-key" style="font-size: 1rem;"></i>
                                    <span>Credenciales del Usuario Administrador (Obligatorio al crear, opcional para resetear clave)</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small mb-1">Código de Usuario (Login)</label>
                                        <input type="text" id="propAdminUser" class="form-control text-uppercase fw-bold bg-white" placeholder="EJ: JUAN">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small mb-1">Contraseña</label>
                                        <input type="text" id="propAdminClave" class="form-control fw-bold bg-white" placeholder="Dejar vacío para mantener clave actual">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 24px;">
                    <button type="button" class="sa-btn sa-btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="sa-btn sa-btn-primary" onclick="guardarPropietario()">
                        <i class="ph ph-check-circle"></i> Guardar Propietario
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL RENOVAR SUSCRIPCIÓN / REGISTRAR PAGO -->
    <div class="modal fade" id="modalRenovar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 18px; border: 1px solid #cbd5e1; overflow: hidden;">
                <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 18px 24px;">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 38px; height: 38px; border-radius: 10px; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                            <i class="ph ph-credit-card"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold m-0 text-dark" style="font-size: 1.02rem;">Registrar Pago y Renovar</h5>
                            <div class="text-muted" style="font-size: 0.75rem;">Extiende el vencimiento y reactiva la cuenta automáticamente</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 22px 24px;">
                    <input type="hidden" id="renovarIdProp">
                    <input type="hidden" id="renovarPrecioBase">
                    <input type="hidden" id="renovarVencActual">

                    <div class="p-3 mb-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                        <div class="small text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Propietario / Cuenta</div>
                        <div id="renovarNombreProp" class="fw-bold fs-6 text-dark mt-1">-</div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top small">
                            <span>Plan: <strong id="renovarPlanNombre" class="text-primary">-</strong></span>
                            <span>Vencimiento actual: <strong id="renovarVencActualTexto" class="text-danger">-</strong></span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Período a Renovar</label>
                            <select id="renovarMeses" class="form-select fw-bold" onchange="recalcularRenovacion()">
                                <option value="1">1 Mes</option>
                                <option value="3">3 Meses (Trimestral)</option>
                                <option value="6">6 Meses (Semestral)</option>
                                <option value="12">12 Meses (Anual)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-success">Monto Abonado (Gs)</label>
                            <input type="number" id="renovarMonto" class="form-control fw-bold" min="0">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-primary">Nueva Fecha de Vencimiento</label>
                            <input type="date" id="renovarNuevaFecha" class="form-control fw-bold">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Nro. Comprobante / Factura</label>
                            <input type="text" id="renovarComprobante" class="form-control" placeholder="Ej: Fact. 001-001-000123">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Observación</label>
                            <input type="text" id="renovarObs" class="form-control" placeholder="Ej: Transferencia Itaú">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 24px;">
                    <button type="button" class="sa-btn sa-btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="sa-btn sa-btn-primary" onclick="confirmarRenovacion()">
                        <i class="ph ph-check-circle"></i> Confirmar Pago y Renovar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let listaPlanes = [];
        let listaPropietarios = [];
        let listaPagos = [];
        let modalPlanObj, modalPropObj, modalRenovarObj;

        function escHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function getInitials(name) {
            const parts = String(name || 'CP').trim().split(/\s+/).filter(Boolean);
            if (parts.length === 0) return 'CP';
            if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }

        function formatDatePy(isoStr) {
            if (!isoStr) return '-';
            const s = String(isoStr).slice(0, 10);
            if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
                const [y, m, d] = s.split('-');
                return `${d}/${m}/${y}`;
            }
            return escHtml(isoStr);
        }

        document.addEventListener('DOMContentLoaded', () => {
            modalPlanObj = new bootstrap.Modal(document.getElementById('modalPlan'));
            modalPropObj = new bootstrap.Modal(document.getElementById('modalPropietario'));
            modalRenovarObj = new bootstrap.Modal(document.getElementById('modalRenovar'));
            cargarDatosSuperAdmin();
        });

        async function cargarDatosSuperAdmin() {
            const res = await fetch('/api/superadmin/data');
            const data = await res.json();
            listaPlanes = data.planes || [];
            listaPropietarios = data.propietarios || [];
            listaPagos = data.pagos || [];

            document.getElementById('tabCountProp').textContent = listaPropietarios.length;
            document.getElementById('tabCountPlanes').textContent = listaPlanes.length;
            document.getElementById('tabCountPagos').textContent = listaPagos.length;

            renderKPIs();
            renderPlanes();
            renderPropietarios();
            renderPagos();
        }

        function cambiarVistaSeccion(sec) {
            document.querySelectorAll('.sa-tab-btn').forEach(b => b.classList.remove('active'));
            const secProp = document.getElementById('secPropietarios');
            const secPlanes = document.getElementById('secPlanes');
            const secPagos = document.getElementById('secPagos');

            if (sec === 'propietarios') {
                document.getElementById('tabBtnProp').classList.add('active');
                secProp.style.display = '';
                secPlanes.style.display = 'none';
                secPagos.style.display = 'none';
            } else if (sec === 'planes') {
                document.getElementById('tabBtnPlanes').classList.add('active');
                secProp.style.display = 'none';
                secPlanes.style.display = '';
                secPagos.style.display = 'none';
            } else if (sec === 'pagos') {
                document.getElementById('tabBtnPagos').classList.add('active');
                secProp.style.display = 'none';
                secPlanes.style.display = 'none';
                secPagos.style.display = '';
            } else {
                document.getElementById('tabBtnAll').classList.add('active');
                secProp.style.display = '';
                secPlanes.style.display = '';
                secPagos.style.display = '';
            }
        }

        function filtrarPorEstadoKpi(estado) {
            const sel = document.getElementById('filterEstadoProp');
            sel.value = estado;
            document.querySelectorAll('.sa-kpi-card').forEach(c => c.classList.remove('active-filter'));
            const activeCard = document.getElementById('kpiCard' + estado);
            if (activeCard && estado !== 'ALL') {
                activeCard.classList.add('active-filter');
            }
            cambiarVistaSeccion('propietarios');
            renderPropietarios();
        }

        function renderKPIs() {
            const total = listaPropietarios.length;
            let alDia = 0, porVencer = 0, vencidos = 0, mrrActivo = 0;

            listaPropietarios.forEach(pr => {
                const st = pr.estado_suscripcion || 'AL_DIA';
                if (st === 'AL_DIA') alDia++;
                else if (st === 'POR_VENCER' || st === 'EN_GRACIA') porVencer++;
                else vencidos++;

                if (pr.estado === 'A' && st !== 'VENCIDO' && st !== 'SUSPENDIDO') {
                    mrrActivo += Number(pr.precio_mensual || 0);
                }
            });

            document.getElementById('kpiTotalProp').textContent = total;
            document.getElementById('kpiAlDia').textContent = alDia;
            document.getElementById('kpiPorVencer').textContent = porVencer;
            document.getElementById('kpiVencidos').textContent = vencidos;
            document.getElementById('kpiMrrSub').textContent = `MRR Activo: Gs. ${mrrActivo.toLocaleString('es-PY')}/mes`;
        }

        function renderPlanes() {
            const tb = document.getElementById('tablaPlanesBody');
            tb.innerHTML = listaPlanes.map(p => {
                const cod = String(p.codigo_plan || '').toUpperCase();
                let pillClass = 'sa-pill-slate';
                if (cod === 'MEDIO') pillClass = 'sa-pill-blue';
                else if (cod === 'PRO') pillClass = 'sa-pill-green';
                else if (cod === 'ILIMITADO') pillClass = 'sa-pill-purple';

                const maxC = Number(p.max_contribuyentes) >= 9999 ? 'Ilimitado' : p.max_contribuyentes;
                const maxU = Number(p.max_usuarios) >= 999 ? 'Ilimitado' : p.max_usuarios;
                const precio = Number(p.precio_mensual || 0);

                return `
                <tr>
                    <td class="ps-4 fw-bold text-muted">#${p.id_plan}</td>
                    <td><span class="sa-pill ${pillClass}">${escHtml(cod)}</span></td>
                    <td>
                        <div class="fw-bold text-dark" style="font-size: 0.9rem;">${escHtml(p.nombre_plan)}</div>
                    </td>
                    <td class="text-center">
                        <span class="sa-pill sa-pill-green">
                            <i class="ph ph-buildings"></i> ${escHtml(maxC)}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="sa-pill sa-pill-blue">
                            <i class="ph ph-users"></i> ${escHtml(maxU)}
                        </span>
                    </td>
                    <td class="text-end fw-bold" style="color: #0f172a; font-size: 0.92rem;">
                        ${precio > 0 ? 'Gs. ' + precio.toLocaleString('es-PY') : '<span class="text-muted fw-semibold">Sin cargo</span>'}
                    </td>
                    <td class="text-muted" style="font-size: 0.82rem; max-width: 360px;">${escHtml(p.descripcion || '')}</td>
                    <td class="text-end pe-4">
                        <button class="sa-btn sa-btn-ghost" onclick="editarPlan(${p.id_plan})">
                            <i class="ph ph-pencil-simple" style="color: #0284c7;"></i> Editar
                        </button>
                    </td>
                </tr>`;
            }).join('');

            const sel = document.getElementById('propIdPlan');
            sel.innerHTML = listaPlanes.map(p => `<option value="${p.id_plan}">${escHtml(p.nombre_plan)} (${p.max_contribuyentes} Contrib. / ${p.max_usuarios} Usu. - Gs. ${Number(p.precio_mensual||0).toLocaleString('es-PY')})</option>`).join('');
        }

        function formatBadgeSuscripcion(pr) {
            const st = pr.estado_suscripcion || 'AL_DIA';
            const d = pr.dias_restantes !== null && pr.dias_restantes !== undefined ? Number(pr.dias_restantes) : null;
            const vencFmt = escHtml(pr.fec_vencimiento_fmt || 'Sin venc.');

            if (st === 'SUSPENDIDO') {
                return `<div class="fw-bold text-danger mb-1" style="font-size: 0.84rem;">${vencFmt}</div>
                        <span class="sa-pill sa-pill-red"><span class="sa-dot sa-dot-red"></span> Cuenta Suspendida</span>`;
            }
            if (st === 'VENCIDO') {
                const hace = d !== null ? Math.abs(d) : 0;
                return `<div class="fw-bold text-danger mb-1" style="font-size: 0.84rem;">${vencFmt}</div>
                        <span class="sa-pill sa-pill-red"><span class="sa-dot sa-dot-red"></span> Vencido hace ${hace}d</span>`;
            }
            if (st === 'EN_GRACIA') {
                const hace = d !== null ? Math.abs(d) : 0;
                return `<div class="fw-bold text-warning-emphasis mb-1" style="font-size: 0.84rem;">${vencFmt}</div>
                        <span class="sa-pill sa-pill-amber"><span class="sa-dot sa-dot-amber"></span> En gracia (venció hace ${hace}d)</span>`;
            }
            if (st === 'POR_VENCER') {
                const label = d === 0 ? '¡Vence Hoy!' : `Vence en ${d} día(s)`;
                return `<div class="fw-bold text-warning-emphasis mb-1" style="font-size: 0.84rem;">${vencFmt}</div>
                        <span class="sa-pill sa-pill-amber"><span class="sa-dot sa-dot-amber"></span> ${label}</span>`;
            }

            if (d !== null && d > 3650) {
                return `<div class="fw-bold text-success mb-1" style="font-size: 0.84rem;">${vencFmt}</div>
                        <span class="sa-pill sa-pill-purple"><i class="ph ph-infinity"></i> Licencia Permanente</span>`;
            }
            const sub = d !== null ? `Al día (${d}d restantes)` : 'Al día';
            return `<div class="fw-bold text-success mb-1" style="font-size: 0.84rem;">${vencFmt}</div>
                    <span class="sa-pill sa-pill-green"><span class="sa-dot sa-dot-green"></span> ${sub}</span>`;
        }

        function renderPropietarios() {
            const tb = document.getElementById('tablaPropietariosBody');
            const q = (document.getElementById('searchPropietarios')?.value || '').trim().toUpperCase();
            const fEst = document.getElementById('filterEstadoProp')?.value || 'ALL';

            const filtrados = listaPropietarios.filter(pr => {
                const st = pr.estado_suscripcion || 'AL_DIA';
                if (fEst === 'AL_DIA' && st !== 'AL_DIA') return false;
                if (fEst === 'POR_VENCER' && st !== 'POR_VENCER' && st !== 'EN_GRACIA') return false;
                if (fEst === 'VENCIDO' && st !== 'VENCIDO' && st !== 'SUSPENDIDO') return false;

                if (q !== '') {
                    const haystack = `${pr.id_propietario} ${pr.nombre_propietario || ''} ${pr.ruc || ''} ${pr.email || ''} ${pr.usuarios_lista || ''} ${pr.nombre_plan || ''}`.toUpperCase();
                    if (!haystack.includes(q)) return false;
                }
                return true;
            });

            if (filtrados.length === 0) {
                tb.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-5">
                    <i class="ph ph-magnifying-glass" style="font-size: 2rem; display: block; margin-bottom: 6px; opacity: 0.5;"></i>
                    No se encontraron propietarios con el filtro seleccionado.
                </td></tr>`;
                return;
            }

            tb.innerHTML = filtrados.map(pr => {
                const usadosC = Number(pr.usados_contribuyentes || 0);
                const maxC = Number(pr.max_contribuyentes || 1);
                const pctC = maxC > 0 ? Math.min(100, Math.round((usadosC / maxC) * 100)) : 0;
                const colorC = usadosC >= maxC ? '#dc2626' : (pctC >= 80 ? '#d97706' : '#16a34a');

                const usadosU = Number(pr.usados_usuarios || 0);
                const maxU = Number(pr.max_usuarios || 1);
                const pctU = maxU > 0 ? Math.min(100, Math.round((usadosU / maxU) * 100)) : 0;
                const colorU = usadosU >= maxU ? '#dc2626' : (pctU >= 80 ? '#d97706' : '#0284c7');

                const isActivo = pr.estado === 'A';
                const initials = escHtml(getInitials(pr.nombre_propietario));
                const planName = escHtml(pr.nombre_plan || 'Plan Básico');
                const isProPlan = planName.toUpperCase().includes('PRO') || planName.toUpperCase().includes('CORPORATIVO');

                const btnToggle = Number(pr.id_propietario) === 1 ? '' : `
                    <button class="sa-btn-icon ${isActivo ? 'danger' : 'success'}"
                            title="${isActivo ? 'Suspender acceso del propietario' : 'Reactivar acceso del propietario'}"
                            onclick="toggleEstadoPropietario(${pr.id_propietario}, '${isActivo ? 'I' : 'A'}', '${escHtml(pr.nombre_propietario)}')">
                        <i class="ph ${isActivo ? 'ph-pause-circle' : 'ph-play-circle'}"></i>
                    </button>`;

                return `
                <tr>
                    <td class="ps-4 fw-bold text-muted">#${pr.id_propietario}</td>
                    <td>
                        <div class="sa-tenant-cell">
                            <div class="sa-tenant-avatar ${isActivo ? '' : 'suspended'}">${initials}</div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size: 0.9rem;">${escHtml(pr.nombre_propietario)}</div>
                                <div class="d-flex align-items-center gap-1 mt-1" style="font-size: 0.75rem; color: #64748b;">
                                    <i class="ph ph-user-circle" style="color: #16a34a; font-size: 0.9rem;"></i>
                                    <span>Usuarios: <strong class="text-dark">${escHtml(pr.usuarios_lista || '-')}</strong></span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-bold text-dark" style="font-size: 0.84rem;">${escHtml(pr.ruc || 'Sin RUC')}</div>
                        <div class="small text-muted" style="font-size: 0.76rem;">
                            ${pr.email ? `<span>${escHtml(pr.email)}</span>` : ''}
                            ${pr.telefono ? `<span class="ms-1 text-secondary">• ${escHtml(pr.telefono)}</span>` : ''}
                        </div>
                    </td>
                    <td>
                        <span class="sa-pill ${isProPlan ? 'sa-pill-blue' : 'sa-pill-slate'}">${planName}</span>
                        <div class="small text-muted fw-semibold mt-1" style="font-size: 0.76rem;">
                            Gs. ${Number(pr.precio_mensual || 0).toLocaleString('es-PY')}/mes
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="sa-meter-wrap">
                            <div class="fw-bold" style="color: ${colorC}; font-size: 0.84rem;">
                                ${usadosC} / ${maxC >= 9999 ? '∞' : maxC}
                            </div>
                            <div class="sa-meter-bar">
                                <div class="sa-meter-fill" style="width: ${pctC}%; background: ${colorC};"></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="sa-meter-wrap">
                            <div class="fw-bold" style="color: ${colorU}; font-size: 0.84rem;">
                                ${usadosU} / ${maxU >= 999 ? '∞' : maxU}
                            </div>
                            <div class="sa-meter-bar">
                                <div class="sa-meter-fill" style="width: ${pctU}%; background: ${colorU};"></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-center">
                        ${formatBadgeSuscripcion(pr)}
                    </td>
                    <td class="text-center">
                        <span class="sa-pill ${isActivo ? 'sa-pill-green' : 'sa-pill-red'}">
                            <span class="sa-dot ${isActivo ? 'sa-dot-green' : 'sa-dot-red'}"></span>
                            ${isActivo ? 'ACTIVO' : 'SUSPENDIDO'}
                        </span>
                    </td>
                    <td class="text-end pe-4">
                        <div class="d-inline-flex align-items-center justify-content-end gap-1">
                            <button class="sa-btn sa-btn-primary" title="Registrar Pago y Renovar Vencimiento" onclick="abrirModalRenovar(${pr.id_propietario})">
                                <i class="ph ph-credit-card"></i> Renovar
                            </button>
                            <button class="sa-btn-icon" title="Editar Configuración del Propietario" onclick="editarPropietario(${pr.id_propietario})">
                                <i class="ph ph-pencil-simple"></i>
                            </button>
                            ${btnToggle}
                        </div>
                    </td>
                </tr>`;
            }).join('');
        }

        function renderPagos() {
            const tb = document.getElementById('tablaPagosBody');
            const totalBadge = document.getElementById('totalPagosBadge');
            const sumaTotal = listaPagos.reduce((acc, pg) => acc + Number(pg.monto || 0), 0);
            if (totalBadge) {
                totalBadge.innerHTML = `<i class="ph ph-trend-up"></i> Total registrado: Gs. ${sumaTotal.toLocaleString('es-PY')}`;
            }

            if (!listaPagos.length) {
                tb.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-5">
                    <i class="ph ph-receipt" style="font-size: 2rem; display: block; margin-bottom: 6px; opacity: 0.45;"></i>
                    Aún no se han registrado pagos o renovaciones manuales.
                </td></tr>`;
                return;
            }
            tb.innerHTML = listaPagos.map(pg => `
                <tr>
                    <td class="ps-4 fw-bold text-muted">#${pg.id_pago}</td>
                    <td class="fw-semibold">${formatDatePy(pg.fecha_pago)}</td>
                    <td class="fw-bold text-dark">${escHtml(pg.nombre_propietario || ('#' + pg.id_propietario))}</td>
                    <td><span class="sa-pill sa-pill-slate">${escHtml(pg.nombre_plan || '-')}</span></td>
                    <td class="text-center"><span class="sa-pill sa-pill-blue">${Number(pg.meses_renovados || 1)} mes(es)</span></td>
                    <td class="text-end fw-bold text-success">Gs. ${Number(pg.monto || 0).toLocaleString('es-PY')}</td>
                    <td class="text-center text-muted">${formatDatePy(pg.fec_vencimiento_anterior)}</td>
                    <td class="text-center fw-bold text-primary">${formatDatePy(pg.fec_vencimiento_nueva)}</td>
                    <td class="small pe-4">${pg.comprobante ? `<span class="sa-pill sa-pill-slate me-1">${escHtml(pg.comprobante)}</span>` : ''}${escHtml(pg.observacion || '')}</td>
                </tr>
            `).join('');
        }

        function abrirModalPlan() {
            document.getElementById('planId').value = '';
            document.getElementById('planCodigo').value = '';
            document.getElementById('planNombre').value = '';
            document.getElementById('planMaxContrib').value = 5;
            document.getElementById('planMaxUsuarios').value = 2;
            document.getElementById('planPrecio').value = 150000;
            document.getElementById('planDescripcion').value = '';
            modalPlanObj.show();
        }

        function editarPlan(id) {
            const p = listaPlanes.find(x => Number(x.id_plan) === Number(id));
            if (!p) return;
            document.getElementById('planId').value = p.id_plan;
            document.getElementById('planCodigo').value = p.codigo_plan || '';
            document.getElementById('planNombre').value = p.nombre_plan || '';
            document.getElementById('planMaxContrib').value = p.max_contribuyentes;
            document.getElementById('planMaxUsuarios').value = p.max_usuarios;
            document.getElementById('planPrecio').value = p.precio_mensual || 0;
            document.getElementById('planDescripcion').value = p.descripcion || '';
            modalPlanObj.show();
        }

        async function guardarPlan() {
            const body = new URLSearchParams({
                idPlan: document.getElementById('planId').value,
                codigoPlan: document.getElementById('planCodigo').value,
                nombrePlan: document.getElementById('planNombre').value,
                maxContribuyentes: document.getElementById('planMaxContrib').value,
                maxUsuarios: document.getElementById('planMaxUsuarios').value,
                precioMensual: document.getElementById('planPrecio').value,
                descripcion: document.getElementById('planDescripcion').value
            });
            const res = await fetch('/api/superadmin/plan/save', { method: 'POST', body });
            const json = await res.json();
            if (json.status === 'success') {
                modalPlanObj.hide();
                showToast(json.message || 'Plan guardado');
                cargarDatosSuperAdmin();
            } else {
                showAlert(json.message || 'Error al guardar plan', 'error');
            }
        }

        function onSelectPlanProp() {
            const idPlan = Number(document.getElementById('propIdPlan').value);
            const p = listaPlanes.find(x => Number(x.id_plan) === idPlan);
            if (p) {
                document.getElementById('propMaxContrib').value = p.max_contribuyentes;
                document.getElementById('propMaxUsuarios').value = p.max_usuarios;
            }
        }

        function abrirModalPropietario() {
            document.getElementById('propId').value = '';
            document.getElementById('propNombre').value = '';
            document.getElementById('propRuc').value = '';
            document.getElementById('propEmail').value = '';
            document.getElementById('propTelefono').value = '';
            document.getElementById('propIdPlan').value = listaPlanes[0]?.id_plan || 1;
            onSelectPlanProp();
            const nextMonth = new Date();
            nextMonth.setMonth(nextMonth.getMonth() + 1);
            document.getElementById('propFecVenc').value = nextMonth.toISOString().slice(0, 10);
            document.getElementById('propDiasAviso').value = 7;
            document.getElementById('propDiasGracia').value = 3;
            document.getElementById('propMensajePago').value = '';
            document.getElementById('propEstado').value = 'A';
            document.getElementById('propAdminUser').value = '';
            document.getElementById('propAdminClave').value = '';
            modalPropObj.show();
        }

        function editarPropietario(id) {
            const pr = listaPropietarios.find(x => Number(x.id_propietario) === Number(id));
            if (!pr) return;
            document.getElementById('propId').value = pr.id_propietario;
            document.getElementById('propNombre').value = pr.nombre_propietario || '';
            document.getElementById('propRuc').value = pr.ruc || '';
            document.getElementById('propEmail').value = pr.email || '';
            document.getElementById('propTelefono').value = pr.telefono || '';
            document.getElementById('propIdPlan').value = pr.id_plan || 1;
            document.getElementById('propMaxContrib').value = pr.max_contribuyentes;
            document.getElementById('propMaxUsuarios').value = pr.max_usuarios;
            document.getElementById('propFecVenc').value = pr.fec_vencimiento ? pr.fec_vencimiento.slice(0, 10) : '';
            document.getElementById('propDiasAviso').value = pr.dias_aviso ?? 7;
            document.getElementById('propDiasGracia').value = pr.dias_gracia ?? 3;
            document.getElementById('propMensajePago').value = pr.mensaje_pago || '';
            document.getElementById('propEstado').value = pr.estado || 'A';
            document.getElementById('propAdminUser').value = pr.primer_usuario || '';
            document.getElementById('propAdminClave').value = '';
            modalPropObj.show();
        }

        async function guardarPropietario() {
            const body = new URLSearchParams({
                idPropietario: document.getElementById('propId').value,
                nombrePropietario: document.getElementById('propNombre').value,
                ruc: document.getElementById('propRuc').value,
                email: document.getElementById('propEmail').value,
                telefono: document.getElementById('propTelefono').value,
                idPlan: document.getElementById('propIdPlan').value,
                maxContribuyentes: document.getElementById('propMaxContrib').value,
                maxUsuarios: document.getElementById('propMaxUsuarios').value,
                fecVencimiento: document.getElementById('propFecVenc').value,
                diasAviso: document.getElementById('propDiasAviso').value,
                diasGracia: document.getElementById('propDiasGracia').value,
                mensajePago: document.getElementById('propMensajePago').value,
                estado: document.getElementById('propEstado').value,
                adminUser: document.getElementById('propAdminUser').value,
                adminClave: document.getElementById('propAdminClave').value
            });
            const res = await fetch('/api/superadmin/propietario/save', { method: 'POST', body });
            const json = await res.json();
            if (json.status === 'success') {
                modalPropObj.hide();
                showToast(json.message || 'Propietario guardado');
                cargarDatosSuperAdmin();
            } else {
                showAlert(json.message || 'Error al guardar propietario', 'error');
            }
        }

        function abrirModalRenovar(idProp) {
            const pr = listaPropietarios.find(x => Number(x.id_propietario) === Number(idProp));
            if (!pr) return;

            document.getElementById('renovarIdProp').value = pr.id_propietario;
            document.getElementById('renovarPrecioBase').value = pr.precio_mensual || 150000;
            document.getElementById('renovarVencActual').value = pr.fec_vencimiento ? pr.fec_vencimiento.slice(0, 10) : '';
            document.getElementById('renovarNombreProp').textContent = `#${pr.id_propietario} - ${pr.nombre_propietario}`;
            document.getElementById('renovarPlanNombre').textContent = `${pr.nombre_plan} (Gs. ${Number(pr.precio_mensual || 0).toLocaleString('es-PY')}/mes)`;
            document.getElementById('renovarVencActualTexto').textContent = pr.fec_vencimiento_fmt || 'Sin vencimiento';
            document.getElementById('renovarMeses').value = '1';
            document.getElementById('renovarComprobante').value = '';
            document.getElementById('renovarObs').value = '';

            recalcularRenovacion();
            modalRenovarObj.show();
        }

        function recalcularRenovacion() {
            const meses = Number(document.getElementById('renovarMeses').value || 1);
            const precioBase = Number(document.getElementById('renovarPrecioBase').value || 0);
            document.getElementById('renovarMonto').value = precioBase * meses;

            const vencAct = document.getElementById('renovarVencActual').value;
            const hoyStr = new Date().toISOString().slice(0, 10);
            const baseStr = (vencAct && vencAct >= hoyStr) ? vencAct : hoyStr;
            const parts = baseStr.split('-').map(Number);
            const dt = new Date(parts[0], parts[1] - 1 + meses, parts[2]);
            const yyyy = dt.getFullYear();
            const mm = String(dt.getMonth() + 1).padStart(2, '0');
            const dd = String(dt.getDate()).padStart(2, '0');
            document.getElementById('renovarNuevaFecha').value = `${yyyy}-${mm}-${dd}`;
        }

        async function confirmarRenovacion() {
            const body = new URLSearchParams({
                idPropietario: document.getElementById('renovarIdProp').value,
                meses: document.getElementById('renovarMeses').value,
                monto: document.getElementById('renovarMonto').value,
                nuevaFechaVencimiento: document.getElementById('renovarNuevaFecha').value,
                comprobante: document.getElementById('renovarComprobante').value,
                observacion: document.getElementById('renovarObs').value
            });
            const res = await fetch('/api/superadmin/propietario/renovar', { method: 'POST', body });
            const json = await res.json();
            if (json.status === 'success') {
                modalRenovarObj.hide();
                showToast(json.message || 'Suscripción renovada');
                cargarDatosSuperAdmin();
            } else {
                showAlert(json.message || 'Error al renovar suscripción', 'error');
            }
        }

        async function toggleEstadoPropietario(idProp, nuevoEstado, nombreProp = '') {
            const accion = nuevoEstado === 'A' ? 'reactivar' : 'suspender';
            const label = nombreProp ? `#${idProp} (${nombreProp})` : `#${idProp}`;
            const conf = await showConfirm(
                `¿Confirma que desea ${accion} el acceso de la cuenta ${label}?`,
                nuevoEstado === 'A' ? 'Reactivar Propietario' : 'Suspender Propietario'
            );
            if (!conf || !conf.isConfirmed) return;

            const body = new URLSearchParams({ idPropietario: idProp, estado: nuevoEstado });
            const res = await fetch('/api/superadmin/propietario/toggle-estado', { method: 'POST', body });
            const json = await res.json();
            if (json.status === 'success') {
                showToast(json.message);
                cargarDatosSuperAdmin();
            } else {
                showAlert(json.message || 'No se pudo cambiar el estado', 'error');
            }
        }
    </script>
    <?= \Core\View::renderScriptsFragment() ?>
</body>
</html>
