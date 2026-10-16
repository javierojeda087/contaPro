<?php
/**
 * Configuración de Base de Datos MySQL para ContaPro
 */
$localSecretFile = __DIR__ . '/database.local.php';
if (is_file($localSecretFile)) {
    return require $localSecretFile;
}

return [
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => getenv('DB_PORT') ?: '3306',
    'dbname'   => getenv('DB_DATABASE') ?: 'contapro',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset'  => 'utf8mb4',
];
