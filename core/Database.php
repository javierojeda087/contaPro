<?php
namespace Core;

use PDO;
use PDOException;

class Database {
    private static ?PDO $pdo = null;
    private static ?PDOException $connError = null;

    public static function getConnection(): PDO {
        if (self::$connError !== null) {
            throw self::$connError;
        }
        if (self::$pdo === null) {
            $cfg = require __DIR__ . '/../config/database.php';
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['port'],
                $cfg['dbname'],
                $cfg['charset']
            );
            try {
                self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 3,
                ]);
            } catch (PDOException $e) {
                self::$connError = $e;
                throw $e;
            }
        }
        return self::$pdo;
    }

    /**
     * Ejecuta un SELECT y devuelve arreglo de filas asociativas con claves en minúscula
     */
    public static function queryForList(string $sql, array $params = []): array {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(array_values($params));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    /**
     * Ejecuta un SELECT y devuelve filas con claves en MAYÚSCULA (compatible 1:1 con Oracle JdbcTemplate.queryForList)
     */
    public static function queryForListUpper(string $sql, array $params = []): array {
        $rows = self::queryForList($sql, $params);
        $out = [];
        foreach ($rows as $r) {
            $item = [];
            foreach ($r as $k => $v) {
                $item[strtoupper($k)] = $v;
            }
            $out[] = $item;
        }
        return $out;
    }

    /**
     * Ejecuta un SELECT y devuelve filas con claves tanto en MAYÚSCULA como en minúscula
     */
    public static function queryForListDual(string $sql, array $params = []): array {
        $rows = self::queryForList($sql, $params);
        $out = [];
        foreach ($rows as $r) {
            $item = [];
            foreach ($r as $k => $v) {
                $item[strtolower($k)] = $v;
                $item[strtoupper($k)] = $v;
            }
            $out[] = $item;
        }
        return $out;
    }

    /**
     * Devuelve la primera fila o null si no hay resultados
     */
    public static function queryForMap(string $sql, array $params = []): ?array {
        $rows = self::queryForList($sql, $params);
        return !empty($rows) ? $rows[0] : null;
    }

    /**
     * Devuelve un único valor escalar (primera columna de la primera fila)
     */
    public static function queryForValue(string $sql, array $params = []) {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(array_values($params));
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : null;
    }

    /**
     * Ejecuta INSERT, UPDATE o DELETE y retorna el número de filas afectadas
     */
    public static function update(string $sql, array $params = []): int {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(array_values($params));
        return $stmt->rowCount();
    }

    /**
     * Ejecuta INSERT y retorna el último ID autogenerado
     */
    public static function insertGetId(string $sql, array $params = []): int {
        self::update($sql, $params);
        return (int) self::getConnection()->lastInsertId();
    }

    /**
     * Ejecuta DDL o SQL directo ignorando errores opcionalmente
     */
    public static function execute(string $sql, bool $ignoreErrors = false): void {
        try {
            self::getConnection()->exec($sql);
        } catch (PDOException $e) {
            if (!$ignoreErrors) {
                throw $e;
            }
        }
    }
}
