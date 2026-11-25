<?php

namespace App\Services\Authorization;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AuthorizationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GlobalAuthorizationService
{
    public function allows(User $user, string $permission, ?Branch $branch = null): bool
    {
        if (! User::query()->whereKey($user->getKey())->where('status', User::STATUS_ACTIVE)->exists()) {
            return false;
        }

        $now = now();
        $grants = DB::table('global_user_roles as assignment')
            ->join('roles as role', 'role.id', '=', 'assignment.role_id')
            ->join('role_has_permissions as role_permission', 'role_permission.role_id', '=', 'role.id')
            ->join('permissions as permission', 'permission.id', '=', 'role_permission.permission_id')
            ->where('assignment.user_id', $user->getKey())
            ->whereNull('assignment.ended_by')
            ->where('assignment.starts_at', '<=', $now)
            ->where(fn ($query) => $query->whereNull('assignment.ends_at')->orWhere('assignment.ends_at', '>', $now))
            ->whereNull('role.branch_id')
            ->where('role.scope_type', Role::SCOPE_GLOBAL)
            ->where('role.guard_name', Permission::GUARD)
            ->where('permission.guard_name', Permission::GUARD)
            ->where('permission.name', $permission);

        $territorialRoles = AuthorizationCatalog::territorialRoles();
        if ((clone $grants)->whereNotIn('role.name', $territorialRoles)->exists()) {
            return true;
        }

        if ($branch === null) {
            return (clone $grants)->whereIn('role.name', $territorialRoles)->exists();
        }

        if (! $branch->is_active
            || $branch->operational_area_id === null
            || ! Schema::hasTable('operational_area_assignments')
            || ! Schema::hasColumn('branches', 'operational_area_id')) {
            return false;
        }

        return (clone $grants)->whereIn('role.name', $territorialRoles)
            ->whereExists(function ($query) use ($user, $branch) {
                $query->selectRaw('1')
                    ->from('operational_area_assignments')
                    ->whereColumn('operational_area_assignments.user_id', 'assignment.user_id')
                    ->where('operational_area_assignments.user_id', $user->getKey())
                    ->where('operational_area_assignments.operational_area_id', $branch->operational_area_id);
            })
            ->exists();
    }

    /** @return list<string> */
    public function permissionsFor(User $user): array
    {
        if (! User::query()->whereKey($user->getKey())->where('status', User::STATUS_ACTIVE)->exists()) {
            return [];
        }

        $now = now();

        return DB::table('global_user_roles as assignment')
            ->join('roles as role', 'role.id', '=', 'assignment.role_id')
            ->join('role_has_permissions as role_permission', 'role_permission.role_id', '=', 'role.id')
            ->join('permissions as permission', 'permission.id', '=', 'role_permission.permission_id')
            ->where('assignment.user_id', $user->getKey())
            ->whereNull('assignment.ended_by')
            ->where('assignment.starts_at', '<=', $now)
            ->where(fn ($query) => $query->whereNull('assignment.ends_at')->orWhere('assignment.ends_at', '>', $now))
            ->whereNull('role.branch_id')
            ->where('role.scope_type', Role::SCOPE_GLOBAL)
            ->where('role.guard_name', Permission::GUARD)
            ->where('permission.guard_name', Permission::GUARD)
            ->whereNotIn('role.name', AuthorizationCatalog::territorialRoles())
            ->distinct()
            ->orderBy('permission.name')
            ->pluck('permission.name')
            ->all();
    }
}
