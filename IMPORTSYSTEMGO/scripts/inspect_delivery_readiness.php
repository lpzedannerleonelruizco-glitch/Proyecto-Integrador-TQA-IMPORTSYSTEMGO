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
$connection = DB::connection();
$identity = $connection->selectOne('SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name');
if ($identity->database_name !== 'importsystemgo' || $identity->account_name !== 'importsystemgo_app@localhost') {
    throw new RuntimeException('Identidad efectiva inesperada; inspección detenida.');
}
$applied = $connection->table('migrations')->orderBy('migration')->pluck('migration')->all();
$files = array_map(static fn (string $path): string => basename($path, '.php'), glob(dirname(__DIR__).'/database/migrations/*.php'));
$pending = array_values(array_diff($files, $applied));
echo json_encode([
    'database' => $identity->database_name,
    'account' => $identity->account_name,
    'tables' => $connection->table('information_schema.tables')->where('table_schema', 'importsystemgo')->where('table_type', 'BASE TABLE')->count(),
    'applied_migrations' => count($applied),
    'pending_migrations' => $pending,
    'users' => $connection->table('users')->count(),
    'branches' => $connection->table('branches')->count(),
    'roles' => $connection->table('roles')->count(),
    'permissions' => $connection->table('permissions')->count(),
    'active_global_administrators' => $connection->table('global_user_roles as assignment')
        ->join('roles', 'roles.id', '=', 'assignment.role_id')
        ->join('users', 'users.id', '=', 'assignment.user_id')
        ->where('roles.name', 'administrador-general')->where('users.status', 'active')
        ->whereNull('assignment.ended_by')->where('assignment.starts_at', '<=', now())
        ->where(fn ($query) => $query->whereNull('assignment.ends_at')->orWhere('assignment.ends_at', '>', now()))->distinct()->count('users.id'),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
