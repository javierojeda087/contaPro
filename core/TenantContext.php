<?php
namespace Core;

class TenantContext {

    public static function check(): bool {
        return isset($_SESSION['usuario']) && trim((string)$_SESSION['usuario']) !== '';
    }

    public static function idPropietario(): int {
        if (isset($_SESSION['id_propietario']) && (int)$_SESSION['id_propietario'] > 0) {
            return (int)$_SESSION['id_propietario'];
        }
        return 1;
    }

    public static function nombrePropietario(): string {
        return trim((string)($_SESSION['nombre_propietario'] ?? 'PROPIETARIO 1'));
    }

    public static function usuario(): string {
        return trim((string)($_SESSION['usuario'] ?? ''));
    }

    public static function grupo(): string {
        $g = trim((string)($_SESSION['grupo'] ?? 'CONTA'));
        return $g !== '' ? $g : 'CONTA';
    }

    public static function empresa(): string {
        $emp = $_SESSION['empresa'] ?? '';
        return trim((string)$emp) !== '' ? trim((string)$emp) : '1';
    }

    public static function nomEmpresa(): string {
        return trim((string)($_SESSION['nomEmpresa'] ?? $_SESSION['empresaDesc'] ?? ''));
    }

    public static function sucursal(): string {
        $suc = $_SESSION['sucursal'] ?? '';
        return trim((string)$suc) !== '' ? trim((string)$suc) : '1';
    }

    public static function ejercicio(): string {
        $ej = $_SESSION['ejercicio'] ?? '';
        return trim((string)$ej) !== '' ? trim((string)$ej) : date('Y');
    }

    public static function esSuperAdmin(): bool {
        return (($_SESSION['es_superadmin'] ?? 'N') === 'S') || (self::idPropietario() === 1 && strtoupper(self::usuario()) === 'CONTA');
    }

    /**
     * Obtiene la información del propietario actual, su plan, uso de contribuyentes/usuarios y estado de suscripción
     */
    public static function getPlanSummary(?int $idProp = null): array {
        $propId = $idProp ?? self::idPropietario();
        $row = Database::queryForMap(
            "SELECT p.id_propietario, p.nombre_propietario, p.ruc, p.email, p.telefono, p.id_plan,
                    p.max_contribuyentes, p.max_usuarios, p.estado, p.fec_vencimiento,
                    p.fec_ultimo_pago, p.dias_aviso, p.dias_gracia, p.mensaje_pago,
                    COALESCE(pl.nombre_plan, 'Plan Básico') AS nombre_plan,
                    COALESCE(pl.codigo_plan, 'BASICO') AS codigo_plan,
                    COALESCE(pl.precio_mensual, 0) AS precio_mensual
             FROM propietarios p
             LEFT JOIN planes pl ON p.id_plan = pl.id_plan
             WHERE p.id_propietario = ?",
            [$propId]
        );

        if (!$row) {
            $row = [
                'id_propietario'     => $propId,
                'nombre_propietario' => 'PROPIETARIO ' . $propId,
                'id_plan'            => 1,
                'nombre_plan'        => 'Plan Básico',
                'codigo_plan'        => 'BASICO',
                'precio_mensual'     => 150000,
                'max_contribuyentes' => 5,
                'max_usuarios'       => 2,
                'estado'             => 'A',
                'fec_vencimiento'    => null,
                'fec_ultimo_pago'    => null,
                'dias_aviso'         => 7,
                'dias_gracia'        => 3,
                'mensaje_pago'       => null,
            ];
        }

        $usedContrib = (int)Database::queryForValue(
            "SELECT COUNT(*) FROM empresas WHERE id_propietario = ?",
            [$propId]
        );

        $usedUsers = (int)Database::queryForValue(
            "SELECT COUNT(*) FROM usuarios WHERE id_propietario = ?",
            [$propId]
        );

        $maxContrib = (int)($row['max_contribuyentes'] ?? 5);
        $maxUsers   = (int)($row['max_usuarios'] ?? 2);

        $row['usados_contribuyentes']      = $usedContrib;
        $row['disponibles_contribuyentes'] = max(0, $maxContrib - $usedContrib);
        $row['usados_usuarios']            = $usedUsers;
        $row['disponibles_usuarios']       = max(0, $maxUsers - $usedUsers);
        $row['puede_crear_contribuyente']  = $usedContrib < $maxContrib;
        $row['puede_crear_usuario']        = $usedUsers < $maxUsers;

        return self::enrichSubscriptionInfo($row);
    }

    /**
     * Calcula días restantes, semáforo de suscripción y textos de aviso de pago para un propietario
     */
    public static function enrichSubscriptionInfo(array $row): array {
        $estadoProp = strtoupper(trim((string)($row['estado'] ?? 'A')));
        $fecVenc    = !empty($row['fec_vencimiento']) ? substr((string)$row['fec_vencimiento'], 0, 10) : null;
        $diasAviso  = isset($row['dias_aviso']) ? max(1, (int)$row['dias_aviso']) : 7;
        $diasGracia = isset($row['dias_gracia']) ? max(0, (int)$row['dias_gracia']) : 3;
        $planNombre = (string)($row['nombre_plan'] ?? 'Plan Básico');
        $precioGs   = number_format((float)($row['precio_mensual'] ?? 0), 0, ',', '.');

        $diasRestantes = null;
        $fecVencFmt    = 'Sin vencimiento';

        if ($fecVenc && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecVenc)) {
            $hoyDt  = new \DateTimeImmutable(date('Y-m-d'));
            $vencDt = new \DateTimeImmutable($fecVenc);
            $diff   = $hoyDt->diff($vencDt);
            $diasRestantes = (int)$diff->format('%r%a');
            $fecVencFmt    = $vencDt->format('d/m/Y');
        }

        $estadoSuscripcion = 'AL_DIA';
        $mostrarAviso      = false;
        $nivelAviso        = 'ok';
        $tituloAviso       = '';
        $textoAviso        = '';
        $bloqueado         = false;

        if ($estadoProp !== 'A') {
            $estadoSuscripcion = 'SUSPENDIDO';
            $mostrarAviso      = true;
            $nivelAviso        = 'danger';
            $bloqueado         = true;
            $tituloAviso       = 'Cuenta Suspendida';
            $textoAviso        = "La cuenta del propietario se encuentra suspendida. Contacte a la administración de ContaPro para regularizar su servicio.";
        } elseif ($diasRestantes === null) {
            $estadoSuscripcion = 'AL_DIA';
        } elseif ($diasRestantes > $diasAviso) {
            $estadoSuscripcion = 'AL_DIA';
        } elseif ($diasRestantes >= 0) {
            $estadoSuscripcion = 'POR_VENCER';
            $mostrarAviso      = true;
            $nivelAviso        = $diasRestantes <= 2 ? 'danger' : 'warning';
            if ($diasRestantes === 0) {
                $tituloAviso = '¡Su suscripción vence HOY (' . $fecVencFmt . ')!';
                $textoAviso  = "Su suscripción al {$planNombre} (Gs. {$precioGs}/mes) vence el día de hoy. Gestione su pago para mantener activo su acceso sin interrupciones.";
            } elseif ($diasRestantes === 1) {
                $tituloAviso = 'Su suscripción vence MAÑANA (' . $fecVencFmt . ')';
                $textoAviso  = "Queda 1 día para el vencimiento de su {$planNombre} (Gs. {$precioGs}/mes).";
            } else {
                $tituloAviso = "Aviso de Vencimiento Próximo: faltan {$diasRestantes} días ({$fecVencFmt})";
                $textoAviso  = "Su suscripción al {$planNombre} (Gs. {$precioGs}/mes) vencerá el {$fecVencFmt}.";
            }
        } elseif ($diasRestantes >= -$diasGracia) {
            $estadoSuscripcion = 'EN_GRACIA';
            $mostrarAviso      = true;
            $nivelAviso        = 'danger';
            $diasVencido       = abs($diasRestantes);
            $diasParaCortar    = max(0, $diasGracia - $diasVencido);
            $tituloAviso       = "¡Suscripción Vencida el {$fecVencFmt} (Período de Gracia)!";
            $textoAviso        = "Su suscripción venció hace {$diasVencido} día(s). Dispone de {$diasParaCortar} día(s) de gracia adicionales antes de la suspensión automática del acceso.";
        } else {
            $estadoSuscripcion = 'VENCIDO';
            $mostrarAviso      = true;
            $nivelAviso        = 'danger';
            $bloqueado         = true;
            $diasVencido       = abs($diasRestantes);
            $tituloAviso       = "Suscripción Vencida ({$fecVencFmt})";
            $textoAviso        = "Su suscripción al {$planNombre} venció el {$fecVencFmt} (hace {$diasVencido} días). Por favor registre su pago para reactivar el servicio.";
        }

        $msgPago = trim((string)($row['mensaje_pago'] ?? ''));
        if ($msgPago === '' || stripos($msgPago, 'SIPAP') !== false || stripos($msgPago, '981 000') !== false || stripos($msgPago, 'Ita') !== false) {
            $msgPago = 'Contactar al WhatsApp +595 982 965 515 para renovar su suscripción.';
        }
        $row['mensaje_pago'] = $msgPago;

        if ($mostrarAviso) {
            $textoAviso .= ' — ' . $msgPago;
        }

        $row['fec_vencimiento_fmt']       = $fecVencFmt;
        $row['dias_restantes']            = $diasRestantes;
        $row['dias_aviso']                = $diasAviso;
        $row['dias_gracia']               = $diasGracia;
        $row['estado_suscripcion']        = $estadoSuscripcion;
        $row['mostrar_aviso_pago']        = $mostrarAviso;
        $row['nivel_aviso_pago']          = $nivelAviso;
        $row['titulo_aviso_pago']         = $tituloAviso;
        $row['texto_aviso_pago']          = $textoAviso;
        $row['bloqueado_por_vencimiento'] = $bloqueado;

        return $row;
    }

    /**
     * Aprovisiona catálogos base (módulos, formas, permisos del grupo CONTA, monedas, cotizaciones, tipos de comprobantes)
     * para un nuevo propietario tomando como plantilla el Propietario 1.
     */
    public static function provisionNewTenant(int $newPropId): void {
        if ($newPropId <= 1) return;

        $catalogTables = [
            'modulos',
            'formas',
            'grupos_usuarios',
            'accesos_grupos',
            'monedas',
            'cotizaciones',
            'tipos_comprobantes',
            'co_plan_cuentas',
            'co_regimen',
            'co_clasificacion_gasto',
            'co_tipos_egreso',
            'co_tipo_ingreso',
            'co_tipos_comprobantes',
        ];

        foreach ($catalogTables as $tb) {
            try {
                $cnt = (int)Database::queryForValue("SELECT COUNT(*) FROM `$tb` WHERE id_propietario = ?", [$newPropId]);
                if ($cnt > 0) continue;

                $rows = Database::queryForList("SELECT * FROM `$tb` WHERE id_propietario = 1");
                foreach ($rows as $r) {
                    $r['id_propietario'] = $newPropId;
                    // Remove auto-increment primary keys if any
                    unset($r['id']);
                    $cols = array_keys($r);
                    $colList = '`' . implode('`, `', $cols) . '`';
                    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                    Database::update("INSERT INTO `$tb` ($colList) VALUES ($placeholders)", array_values($r));
                }
            } catch (\Throwable $ignored) {}
        }
    }
}
