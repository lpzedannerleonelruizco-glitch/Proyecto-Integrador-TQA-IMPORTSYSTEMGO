<?php

namespace App\Services\Authorization;

use App\Models\Branch;
use App\Models\User;
use App\Support\AuthorizationCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationalAreaAccessService
{
    public function isTerritoriallyBound(User $user): bool
    {
        if (! $this->hasActiveTerritorialRole($user)) {
            return false;
        }

        return ! $this->hasActiveUnrestrictedGlobalRole($user);
    }

    public function allowsBranch(User $user, Branch $branch): bool
    {
        if (! $this->isTerritoriallyBound($user)) {
            return true;
        }

        if (! Schema::hasTable('operational_area_assignments')
            || ! Schema::hasColumn('branches', 'operational_area_id')
            || $branch->operational_area_id === null) {
            return false;
        }

        return DB::table('operational_area_assignments')
            ->where('user_id', $user->getKey())
            ->where('operational_area_id', $branch->operational_area_id)
            ->exists();
    }

    public function constrainBranches(Builder $query, User $user): Builder
    {
        if (! $this->isTerritoriallyBound($user)) {
            return $query;
        }

        if (! Schema::hasTable('operational_area_assignments')
            || ! Schema::hasColumn('branches', 'operational_area_id')) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereNotNull('branches.operational_area_id')
            ->whereIn('branches.operational_area_id', function ($subquery) use ($user) {
                $subquery->select('operational_area_id')
                    ->from('operational_area_assignments')
                    ->where('user_id', $user->getKey());
            });
    }

    private function hasActiveTerritorialRole(User $user): bool
    {
        if (AuthorizationCatalog::territorialRoles() === []) {
            return false;
        }

        return DB::table('global_user_roles as assignment')
            ->join('roles as role', 'role.id', '=', 'assignment.role_id')
            ->where('assignment.user_id', $user->getKey())
            ->whereNull('assignment.ended_by')
            ->where('assignment.starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('assignment.ends_at')->orWhere('assignment.ends_at', '>', now()))
            ->whereIn('role.name', AuthorizationCatalog::territorialRoles())
            ->whereNull('role.branch_id')
            ->where('role.scope_type', 'global')
            ->where('role.guard_name', 'web')
            ->exists();
    }

    private function hasActiveUnrestrictedGlobalRole(User $user): bool
    {
        return DB::table('global_user_roles as assignment')
            ->join('roles as role', 'role.id', '=', 'assignment.role_id')
            ->where('assignment.user_id', $user->getKey())
            ->whereNull('assignment.ended_by')
            ->where('assignment.starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('assignment.ends_at')->orWhere('assignment.ends_at', '>', now()))
            ->whereNotIn('role.name', AuthorizationCatalog::territorialRoles())
            ->whereNull('role.branch_id')
            ->where('role.scope_type', 'global')
            ->where('role.guard_name', 'web')
            ->exists();
    }
}
