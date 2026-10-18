<?php
namespace Core;

class RouteHelper {
    private static array $ROUTES = [
        'SUPERADMIN'            => 'superadmin',
        'BSPROPIE'              => 'superadmin',
        'COACTIVI'              => 'actividades',
        'BSCAMCLA'              => 'cambiar-clave',
        'CLAVE'                 => 'cambiar-clave',
        'COCONTRI'              => 'contribuyentes',
        'ADCONTRI'              => 'contribuyentes',
        'BSEMPRES'              => 'contribuyentes',
        'EMPRESAS'              => 'contribuyentes',
        'BSBACKUP'              => 'backup',
        'BACKUP'                => 'backup',
        'BSUSUARI'              => 'usuarios',
        'BSGRPUSU'              => 'accesos_grupos',
        'ACCESOS_GRUPOS'        => 'accesos_grupos',
        'ACCESOS-GRUPOS'        => 'accesos_grupos',
        'COCLASGA'              => 'clasificacion_gasto_registro',
        'COCLASGAS'             => 'clasificacion_gasto_registro',
        'CLASIFICACION_GASTO'   => 'clasificacion_gasto_registro',
        'BSCOTIZA'              => 'cotizaciones',
        'COCOTIZA'              => 'cotizaciones',
        'COTIZACIONES'          => 'cotizaciones',
        'COTIPEGR'              => 'tipo_egreso_registro',
        'COTIPEG'               => 'tipo_egreso_registro',
        'TIPO_EGRESO'           => 'tipo_egreso_registro',
        'CO_TIPOS_EGRESO'       => 'tipo_egreso_registro',
        'COCLIENT'              => 'co_clientes',
        'COPERSON'              => 'co_personas',
        'COPROVEE'              => 'co_proveedores',
        'COSUCURS'              => 'co_sucursales',
        'COF12000'              => 'formulario_120',
        'FORMULARIO_120'        => 'formulario_120',
        'FORM120'               => 'formulario_120',
        'COPLANCT'              => 'plan_cuentas',
        'COPLANCU'              => 'plan_cuentas',
        'PLAN_CUENTAS'          => 'plan_cuentas',
        'COASIENT'              => 'asientos',
        'COASIEN2'              => 'asientos',
        'ASIENTOS'              => 'asientos',
        'COEGRES'               => 'egresos',
        'VTEGRES'               => 'egresos',
        'COINGRES'              => 'ingresos',
        'VTINGRES'              => 'ingresos',
        'VTVENTAS'              => 'ingresos',
        'COTIMBRA'              => 'timbrados',
        'BSTIMBRA'              => 'timbrados',
        'BSSERIES'              => 'timbrados',
        'COTIPCOM'              => 'co_tipos_comprobantes',
        'CO_TIPOS_COMPROBANTES' => 'co_tipos_comprobantes',
        'COTIPING'              => 'tipo_ingreso_registro',
        'COTIPINGR'             => 'tipo_ingreso_registro',
        'TIPO_INGRESO'          => 'tipo_ingreso_registro',
        'COLIBVEN'              => 'libro-ventas',
        'CORPVENT'              => 'libro-ventas',
        'LIBRO_VENTAS'          => 'libro-ventas',
        'COREGIMN'              => 'regimen_registro',
        'COREGIME'              => 'regimen_registro',
        'REGIMEN'               => 'regimen_registro',
        'CO_REGIMEN'            => 'regimen_registro',
        'COLIBCOM'              => 'libro-compras',
        'CORPCOMP'              => 'libro-compras',
        'LIBRO_COMPRAS'         => 'libro-compras',
        'COLIBDIA'              => 'libro-diario',
        'CORPDIAR'              => 'libro-diario',
        'LIBRO_DIARIO'          => 'libro-diario',
        'COLIBMAY'              => 'libro-mayor',
        'CORPMAYO'              => 'libro-mayor',
        'LIBRO_MAYOR'           => 'libro-mayor',
        'COMARANG'              => 'exportar-marangatu',
        'BSMARANG'              => 'exportar-marangatu',
        'MARANGATU'             => 'exportar-marangatu',
        'COEJERCI'              => 'coejerci',
    ];

    public static function getRoute(?string $nomForma): string {
        if ($nomForma === null) return '';
        $key = strtoupper(trim($nomForma));
        return self::$ROUTES[$key] ?? $nomForma;
    }

    public static function resolveTemplate(?string $slug): ?string {
        if ($slug === null || trim($slug) === '') return null;
        $viewsDir = __DIR__ . '/../views';
        $candidates = [
            strtolower(trim($slug)),
            str_replace('-', '_', strtolower(trim($slug))),
            self::getRoute($slug),
        ];
        foreach ($candidates as $c) {
            if (is_file($viewsDir . '/' . $c . '.html') || is_file($viewsDir . '/' . $c . '.php')) {
                return $c;
            }
        }
        return null;
    }
}

