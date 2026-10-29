<?php
namespace Core;

/**
 * Migrador automático de esquema MySQL.
 * Permite agregar tablas o columnas nuevas en cPanel simplemente subiendo este archivo actualizado,
 * sin perder datos existentes ni tener que correr comandos manuales.
 */
class DatabaseMigrator {

    private const SCHEMA_VERSION = 26;

    public static function runIfNeeded(): void {
        if (($_SESSION['_migrator_version'] ?? 0) === self::SCHEMA_VERSION) {
            return;
        }
        $lockFile = __DIR__ . '/schema_v' . self::SCHEMA_VERSION . '.lock';
        if (is_file($lockFile)) {
            $_SESSION['_migrator_version'] = self::SCHEMA_VERSION;
            return;
        }
        try {
            @set_time_limit(120);
            Database::getConnection();
            self::migrate();
            @file_put_contents($lockFile, (string)time());
            $_SESSION['_migrator_version'] = self::SCHEMA_VERSION;
        } catch (\Throwable $ignored) {
            // Si el motor MySQL local aún no está encendido, no bloquear la carga de páginas públicas/login
        }
    }

    public static function migrate(): array {
        $applied = [];

        // 1. Asegurar tabla planes
        Database::execute("
            CREATE TABLE IF NOT EXISTS `planes` (
              `id_plan` INT NOT NULL AUTO_INCREMENT,
              `codigo_plan` VARCHAR(30) NOT NULL,
              `nombre_plan` VARCHAR(100) NOT NULL,
              `cod_grupo` VARCHAR(10) DEFAULT 'CONTA',
              `max_contribuyentes` INT NOT NULL DEFAULT 5,
              `max_usuarios` INT NOT NULL DEFAULT 2,
              `precio_anterior` DECIMAL(15,2) DEFAULT 0,
              `precio_mensual` DECIMAL(15,2) DEFAULT 0,
              `descripcion` VARCHAR(255) DEFAULT NULL,
              `estado` VARCHAR(1) DEFAULT 'A',
              `orden` INT DEFAULT 1,
              PRIMARY KEY (`id_plan`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);

        if (!self::columnExists('planes', 'precio_anterior')) {
            Database::execute("ALTER TABLE `planes` ADD COLUMN `precio_anterior` DECIMAL(15,2) DEFAULT 0 AFTER `max_usuarios`", true);
            $applied[] = 'planes.precio_anterior';
        }

        if (!self::columnExists('planes', 'cod_grupo')) {
            Database::execute("ALTER TABLE `planes` ADD COLUMN `cod_grupo` VARCHAR(10) DEFAULT 'CONTA' AFTER `nombre_plan`", true);
            Database::execute("UPDATE `planes` SET `cod_grupo` = 'CONTA'  WHERE UPPER(TRIM(`codigo_plan`)) = 'BASICO'", true);
            Database::execute("UPDATE `planes` SET `cod_grupo` = 'CONTAM' WHERE UPPER(TRIM(`codigo_plan`)) = 'MEDIO'", true);
            Database::execute("UPDATE `planes` SET `cod_grupo` = 'CONTAP' WHERE UPPER(TRIM(`codigo_plan`)) = 'PRO'", true);
            Database::execute("UPDATE `planes` SET `cod_grupo` = 'CONADM' WHERE UPPER(TRIM(`codigo_plan`)) = 'ILIMITADO'", true);
            $applied[] = 'planes.cod_grupo';
        }

        // Asegurar mapeo por defecto de grupos por plan si están vacíos
        Database::execute("UPDATE `planes` SET `cod_grupo` = 'CONTA'  WHERE UPPER(TRIM(`codigo_plan`)) = 'BASICO' AND (COALESCE(TRIM(`cod_grupo`), '') = '')", true);
        Database::execute("UPDATE `planes` SET `cod_grupo` = 'CONTAM' WHERE UPPER(TRIM(`codigo_plan`)) = 'MEDIO'  AND (COALESCE(TRIM(`cod_grupo`), '') IN ('', 'CONTA'))", true);
        Database::execute("UPDATE `planes` SET `cod_grupo` = 'CONTAP' WHERE UPPER(TRIM(`codigo_plan`)) = 'PRO'    AND (COALESCE(TRIM(`cod_grupo`), '') IN ('', 'CONTA'))", true);
        Database::execute("UPDATE `planes` SET `cod_grupo` = 'CONADM' WHERE UPPER(TRIM(`codigo_plan`)) = 'ILIMITADO' AND (COALESCE(TRIM(`cod_grupo`), '') IN ('', 'CONTA'))", true);

        // Aplicar precio anterior 150.000 y precio actual 140.000 al Plan Básico si aún tiene el valor inicial por defecto
        Database::execute(
            "UPDATE `planes`
             SET `precio_anterior` = 150000, `precio_mensual` = 140000
             WHERE UPPER(TRIM(`codigo_plan`)) = 'BASICO' AND COALESCE(`precio_anterior`, 0) = 0 AND `precio_mensual` = 150000",
            true
        );

        // 2. Asegurar tabla propietarios
        Database::execute("
            CREATE TABLE IF NOT EXISTS `propietarios` (
              `id_propietario` INT NOT NULL AUTO_INCREMENT,
              `nombre_propietario` VARCHAR(150) NOT NULL,
              `ruc` VARCHAR(30) DEFAULT NULL,
              `email` VARCHAR(150) DEFAULT NULL,
              `telefono` VARCHAR(50) DEFAULT NULL,
              `id_plan` INT NOT NULL DEFAULT 1,
              `max_contribuyentes` INT NOT NULL DEFAULT 5,
              `max_usuarios` INT NOT NULL DEFAULT 2,
              `estado` VARCHAR(1) NOT NULL DEFAULT 'A',
              `fec_vencimiento` DATE DEFAULT NULL,
              `fec_ultimo_pago` DATE DEFAULT NULL,
              `dias_aviso` INT NOT NULL DEFAULT 7,
              `dias_gracia` INT NOT NULL DEFAULT 3,
              `mensaje_pago` VARCHAR(255) DEFAULT NULL,
              `fec_alta` DATETIME DEFAULT CURRENT_TIMESTAMP,
              `observacion` VARCHAR(255) DEFAULT NULL,
              PRIMARY KEY (`id_propietario`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);

        $extraColsProp = [
            'email'           => 'VARCHAR(150) DEFAULT NULL',
            'fec_ultimo_pago' => 'DATE DEFAULT NULL',
            'dias_aviso'      => 'INT NOT NULL DEFAULT 7',
            'dias_gracia'     => 'INT NOT NULL DEFAULT 3',
            'mensaje_pago'    => 'VARCHAR(255) DEFAULT NULL',
        ];
        foreach ($extraColsProp as $col => $def) {
            if (!self::columnExists('propietarios', $col)) {
                Database::execute("ALTER TABLE `propietarios` ADD COLUMN `$col` $def", true);
                $applied[] = "propietarios.$col";
            }
        }
        Database::execute("ALTER TABLE `propietarios` MODIFY COLUMN `email` VARCHAR(150) DEFAULT NULL", true);

        // Limpiar datos de ejemplo antiguos (SIPAP / +595 981 000 000) y actualizar al número oficial +595 982 965 515
        Database::execute(
            "UPDATE `propietarios`
             SET `mensaje_pago` = 'Contactar al WhatsApp +595 982 965 515 para renovar su suscripción.'
             WHERE `mensaje_pago` LIKE '%SIPAP%' OR `mensaje_pago` LIKE '%981 000%'",
            true
        );

        // 2b. Asegurar tabla pagos_propietarios (Historial de Pagos y Renovaciones de Suscripción)
        Database::execute("
            CREATE TABLE IF NOT EXISTS `pagos_propietarios` (
              `id_pago` INT NOT NULL AUTO_INCREMENT,
              `id_propietario` INT NOT NULL,
              `id_plan` INT DEFAULT NULL,
              `fecha_pago` DATE NOT NULL,
              `monto` DECIMAL(15,2) NOT NULL DEFAULT 0,
              `meses_renovados` INT NOT NULL DEFAULT 1,
              `fec_vencimiento_anterior` DATE DEFAULT NULL,
              `fec_vencimiento_nueva` DATE NOT NULL,
              `comprobante` VARCHAR(80) DEFAULT NULL,
              `observacion` VARCHAR(255) DEFAULT NULL,
              `usuario_registro` VARCHAR(50) DEFAULT NULL,
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id_pago`),
              KEY `idx_pagos_prop` (`id_propietario`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);

        // 3. Asegurar columna es_superadmin y longitud de clave (VARCHAR 255 para hash bcrypt) en usuarios
        if (!self::columnExists('usuarios', 'es_superadmin')) {
            Database::execute("ALTER TABLE `usuarios` ADD COLUMN `es_superadmin` VARCHAR(1) DEFAULT 'N'", true);
            Database::execute("UPDATE `usuarios` SET `es_superadmin` = 'S' WHERE `id_propietario` = 1 AND UPPER(`cod_usuario`) = 'CONTA'", true);
            $applied[] = 'usuarios.es_superadmin';
        }
        if (!self::columnExists('usuarios', 'e_mail')) {
            Database::execute("ALTER TABLE `usuarios` ADD COLUMN `e_mail` VARCHAR(150) DEFAULT NULL", true);
            $applied[] = 'usuarios.e_mail';
        } else {
            Database::execute("ALTER TABLE `usuarios` MODIFY COLUMN `e_mail` VARCHAR(150) DEFAULT NULL", true);
        }
        Database::execute("ALTER TABLE `usuarios` MODIFY COLUMN `clave` VARCHAR(255) DEFAULT NULL", true);
        // Reparar claves cuyos hashes bcrypt hayan quedado truncados por el antiguo VARCHAR(15)
        Database::execute(
            "UPDATE `usuarios`
             SET `clave` = CASE
                 WHEN UPPER(TRIM(`cod_usuario`)) = 'CONTA' THEN 'CONTA'
                 ELSE CONCAT(UPPER(TRIM(`cod_usuario`)), '123')
             END
             WHERE `clave` IS NULL
                OR TRIM(`clave`) = ''
                OR (`clave` LIKE '$2%' AND CHAR_LENGTH(`clave`) < 55)",
            true
        );
        Database::execute("ALTER TABLE `usuarios` MODIFY COLUMN `cod_grupo` VARCHAR(10) DEFAULT NULL", true);
        Database::execute("ALTER TABLE `grupos_usuarios` MODIFY COLUMN `cod_grupo` VARCHAR(10) DEFAULT NULL", true);
        Database::execute("ALTER TABLE `accesos_grupos` MODIFY COLUMN `cod_grupo` VARCHAR(10) DEFAULT NULL", true);

        // Sincronizar correo entre propietario y su usuario administrador cuando alguno esté vacío
        Database::execute(
            "UPDATE `propietarios` p
             SET p.`email` = (
                 SELECT LOWER(TRIM(u.`e_mail`))
                 FROM `usuarios` u
                 WHERE u.`id_propietario` = p.`id_propietario`
                   AND u.`e_mail` IS NOT NULL AND TRIM(u.`e_mail`) <> ''
                 ORDER BY CASE WHEN u.`es_admin` = 'S' THEN 0 ELSE 1 END, u.`cod_usuario` ASC
                 LIMIT 1
             )
             WHERE (p.`email` IS NULL OR TRIM(p.`email`) = '')
               AND EXISTS (
                   SELECT 1 FROM `usuarios` u2
                   WHERE u2.`id_propietario` = p.`id_propietario`
                     AND u2.`e_mail` IS NOT NULL AND TRIM(u2.`e_mail`) <> ''
               )",
            true
        );
        Database::execute(
            "UPDATE `usuarios` u
             JOIN `propietarios` p ON p.`id_propietario` = u.`id_propietario`
             SET u.`e_mail` = LOWER(TRIM(p.`email`))
             WHERE p.`email` IS NOT NULL AND TRIM(p.`email`) <> ''
               AND (u.`e_mail` IS NULL OR TRIM(u.`e_mail`) = '')
               AND (u.`es_admin` = 'S' OR NOT EXISTS (
                   SELECT 1 FROM (SELECT `id_propietario` FROM `usuarios` WHERE `es_admin` = 'S') ua
                   WHERE ua.`id_propietario` = u.`id_propietario`
               ))",
            true
        );

        // 4. Asegurar columnas extendidas en co_egresos y co_ingresos si faltan
        $extraColsEgresos = [
            'cta_grav10'      => 'VARCHAR(40) DEFAULT NULL',
            'cta_iva10'       => 'VARCHAR(40) DEFAULT NULL',
            'cta_grav5'       => 'VARCHAR(40) DEFAULT NULL',
            'cta_iva5'        => 'VARCHAR(40) DEFAULT NULL',
            'cta_exentas'     => 'VARCHAR(40) DEFAULT NULL',
            'cta_credito'     => 'VARCHAR(40) DEFAULT NULL',
            'timbrado_asoc'   => 'VARCHAR(50) DEFAULT NULL',
            'comprobante_asoc'=> 'VARCHAR(50) DEFAULT NULL',
        ];
        foreach ($extraColsEgresos as $col => $def) {
            if (!self::columnExists('co_egresos', $col)) {
                Database::execute("ALTER TABLE `co_egresos` ADD COLUMN `$col` $def", true);
                $applied[] = "co_egresos.$col";
            }
        }

        $extraColsIngresos = [
            'cta_debito'      => 'VARCHAR(40) DEFAULT NULL',
            'cta_grav10'      => 'VARCHAR(40) DEFAULT NULL',
            'cta_iva10'       => 'VARCHAR(40) DEFAULT NULL',
            'cta_grav5'       => 'VARCHAR(40) DEFAULT NULL',
            'cta_iva5'        => 'VARCHAR(40) DEFAULT NULL',
            'cta_exentas'     => 'VARCHAR(40) DEFAULT NULL',
            'timbrado_asoc'   => 'VARCHAR(50) DEFAULT NULL',
            'comprobante_asoc'=> 'VARCHAR(50) DEFAULT NULL',
            'sifen_estado'    => 'VARCHAR(40) DEFAULT NULL',
            'sifen_protocolo' => 'VARCHAR(60) DEFAULT NULL',
            'sifen_qr_url'    => 'TEXT DEFAULT NULL',
            'sifen_xml'       => 'LONGTEXT DEFAULT NULL',
            'sifen_mensaje'   => 'VARCHAR(255) DEFAULT NULL',
            'sifen_fecha_firma' => 'DATETIME DEFAULT NULL',
        ];
        foreach ($extraColsIngresos as $col => $def) {
            if (!self::columnExists('co_ingresos', $col)) {
                Database::execute("ALTER TABLE `co_ingresos` ADD COLUMN `$col` $def", true);
                $applied[] = "co_ingresos.$col";
            }
        }

        // 4b. Asegurar columnas de configuración SIFEN en empresas (Contribuyentes)
        $extraColsEmpresas = [
            'sifen_ambiente'           => "VARCHAR(10) DEFAULT 'TEST'",
            'sifen_id_csc'             => "VARCHAR(10) DEFAULT '0001'",
            'sifen_csc'                => 'VARCHAR(64) DEFAULT NULL',
            'sifen_certificado_b64'    => 'LONGTEXT DEFAULT NULL',
            'sifen_certificado_nombre' => 'VARCHAR(120) DEFAULT NULL',
            'sifen_certificado_pass'   => 'VARCHAR(120) DEFAULT NULL',
            'sifen_api_url'            => 'VARCHAR(255) DEFAULT NULL',
        ];
        foreach ($extraColsEmpresas as $col => $def) {
            if (!self::columnExists('empresas', $col)) {
                Database::execute("ALTER TABLE `empresas` ADD COLUMN `$col` $def", true);
                $applied[] = "empresas.$col";
            }
        }

        // 4c. Asegurar columnas auxiliares en personas e ident_personas
        $extraColsPersonas = [
            'ruc'            => 'VARCHAR(30) DEFAULT NULL',
            'ci'             => 'VARCHAR(30) DEFAULT NULL',
            'direccion'      => 'VARCHAR(300) DEFAULT NULL',
            'telefono'       => 'VARCHAR(50) DEFAULT NULL',
            'email'          => 'VARCHAR(150) DEFAULT NULL',
            'estado'         => "VARCHAR(1) DEFAULT 'A'",
            'tipo_documento' => "VARCHAR(30) DEFAULT 'RUC'",
        ];
        foreach ($extraColsPersonas as $col => $def) {
            if (!self::columnExists('personas', $col)) {
                Database::execute("ALTER TABLE `personas` ADD COLUMN `$col` $def", true);
                $applied[] = "personas.$col";
            }
        }
        if (!self::columnExists('ident_personas', 'por_defecto')) {
            Database::execute("ALTER TABLE `ident_personas` ADD COLUMN `por_defecto` VARCHAR(1) DEFAULT 'S'", true);
            $applied[] = 'ident_personas.por_defecto';
        }
        Database::execute("ALTER TABLE `ident_personas` MODIFY COLUMN `cod_ident` VARCHAR(20) DEFAULT NULL", true);

        // 5. Asegurar los grupos por plan: CONADM, CONTA (Plan Básico), CONTAM (Plan Medio), CONTAP (Plan Pro)
        $gruposBase = [
            'CONADM' => 'ADMINISTRADOR CONTABLE',
            'CONTA'  => 'GRUPO CONTABILIDAD (PLAN BASICO)',
            'CONTAM' => 'GRUPO CONTABILIDAD (PLAN MEDIO)',
            'CONTAP' => 'GRUPO CONTABILIDAD (PLAN PRO)',
        ];
        foreach ($gruposBase as $gCod => $gDesc) {
            Database::execute(
                "INSERT INTO `grupos_usuarios` (`id_propietario`, `cod_grupo`, `descripcion`, `ind_clave_sin_caducidad`)
                 SELECT DISTINCT p.id_prop, '$gCod', '$gDesc', 'S'
                 FROM (
                     SELECT `id_propietario` AS id_prop FROM `propietarios`
                     UNION
                     SELECT `id_propietario` AS id_prop FROM `grupos_usuarios`
                     UNION
                     SELECT 1 AS id_prop
                 ) p
                 WHERE NOT EXISTS (
                     SELECT 1 FROM `grupos_usuarios` g
                     WHERE g.`id_propietario` = p.id_prop AND UPPER(TRIM(g.`cod_grupo`)) = '$gCod'
                 )",
                true
            );
        }

        // Limpiar únicamente grupos semilla antiguos obsoletos (DEV, MANT, SOP, TEST), preservando los grupos creados
        Database::execute(
            "DELETE FROM `grupos_usuarios` WHERE UPPER(TRIM(COALESCE(`cod_grupo`, ''))) IN ('DEV', 'MANT', 'SOP', 'TEST', '')",
            true
        );

        Database::execute(
            "DELETE FROM `accesos_grupos` WHERE UPPER(TRIM(COALESCE(`cod_grupo`, ''))) IN ('DEV', 'MANT', 'SOP', 'TEST', '')",
            true
        );

        // Copiar accesos base desde CONTA hacia CONADM, CONTAM y CONTAP si aún no tienen filas en accesos_grupos
        foreach (['CONADM', 'CONTAM', 'CONTAP'] as $targetGrp) {
            Database::execute(
                "INSERT INTO `accesos_grupos` (
                     `id_propietario`, `cod_grupo`, `cod_modulo`, `nom_forma`,
                     `puede_insertar`, `puede_borrar`, `puede_actualizar`, `puede_consultar`,
                     `item_menu`, `mostrar_en_menu`
                 )
                 SELECT a.`id_propietario`, '$targetGrp', a.`cod_modulo`, a.`nom_forma`,
                        a.`puede_insertar`, a.`puede_borrar`, a.`puede_actualizar`, a.`puede_consultar`,
                        a.`item_menu`, a.`mostrar_en_menu`
                 FROM `accesos_grupos` a
                 WHERE UPPER(TRIM(a.`cod_grupo`)) = 'CONTA'
                   AND NOT EXISTS (
                       SELECT 1 FROM `accesos_grupos` x
                       WHERE x.`id_propietario` = a.`id_propietario`
                         AND UPPER(TRIM(x.`cod_grupo`)) = '$targetGrp'
                   )",
                true
            );
        }

        // Registrar formulario BSGRPUSU (Accesos Grupos) en formas y dar acceso EXCLUSIVAMENTE a CONADM
        Database::execute(
            "INSERT INTO `formas` (`id_propietario`, `cod_modulo`, `nom_forma`, `titulo`, `mostrar_en_menu`, `tipo`, `orden_en_menu`)
             SELECT DISTINCT p.id_prop, 'AD', 'BSGRPUSU', 'Accesos Grupos', 'S', 'D', 2
             FROM (
                 SELECT `id_propietario` AS id_prop FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop FROM `formas`
                 UNION
                 SELECT 1 AS id_prop
             ) p
             WHERE NOT EXISTS (
                 SELECT 1 FROM `formas` f
                 WHERE f.`id_propietario` = p.id_prop AND UPPER(TRIM(f.`nom_forma`)) = 'BSGRPUSU'
             )",
            true
        );

        Database::execute(
            "INSERT INTO `accesos_grupos` (
                 `id_propietario`, `cod_grupo`, `cod_modulo`, `nom_forma`,
                 `puede_insertar`, `puede_borrar`, `puede_actualizar`, `puede_consultar`,
                 `item_menu`, `mostrar_en_menu`
             )
             SELECT DISTINCT p.id_prop, 'CONADM', 'AD', 'BSGRPUSU', 'S', 'S', 'S', 'S', 'Accesos Grupos', 'S'
             FROM (
                 SELECT `id_propietario` AS id_prop FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop FROM `grupos_usuarios`
                 UNION
                 SELECT 1 AS id_prop
             ) p
             WHERE NOT EXISTS (
                 SELECT 1 FROM `accesos_grupos` x
                 WHERE x.`id_propietario` = p.id_prop
                   AND UPPER(TRIM(x.`cod_grupo`)) = 'CONADM'
                   AND UPPER(TRIM(x.`nom_forma`)) = 'BSGRPUSU'
             )",
            true
        );

        // Asegurar que ningún otro grupo distinto de CONADM tenga BSGRPUSU en accesos_grupos
        Database::execute(
            "DELETE FROM `accesos_grupos` WHERE UPPER(TRIM(`nom_forma`)) = 'BSGRPUSU' AND UPPER(TRIM(`cod_grupo`)) <> 'CONADM'",
            true
        );

        // El usuario CONTA debe pertenecer siempre a CONADM
        Database::execute(
            "UPDATE `usuarios`
             SET `cod_grupo` = 'CONADM'
             WHERE UPPER(TRIM(`cod_usuario`)) = 'CONTA' AND (UPPER(TRIM(COALESCE(`cod_grupo`, ''))) <> 'CONADM')",
            true
        );

        // Asegurar que los usuarios de propietarios que no estén en un grupo válido tomen el grupo del plan de su propietario
        Database::execute(
            "UPDATE `usuarios` u
             LEFT JOIN `propietarios` pr ON pr.`id_propietario` = u.`id_propietario`
             LEFT JOIN `planes` pl ON pl.`id_plan` = pr.`id_plan`
             SET u.`cod_grupo` = COALESCE(NULLIF(TRIM(pl.`cod_grupo`), ''), 'CONTA')
             WHERE UPPER(TRIM(u.`cod_usuario`)) <> 'CONTA'
               AND UPPER(TRIM(COALESCE(u.`cod_grupo`, ''))) IN ('', 'DEV', 'MANT', 'SOP', 'TEST')",
            true
        );

        if (strtoupper(trim((string)($_SESSION['usuario'] ?? ''))) === 'CONTA') {
            $_SESSION['grupo'] = 'CONADM';
        }

        // Registrar reporte CORESCOM (Resumen Comparativo Ingresos vs. Egresos) en Contabilidad > Reportes (tipo = 'R')
        Database::execute(
            "INSERT INTO `formas` (`id_propietario`, `cod_modulo`, `nom_forma`, `titulo`, `mostrar_en_menu`, `tipo`, `orden_en_menu`)
             SELECT DISTINCT p.id_prop, 'CO', 'CORESCOM', 'Resumen Comparativo', 'S', 'R', 7
             FROM (
                 SELECT `id_propietario` AS id_prop FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop FROM `formas`
                 UNION
                 SELECT 1 AS id_prop
             ) p
             WHERE NOT EXISTS (
                 SELECT 1 FROM `formas` f
                 WHERE f.`id_propietario` = p.id_prop AND UPPER(TRIM(f.`nom_forma`)) = 'CORESCOM'
             )",
            true
        );

        // Otorgar acceso a CORESCOM al grupo correspondiente al usuario CONTA (CONADM y cualquier grupo asignado a CONTA/CONTA base)
        Database::execute(
            "INSERT INTO `accesos_grupos` (
                 `id_propietario`, `cod_grupo`, `cod_modulo`, `nom_forma`,
                 `puede_insertar`, `puede_borrar`, `puede_actualizar`, `puede_consultar`,
                 `item_menu`, `mostrar_en_menu`
             )
             SELECT DISTINCT g.id_prop, g.cod_grp, 'CO', 'CORESCOM', 'S', 'S', 'S', 'S', 'Resumen Comparativo', 'S'
             FROM (
                 SELECT `id_propietario` AS id_prop, UPPER(TRIM(`cod_grupo`)) AS cod_grp
                 FROM `usuarios`
                 WHERE UPPER(TRIM(`cod_usuario`)) = 'CONTA' AND TRIM(COALESCE(`cod_grupo`, '')) <> ''
                 UNION
                 SELECT `id_propietario` AS id_prop, 'CONADM' AS cod_grp FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop, 'CONTA' AS cod_grp FROM `propietarios`
                 UNION
                 SELECT 1 AS id_prop, 'CONADM' AS cod_grp
                 UNION
                 SELECT 1 AS id_prop, 'CONTA' AS cod_grp
             ) g
             WHERE NOT EXISTS (
                 SELECT 1 FROM `accesos_grupos` x
                 WHERE x.`id_propietario` = g.id_prop
                   AND UPPER(TRIM(x.`cod_grupo`)) = g.cod_grp
                   AND UPPER(TRIM(x.`nom_forma`)) = 'CORESCOM'
             )",
            true
        );

        // 6. Asegurar casillas nuevas de Formulario 120 Versión 4 (DNIT) en co_formulario_120
        $colsForm120V4 = [
            'c150','c151','c152','c153','c154','c155','c156','c157','c158','c159',
            'c160','c161','c162','c163','c164','c165','c166','c167','c168','c169','c170'
        ];
        foreach ($colsForm120V4 as $colF) {
            if (!self::columnExists('co_formulario_120', $colF)) {
                Database::execute("ALTER TABLE `co_formulario_120` ADD COLUMN `$colF` DECIMAL(18,0) DEFAULT 0", true);
                $applied[] = "co_formulario_120.$colF";
            }
        }

        // 7. Registrar BSFORMAS (Formas del Sistema) en Administración > Definiciones para CONADM
        Database::execute(
            "INSERT INTO `formas` (`id_propietario`, `cod_modulo`, `nom_forma`, `titulo`, `descripcion`, `esta`, `mostrar_en_menu`, `cod_modulo_menu`, `tipo`, `orden_en_menu`)
             SELECT DISTINCT p.id_prop, 'AD', 'BSFORMAS', 'Formas del Sistema', 'FORMULARIOS DEL SISTEMA', 'A', 'S', 'AD', 'D', 6
             FROM (
                 SELECT `id_propietario` AS id_prop FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop FROM `formas`
                 UNION
                 SELECT 1 AS id_prop
             ) p
             WHERE NOT EXISTS (
                 SELECT 1 FROM `formas` f
                 WHERE f.`id_propietario` = p.id_prop AND UPPER(TRIM(f.`nom_forma`)) = 'BSFORMAS'
             )",
            true
        );
        Database::execute(
            "INSERT INTO `accesos_grupos` (
                 `id_propietario`, `cod_grupo`, `cod_modulo`, `nom_forma`,
                 `puede_insertar`, `puede_borrar`, `puede_actualizar`, `puede_consultar`,
                 `item_menu`, `mostrar_en_menu`
             )
             SELECT DISTINCT p.id_prop, 'CONADM', 'AD', 'BSFORMAS', 'S', 'S', 'S', 'S', 'Formas del Sistema', 'S'
             FROM (
                 SELECT `id_propietario` AS id_prop FROM `propietarios`
                 UNION
                 SELECT 1 AS id_prop
             ) p
             WHERE NOT EXISTS (
                 SELECT 1 FROM `accesos_grupos` x
                 WHERE x.`id_propietario` = p.id_prop
                   AND UPPER(TRIM(x.`cod_grupo`)) = 'CONADM'
                   AND UPPER(TRIM(x.`nom_forma`)) = 'BSFORMAS'
             )",
            true
        );

        // 8. Registrar CORESIRP (Resumen IRP) en Contabilidad > Reportes
        Database::execute(
            "INSERT INTO `formas` (`id_propietario`, `cod_modulo`, `nom_forma`, `titulo`, `descripcion`, `esta`, `mostrar_en_menu`, `cod_modulo_menu`, `tipo`, `orden_en_menu`)
             SELECT DISTINCT p.id_prop, 'CO', 'CORESIRP', 'Resumen IRP', 'RESUMEN DE IMPUESTO A LA RENTA PERSONAL', 'A', 'S', 'CO', 'R', 8
             FROM (
                 SELECT `id_propietario` AS id_prop FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop FROM `formas`
                 UNION
                 SELECT 1 AS id_prop
             ) p
             WHERE NOT EXISTS (
                 SELECT 1 FROM `formas` f
                 WHERE f.`id_propietario` = p.id_prop AND UPPER(TRIM(f.`nom_forma`)) = 'CORESIRP'
             )",
            true
        );
        Database::execute(
            "INSERT INTO `accesos_grupos` (
                 `id_propietario`, `cod_grupo`, `cod_modulo`, `nom_forma`,
                 `puede_insertar`, `puede_borrar`, `puede_actualizar`, `puede_consultar`,
                 `item_menu`, `mostrar_en_menu`
             )
             SELECT DISTINCT g.id_prop, g.cod_grp, 'CO', 'CORESIRP', 'S', 'S', 'S', 'S', 'Resumen IRP', 'S'
             FROM (
                 SELECT `id_propietario` AS id_prop, UPPER(TRIM(`cod_grupo`)) AS cod_grp
                 FROM `usuarios`
                 WHERE UPPER(TRIM(`cod_usuario`)) = 'CONTA' AND TRIM(COALESCE(`cod_grupo`, '')) <> ''
                 UNION
                 SELECT `id_propietario` AS id_prop, 'CONADM' AS cod_grp FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop, 'CONTA' AS cod_grp FROM `propietarios`
                 UNION
                 SELECT 1 AS id_prop, 'CONADM' AS cod_grp
                 UNION
                 SELECT 1 AS id_prop, 'CONTA' AS cod_grp
             ) g
             WHERE NOT EXISTS (
                 SELECT 1 FROM `accesos_grupos` x
                 WHERE x.`id_propietario` = g.id_prop
                   AND UPPER(TRIM(x.`cod_grupo`)) = g.cod_grp
                   AND UPPER(TRIM(x.`nom_forma`)) = 'CORESIRP'
             )",
            true
        );

        // 9. Registrar LIQ -> LIQUIDACION DE SALARIO en co_tipos_comprobantes (Ingresos) para todos los propietarios
        // Ampliar columna descripcion (originalmente varchar(20), y 'LIQUIDACION DE SALARIO' tiene 22 caracteres)
        Database::execute("ALTER TABLE `co_tipos_comprobantes` MODIFY COLUMN `descripcion` VARCHAR(100) DEFAULT NULL", true);
        Database::execute("ALTER TABLE `co_tipos_comprobantes` MODIFY COLUMN `tip_comprobante` VARCHAR(10) DEFAULT NULL", true);
        Database::execute("ALTER TABLE `co_tipos_comprobantes` MODIFY COLUMN `usuario_reg` VARCHAR(50) DEFAULT NULL", true);

        Database::execute(
            "UPDATE `co_tipos_comprobantes`
             SET `tip_comprobante` = 'LIQ', `descripcion` = 'LIQUIDACION DE SALARIO', `tipo` = 'INGRESOS'
             WHERE UPPER(TRIM(`tip_comprobante`)) IN ('LIQUIDACION DE SALARIO', 'LIQUIDACIÓN DE SALARIO')
                OR UPPER(TRIM(`descripcion`)) LIKE 'LIQUIDAC%SALARIO%'",
            true
        );
        Database::execute(
            "UPDATE `co_ingresos`
             SET `tip_doc` = 'LIQ'
             WHERE UPPER(TRIM(`tip_doc`)) IN ('LIQUIDACION DE SALARIO', 'LIQUIDACIÓN DE SALARIO')",
            true
        );

        Database::execute(
            "INSERT INTO `co_tipos_comprobantes` (`id_propietario`, `tip_comprobante`, `descripcion`, `cod_modulo`, `tipo`, `estado`, `usuario_reg`, `fec_reg`)
             SELECT DISTINCT p.id_prop, 'LIQ', 'LIQUIDACION DE SALARIO', 'ST', 'INGRESOS', 'A', 'CONTA', NOW()
             FROM (
                 SELECT `id_propietario` AS id_prop FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop FROM `co_tipos_comprobantes`
                 UNION
                 SELECT 1 AS id_prop
             ) p
             WHERE NOT EXISTS (
                 SELECT 1 FROM `co_tipos_comprobantes` c
                 WHERE c.`id_propietario` = p.id_prop
                   AND (
                       UPPER(TRIM(c.`tip_comprobante`)) = 'LIQ'
                       OR UPPER(TRIM(c.`descripcion`)) LIKE '%LIQUIDAC%SALARIO%'
                   )
             )",
            true
        );

        // Asegurar REC -> RECIBO (AMBOS) en co_tipos_comprobantes para todos los propietarios
        Database::execute(
            "INSERT INTO `co_tipos_comprobantes` (`id_propietario`, `tip_comprobante`, `descripcion`, `cod_modulo`, `tipo`, `estado`, `usuario_reg`, `fec_reg`)
             SELECT DISTINCT p.id_prop, 'REC', 'RECIBO', 'AMBOS', 'AMBOS', 'A', 'CONTA', NOW()
             FROM (
                 SELECT `id_propietario` AS id_prop FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop FROM `co_tipos_comprobantes`
                 UNION
                 SELECT 1 AS id_prop
             ) p
             WHERE NOT EXISTS (
                 SELECT 1 FROM `co_tipos_comprobantes` c
                 WHERE c.`id_propietario` = p.id_prop
                   AND UPPER(TRIM(c.`tip_comprobante`)) = 'REC'
             )",
            true
        );
        Database::execute(
            "UPDATE `co_tipos_comprobantes`
             SET `cod_modulo` = 'AMBOS', `tipo` = 'AMBOS'
             WHERE UPPER(TRIM(`tip_comprobante`)) = 'REC'",
            true
        );

        // Asegurar Tipo de Ingreso de Salarios en relación de dependencia para todos los propietarios
        try {
            $props = Database::queryForList(
                "SELECT `id_propietario` AS id_prop FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop FROM `co_tipo_ingreso`
                 UNION
                 SELECT 1 AS id_prop"
            );
            foreach ($props as $pr) {
                $pid = (int)($pr['id_prop'] ?? 0);
                if ($pid <= 0) continue;
                $existsSal = (int)Database::queryForValue(
                    "SELECT COUNT(*) FROM `co_tipo_ingreso`
                     WHERE `id_propietario` = ? AND UPPER(TRIM(`descripcion`)) LIKE '%SALARIO%'",
                    [$pid]
                );
                if ($existsSal === 0) {
                    $nextCod = (int)Database::queryForValue(
                        "SELECT COALESCE(MAX(CAST(`cod_tipo_ingreso` AS UNSIGNED)), 0) + 1 FROM `co_tipo_ingreso` WHERE `id_propietario` = ?",
                        [$pid]
                    );
                    if ($nextCod <= 0) $nextCod = 1;
                    Database::update(
                        "INSERT INTO `co_tipo_ingreso` (`id_propietario`, `cod_tipo_ingreso`, `descripcion`, `estado`, `usuario_reg`, `fec_reg`)
                         VALUES (?, ?, 'SALARIOS Y CUALQUIER OTRA REMUNERACION OBTENIDA EN RELACION DE DEPENDENCIA', 'A', 'SYSTEM', NOW())",
                        [$pid, $nextCod]
                    );
                }
            }
        } catch (\Throwable $ignored) {}

        // 10. Tablas de Recibos de Cobro (co_recibos_cobro y co_recibos_cobro_det) alineadas con co_ingresos y registro en Menú (CORECIBO)
        Database::execute("
            CREATE TABLE IF NOT EXISTS `co_recibos_cobro` (
              `id_propietario` int NOT NULL DEFAULT '1',
              `id_recibo` bigint NOT NULL AUTO_INCREMENT,
              `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tip_doc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'REC',
              `anulada` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'N',
              `talonario` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'RECIBO DE COBRO [001-001]',
              `cod_establecimiento` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT '001',
              `cod_punto_expedicion` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT '001',
              `nro_recibo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `serie` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_recibo` datetime DEFAULT NULL,
              `cod_cliente` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `nom_cliente` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `ruc_cliente` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tip_doc_cliente` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'RUC',
              `cod_moneda` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tasa_cambio` decimal(12,4) DEFAULT NULL,
              `valor_total` decimal(15,2) DEFAULT NULL,
              `valor_otras_facturas` decimal(15,2) DEFAULT NULL,
              `total_facturas` decimal(15,2) DEFAULT NULL,
              `diferencia` decimal(15,2) DEFAULT NULL,
              `observacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'A',
              `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_reg` datetime DEFAULT NULL,
              PRIMARY KEY (`id_recibo`),
              KEY `idx_co_recibos_cobro_prop` (`id_propietario`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);

        $colsRecCab = [
            'tip_doc'     => "varchar(30) DEFAULT 'REC'",
            'anulada'     => "varchar(1) DEFAULT 'N'",
            'serie'       => "varchar(10) DEFAULT NULL",
            'cod_empresa' => "varchar(20) DEFAULT NULL",
            'concepto'    => "varchar(150) DEFAULT NULL",
            'metodo_pago' => "varchar(100) DEFAULT NULL",
            'nro_cheque'  => "varchar(50) DEFAULT NULL",
            'banco'       => "varchar(100) DEFAULT NULL",
        ];
        foreach ($colsRecCab as $cName => $cDef) {
            if (!self::columnExists('co_recibos_cobro', $cName)) {
                Database::execute("ALTER TABLE `co_recibos_cobro` ADD COLUMN `$cName` $cDef", true);
            }
        }

        Database::execute("
            CREATE TABLE IF NOT EXISTS `co_recibos_cobro_det` (
              `id_detalle` bigint NOT NULL AUTO_INCREMENT,
              `id_propietario` int NOT NULL DEFAULT '1',
              `id_recibo` bigint NOT NULL,
              `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `id_ingreso` bigint DEFAULT NULL,
              `tip_doc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'FAC',
              `nro_timbrado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `nro_factura` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `serie` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_ingreso` datetime DEFAULT NULL,
              `valor_total` decimal(15,2) DEFAULT NULL,
              `saldo` decimal(15,2) DEFAULT NULL,
              `importe` decimal(15,2) DEFAULT NULL,
              `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'A',
              `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_reg` datetime DEFAULT NULL,
              PRIMARY KEY (`id_detalle`),
              KEY `idx_co_recibos_cobro_det_prop` (`id_propietario`),
              KEY `idx_co_recibos_cobro_det_rec` (`id_propietario`, `id_recibo`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);

        $colsRecDet = [
            'tip_doc'      => "varchar(30) DEFAULT 'FAC'",
            'nro_timbrado' => "varchar(20) DEFAULT NULL",
            'nro_factura'  => "varchar(30) DEFAULT NULL",
            'serie'        => "varchar(10) DEFAULT NULL",
            'fec_ingreso'  => "datetime DEFAULT NULL",
            'valor_total'  => "decimal(15,2) DEFAULT NULL",
            'saldo'        => "decimal(15,2) DEFAULT NULL",
            'importe'      => "decimal(15,2) DEFAULT NULL",
            'estado'       => "varchar(1) DEFAULT 'A'",
            'usuario_reg'  => "varchar(50) DEFAULT NULL",
            'fec_reg'      => "datetime DEFAULT NULL",
        ];
        foreach ($colsRecDet as $cName => $cDef) {
            if (!self::columnExists('co_recibos_cobro_det', $cName)) {
                Database::execute("ALTER TABLE `co_recibos_cobro_det` ADD COLUMN `$cName` $cDef", true);
            }
        }

        Database::execute(
            "INSERT INTO `formas` (`id_propietario`, `cod_modulo`, `nom_forma`, `titulo`, `descripcion`, `esta`, `mostrar_en_menu`, `cod_modulo_menu`, `tipo`, `orden_en_menu`)
             SELECT DISTINCT p.id_prop, 'CO', 'CORECIBO', 'Recibos de Cobro', 'REGISTRO DE RECIBOS DE COBRO DE FACTURAS CREDITO', 'A', 'S', 'CO', 'M', 3
             FROM (
                 SELECT `id_propietario` AS id_prop FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop FROM `formas`
                 UNION
                 SELECT 1 AS id_prop
             ) p
             WHERE NOT EXISTS (
                 SELECT 1 FROM `formas` f
                 WHERE f.`id_propietario` = p.id_prop AND UPPER(TRIM(f.`nom_forma`)) = 'CORECIBO'
             )",
            true
        );

        Database::execute(
            "INSERT INTO `accesos_grupos` (
                 `id_propietario`, `cod_grupo`, `cod_modulo`, `nom_forma`,
                 `puede_insertar`, `puede_borrar`, `puede_actualizar`, `puede_consultar`,
                 `item_menu`, `mostrar_en_menu`
             )
             SELECT DISTINCT g.id_prop, g.cod_grp, 'CO', 'CORECIBO', 'S', 'S', 'S', 'S', 'Recibos de Cobro', 'S'
             FROM (
                 SELECT `id_propietario` AS id_prop, UPPER(TRIM(`cod_grupo`)) AS cod_grp
                 FROM `usuarios`
                 WHERE TRIM(COALESCE(`cod_grupo`, '')) <> ''
                 UNION
                 SELECT `id_propietario` AS id_prop, 'CONADM' AS cod_grp FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop, 'CONTA' AS cod_grp FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop, 'CONTAM' AS cod_grp FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop, 'CONTAP' AS cod_grp FROM `propietarios`
                 UNION
                 SELECT 1 AS id_prop, 'CONADM' AS cod_grp
                 UNION
                 SELECT 1 AS id_prop, 'CONTA' AS cod_grp
             ) g
             WHERE NOT EXISTS (
                 SELECT 1 FROM `accesos_grupos` x
                 WHERE x.`id_propietario` = g.id_prop
                   AND UPPER(TRIM(x.`cod_grupo`)) = g.cod_grp
                   AND UPPER(TRIM(x.`nom_forma`)) = 'CORECIBO'
             )",
            true
        );

        // 19. Tablas de Recibos de Pago (alineadas a co_egresos)
        Database::execute("
            CREATE TABLE IF NOT EXISTS `co_recibos_pago` (
              `id_propietario` int NOT NULL DEFAULT '1',
              `id_recibo_pago` bigint NOT NULL AUTO_INCREMENT,
              `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tip_doc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tip_proveedor` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'PROVEEDOR LOCAL',
              `cod_proveedor` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tip_doc_proveedor` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'RUC',
              `nom_proveedor` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `ruc_proveedor` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `nro_recibo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `serie` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `anulada` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'N',
              `fec_recibo` datetime DEFAULT NULL,
              `cod_moneda` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `tasa_cambio` decimal(12,4) DEFAULT NULL,
              `valor_total` decimal(15,2) DEFAULT NULL,
              `valor_otras_facturas` decimal(15,2) DEFAULT NULL,
              `total_facturas` decimal(15,2) DEFAULT NULL,
              `diferencia` decimal(15,2) DEFAULT NULL,
              `observacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'A',
              `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_reg` datetime DEFAULT NULL,
              PRIMARY KEY (`id_recibo_pago`,`id_propietario`),
              KEY `idx_co_recibos_pago_prop` (`id_propietario`),
              KEY `idx_co_recibos_pago_emp_fec` (`id_propietario`,`cod_empresa`,`fec_recibo`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);

        $colsRecPago = [
            'tip_doc'              => "varchar(30) DEFAULT 'REC'",
            'tip_proveedor'        => "varchar(50) DEFAULT 'PROVEEDOR LOCAL'",
            'cod_proveedor'        => "varchar(30) DEFAULT NULL",
            'tip_doc_proveedor'    => "varchar(20) DEFAULT 'RUC'",
            'nom_proveedor'        => "varchar(150) DEFAULT NULL",
            'ruc_proveedor'        => "varchar(30) DEFAULT NULL",
            'nro_recibo'           => "varchar(30) DEFAULT NULL",
            'serie'                => "varchar(10) DEFAULT NULL",
            'anulada'              => "varchar(1) DEFAULT 'N'",
            'fec_recibo'           => "datetime DEFAULT NULL",
            'cod_moneda'           => "varchar(20) DEFAULT NULL",
            'tasa_cambio'          => "decimal(12,4) DEFAULT NULL",
            'valor_total'          => "decimal(15,2) DEFAULT NULL",
            'valor_otras_facturas' => "decimal(15,2) DEFAULT NULL",
            'total_facturas'       => "decimal(15,2) DEFAULT NULL",
            'diferencia'           => "decimal(15,2) DEFAULT NULL",
            'observacion'          => "varchar(500) DEFAULT NULL",
            'estado'               => "varchar(1) DEFAULT 'A'",
            'usuario_reg'          => "varchar(50) DEFAULT NULL",
            'fec_reg'              => "datetime DEFAULT NULL",
        ];
        foreach ($colsRecPago as $cName => $cDef) {
            if (!self::columnExists('co_recibos_pago', $cName)) {
                Database::execute("ALTER TABLE `co_recibos_pago` ADD COLUMN `$cName` $cDef", true);
            }
        }

        Database::execute("
            CREATE TABLE IF NOT EXISTS `co_recibos_pago_det` (
              `id_propietario` int NOT NULL DEFAULT '1',
              `id_detalle` bigint NOT NULL AUTO_INCREMENT,
              `id_recibo_pago` bigint NOT NULL,
              `cod_empresa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `id_egreso` bigint DEFAULT NULL,
              `tip_comprobante` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'FAC',
              `nro_timbrado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `nro_factura` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `serie` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_egreso` datetime DEFAULT NULL,
              `valor_total` decimal(15,2) DEFAULT NULL,
              `saldo` decimal(15,2) DEFAULT NULL,
              `importe` decimal(15,2) DEFAULT NULL,
              `estado` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'A',
              `usuario_reg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `fec_reg` datetime DEFAULT NULL,
              PRIMARY KEY (`id_detalle`,`id_propietario`),
              KEY `idx_co_recibos_pago_det_rec` (`id_propietario`,`id_recibo_pago`),
              KEY `idx_co_recibos_pago_det_egr` (`id_propietario`,`id_egreso`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);

        $colsRecPagoDet = [
            'id_egreso'       => "bigint DEFAULT NULL",
            'tip_comprobante' => "varchar(20) DEFAULT 'FAC'",
            'nro_timbrado'    => "varchar(20) DEFAULT NULL",
            'nro_factura'     => "varchar(30) DEFAULT NULL",
            'serie'           => "varchar(10) DEFAULT NULL",
            'fec_egreso'      => "datetime DEFAULT NULL",
            'valor_total'     => "decimal(15,2) DEFAULT NULL",
            'saldo'           => "decimal(15,2) DEFAULT NULL",
            'importe'         => "decimal(15,2) DEFAULT NULL",
            'estado'          => "varchar(1) DEFAULT 'A'",
            'usuario_reg'     => "varchar(50) DEFAULT NULL",
            'fec_reg'         => "datetime DEFAULT NULL",
        ];
        foreach ($colsRecPagoDet as $cName => $cDef) {
            if (!self::columnExists('co_recibos_pago_det', $cName)) {
                Database::execute("ALTER TABLE `co_recibos_pago_det` ADD COLUMN `$cName` $cDef", true);
            }
        }

        Database::execute(
            "INSERT INTO `formas` (`id_propietario`, `cod_modulo`, `nom_forma`, `titulo`, `descripcion`, `esta`, `mostrar_en_menu`, `cod_modulo_menu`, `tipo`, `orden_en_menu`)
             SELECT DISTINCT p.id_prop, 'CO', 'CORECIBOPAGO', 'Recibos de Pago', 'REGISTRO DE RECIBOS DE PAGO A PROVEEDORES DE FACTURAS CREDITO', 'A', 'S', 'CO', 'M', 4
             FROM (
                 SELECT `id_propietario` AS id_prop FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop FROM `formas`
                 UNION
                 SELECT 1 AS id_prop
             ) p
             WHERE NOT EXISTS (
                 SELECT 1 FROM `formas` f
                 WHERE f.`id_propietario` = p.id_prop AND UPPER(TRIM(f.`nom_forma`)) = 'CORECIBOPAGO'
             )",
            true
        );

        Database::execute(
            "INSERT INTO `accesos_grupos` (
                 `id_propietario`, `cod_grupo`, `cod_modulo`, `nom_forma`,
                 `puede_insertar`, `puede_borrar`, `puede_actualizar`, `puede_consultar`,
                 `item_menu`, `mostrar_en_menu`
             )
             SELECT DISTINCT g.id_prop, g.cod_grp, 'CO', 'CORECIBOPAGO', 'S', 'S', 'S', 'S', 'Recibos de Pago', 'S'
             FROM (
                 SELECT `id_propietario` AS id_prop, UPPER(TRIM(`cod_grupo`)) AS cod_grp
                 FROM `usuarios`
                 WHERE TRIM(COALESCE(`cod_grupo`, '')) <> ''
                 UNION
                 SELECT `id_propietario` AS id_prop, 'CONADM' AS cod_grp FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop, 'CONTA' AS cod_grp FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop, 'CONTAM' AS cod_grp FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop, 'CONTAP' AS cod_grp FROM `propietarios`
                 UNION
                 SELECT 1 AS id_prop, 'CONADM' AS cod_grp
                 UNION
                 SELECT 1 AS id_prop, 'CONTA' AS cod_grp
             ) g
             WHERE NOT EXISTS (
                 SELECT 1 FROM `accesos_grupos` x
                 WHERE x.`id_propietario` = g.id_prop
                   AND UPPER(TRIM(x.`cod_grupo`)) = g.cod_grp
                   AND UPPER(TRIM(x.`nom_forma`)) = 'CORECIBOPAGO'
             )",
            true
        );

        return $applied;
    }

    public static function columnExists(string $table, string $column): bool {
        try {
            $cnt = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND LOWER(TABLE_NAME) = LOWER(?) AND LOWER(COLUMN_NAME) = LOWER(?)",
                [$table, $column]
            );
            return $cnt > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
