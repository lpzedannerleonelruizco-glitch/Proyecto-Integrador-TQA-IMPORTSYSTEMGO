<?php

declare(strict_types=1);

$database = 'importsystemgo_testing';
$username = 'importsystemgo_test';
$account = "'{$username}'@'localhost'";
$host = getenv('MYSQL_ADMIN_HOST') ?: '127.0.0.1';
$port = getenv('MYSQL_ADMIN_PORT') ?: '3306';
$adminUser = getenv('MYSQL_ADMIN_USER');
$adminPassword = getenv('MYSQL_ADMIN_PASSWORD');
$testPassword = getenv('MYSQL_TEST_PASSWORD');

if (! is_string($adminUser) || $adminUser === ''
    || ! is_string($adminPassword) || $adminPassword === ''
    || ! is_string($testPassword) || $testPassword === '') {
    fwrite(STDERR, "Faltan credenciales protegidas en el entorno del proceso.\n");
    exit(10);
}

$pdo = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $adminUser,
    $adminPassword,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$schemaQuery = $pdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
$schemaQuery->execute([$database]);

$userQuery = $pdo->prepare('SELECT COUNT(*) FROM mysql.user WHERE user = ? AND host = ?');
$userQuery->execute([$username, 'localhost']);

if ((int) $schemaQuery->fetchColumn() !== 0 || (int) $userQuery->fetchColumn() !== 0) {
    fwrite(STDERR, "La base o la cuenta de pruebas ya existe; no se realizo ningun cambio.\n");
    exit(11);
}

$databaseCreated = false;
$userCreated = false;

try {
    $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $databaseCreated = true;

    $quotedPassword = $pdo->quote($testPassword);
    $pdo->exec("CREATE USER {$account} IDENTIFIED BY {$quotedPassword}");
    $userCreated = true;

    $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, TRIGGER ON `{$database}`.* TO {$account}");

    $testPdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $username,
        $testPassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $identity = $testPdo->query('SELECT DATABASE(), CURRENT_USER()')->fetch(PDO::FETCH_NUM);

    if (($identity[0] ?? null) !== $database || ($identity[1] ?? null) !== 'importsystemgo_test@localhost') {
        throw new RuntimeException('La identidad efectiva de la conexion de pruebas no coincide.');
    }

    echo "database_created=yes\n";
    echo "test_account_created=yes\n";
    echo "identity_verified=yes\n";
} catch (Throwable $exception) {
    if ($userCreated) {
        $pdo->exec("DROP USER IF EXISTS {$account}");
    }

    if ($databaseCreated) {
        $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
    }

    fwrite(STDERR, "El aprovisionamiento fallo y sus recursos nuevos fueron retirados.\n");
    exit(12);
} finally {
    unset($quotedPassword, $testPassword, $adminPassword, $testPdo, $pdo);
}
