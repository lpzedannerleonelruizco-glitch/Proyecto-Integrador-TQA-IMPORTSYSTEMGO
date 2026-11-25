<?php

declare(strict_types=1);

$database = 'importsystemgo_demo';
$username = 'importsystemgo_demo';
$account = "'{$username}'@'localhost'";
$host = getenv('MYSQL_ADMIN_HOST') ?: '127.0.0.1';
$port = getenv('MYSQL_ADMIN_PORT') ?: '3306';
$adminUser = getenv('MYSQL_ADMIN_USER');
$adminPassword = getenv('MYSQL_ADMIN_PASSWORD');
$demoPassword = getenv('MYSQL_DEMO_PASSWORD');

if (! is_string($adminUser) || $adminUser === ''
    || ! is_string($adminPassword) || $adminPassword === ''
    || ! is_string($demoPassword) || strlen($demoPassword) < 20) {
    fwrite(STDERR, "Faltan credenciales protegidas en el entorno del proceso.\n");
    exit(10);
}

$pdo = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $adminUser,
    $adminPassword,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$identity = $pdo->query('SELECT CURRENT_USER()')->fetchColumn();
if (! is_string($identity) || ! str_ends_with($identity, '@localhost')) {
    fwrite(STDERR, "La identidad administrativa no es local.\n");
    exit(11);
}

$schemaQuery = $pdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
$schemaQuery->execute([$database]);
$userQuery = $pdo->prepare('SELECT COUNT(*) FROM mysql.user WHERE user = ? AND host = ?');
$userQuery->execute([$username, 'localhost']);

if ((int) $schemaQuery->fetchColumn() !== 0 || (int) $userQuery->fetchColumn() !== 0) {
    fwrite(STDERR, "La base o la cuenta DEMO ya existe; no se realizo ningun cambio.\n");
    exit(12);
}

$databaseCreated = false;
$userCreated = false;

try {
    $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $databaseCreated = true;

    $quotedPassword = $pdo->quote($demoPassword);
    $pdo->exec("CREATE USER {$account} IDENTIFIED BY {$quotedPassword}");
    $userCreated = true;
    $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER ON `{$database}`.* TO {$account}");

    $demoPdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $username,
        $demoPassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $demoIdentity = $demoPdo->query('SELECT DATABASE(), CURRENT_USER()')->fetch(PDO::FETCH_NUM);
    if (($demoIdentity[0] ?? null) !== $database || ($demoIdentity[1] ?? null) !== 'importsystemgo_demo@localhost') {
        throw new RuntimeException('La identidad efectiva DEMO no coincide.');
    }

    echo "database_created=yes\naccount_created=yes\nidentity_verified=yes\n";
} catch (Throwable) {
    if ($userCreated) {
        $pdo->exec("DROP USER IF EXISTS {$account}");
    }
    if ($databaseCreated) {
        $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
    }
    fwrite(STDERR, "El aprovisionamiento fallo y los recursos nuevos fueron retirados.\n");
    exit(13);
} finally {
    unset($quotedPassword, $demoPassword, $adminPassword, $demoPdo, $pdo);
}
