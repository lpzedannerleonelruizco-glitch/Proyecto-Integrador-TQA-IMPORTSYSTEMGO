<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = 'importsystemgo';
$expectedAccount = 'importsystemgo_app@localhost';
$connection = DB::connection();
$identity = $connection->selectOne(
    'SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name, '
    .'@@version AS server_version, @@sql_mode AS sql_mode'
);

if ($identity->database_name !== $database || $identity->account_name !== $expectedAccount) {
    throw new RuntimeException('La identidad efectiva de MySQL no corresponde a la base principal esperada.');
}

$expectedTables = [
    'branch_user_memberships',
    'branches',
    'cache',
    'cache_locks',
    'employees',
    'failed_jobs',
    'global_user_roles',
    'job_batches',
    'jobs',
    'migrations',
    'model_has_permissions',
    'model_has_roles',
    'password_reset_tokens',
    'permissions',
    'role_has_permissions',
    'roles',
    'sessions',
    'users',
    'warehouses',
];

$tables = $connection->table('information_schema.tables')
    ->where('table_schema', $database)
    ->where('table_type', 'BASE TABLE')
    ->selectRaw('table_name AS resolved_table_name')
    ->pluck('resolved_table_name')
    ->map(static fn (mixed $value): string => (string) $value)
    ->sort()
    ->values()
    ->all();

sort($expectedTables);

$expectedMigrations = [
    '0001_01_01_000000_create_users_table',
    '0001_01_01_000001_create_cache_table',
    '0001_01_01_000002_create_jobs_table',
    '2026_10_21_020001_create_branches_table',
    '2026_10_21_020002_create_warehouses_table',
    '2026_10_21_020003_add_security_columns_to_users_table',
    '2026_10_21_020004_create_employees_table',
    '2026_10_21_020005_create_branch_user_memberships_table',
    '2026_10_21_020006_create_permission_tables',
    '2026_10_21_020007_create_global_user_roles_table',
];

$appliedMigrations = $connection->table('migrations')
    ->orderBy('migration')
    ->pluck('migration')
    ->map(static fn (mixed $value): string => (string) $value)
    ->all();

$migrationFiles = collect(glob(database_path('migrations/*.php')) ?: [])
    ->map(static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
    ->sort()
    ->values()
    ->all();

$pendingMigrations = array_values(array_diff($migrationFiles, $appliedMigrations));

$expectedRelations = [
    'branch_user_memberships.branch_id->branches.id',
    'branch_user_memberships.ended_by->users.id',
    'branch_user_memberships.granted_by->users.id',
    'branch_user_memberships.user_id->users.id',
    'employees.home_branch_id->branches.id',
    'employees.user_id->users.id',
    'global_user_roles.ended_by->users.id',
    'global_user_roles.granted_by->users.id',
    'global_user_roles.role_id->roles.id',
    'global_user_roles.user_id->users.id',
    'model_has_permissions.branch_id->branches.id',
    'model_has_permissions.permission_id->permissions.id',
    'model_has_roles.branch_id->branches.id',
    'model_has_roles.role_id->roles.id',
    'role_has_permissions.permission_id->permissions.id',
    'role_has_permissions.role_id->roles.id',
    'roles.branch_id->branches.id',
    'users.primary_branch_id->branches.id',
    'warehouses.branch_id->branches.id',
];

$relations = $connection->table('information_schema.key_column_usage')
    ->where('constraint_schema', $database)
    ->whereNotNull('referenced_table_name')
    ->selectRaw("CONCAT(table_name, '.', column_name, '->', referenced_table_name, '.', referenced_column_name) AS resolved_relation")
    ->pluck('resolved_relation')
    ->map(static fn (mixed $value): string => (string) $value)
    ->sort()
    ->values()
    ->all();

sort($expectedRelations);

$expectedChecks = [
    'branch_memberships_valid_period',
    'employees_valid_employment_dates',
    'global_roles_valid_period',
    'roles_valid_scope',
];

$checks = $connection->table('information_schema.table_constraints')
    ->where('constraint_schema', $database)
    ->where('constraint_type', 'CHECK')
    ->where('enforced', 'YES')
    ->selectRaw('constraint_name AS resolved_check_name')
    ->pluck('resolved_check_name')
    ->map(static fn (mixed $value): string => (string) $value)
    ->sort()
    ->values()
    ->all();

sort($expectedChecks);

$expectedGeneratedColumns = ['active_assignment', 'active_membership', 'scope_branch_id'];
$generatedColumns = $connection->table('information_schema.columns')
    ->where('table_schema', $database)
    ->where('extra', 'like', '%STORED GENERATED%')
    ->selectRaw('column_name AS resolved_column_name')
    ->pluck('resolved_column_name')
    ->map(static fn (mixed $value): string => (string) $value)
    ->sort()
    ->values()
    ->all();

sort($expectedGeneratedColumns);

$expectedIndexStructures = [
    'branch_user_memberships|branch_memberships_branch_period_index' => 'non_unique|branch_id,starts_at,ends_at',
    'branch_user_memberships|branch_memberships_one_open_unique' => 'unique|user_id,branch_id,active_membership',
    'branch_user_memberships|branch_memberships_user_period_index' => 'non_unique|user_id,branch_id,starts_at,ends_at',
    'branches|branches_code_unique' => 'unique|code',
    'branches|branches_is_active_name_index' => 'non_unique|is_active,name',
    'branches|branches_type_is_active_index' => 'non_unique|type,is_active',
    'employees|employees_employee_code_unique' => 'unique|employee_code',
    'employees|employees_home_branch_id_status_index' => 'non_unique|home_branch_id,status',
    'employees|employees_status_last_name_index' => 'non_unique|status,last_name',
    'employees|employees_user_id_unique' => 'unique|user_id',
    'global_user_roles|global_roles_one_open_unique' => 'unique|user_id,role_id,active_assignment',
    'global_user_roles|global_roles_role_period_index' => 'non_unique|role_id,starts_at,ends_at',
    'global_user_roles|global_roles_user_period_index' => 'non_unique|user_id,starts_at,ends_at',
    'model_has_permissions|model_permissions_model_index' => 'non_unique|model_id,model_type',
    'model_has_permissions|PRIMARY' => 'unique|branch_id,permission_id,model_id,model_type',
    'model_has_roles|model_roles_model_index' => 'non_unique|model_id,model_type',
    'model_has_roles|PRIMARY' => 'unique|branch_id,role_id,model_id,model_type',
    'permissions|permissions_name_guard_name_unique' => 'unique|name,guard_name',
    'role_has_permissions|PRIMARY' => 'unique|permission_id,role_id',
    'roles|roles_branch_id_scope_type_index' => 'non_unique|branch_id,scope_type',
    'roles|roles_scope_name_guard_unique' => 'unique|scope_branch_id,name,guard_name',
    'users|users_status_index' => 'non_unique|status',
    'warehouses|warehouses_branch_id_is_active_index' => 'non_unique|branch_id,is_active',
    'warehouses|warehouses_branch_id_type_is_active_index' => 'non_unique|branch_id,type,is_active',
    'warehouses|warehouses_code_unique' => 'unique|code',
];

$indexRows = $connection->table('information_schema.statistics')
    ->where('table_schema', $database)
    ->selectRaw('table_name AS resolved_table_name')
    ->selectRaw('index_name AS resolved_index_name')
    ->selectRaw('non_unique AS resolved_non_unique')
    ->selectRaw('seq_in_index AS resolved_position')
    ->selectRaw('column_name AS resolved_column_name')
    ->get();

$actualIndexStructures = $indexRows
    ->groupBy(static fn (object $row): string => $row->resolved_table_name.'|'.$row->resolved_index_name)
    ->map(static function ($rows): string {
        $orderedRows = $rows->sortBy('resolved_position')->values();
        $uniqueness = (int) $orderedRows->first()->resolved_non_unique === 0 ? 'unique' : 'non_unique';
        $columns = $orderedRows->pluck('resolved_column_name')->implode(',');

        return $uniqueness.'|'.$columns;
    })
    ->all();

$invalidIndexes = array_filter(
    $expectedIndexStructures,
    static fn (string $structure, string $identifier): bool => ($actualIndexStructures[$identifier] ?? null) !== $structure,
    ARRAY_FILTER_USE_BOTH,
);
$nonInnoDbTables = $connection->table('information_schema.tables')
    ->where('table_schema', $database)
    ->where('table_type', 'BASE TABLE')
    ->where('engine', '<>', 'InnoDB')
    ->count();

$assertions = [
    'tables' => $tables === $expectedTables,
    'migrations' => $appliedMigrations === $expectedMigrations,
    'pending_migrations' => $pendingMigrations === [],
    'foreign_keys' => $relations === $expectedRelations,
    'checks' => $checks === $expectedChecks,
    'generated_columns' => $generatedColumns === $expectedGeneratedColumns,
    'indexes' => $invalidIndexes === [],
    'engines' => $nonInnoDbTables === 0,
    'strict_mode' => str_contains((string) $identity->sql_mode, 'STRICT_'),
];

foreach ($assertions as $name => $passed) {
    if (! $passed) {
        throw new RuntimeException("Fallo la verificacion de solo lectura: {$name}.");
    }
}

echo 'database='.$identity->database_name.PHP_EOL;
echo 'account='.$identity->account_name.PHP_EOL;
echo 'server_version='.$identity->server_version.PHP_EOL;
echo 'tables='.count($tables).PHP_EOL;
echo 'migrations='.count($appliedMigrations).PHP_EOL;
echo 'pending_migrations='.count($pendingMigrations).PHP_EOL;
echo 'foreign_keys='.count($relations).PHP_EOL;
echo 'enforced_checks='.count($checks).PHP_EOL;
echo 'stored_generated_columns='.count($generatedColumns).PHP_EOL;
echo 'required_index_structures='.count($expectedIndexStructures).PHP_EOL;
echo 'invalid_index_structures='.count($invalidIndexes).PHP_EOL;
echo 'non_innodb_tables='.$nonInnoDbTables.PHP_EOL;
echo 'strict_mode=yes'.PHP_EOL;
echo 'laravel_connection=ok'.PHP_EOL;
