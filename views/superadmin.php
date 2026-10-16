<!DOCTYPE html>
<html lang="es">
<?= \Core\View::renderHeadFragment('SuperAdmin - Propietarios, Planes y Suscripciones') ?>
<body style="background: var(--bg-color);">

    <?= \Core\View::renderSidebarFragment($modulos ?? [], $planSummary ?? null, $esSuperAdmin ?? false) ?>

    <main class="main-content">
        <?= \Core\View::renderNavbarFragment('Administración de Propietarios, Planes y Suscripciones (SaaS)', $planSummary ?? null, $esSuperAdmin ?? false) ?>

        <div class="content-area">

            <!-- KPIs DE SUSCRIPCIONES -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div style="background: #ffffff; border-radius: 14px; border: 1px solid #cbd5e1; border-left: 5px solid #0284c7; padding: 14px 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
                        <div class="text-muted fw-bold small text-uppercase">Total Propietarios</div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span id="kpiTotalProp" style="font-size: 1.65rem; font-weight: 800; color: #0f172a;">0</span>
                            <i class="ph ph-users-four" style="font-size: 1.8rem; color: #0284c7;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div style="background: #ffffff; border-radius: 14px; border: 1px solid #cbd5e1; border-left: 5px solid #16a34a; padding: 14px 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
                        <div class="text-muted fw-bold small text-uppercase">Suscripciones Al Día</div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span id="kpiAlDia" style="font-size: 1.65rem; font-weight: 800; color: #16a34a;">0</span>
                            <i class="ph ph-check-circle" style="font-size: 1.8rem; color: #16a34a;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div style="background: #ffffff; border-radius: 14px; border: 1px solid #cbd5e1; border-left: 5px solid #d97706; padding: 14px 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
                        <div class="text-muted fw-bold small text-uppercase">Por Vencer / En Gracia</div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span id="kpiPorVencer" style="font-size: 1.65rem; font-weight: 800; color: #d97706;">0</span>
                            <i class="ph ph-clock-countdown" style="font-size: 1.8rem; color: #d97706;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div style="background: #ffffff; border-radius: 14px; border: 1px solid #cbd5e1; border-left: 5px solid #dc2626; padding: 14px 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
                        <div class="text-muted fw-bold small text-uppercase">Vencidos / Suspendidos</div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span id="kpiVencidos" style="font-size: 1.65rem; font-weight: 800; color: #dc2626;">0</span>
                            <i class="ph ph-warning-octagon" style="font-size: 1.8rem; color: #dc2626;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 1: PLANES COMERCIALES -->
            <div style="background: #ffffff; border-radius: 14px; border: 1px solid #cbd5e1; box-shadow: 0 8px 25px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 24px;">
                <div style="padding: 14px 22px; background: #0f172a; color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 0.96rem;">
                        <i class="ph ph-stack" style="color: #38bdf8; font-size: 1.3rem;"></i>
                        <span>PLANES Y LÍMITES DEL SISTEMA (CONTRIBUYENTES Y USUARIOS)</span>
                    </div>
                    <button class="btn btn-sm btn-success fw-bold" onclick="abrirModalPlan()">
                        <i class="ph ph-plus-circle"></i> Nuevo Plan
                    </button>
                </div>

                <div style="overflow-x: auto;">
                    <table class="table table-hover align-middle m-0" style="font-size: 0.88rem;">
                        <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; font-size: 0.76rem; color: #475569;">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Código</th>
                                <th>Nombre del Plan</th>
                                <th class="text-center">Máx. Contribuyentes</th>
                                <th class="text-center">Máx. Usuarios</th>
                                <th class="text-end">Precio Mensual (Gs)</th>
                                <th>Descripción</th>
                                <th class="text-center pe-4">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tablaPlanesBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- SECCIÓN 2: PROPIETARIOS (TENANTS / CLIENTES SAAS) -->
            <div style="background: #ffffff; border-radius: 14px; border: 1px solid #cbd5e1; box-shadow: 0 8px 25px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 24px;">
                <div style="padding: 14px 22px; background: #15803d; color: #ffffff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 0.96rem;">
                        <i class="ph ph-users-four" style="font-size: 1.35rem;"></i>
                        <span>PROPIETARIOS / CONTROL DE VENCIMIENTO Y SUSCRIPCIONES</span>
                    </div>
                    <button class="btn btn-sm btn-light fw-bold text-success" onclick="abrirModalPropietario()">
                        <i class="ph ph-user-plus"></i> Nuevo Propietario
                    </button>
                </div>

                <div style="overflow-x: auto;">
                    <table class="table table-hover align-middle m-0" style="font-size: 0.86rem;">
                        <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; font-size: 0.74rem; color: #475569;">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Propietario / Estudio</th>
                                <th>RUC / Contacto</th>
                                <th>Plan Asignado</th>
                                <th class="text-center">Contribuyentes</th>
                                <th class="text-center">Usuarios</th>
                                <th class="text-center">Vencimiento / Estado Pago</th>
                                <th class="text-center">Estado Cuenta</th>
                                <th class="text-center pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaPropietariosBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- SECCIÓN 3: HISTORIAL DE PAGOS Y RENOVACIONES -->
            <div style="background: #ffffff; border-radius: 14px; border: 1px solid #cbd5e1; box-shadow: 0 8px 25px rgba(0,0,0,0.05); overflow: hidden;">
                <div style="padding: 12px 22px; background: #1e293b; color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 0.92rem;">
                        <i class="ph ph-receipt" style="color: #4ade80; font-size: 1.3rem;"></i>
                        <span>HISTORIAL RECIENTE DE PAGOS Y RENOVACIONES DE SUSCRIPCIÓN</span>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table class="table table-hover align-middle m-0" style="font-size: 0.84rem;">
                        <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; font-size: 0.73rem; color: #475569;">
                            <tr>
                                <th class="ps-4"># Pago</th>
                                <th>Fecha Pago</th>
                                <th>Propietario</th>
                                <th>Plan</th>
                                <th class="text-center">Meses</th>
                                <th class="text-end">Monto (Gs)</th>
                                <th class="text-center">Venc. Anterior</th>
                                <th class="text-center">Nuevo Vencimiento</th>
                                <th>Comprobante / Obs.</th>
                            </tr>
                        </thead>
                        <tbody id="tablaPagosBody"></tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL PLAN -->
    <div class="modal fade" id="modalPlan" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px;">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="modalPlanTitle">Configurar Plan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
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
                            <input type="number" id="planPrecio" class="form-control" value="150000">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small">Descripción</label>
                            <input type="text" id="planDescripcion" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success fw-bold" onclick="guardarPlan()">Guardar Plan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL PROPIETARIO -->
    <div class="modal fade" id="modalPropietario" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px;">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="modalPropTitle">Registrar / Editar Propietario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="propId">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-bold small">Nombre del Propietario / Estudio Contable *</label>
                            <input type="text" id="propNombre" class="form-control text-uppercase fw-bold" placeholder="EJ: JUAN PEREZ">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold small">RUC / CI</label>
                            <input type="text" id="propRuc" class="form-control" placeholder="8000000-1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Correo Electrónico</label>
                            <input type="email" id="propEmail" class="form-control" placeholder="cliente@correo.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Teléfono</label>
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
                            <hr class="my-2">
                            <div class="fw-bold text-success small mb-2">
                                <i class="ph ph-key"></i> Usuario Administrador Inicial para este Propietario (Opcional al editar, obligatorio al crear)
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Código de Usuario (Login)</label>
                            <input type="text" id="propAdminUser" class="form-control text-uppercase fw-bold" placeholder="EJ: JUAN">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Contraseña Inicial</label>
                            <input type="text" id="propAdminClave" class="form-control fw-bold" placeholder="Ingrese clave para crear o resetear">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success fw-bold" onclick="guardarPropietario()">Guardar Propietario</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL RENOVAR SUSCRIPCIÓN / REGISTRAR PAGO -->
    <div class="modal fade" id="modalRenovar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px;">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="ph ph-credit-card"></i> Registrar Pago / Renovar Suscripción
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="renovarIdProp">
                    <input type="hidden" id="renovarPrecioBase">
                    <input type="hidden" id="renovarVencActual">

                    <div class="p-3 mb-3 rounded-3" style="background: #f8fafc; border: 1px solid #cbd5e1;">
                        <div class="small text-muted fw-bold text-uppercase">Propietario</div>
                        <div id="renovarNombreProp" class="fw-bold fs-5 text-dark">-</div>
                        <div class="d-flex justify-content-between mt-1 small">
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary fw-bold" onclick="confirmarRenovacion()">
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
            renderKPIs();
            renderPlanes();
            renderPropietarios();
            renderPagos();
        }

        function renderKPIs() {
            const total = listaPropietarios.length;
            let alDia = 0, porVencer = 0, vencidos = 0;
            listaPropietarios.forEach(pr => {
                const st = pr.estado_suscripcion || 'AL_DIA';
                if (st === 'AL_DIA') alDia++;
                else if (st === 'POR_VENCER' || st === 'EN_GRACIA') porVencer++;
                else vencidos++;
            });
            document.getElementById('kpiTotalProp').textContent = total;
            document.getElementById('kpiAlDia').textContent = alDia;
            document.getElementById('kpiPorVencer').textContent = porVencer;
            document.getElementById('kpiVencidos').textContent = vencidos;
        }

        function renderPlanes() {
            const tb = document.getElementById('tablaPlanesBody');
            tb.innerHTML = listaPlanes.map(p => `
                <tr>
                    <td class="ps-4 fw-bold">${p.id_plan}</td>
                    <td><span class="badge bg-secondary">${p.codigo_plan}</span></td>
                    <td class="fw-bold">${p.nombre_plan}</td>
                    <td class="text-center"><span class="badge bg-success fs-6">${p.max_contribuyentes}</span></td>
                    <td class="text-center"><span class="badge bg-primary fs-6">${p.max_usuarios}</span></td>
                    <td class="text-end fw-bold">${Number(p.precio_mensual || 0).toLocaleString('es-PY')}</td>
                    <td class="text-muted small">${p.descripcion || ''}</td>
                    <td class="text-center pe-4">
                        <button class="btn btn-sm btn-outline-primary" onclick="editarPlan(${p.id_plan})">
                            <i class="ph ph-pencil-simple"></i> Editar
                        </button>
                    </td>
                </tr>
            `).join('');

            const sel = document.getElementById('propIdPlan');
            sel.innerHTML = listaPlanes.map(p => `<option value="${p.id_plan}">${p.nombre_plan} (${p.max_contribuyentes} Contrib. / ${p.max_usuarios} Usu. - Gs. ${Number(p.precio_mensual||0).toLocaleString('es-PY')})</option>`).join('');
        }

        function formatBadgeSuscripcion(pr) {
            const st = pr.estado_suscripcion || 'AL_DIA';
            const d = pr.dias_restantes;
            const vencFmt = pr.fec_vencimiento_fmt || 'Sin venc.';

            if (st === 'SUSPENDIDO') {
                return `<div class="fw-bold text-danger">${vencFmt}</div>
                        <span class="badge bg-danger">CUENTA SUSPENDIDA</span>`;
            }
            if (st === 'VENCIDO') {
                const hace = d !== null ? Math.abs(d) : 0;
                return `<div class="fw-bold text-danger">${vencFmt}</div>
                        <span class="badge bg-danger">VENCIDO (hace ${hace}d)</span>`;
            }
            if (st === 'EN_GRACIA') {
                const hace = d !== null ? Math.abs(d) : 0;
                return `<div class="fw-bold text-danger">${vencFmt}</div>
                        <span class="badge bg-warning text-dark">EN GRACIA (venció hace ${hace}d)</span>`;
            }
            if (st === 'POR_VENCER') {
                const label = d === 0 ? '¡VENCE HOY!' : `Vence en ${d} día(s)`;
                return `<div class="fw-bold text-warning-emphasis">${vencFmt}</div>
                        <span class="badge bg-warning text-dark">${label}</span>`;
            }
            const sub = d !== null ? `Al día (${d}d restantes)` : 'Al día';
            return `<div class="fw-bold text-success">${vencFmt}</div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">${sub}</span>`;
        }

        function renderPropietarios() {
            const tb = document.getElementById('tablaPropietariosBody');
            tb.innerHTML = listaPropietarios.map(pr => {
                const pctC = pr.max_contribuyentes > 0 ? Math.min(100, Math.round((pr.usados_contribuyentes / pr.max_contribuyentes) * 100)) : 0;
                const pctU = pr.max_usuarios > 0 ? Math.min(100, Math.round((pr.usados_usuarios / pr.max_usuarios) * 100)) : 0;
                const isActivo = pr.estado === 'A';
                const btnToggle = Number(pr.id_propietario) === 1 ? '' : `
                    <button class="btn btn-sm ${isActivo ? 'btn-outline-danger' : 'btn-outline-success'} fw-bold"
                            title="${isActivo ? 'Suspender acceso del propietario' : 'Reactivar acceso del propietario'}"
                            onclick="toggleEstadoPropietario(${pr.id_propietario}, '${isActivo ? 'I' : 'A'}')">
                        <i class="ph ${isActivo ? 'ph-pause-circle' : 'ph-play-circle'}"></i>
                    </button>`;

                return `
                <tr>
                    <td class="ps-4 fw-bold">#${pr.id_propietario}</td>
                    <td>
                        <div class="fw-bold text-dark">${pr.nombre_propietario}</div>
                        <div class="small text-muted">Usuarios: <strong>${pr.usuarios_lista || '-'}</strong></div>
                    </td>
                    <td>
                        <div>${pr.ruc || '-'}</div>
                        <div class="small text-muted">${pr.email || ''} ${pr.telefono || ''}</div>
                    </td>
                    <td>
                        <span class="badge bg-info text-dark fw-bold">${pr.nombre_plan || 'Plan Básico'}</span>
                        <div class="small text-muted mt-1">Gs. ${Number(pr.precio_mensual || 0).toLocaleString('es-PY')}/mes</div>
                    </td>
                    <td class="text-center">
                        <div class="fw-bold ${pr.usados_contribuyentes >= pr.max_contribuyentes ? 'text-danger' : 'text-success'}">
                            ${pr.usados_contribuyentes} / ${pr.max_contribuyentes}
                        </div>
                        <div class="progress" style="height: 5px; width: 80px; margin: 3px auto 0;">
                            <div class="progress-bar bg-success" style="width: ${pctC}%"></div>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="fw-bold ${pr.usados_usuarios >= pr.max_usuarios ? 'text-danger' : 'text-primary'}">
                            ${pr.usados_usuarios} / ${pr.max_usuarios}
                        </div>
                        <div class="progress" style="height: 5px; width: 80px; margin: 3px auto 0;">
                            <div class="progress-bar bg-primary" style="width: ${pctU}%"></div>
                        </div>
                    </td>
                    <td class="text-center">
                        ${formatBadgeSuscripcion(pr)}
                    </td>
                    <td class="text-center">
                        <span class="badge ${isActivo ? 'bg-success' : 'bg-danger'}">${isActivo ? 'ACTIVO' : 'SUSPENDIDO'}</span>
                    </td>
                    <td class="text-center pe-4">
                        <div class="d-flex justify-content-center gap-1 flex-wrap">
                            <button class="btn btn-sm btn-primary fw-bold" title="Registrar Pago y Renovar Vencimiento" onclick="abrirModalRenovar(${pr.id_propietario})">
                                <i class="ph ph-credit-card"></i> Renovar
                            </button>
                            <button class="btn btn-sm btn-outline-success fw-bold" title="Editar Configuración" onclick="editarPropietario(${pr.id_propietario})">
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
            if (!listaPagos.length) {
                tb.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-3">Aún no se han registrado pagos o renovaciones manuales.</td></tr>`;
                return;
            }
            tb.innerHTML = listaPagos.map(pg => `
                <tr>
                    <td class="ps-4 fw-bold">#${pg.id_pago}</td>
                    <td>${pg.fecha_pago || '-'}</td>
                    <td class="fw-bold">${pg.nombre_propietario || ('#' + pg.id_propietario)}</td>
                    <td><span class="badge bg-light text-dark border">${pg.nombre_plan || '-'}</span></td>
                    <td class="text-center fw-bold">${pg.meses_renovados} mes(es)</td>
                    <td class="text-end fw-bold text-success">Gs. ${Number(pg.monto || 0).toLocaleString('es-PY')}</td>
                    <td class="text-center text-muted">${pg.fec_vencimiento_anterior || '-'}</td>
                    <td class="text-center fw-bold text-primary">${pg.fec_vencimiento_nueva || '-'}</td>
                    <td class="small">${pg.comprobante ? `<strong>${pg.comprobante}</strong> ` : ''}${pg.observacion || ''}</td>
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

        async function toggleEstadoPropietario(idProp, nuevoEstado) {
            const accion = nuevoEstado === 'A' ? 'REACTIVAR' : 'SUSPENDER';
            if (!confirm(`¿Confirma que desea ${accion} la cuenta del Propietario #${idProp}?`)) return;

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
