<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$expectedDatabase = 'importsystemgo_testing';
$expectedAccount = 'importsystemgo_test@localhost';

if (getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'mysql'
    || getenv('DB_DATABASE') !== $expectedDatabase
    || getenv('DB_USERNAME') !== 'importsystemgo_test') {
    throw new RuntimeException('Configuracion MySQL de pruebas insegura o incompleta.');
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$password = getenv('DB_PASSWORD');

if (! is_string($password) || $password === '') {
    throw new RuntimeException('La credencial MySQL de pruebas no esta disponible en el entorno del proceso.');
}

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$expectedDatabase};charset=utf8mb4",
    'importsystemgo_test',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$identity = $pdo->query('SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name')->fetch(PDO::FETCH_ASSOC);

if (($identity['database_name'] ?? null) !== $expectedDatabase
    || ($identity['account_name'] ?? null) !== $expectedAccount) {
    throw new RuntimeException('La conexion efectiva no corresponde al entorno MySQL de pruebas autorizado.');
}

unset($password, $pdo);
