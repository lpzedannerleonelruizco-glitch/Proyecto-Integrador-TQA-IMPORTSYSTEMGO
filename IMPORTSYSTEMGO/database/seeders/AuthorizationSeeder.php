<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AuthorizationCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        setPermissionsTeamId(null);
        $auditActor = null;
        $isDemoRuntime = app()->environment('local')
            && config('demo.retail_enabled')
            && DB::connection()->getDatabaseName() === config('demo.database');

        if ($isDemoRuntime) {
            if (! Schema::hasTable('audit_logs')) {
                throw new \RuntimeException('La sincronización DEMO requiere la tabla de auditoría.');
            }

            $auditActor = User::query()
                ->where('email', 'admin.demo@importsystemgo.example')
                ->where('is_demo', true)
                ->where('status', User::STATUS_ACTIVE)
                ->firstOrFail();
        }

        DB::transaction(function () use ($auditActor): void {
            foreach (AuthorizationCatalog::permissions() as $permissionName) {
                $permission = Permission::query()
                    ->where('name', $permissionName)
                    ->where('guard_name', Permission::GUARD)
                    ->first();
                if (! $permission) {
                    $permission = Permission::findOrCreate($permissionName, Permission::GUARD);
                    if ($auditActor) {
                        DB::table('audit_logs')->insert([
                            'actor_user_id' => $auditActor->id,
                            'branch_id' => null,
                            'action' => 'authorization.permission.created',
                            'resource_type' => 'permissions',
                            'resource_id' => $permission->id,
                            'before_values' => null,
                            'after_values' => json_encode(['name' => $permissionName, 'guard_name' => Permission::GUARD], JSON_THROW_ON_ERROR),
                            'occurred_at' => now(),
                        ]);
                    }
                }
            }

            foreach (AuthorizationCatalog::roles() as $roleName => $definition) {
                $role = Role::query()
                    ->whereNull('branch_id')
                    ->where('name', $roleName)
                    ->where('guard_name', Permission::GUARD)
                    ->first();
                $before = $role ? $this->snapshot($role) : null;

                if (! $role) {
                    $role = Role::create([
                        'branch_id' => null,
                        'name' => $roleName,
                        'guard_name' => Permission::GUARD,
                        'scope_type' => $definition['scope'],
                    ]);
                } else {
                    $role->forceFill(['scope_type' => $definition['scope']])->save();
                }

                $role->syncPermissions($definition['permissions']);
                $after = $this->snapshot($role->fresh());
                if ($auditActor && $before !== $after) {
                    DB::table('audit_logs')->insert([
                        'actor_user_id' => $auditActor->id,
                        'branch_id' => $role->branch_id,
                        'action' => 'authorization.role_permissions.synchronized',
                        'resource_type' => 'roles',
                        'resource_id' => $role->id,
                        'before_values' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
                        'after_values' => json_encode($after, JSON_THROW_ON_ERROR),
                        'occurred_at' => now(),
                    ]);
                }
            }
        }, 3);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return array{name: string, scope_type: string, permissions: list<string>} */
    private function snapshot(Role $role): array
    {
        return [
            'name' => $role->name,
            'scope_type' => $role->scope_type,
            'permissions' => $role->permissions()->where('permissions.guard_name', Permission::GUARD)
                ->orderBy('permissions.name')->pluck('permissions.name')->all(),
        ];
    }
}
