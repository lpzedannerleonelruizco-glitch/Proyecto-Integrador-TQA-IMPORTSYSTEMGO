<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\GlobalAuthorizationService;
use App\Support\AuthorizationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RoleAdministrationService
{
    public function __construct(private readonly GlobalAuthorizationService $authorization) {}

    public function authorize(User $actor): void
    {
        abort_unless($this->authorization->allows($actor, 'security.roles.manage'), 403);
    }

    public function save(User $actor, array $data, ?int $id = null): Role
    {
        abort_unless(Schema::hasTable('audit_logs'), 503, 'La auditoría requiere aplicar la migración pendiente con autorización.');

        return DB::transaction(function () use ($actor, $data, $id) {
            $this->authorize($actor);
            $permissions = array_values(array_unique($data['permissions']));
            $delegable = $this->authorization->permissionsFor($actor);

            if (array_diff($permissions, $delegable) !== []) {
                throw ValidationException::withMessages([
                    'permissions' => 'No puedes delegar permisos globales que no tienes asignados.',
                ]);
            }

            if ($id !== null) {
                $role = Role::query()->lockForUpdate()->findOrFail($id);
                $this->ensureCustomRole($role);
                if ($data['name'] !== $role->name && array_key_exists($data['name'], AuthorizationCatalog::roles())) {
                    throw ValidationException::withMessages(['name' => 'Ese nombre está reservado para un rol del sistema.']);
                }
                $before = $this->snapshot($role);
                $branchId = $role->branch_id;
                $role->forceFill(['name' => $data['name']])->save();
            } else {
                if (array_key_exists($data['name'], AuthorizationCatalog::roles())) {
                    throw ValidationException::withMessages(['name' => 'Ese nombre está reservado para un rol del sistema.']);
                }
                $scopeType = $data['scope_type'];
                $branchId = $scopeType === Role::SCOPE_BRANCH && ! empty($data['branch_id'])
                    ? (int) $data['branch_id']
                    : null;
                $before = null;
                $role = (new Role)->forceFill([
                    'name' => $data['name'],
                    'guard_name' => Permission::GUARD,
                    'scope_type' => $scopeType,
                    'branch_id' => $branchId,
                ]);
                $role->save();
            }

            $role->syncPermissions($permissions);
            $after = $this->snapshot($role->fresh());
            DB::table('audit_logs')->insert([
                'actor_user_id' => $actor->id,
                'branch_id' => $branchId,
                'action' => $id === null ? 'roles.created' : 'roles.updated',
                'resource_type' => 'roles',
                'resource_id' => $role->id,
                'before_values' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
                'after_values' => json_encode($after, JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
            ]);

            return $role;
        }, 3);
    }

    public function isSystemRole(Role $role): bool
    {
        return array_key_exists($role->name, AuthorizationCatalog::roles());
    }

    private function ensureCustomRole(Role $role): void
    {
        abort_unless($role->guard_name === Permission::GUARD && ! $this->isSystemRole($role), 403);
    }

    /** @return array{name: string, scope_type: string, branch_id: int|null, permissions: list<string>} */
    private function snapshot(Role $role): array
    {
        return [
            'name' => $role->name,
            'scope_type' => $role->scope_type,
            'branch_id' => $role->branch_id === null ? null : (int) $role->branch_id,
            'permissions' => $role->permissions()->where('permissions.guard_name', Permission::GUARD)
                ->orderBy('permissions.name')->pluck('permissions.name')->all(),
        ];
    }
}
