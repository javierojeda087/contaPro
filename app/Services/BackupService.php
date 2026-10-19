<?php
namespace App\Services;

use Core\Database;
use Core\TenantContext;

class BackupService {

    /**
     * Genera el respaldo SQL completo de todas las tablas del proyecto
     * o filtrado por un Contribuyente (cod_empresa) específico.
     *
     * @param string|null $codEmpresa  Código de empresa/contribuyente ('ALL' o '' para todos, o código específico)
     * @param string|null $customDir   Ruta de carpeta adicional opcional en el servidor/PC local
     */
    public static function generateBackup(?string $codEmpresa = null, ?string $customDir = null): array {
        $startTime = microtime(true);
        $propId    = TenantContext::idPropietario();
        $isSuper   = TenantContext::esSuperAdmin();
        $timestamp = date('Ymd_His');

        $codEmpresaClean = trim((string)$codEmpresa);
        $filtrarPorEmpresa = ($codEmpresaClean !== '' && strtoupper($codEmpresaClean) !== 'ALL' && strtoupper($codEmpresaClean) !== 'TODOS');

        $nombreContribuyente = 'TODOS_LOS_CONTRIBUYENTES';
        $rucContribuyente    = '';
        if ($filtrarPorEmpresa) {
            $empRow = Database::queryForMap(
                "SELECT descripcion, ruc_empresa FROM empresas WHERE id_propietario = ? AND cod_empresa = ? LIMIT 1",
                [$propId, $codEmpresaClean]
            );
            if (!$empRow) {
                throw new \InvalidArgumentException("No se encontró el contribuyente #{$codEmpresaClean}.");
            }
            $nombreContribuyente = (string)($empRow['descripcion'] ?? ("EMPRESA_" . $codEmpresaClean));
            $rucContribuyente    = (string)($empRow['ruc_empresa'] ?? '');
        }

        $backupDir = __DIR__ . '/../../backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        // Mantener hasta 15 respaldos recientes en el servidor
        self::pruneOldBackups($backupDir, 15);

        if ($filtrarPorEmpresa) {
            $slugEmp = preg_replace('/[^A-Z0-9]+/', '_', strtoupper($nombreContribuyente));
            $slugEmp = trim(substr($slugEmp, 0, 28), '_') ?: ('EMP' . $codEmpresaClean);
            $fileName = sprintf('backup_contapro_prop%d_emp%s_%s_%s.sql', $propId, $codEmpresaClean, $slugEmp, $timestamp);
        } else {
            $fileName = sprintf('backup_contapro_prop%d_COMPLETO_%s.sql', $propId, $timestamp);
        }

        $filePath = $backupDir . '/' . $fileName;

        // Obtener absolutamente todas las tablas base y vistas del proyecto en la base de datos
        $fullTables = Database::queryForList("SHOW FULL TABLES");
        $baseTables = [];
        $views      = [];

        foreach ($fullTables as $tRow) {
            $vals = array_values($tRow);
            $tbName = (string)($vals[0] ?? '');
            $tbType = strtoupper((string)($vals[1] ?? 'BASE TABLE'));
            if ($tbName === '') continue;
            if (str_contains($tbType, 'VIEW')) {
                $views[] = $tbName;
            } else {
                $baseTables[] = $tbName;
            }
        }

        $totalTablas = 0;
        $totalFilas  = 0;
        $tablasDetalle = [];

        $fp = fopen($filePath, 'w');
        fwrite($fp, "-- =============================================================================\n");
        fwrite($fp, "-- COPIA DE SEGURIDAD OFICIAL - CONTAPRO CLOUD (MYSQL / MARIADB)\n");
        fwrite($fp, "-- Propietario ID : {$propId}\n");
        if ($filtrarPorEmpresa) {
            fwrite($fp, "-- Alcance        : POR CONTRIBUYENTE (Empresa #{$codEmpresaClean} - {$nombreContribuyente}" . ($rucContribuyente ? " | RUC: {$rucContribuyente}" : "") . ")\n");
        } else {
            fwrite($fp, "-- Alcance        : COMPLETO (Todas las tablas y contribuyentes del proyecto)\n");
        }
        fwrite($fp, "-- Fecha y Hora   : " . date('d/m/Y H:i:s') . "\n");
        fwrite($fp, "-- Total Tablas   : " . count($baseTables) . "\n");
        fwrite($fp, "-- =============================================================================\n\n");
        fwrite($fp, "SET NAMES utf8mb4;\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 0;\n");
        fwrite($fp, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

        $pdo = Database::getConnection();

        foreach ($baseTables as $tb) {
            $totalTablas++;

            fwrite($fp, "-- -----------------------------------------------------------------------------\n");
            fwrite($fp, "-- Tabla #{$totalTablas}: `{$tb}`\n");
            fwrite($fp, "-- -----------------------------------------------------------------------------\n");

            $createRow = Database::queryForMap("SHOW CREATE TABLE `{$tb}`");
            if ($createRow && isset($createRow['Create Table'])) {
                $createSql = preg_replace('/^CREATE TABLE/i', 'CREATE TABLE IF NOT EXISTS', $createRow['Create Table'], 1);
                fwrite($fp, $createSql . ";\n\n");
            }

            // Detectar columnas de filtro disponibles en la tabla
            $hasProp = false;
            $hasEmp  = false;
            try {
                $cols = Database::queryForList("SHOW COLUMNS FROM `{$tb}`");
                foreach ($cols as $c) {
                    $field = strtolower((string)($c['Field'] ?? ''));
                    if ($field === 'id_propietario') $hasProp = true;
                    if ($field === 'cod_empresa')    $hasEmp  = true;
                }
            } catch (\Throwable $ignored) {}

            $whereParts = [];
            $params     = [];

            // Si no es SuperAdmin haciendo backup completo, o si es backup por contribuyente, filtrar por propietario
            if ($hasProp && (!$isSuper || $filtrarPorEmpresa)) {
                $whereParts[] = "`id_propietario` = ?";
                $params[]     = $propId;
            }

            // Si se eligió un contribuyente específico y la tabla tiene cod_empresa, filtrar por ese contribuyente
            if ($filtrarPorEmpresa && $hasEmp) {
                if (strtolower($tb) === 'usuarios') {
                    // En usuarios, incluir los usuarios del propietario para no perder credenciales
                } else {
                    $whereParts[] = "`cod_empresa` = ?";
                    $params[]     = $codEmpresaClean;
                }
            }

            $whereSql = !empty($whereParts) ? (' WHERE ' . implode(' AND ', $whereParts)) : '';
            $rows = Database::queryForList("SELECT * FROM `{$tb}`{$whereSql}", $params);
            $filasTabla = count($rows);
            $tablasDetalle[] = $tb;

            if ($filasTabla > 0) {
                fwrite($fp, "-- Volcado de datos para la tabla `{$tb}` ({$filasTabla} registro(s))\n");
                foreach ($rows as $r) {
                    $colNames = '`' . implode('`, `', array_keys($r)) . '`';
                    $vals = [];
                    foreach ($r as $v) {
                        if ($v === null) {
                            $vals[] = 'NULL';
                        } elseif (is_int($v) || is_float($v)) {
                            $vals[] = $v;
                        } else {
                            $vals[] = $pdo->quote((string)$v);
                        }
                    }
                    fwrite($fp, "REPLACE INTO `{$tb}` ({$colNames}) VALUES (" . implode(', ', $vals) . ");\n");
                    $totalFilas++;
                }
                fwrite($fp, "\n");
            } else {
                fwrite($fp, "-- (Sin registros para exportar en `{$tb}`)\n\n");
            }
        }

        // Exportar vistas si existen en la BD
        foreach ($views as $vw) {
            try {
                $vwRow = Database::queryForMap("SHOW CREATE VIEW `{$vw}`");
                if ($vwRow && isset($vwRow['Create View'])) {
                    fwrite($fp, "-- Vista: `{$vw}`\n");
                    fwrite($fp, $vwRow['Create View'] . ";\n\n");
                }
            } catch (\Throwable $ignored) {}
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fwrite($fp, "-- =============================================================================\n");
        fwrite($fp, "-- FIN DE LA COPIA DE SEGURIDAD ({$totalTablas} tablas, {$totalFilas} registros)\n");
        fwrite($fp, "-- =============================================================================\n");
        fclose($fp);

        // Si el usuario especificó una ruta de carpeta local accesible desde este equipo/servidor, guardar copia allí también
        $savedCustomPath = null;
        $customDirClean = trim((string)$customDir);
        if ($customDirClean !== '') {
            $customDirClean = rtrim($customDirClean, "/\\");
            if (!is_dir($customDirClean)) {
                @mkdir($customDirClean, 0775, true);
            }
            if (is_dir($customDirClean) && is_writable($customDirClean)) {
                $targetCustomFile = $customDirClean . DIRECTORY_SEPARATOR . $fileName;
                if (@copy($filePath, $targetCustomFile)) {
                    $savedCustomPath = realpath($targetCustomFile) ?: $targetCustomFile;
                }
            }
        }

        $duration = max(1, (int)round(microtime(true) - $startTime));
        $size = filesize($filePath);

        return [
            'fileName'            => $fileName,
            'filePath'            => realpath($filePath),
            'savedCustomPath'     => $savedCustomPath,
            'fileSizeFormatted'   => self::formatFileSize($size),
            'fileSizeBytes'       => $size,
            'tablesCount'         => $totalTablas,
            'recordsCount'        => $totalFilas,
            'durationSeconds'     => $duration,
            'timestamp'           => $timestamp,
            'alcance'             => $filtrarPorEmpresa ? 'CONTRIBUYENTE' : 'COMPLETO',
            'codEmpresa'          => $filtrarPorEmpresa ? $codEmpresaClean : 'ALL',
            'nombreContribuyente' => $nombreContribuyente,
            'rucContribuyente'    => $rucContribuyente,
        ];
    }

    public static function listBackups(): array {
        $backupDir = __DIR__ . '/../../backups';
        $propId = TenantContext::idPropietario();
        $isSuper = TenantContext::esSuperAdmin();
        $list = [];

        if (is_dir($backupDir)) {
            $files = glob($backupDir . '/*.sql') ?: [];
            usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
            foreach ($files as $f) {
                $name = basename($f);
                if (!$isSuper && !str_contains($name, "_prop{$propId}_")) {
                    continue;
                }
                $size = filesize($f);
                $mtime = filemtime($f);
                $tipo = str_contains($name, '_emp') ? 'Por Contribuyente' : 'Completo (Todas las tablas)';
                $list[] = [
                    'fileName'              => $name,
                    'tipo'                  => $tipo,
                    'fileSizeBytes'         => $size,
                    'fileSizeFormatted'     => self::formatFileSize($size),
                    'lastModifiedFormatted' => date('d/m/Y H:i:s', $mtime),
                    'lastModifiedMillis'    => $mtime * 1000,
                ];
            }
        }
        return $list;
    }

    public static function getBackupFile(string $fileName): string {
        $clean = basename($fileName);
        $path = __DIR__ . '/../../backups/' . $clean;
        if (!is_file($path)) {
            throw new \InvalidArgumentException("El archivo de backup no existe.");
        }
        return $path;
    }

    public static function deleteBackup(string $fileName): bool {
        $path = self::getBackupFile($fileName);
        return @unlink($path);
    }

    private static function pruneOldBackups(string $dir, int $keepMax): void {
        $files = glob($dir . '/*.sql') ?: [];
        if (count($files) <= $keepMax) return;
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, $keepMax) as $old) {
            @unlink($old);
        }
    }

    public static function formatFileSize(int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        $units = ['KB', 'MB', 'GB'];
        $exp = min((int)floor(log($bytes, 1024)), count($units));
        return sprintf('%.2f %s', $bytes / pow(1024, $exp), $units[$exp - 1]);
    }
}
