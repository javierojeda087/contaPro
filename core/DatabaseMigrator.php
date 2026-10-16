<?php
namespace Core;

/**
 * Migrador automático de esquema MySQL.
 * Permite agregar tablas o columnas nuevas en cPanel simplemente subiendo este archivo actualizado,
 * sin perder datos existentes ni tener que correr comandos manuales.
 */
class DatabaseMigrator {

    private const SCHEMA_VERSION = 2;

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

        // 3. Asegurar columna es_superadmin en usuarios
        if (!self::columnExists('usuarios', 'es_superadmin')) {
            Database::execute("ALTER TABLE `usuarios` ADD COLUMN `es_superadmin` VARCHAR(1) DEFAULT 'N'", true);
            Database::execute("UPDATE `usuarios` SET `es_superadmin` = 'S' WHERE `id_propietario` = 1 AND UPPER(`cod_usuario`) = 'CONTA'", true);
            $applied[] = 'usuarios.es_superadmin';
        }

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
