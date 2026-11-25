<?php

declare(strict_types=1);

$database = 'importsystemgo_demo';
$expectedAccount = 'importsystemgo_demo@localhost';
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');

if ($username !== 'importsystemgo_demo' || ! is_string($password) || $password === '') {
    fwrite(STDERR, "Credenciales DEMO invalidas.\n");
    exit(20);
}

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$identity = $pdo->query('SELECT DATABASE(), CURRENT_USER()')->fetch(PDO::FETCH_NUM);
if (($identity[0] ?? null) !== $database || ($identity[1] ?? null) !== $expectedAccount) {
    fwrite(STDERR, "Identidad MySQL DEMO inesperada.\n");
    exit(21);
}

$tablesQuery = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'");
$tablesQuery->execute([$database]);
$foreignKeysQuery = $pdo->prepare('SELECT COUNT(*) FROM information_schema.key_column_usage WHERE constraint_schema = ? AND referenced_table_name IS NOT NULL');
$foreignKeysQuery->execute([$database]);
$checksQuery = $pdo->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = ? AND constraint_type = 'CHECK' AND enforced = 'YES'");
$checksQuery->execute([$database]);
$generatedQuery = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND extra LIKE '%STORED GENERATED%'");
$generatedQuery->execute([$database]);
$migrationCount = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'migrations'")->fetchColumn()
    ? (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn()
    : 0;
$grants = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
$hasOnlyUsageGlobally = count(array_filter($grants, static fn (string $grant): bool => str_contains($grant, ' ON *.* '))) === 1
    && count(array_filter($grants, static fn (string $grant): bool => str_contains($grant, 'GRANT USAGE ON *.*'))) === 1;
$hasDemoGrant = count(array_filter($grants, static fn (string $grant): bool => str_contains($grant, 'ON `importsystemgo_demo`.*'))) === 1;
$hasOtherSchemaGrant = count(array_filter($grants, static fn (string $grant): bool => preg_match('/ ON `(?!importsystemgo_demo`)[^`]+`\.\*/', $grant) === 1)) > 0;
$primaryDenied = false;
try {
    $pdo->query('SELECT COUNT(*) FROM importsystemgo.users')->fetchColumn();
} catch (PDOException) {
    $primaryDenied = true;
}

echo 'identity_verified=yes'.PHP_EOL;
echo 'tables='.(int) $tablesQuery->fetchColumn().PHP_EOL;
echo 'migrations='.$migrationCount.PHP_EOL;
echo 'foreign_keys='.(int) $foreignKeysQuery->fetchColumn().PHP_EOL;
echo 'enforced_checks='.(int) $checksQuery->fetchColumn().PHP_EOL;
echo 'stored_generated_columns='.(int) $generatedQuery->fetchColumn().PHP_EOL;
echo 'users='.(int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn().PHP_EOL;
echo 'branches='.(int) $pdo->query('SELECT COUNT(*) FROM branches')->fetchColumn().PHP_EOL;
echo 'employees='.(int) $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn().PHP_EOL;
echo 'roles='.(int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn().PHP_EOL;
echo 'permissions='.(int) $pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn().PHP_EOL;
echo 'global_privileges_only_usage='.($hasOnlyUsageGlobally ? 'yes' : 'no').PHP_EOL;
echo 'demo_schema_grant='.($hasDemoGrant ? 'yes' : 'no').PHP_EOL;
echo 'other_schema_grant='.($hasOtherSchemaGrant ? 'yes' : 'no').PHP_EOL;
echo 'primary_database_access_denied='.($primaryDenied ? 'yes' : 'no').PHP_EOL;

unset($password, $pdo);
