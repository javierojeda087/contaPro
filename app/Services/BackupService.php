<?php
namespace App\Services;

use Core\Database;
use Core\TenantContext;

class BackupService {

    /**
     * Obtiene la lista de propietarios con su cantidad de contribuyentes y usuarios
     * para poblar el selector de alcance del backup.
     */
    public static function getOpcionesPropietarios(): array {
        $propIdActual = TenantContext::idPropietario();
        $isSuper      = TenantContext::esSuperAdmin() || $propIdActual === 1;

        $sql = "SELECT pr.id_propietario AS idPropietario,
                       pr.nombre_propietario AS nombrePropietario,
                       COALESCE(pr.ruc, '') AS ruc,
                       (SELECT COUNT(*) FROM empresas e WHERE e.id_propietario = pr.id_propietario) AS totalContribuyentes,
                       (SELECT COUNT(*) FROM usuarios u WHERE u.id_propietario = pr.id_propietario) AS totalUsuarios
                FROM propietarios pr "
             . ($isSuper ? "" : "WHERE pr.id_propietario = " . (int)$propIdActual . " ")
             . "ORDER BY pr.id_propietario ASC";

        $propietarios = Database::queryForList($sql);

        return [
            'esSuperAdmin'        => $isSuper,
            'idPropietarioActual' => $propIdActual,
            'propietarios'        => $propietarios,
        ];
    }

    /**
     * Genera el respaldo SQL de todas las tablas del proyecto:
     * - Si $idPropietarioTarget es 'ALL' (y es SuperAdmin): exporta TODOS los propietarios y TODOS sus contribuyentes.
     * - Si $idPropietarioTarget es un ID de propietario (ej: '1', '2'): exporta TODO ese propietario y TODOS sus contribuyentes.
     *
     * @param string|null $idPropietarioTarget 'ALL' para todos los propietarios, o el ID del propietario (ej: '1')
     * @param string|null $customDir           Ruta de carpeta adicional opcional en el servidor/PC local
     */
    public static function generateBackup(?string $idPropietarioTarget = 'ALL', ?string $customDir = null): array {
        $startTime    = microtime(true);
        $propIdActual = TenantContext::idPropietario();
        $isSuper      = TenantContext::esSuperAdmin() || $propIdActual === 1;
        $timestamp    = date('Ymd_His');

        $targetClean = strtoupper(trim((string)$idPropietarioTarget));
        $targetClean = str_replace('PROP:', '', $targetClean);

        // Determinar si es backup global (Todos los propietarios) o de un propietario específico
        if (!$isSuper) {
            $filtrarPorPropietario = true;
            $targetPropId          = $propIdActual;
        } elseif ($targetClean === '' || $targetClean === 'ALL' || $targetClean === 'TODOS') {
            $filtrarPorPropietario = false;
            $targetPropId          = 0;
        } else {
            $filtrarPorPropietario = true;
            $targetPropId          = max(1, (int)$targetClean);
        }

        $nombrePropietario   = 'TODOS LOS PROPIETARIOS Y TODOS LOS CONTRIBUYENTES';
        $totalContribuyentes = 0;

        if ($filtrarPorPropietario) {
            $propRow = Database::queryForMap(
                "SELECT nombre_propietario, ruc FROM propietarios WHERE id_propietario = ? LIMIT 1",
                [$targetPropId]
            );
            $nombrePropietario = (string)($propRow['nombre_propietario'] ?? ("PROPIETARIO #" . $targetPropId));
            $totalContribuyentes = (int)Database::queryForValue(
                "SELECT COUNT(*) FROM empresas WHERE id_propietario = ?",
                [$targetPropId]
            );
        } else {
            $totalContribuyentes = (int)Database::queryForValue("SELECT COUNT(*) FROM empresas");
        }

        $backupDir = __DIR__ . '/../../backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        // Mantener hasta 15 respaldos recientes en el servidor
        self::pruneOldBackups($backupDir, 15);

        if ($filtrarPorPropietario) {
            $slugProp = preg_replace('/[^A-Z0-9]+/', '_', strtoupper($nombrePropietario));
            $slugProp = trim(substr($slugProp, 0, 26), '_') ?: ('PROP' . $targetPropId);
            $fileName = sprintf('backup_contapro_prop%d_%s_%s.sql', $targetPropId, $slugProp, $timestamp);
        } else {
            $fileName = sprintf('backup_contapro_TODOS_PROPIETARIOS_%s.sql', $timestamp);
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

        $fp = fopen($filePath, 'w');
        fwrite($fp, "-- =============================================================================\n");
        fwrite($fp, "-- COPIA DE SEGURIDAD OFICIAL - CONTAPRO CLOUD (MYSQL / MARIADB)\n");
        if ($filtrarPorPropietario) {
            fwrite($fp, "-- Alcance        : POR PROPIETARIO (Propietario #{$targetPropId} - {$nombrePropietario})\n");
            fwrite($fp, "-- Contribuyentes : {$totalContribuyentes} contribuyente(s) incluido(s)\n");
        } else {
            fwrite($fp, "-- Alcance        : COMPLETO (Todos los Propietarios y Todos los Contribuyentes)\n");
            fwrite($fp, "-- Contribuyentes : {$totalContribuyentes} contribuyente(s) en total\n");
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

            // Verificar si la tabla tiene la columna id_propietario
            $hasProp = false;
            try {
                $cols = Database::queryForList("SHOW COLUMNS FROM `{$tb}` LIKE 'id_propietario'");
                $hasProp = !empty($cols);
            } catch (\Throwable $ignored) {}

            $whereSql = '';
            $params   = [];

            if ($filtrarPorPropietario && $hasProp) {
                $whereSql = " WHERE `id_propietario` = ?";
                $params[] = $targetPropId;
            }

            $rows = Database::queryForList("SELECT * FROM `{$tb}`{$whereSql}", $params);
            $filasTabla = count($rows);

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
            'contribuyentesCount' => $totalContribuyentes,
            'durationSeconds'     => $duration,
            'timestamp'           => $timestamp,
            'alcance'             => $filtrarPorPropietario ? 'PROPIETARIO' : 'TODOS_PROPIETARIOS',
            'idPropietario'       => $filtrarPorPropietario ? $targetPropId : 'ALL',
            'nombrePropietario'   => $nombrePropietario,
        ];
    }

    public static function listBackups(): array {
        $backupDir = __DIR__ . '/../../backups';
        $propId = TenantContext::idPropietario();
        $isSuper = TenantContext::esSuperAdmin() || $propId === 1;
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
                if (str_contains($name, '_TODOS_PROPIETARIOS_')) {
                    $tipo = 'Todos los Propietarios y Contribuyentes';
                } elseif (preg_match('/_prop(\d+)_/', $name, $m)) {
                    $tipo = 'Propietario #' . $m[1] . ' y sus Contribuyentes';
                } else {
                    $tipo = 'Completo';
                }
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
