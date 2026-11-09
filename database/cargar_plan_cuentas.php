<?php
/**
 * Script para sembrar / actualizar el Plan de Cuentas Recomendado en ContaPro.
 * Uso por CLI:
 *   php database/cargar_plan_cuentas.php [id_propietario]
 */

require_once __DIR__ . '/../core/Database.php';

use Core\Database;

$idProp = isset($argv[1]) ? (int)$argv[1] : 1;

echo "========================================================\n";
echo "ContaPro - Sembrador de Plan de Cuentas Recomendado\n";
echo "Propietario / Tenant ID: {$idProp}\n";
echo "========================================================\n\n";

$sqlFile = __DIR__ . '/plan_cuentas_recomendado.sql';
if (!file_exists($sqlFile)) {
    die("Error: No se encontró el archivo {$sqlFile}\n");
}

$sqlContent = file_get_contents($sqlFile);

try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    // Reemplazar el id_propietario si no es 1
    if ($idProp !== 1) {
        $sqlContent = preg_replace('/\(1, /', "({$idProp}, ", $sqlContent);
    }

    $pdo->exec($sqlContent);
    $pdo->commit();

    $total = (int)Database::queryForValue(
        "SELECT COUNT(*) FROM co_plan_cuentas WHERE id_propietario = ?",
        [$idProp]
    );

    echo "✅ Éxito: Plan de Cuentas cargado correctamente.\n";
    echo "Total de cuentas activas para el Propietario {$idProp}: {$total}\n\n";

} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error al cargar el plan de cuentas: " . $e->getMessage() . "\n";
    exit(1);
}
