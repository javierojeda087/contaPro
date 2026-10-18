<?php
namespace Core;

/**
 * Migrador automático de esquema MySQL.
 * Permite agregar tablas o columnas nuevas en cPanel simplemente subiendo este archivo actualizado,
 * sin perder datos existentes ni tener que correr comandos manuales.
 */
class DatabaseMigrator {

    private const SCHEMA_VERSION = 6;

    public static function runIfNeeded(): void {
        if (($_SESSION['_migrator_version'] ?? 0) === self::SCHEMA_VERSION) {
            return;
        }
        self::migrate();
        $_SESSION['_migrator_version'] = self::SCHEMA_VERSION;
    }

    public static function migrate(): array {
        $applied = [];

        // 1. Asegurar tabla planes
        Database::execute("
            CREATE TABLE IF NOT EXISTS `planes` (
              `id_plan` INT NOT NULL AUTO_INCREMENT,
              `codigo_plan` VARCHAR(30) NOT NULL,
              `nombre_plan` VARCHAR(100) NOT NULL,
              `max_contribuyentes` INT NOT NULL DEFAULT 5,
              `max_usuarios` INT NOT NULL DEFAULT 2,
              `precio_mensual` DECIMAL(15,2) DEFAULT 0,
              `descripcion` VARCHAR(255) DEFAULT NULL,
              `estado` VARCHAR(1) DEFAULT 'A',
              `orden` INT DEFAULT 1,
              PRIMARY KEY (`id_plan`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", true);

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
        Database::execute("ALTER TABLE `usuarios` MODIFY COLUMN `clave` VARCHAR(255) DEFAULT NULL", true);

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
        ];
        foreach ($extraColsIngresos as $col => $def) {
            if (!self::columnExists('co_ingresos', $col)) {
                Database::execute("ALTER TABLE `co_ingresos` ADD COLUMN `$col` $def", true);
                $applied[] = "co_ingresos.$col";
            }
        }

        // 5. Asegurar grupo CONADM en grupos_usuarios, sus permisos en accesos_grupos y asignar el usuario CONTA a CONADM
        Database::execute(
            "INSERT INTO `grupos_usuarios` (`id_propietario`, `cod_grupo`, `descripcion`, `ind_clave_sin_caducidad`)
             SELECT DISTINCT p.id_prop, 'CONADM', 'ADMINISTRADOR CONTABLE', 'S'
             FROM (
                 SELECT `id_propietario` AS id_prop FROM `propietarios`
                 UNION
                 SELECT `id_propietario` AS id_prop FROM `grupos_usuarios`
                 UNION
                 SELECT 1 AS id_prop
             ) p
             WHERE NOT EXISTS (
                 SELECT 1 FROM `grupos_usuarios` g
                 WHERE g.`id_propietario` = p.id_prop AND UPPER(TRIM(g.`cod_grupo`)) = 'CONADM'
             )",
            true
        );

        Database::execute(
            "INSERT INTO `accesos_grupos` (
                 `id_propietario`, `cod_grupo`, `cod_modulo`, `nom_forma`,
                 `puede_insertar`, `puede_borrar`, `puede_actualizar`, `puede_consultar`,
                 `item_menu`, `mostrar_en_menu`
             )
             SELECT a.`id_propietario`, 'CONADM', a.`cod_modulo`, a.`nom_forma`,
                    a.`puede_insertar`, a.`puede_borrar`, a.`puede_actualizar`, a.`puede_consultar`,
                    a.`item_menu`, a.`mostrar_en_menu`
             FROM `accesos_grupos` a
             WHERE UPPER(TRIM(a.`cod_grupo`)) = 'CONTA'
               AND NOT EXISTS (
                   SELECT 1 FROM `accesos_grupos` x
                   WHERE x.`id_propietario` = a.`id_propietario`
                     AND UPPER(TRIM(x.`cod_grupo`)) = 'CONADM'
                     AND UPPER(TRIM(x.`nom_forma`)) = UPPER(TRIM(a.`nom_forma`))
               )",
            true
        );

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

        Database::execute(
            "UPDATE `usuarios`
             SET `cod_grupo` = 'CONADM'
             WHERE UPPER(TRIM(`cod_usuario`)) = 'CONTA' AND (UPPER(TRIM(COALESCE(`cod_grupo`, ''))) <> 'CONADM')",
            true
        );

        Database::execute(
            "UPDATE `usuarios`
             SET `cod_grupo` = 'CONTA'
             WHERE UPPER(TRIM(`cod_usuario`)) <> 'CONTA' AND (`cod_grupo` IS NULL OR TRIM(`cod_grupo`) = '')",
            true
        );

        if (strtoupper(trim((string)($_SESSION['usuario'] ?? ''))) === 'CONTA') {
            $_SESSION['grupo'] = 'CONADM';
        }

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
