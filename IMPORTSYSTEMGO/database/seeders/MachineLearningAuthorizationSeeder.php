<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

class MachineLearningAuthorizationSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private array $roles = [
        'administrador-general' => ['ml.forecasts.view', 'ml.models.manage', 'ml.replenishment.decide'],
        'jefe-operaciones' => ['ml.forecasts.view', 'ml.replenishment.decide'],
        'jefe-tienda' => ['ml.forecasts.view'],
        'encargado-compras' => ['ml.forecasts.view', 'ml.replenishment.decide'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('demo.retail_enabled')) {
            throw new \RuntimeException('La autorización ML solo puede sincronizarse en DEMO o pruebas aisladas.');
        }
        $actor = null;
        if (app()->environment('local')) {
            $identity = DB::selectOne('SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name');
            if ($identity->database_name !== config('demo.database') || $identity->account_name !== 'importsystemgo_demo@localhost') {
                throw new \RuntimeException('La sincronización ML requiere importsystemgo_demo@localhost.');
            }
            if (! Schema::hasTable('audit_logs')) {
                throw new \RuntimeException('La sincronización ML requiere auditoría.');
            }
            $actor = User::where('email', 'admin.demo@importsystemgo.example')->where('is_demo', true)->firstOrFail();
        }

        DB::transaction(function () use ($actor): void {
            foreach (collect($this->roles)->flatten()->unique() as $permissionName) {
                $permission = Permission::where('name', $permissionName)->where('guard_name', Permission::GUARD)->first();
                if (! $permission) {
                    $permission = Permission::findOrCreate($permissionName, Permission::GUARD);
                    $this->auditOnce($actor, 'authorization.permission.created', 'permissions', $permission->id, ['name' => $permissionName]);
                }
            }
            foreach ($this->roles as $roleName => $permissionNames) {
                $role = Role::whereNull('branch_id')->where('name', $roleName)->where('guard_name', Permission::GUARD)->firstOrFail();
                $before = $role->permissions()->whereIn('permissions.name', $permissionNames)->pluck('permissions.name')->sort()->values()->all();
                $role->givePermissionTo($permissionNames);
                $after = $role->permissions()->whereIn('permissions.name', $permissionNames)->pluck('permissions.name')->sort()->values()->all();
                if ($before !== $after) {
                    $this->auditOnce($actor, 'authorization.role_ml_permissions.granted', 'roles', $role->id, ['permissions' => $after]);
                }
            }
        }, 3);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @param array<string, mixed> $after */
    private function auditOnce(?User $actor, string $action, string $type, int $id, array $after): void
    {
        if (! $actor || DB::table('audit_logs')->where('action', $action)->where('resource_type', $type)->where('resource_id', $id)
            ->where('after_values', json_encode($after, JSON_THROW_ON_ERROR))->exists()) {
            return;
        }
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actor->id,
            'branch_id' => null,
            'action' => $action,
            'resource_type' => $type,
            'resource_id' => $id,
            'before_values' => null,
            'after_values' => json_encode($after, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
        ]);
    }
}
