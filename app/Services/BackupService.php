<?php
namespace App\Services;

use Core\Database;
use Core\TenantContext;

class BackupService {

    public static function generateBackup(): array {
        $startTime = microtime(true);
        $propId = TenantContext::idPropietario();
        $timestamp = date('Ymd_His');

        $backupDir = __DIR__ . '/../../backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        // Limitar backups antiguos en disco para cuidar los 1000 MB del Plan Inicial de Hosting
        self::pruneOldBackups($backupDir, 4);

        $fileName = sprintf('backup_contapro_prop%d_%s.sql', $propId, $timestamp);
        $filePath = $backupDir . '/' . $fileName;

        $tables = Database::queryForList("SHOW TABLES");
        $totalTablas = 0;
        $totalFilas = 0;

        $fp = fopen($filePath, 'w');
        fwrite($fp, "-- =====================================================\n");
        fwrite($fp, "-- COPIA DE SEGURIDAD - CONTAPRO (MYSQL)\n");
        fwrite($fp, "-- Propietario ID: $propId\n");
        fwrite($fp, "-- Generado: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "-- =====================================================\n\n");
        fwrite($fp, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        $pdo = Database::getConnection();

        foreach ($tables as $tRow) {
            $tb = reset($tRow);
            $totalTablas++;

            $createRow = Database::queryForMap("SHOW CREATE TABLE `$tb`");
            if ($createRow && isset($createRow['Create Table'])) {
                fwrite($fp, "-- Estructura de `$tb`\n");
                fwrite($fp, "CREATE TABLE IF NOT EXISTS " . substr($createRow['Create Table'], 13) . ";\n\n");
            }

            // Si la tabla tiene id_propietario y el usuario no es SuperAdmin, respaldar solo los datos de su propietario
            $hasProp = false;
            try {
                $cols = Database::queryForList("SHOW COLUMNS FROM `$tb` LIKE 'id_propietario'");
                $hasProp = !empty($cols);
            } catch (\Throwable $ignored) {}

            $sqlData = "SELECT * FROM `$tb`" . ($hasProp ? " WHERE id_propietario = $propId" : "");
            $rows = Database::queryForList($sqlData);

            foreach ($rows as $r) {
                if (strtolower($tb) === 'usuarios' && isset($r['clave'])) {
                    $r['clave'] = '*****';
                }
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
                fwrite($fp, "INSERT INTO `$tb` ($colNames) VALUES (" . implode(', ', $vals) . ");\n");
                $totalFilas++;
            }
            if (!empty($rows)) {
                fwrite($fp, "\n");
            }
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fp);

        $duration = max(1, (int)round(microtime(true) - $startTime));
        $size = filesize($filePath);

        return [
            'fileName'          => $fileName,
            'filePath'          => realpath($filePath),
            'fileSizeFormatted' => self::formatFileSize($size),
            'fileSizeBytes'     => $size,
            'tablesCount'       => $totalTablas,
            'recordsCount'      => $totalFilas,
            'durationSeconds'   => $duration,
            'timestamp'         => $timestamp,
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
                $list[] = [
                    'fileName'              => $name,
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
