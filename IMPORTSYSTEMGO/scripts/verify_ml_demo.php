<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$identity = DB::selectOne('SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name');
if ($identity->database_name !== 'importsystemgo_demo' || $identity->account_name !== 'importsystemgo_demo@localhost') {
    fwrite(STDERR, "Identidad DEMO inesperada.\n");
    exit(41);
}
$requiredPermissions = ['ml.forecasts.view', 'ml.models.manage', 'ml.replenishment.decide'];
$pendingInventory = [
    '2026_10_22_030001_create_backup_and_database_audit_tables',
    '2026_10_23_010001_expand_inventory_catalog_and_operations',
    '2026_10_23_020001_add_inventory_database_triggers',
];
$model = DB::table('ml_model_versions')->where('status', 'active')->orderByDesc('id')->first();
if (! $model) {
    fwrite(STDERR, "No existe modelo ML activo.\n");
    exit(42);
}
$artifact = storage_path('app/private/'.$model->artifact_path);
$checks = [
    'identity_verified' => true,
    'tables' => (int) DB::scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"),
    'migrations' => (int) DB::table('migrations')->count(),
    'ml_migration_applied' => DB::table('migrations')->where('migration', '2026_11_18_010001_create_machine_learning_forecast_tables')->exists(),
    'inventory_migrations_still_pending' => DB::table('migrations')->whereIn('migration', $pendingInventory)->count() === 0,
    'permissions' => (int) DB::table('permissions')->count(),
    'ml_permissions' => (int) DB::table('permissions')->whereIn('name', $requiredPermissions)->count(),
    'observations' => (int) DB::table('ml_demand_observations')->where('source', 'synthetic_demo')->where('is_demo', true)->count(),
    'models' => (int) DB::table('ml_model_versions')->count(),
    'active_models' => (int) DB::table('ml_model_versions')->where('status', 'active')->count(),
    'forecasts' => (int) DB::table('demand_forecasts')->count(),
    'forecast_branches' => (int) DB::table('demand_forecasts')->distinct()->count('branch_id'),
    'forecast_products' => (int) DB::table('demand_forecasts')->distinct()->count('product_id'),
    'recommendations' => (int) DB::table('replenishment_recommendations')->count(),
    'pending_recommendations' => (int) DB::table('replenishment_recommendations')->where('status', 'pending')->count(),
    'sales' => (int) DB::table('sales')->count(),
    'inventory_balances' => (int) DB::table('inventory_balances')->count(),
    'inventory_movements' => (int) DB::table('inventory_movements')->count(),
    'artifact_exists' => is_file($artifact),
    'artifact_sha256_verified' => is_file($artifact) && hash_file('sha256', $artifact) === $model->artifact_sha256,
    'model_status' => $model->status,
    'model_metrics' => json_decode($model->metrics, true, flags: JSON_THROW_ON_ERROR),
];
$roleExpectations = [
    'administrador-general' => 3,
    'jefe-operaciones' => 2,
    'jefe-tienda' => 1,
    'encargado-compras' => 2,
    'vendedor' => 0,
    'cajero' => 0,
];
foreach ($roleExpectations as $role => $expected) {
    $checks['role_'.$role.'_ml_permissions'] = (int) DB::table('roles')
        ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'roles.id')
        ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
        ->whereNull('roles.branch_id')->where('roles.name', $role)->whereIn('permissions.name', $requiredPermissions)
        ->distinct()->count('permissions.id');
    if ($checks['role_'.$role.'_ml_permissions'] !== $expected) {
        fwrite(STDERR, "Permisos ML inesperados para {$role}.\n");
        exit(43);
    }
}
foreach ($checks as $name => $value) {
    echo $name.'='.(is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (is_bool($value) ? ($value ? 'yes' : 'no') : $value)).PHP_EOL;
}

$requiredTruths = ['identity_verified', 'ml_migration_applied', 'inventory_migrations_still_pending', 'artifact_exists', 'artifact_sha256_verified'];
foreach ($requiredTruths as $name) {
    if ($checks[$name] !== true) {
        exit(44);
    }
}
if ($checks['observations'] !== 45360 || $checks['forecasts'] !== 84 || $checks['recommendations'] !== 84 || $checks['ml_permissions'] !== 3) {
    exit(45);
}

try {
    DB::selectOne('SELECT COUNT(*) AS aggregate FROM importsystemgo.users');
    fwrite(STDERR, "La cuenta DEMO obtuvo acceso inesperado a la base principal.\n");
    exit(46);
} catch (Throwable) {
    echo "primary_database_access_denied=yes\n";
}
