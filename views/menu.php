<!DOCTYPE html>
<html lang="es">
<?= \Core\View::renderHeadFragment('Sistema - Inicio') ?>
<body style="background: var(--bg-color);">

    <?= \Core\View::renderSidebarFragment($modulos ?? [], $planSummary ?? null, $esSuperAdmin ?? false) ?>

    <main class="main-content">
        <?= \Core\View::renderNavbarFragment('Inicio', $planSummary ?? null, $esSuperAdmin ?? false) ?>

        <div class="content-area" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: calc(100vh - 80px); padding: 2rem; box-sizing: border-box;">

            <?= !empty($planSummary) ? \Core\View::renderSubscriptionAlertBanner($planSummary) : '' ?>

            <div style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(16px); border-radius: 20px; border: 1px solid rgba(203, 213, 225, 0.8); padding: 2.25rem 2.5rem; max-width: 740px; width: 100%; box-shadow: 0 20px 40px -15px rgba(22, 101, 52, 0.12); margin-top: auto; margin-bottom: 2rem;">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1.25rem;">
                    <div style="width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #86efac; display: flex; align-items: center; justify-content: center; color: #15803d; font-size: 1.6rem; flex-shrink: 0;">
                        <i class="ph ph-buildings"></i>
                    </div>
                    <div style="flex: 1;">
                        <span style="font-size: 0.72rem; font-weight: 800; color: #16a34a; text-transform: uppercase; letter-spacing: 0.08em;">Contribuyente / Empresa Activa</span>
                        <h3 style="margin: 2px 0 0 0; font-weight: 800; font-size: 1.25rem; color: #0f172a;">
                            <?= htmlspecialchars($_SESSION['empresaDesc'] ?? ($_SESSION['empresa'] ?? 'Sin Contribuyente Seleccionado')) ?>
                        </h3>
                    </div>
                    <?php if (!empty($planSummary)):
                        $estSub = $planSummary['estado_suscripcion'] ?? 'AL_DIA';
                        $vencColor = '#16a34a';
                        if ($estSub === 'POR_VENCER') $vencColor = '#d97706';
                        elseif ($estSub === 'EN_GRACIA' || $estSub === 'VENCIDO' || $estSub === 'SUSPENDIDO') $vencColor = '#dc2626';
                    ?>
                    <div style="text-align: right; background: transparent; border: 1px solid #e2e8f0; padding: 10px 16px; border-radius: 12px;">
                        <div style="font-size: 0.68rem; font-weight: 800; color: #15803d; text-transform: uppercase;">Propietario Cuenta</div>
                        <div style="font-size: 0.92rem; font-weight: 800; color: #0f172a;"><?= htmlspecialchars($planSummary['nombre_propietario']) ?></div>
                        <div style="font-size: 0.74rem; color: #64748b; font-weight: 700;">
                            <?= htmlspecialchars($planSummary['nombre_plan']) ?> &bull; Contrib.: <?= (int)$planSummary['usados_contribuyentes'] ?>/<?= (int)$planSummary['max_contribuyentes'] ?> &bull; Usu.: <?= (int)$planSummary['usados_usuarios'] ?>/<?= (int)$planSummary['max_usuarios'] ?>
                        </div>
                        <div style="font-size: 0.74rem; font-weight: 800; color: <?= $vencColor ?>; margin-top: 2px;">
                            <i class="ph ph-calendar-check"></i> Vencimiento: <?= htmlspecialchars($planSummary['fec_vencimiento_fmt'] ?? 'Sin vencimiento') ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem;">
                        <div style="display: flex; align-items: center; gap: 6px; color: #15803d; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; margin-bottom: 4px;">
                            <i class="ph ph-calendar-blank" style="font-size: 1rem;"></i> Ejercicio Contable
                        </div>
                        <div style="font-weight: 800; font-size: 1.2rem; color: #0f172a;"><?= htmlspecialchars($_SESSION['ejercicio'] ?? date('Y')) ?></div>
                        <?php if (!empty($_SESSION['fecInicio'])): ?>
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 4px; font-weight: 600;"><?= htmlspecialchars(($_SESSION['fecInicio'] ?? '') . ' - ' . ($_SESSION['fecFinal'] ?? '')) ?></div>
                        <?php endif; ?>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem;">
                        <div style="display: flex; align-items: center; gap: 6px; color: #15803d; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; margin-bottom: 4px;">
                            <i class="ph ph-storefront" style="font-size: 1rem;"></i> Sucursal
                        </div>
                        <div style="font-weight: 800; font-size: 1rem; color: #0f172a;"><?= htmlspecialchars($_SESSION['sucursalDesc'] ?? ($_SESSION['sucursal'] ?? '01 - MATRIZ')) ?></div>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem;">
                        <div style="display: flex; align-items: center; gap: 6px; color: #15803d; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; margin-bottom: 4px;">
                            <i class="ph ph-briefcase" style="font-size: 1rem;"></i> Actividad
                        </div>
                        <div style="font-weight: 800; font-size: 1rem; color: #0f172a;"><?= htmlspecialchars($_SESSION['actividad'] ?? 'COMERCIAL') ?></div>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem;">
                        <div style="display: flex; align-items: center; gap: 6px; color: #15803d; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; margin-bottom: 4px;">
                            <i class="ph ph-user" style="font-size: 1rem;"></i> Usuario Conectado
                        </div>
                        <div style="font-weight: 800; font-size: 1rem; color: #0f172a;"><?= htmlspecialchars($_SESSION['nombrePersona'] ?? ($_SESSION['usuario'] ?? 'USUARIO')) ?></div>
                    </div>
                </div>
            </div>

            <div style="margin-top: auto; margin-bottom: 1.5rem; text-align: center;">
                <a href="https://www.josystem.click/" target="_blank" rel="noopener noreferrer" style="text-decoration: none; display: inline-flex; align-items: center; background: #0b1325; border: 1.5px solid rgba(0, 153, 255, 0.45); padding: 0.65rem 1.6rem; border-radius: 9999px; gap: 0.75rem; font-size: 0.95rem; color: #ffffff; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);">
                    <span style="color: #f1f5f9; font-weight: 600;">Desarrollado por</span>
                    <div style="display: flex; align-items: center; gap: 0.55rem;">
                        <div style="background: #0099ff; color: white; padding: 0.22rem 0.6rem; border-radius: 8px; font-weight: 800; font-family: monospace; font-size: 0.9rem; box-shadow: 0 0 16px rgba(0, 153, 255, 0.8);">&lt;/&gt;</div>
                        <span style="color: #0099ff; font-weight: 800; font-size: 1.15rem; letter-spacing: -0.01em;">JO System</span>
                    </div>
                    <div style="width: 1.5px; height: 20px; background: #475569; margin: 0 0.2rem;"></div>
                    <span style="color: #0099ff; font-weight: 800; letter-spacing: 0.06em; font-size: 0.82rem;">SOFTWARE</span>
                </a>
            </div>
        </div>
    </main>

    <?= \Core\View::renderScriptsFragment() ?>
</body>
</html>
