<?php

declare(strict_types=1);

$database = 'importsystemgo_testing';
$expectedAccount = 'importsystemgo_test@localhost';
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');

if ($username !== 'importsystemgo_test' || ! is_string($password) || $password === '') {
    fwrite(STDERR, "Credenciales de inspeccion invalidas.\n");
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
    fwrite(STDERR, "Identidad MySQL de inspeccion inesperada.\n");
    exit(21);
}

$expectedTables = [
    'audit_logs',
    'branch_user_memberships', 'branches', 'cache', 'cache_locks', 'employees',
    'failed_jobs', 'global_user_roles', 'job_batches', 'jobs', 'migrations',
    'model_has_permissions', 'model_has_roles', 'password_reset_tokens', 'permissions',
    'operational_area_assignments', 'operational_areas', 'role_has_permissions',
    'roles', 'sessions', 'users', 'warehouses',
];

$tablesQuery = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'");
$tablesQuery->execute([$database]);
$tables = $tablesQuery->fetchAll(PDO::FETCH_COLUMN);

$foreignKeysQuery = $pdo->prepare('SELECT COUNT(*) FROM information_schema.key_column_usage WHERE constraint_schema = ? AND referenced_table_name IS NOT NULL');
$foreignKeysQuery->execute([$database]);

$checksQuery = $pdo->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = ? AND constraint_type = 'CHECK' AND enforced = 'YES'");
$checksQuery->execute([$database]);

$generatedQuery = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND extra LIKE '%STORED GENERATED%'");
$generatedQuery->execute([$database]);

$migrationCount = (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
$grants = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
$hasOnlyUsageGlobally = count(array_filter($grants, static fn (string $grant): bool => str_contains($grant, ' ON *.* '))) === 1
    && count(array_filter($grants, static fn (string $grant): bool => str_contains($grant, 'GRANT USAGE ON *.*'))) === 1;
$hasTestingGrant = count(array_filter($grants, static fn (string $grant): bool => str_contains($grant, 'ON `importsystemgo_testing`.*'))) === 1;
$hasOtherSchemaGrant = count(array_filter($grants, static fn (string $grant): bool => preg_match('/ ON `(?!importsystemgo_testing`)[^`]+`\.\*/', $grant) === 1)) > 0;

$primaryDatabaseDenied = false;

try {
    $pdo->query('SELECT COUNT(*) FROM importsystemgo.users')->fetchColumn();
} catch (PDOException) {
    $primaryDatabaseDenied = true;
}

sort($tables);
sort($expectedTables);

echo 'identity_verified=yes'.PHP_EOL;
echo 'tables='.count($tables).PHP_EOL;
echo 'expected_tables_match='.($tables === $expectedTables ? 'yes' : 'no').PHP_EOL;
echo 'migrations='.$migrationCount.PHP_EOL;
echo 'foreign_keys='.(int) $foreignKeysQuery->fetchColumn().PHP_EOL;
echo 'enforced_checks='.(int) $checksQuery->fetchColumn().PHP_EOL;
echo 'stored_generated_columns='.(int) $generatedQuery->fetchColumn().PHP_EOL;
echo 'global_privileges_only_usage='.($hasOnlyUsageGlobally ? 'yes' : 'no').PHP_EOL;
echo 'testing_schema_grant='.($hasTestingGrant ? 'yes' : 'no').PHP_EOL;
echo 'other_schema_grant='.($hasOtherSchemaGrant ? 'yes' : 'no').PHP_EOL;
echo 'primary_database_access_denied='.($primaryDatabaseDenied ? 'yes' : 'no').PHP_EOL;

unset($password, $pdo);
