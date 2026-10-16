<?php
/**
 * Configuración de Base de Datos MySQL para ContaPro (cPanel + Entorno Local)
 */
$isLocalDev = (PHP_SAPI === 'cli-server');

return [
    'host'     => getenv('DB_HOST') ?: ($isLocalDev ? '127.0.0.1' : 'localhost'),
    'port'     => getenv('DB_PORT') ?: '3306',
    'dbname'   => getenv('DB_DATABASE') ?: ($isLocalDev ? 'contapro' : 'contaprocompy_sistema'),
    'username' => getenv('DB_USERNAME') ?: ($isLocalDev ? 'root' : 'contaprocompy_admin'),
    'password' => getenv('DB_PASSWORD') ?: ($isLocalDev ? '' : 'contaproadmin'),
    'charset'  => 'utf8mb4',
];
