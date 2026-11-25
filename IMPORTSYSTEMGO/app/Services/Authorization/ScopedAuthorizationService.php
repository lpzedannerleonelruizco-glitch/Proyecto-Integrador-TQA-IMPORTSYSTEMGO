<?php

namespace App\Services\Authorization;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ScopedAuthorizationService
{
    public function __construct(
        private readonly BranchContext $context,
        private readonly GlobalAuthorizationService $global,
    ) {}

    public function allows(User $user, string $permission, ?Branch $branch = null): bool
    {
        $currentBranch = $this->context->current();
        $branch ??= $currentBranch;

        if (! $user->fresh()?->isActive() || ! $branch?->fresh()?->is_active) {
            return false;
        }

        if ($this->global->allows($user, $permission, $branch)) {
            return true;
        }

        if (! $currentBranch?->fresh()?->is_active) {
            return false;
        }

        $hasMembership = BranchUserMembership::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($currentBranch)
            ->activeAt(now())
            ->exists();

        if (! $hasMembership) {
            return false;
        }

        $previousBranchId = getPermissionsTeamId();
        $assignmentBranch = $permission === 'reports.consolidated.view'
            ? $currentBranch
            : ($currentBranch->is($branch) ? $branch : null);

        try {
            if ($assignmentBranch === null) {
                return false;
            }
            setPermissionsTeamId($assignmentBranch->getKey());
            $user->unsetRelation('roles')->unsetRelation('permissions');

            // Read current role grants rather than cached permissions or direct user grants.
            return DB::table('model_has_roles as assignment')
                ->join('roles as role', 'role.id', '=', 'assignment.role_id')
                ->join('role_has_permissions as grant', 'grant.role_id', '=', 'role.id')
                ->join('permissions as permission', 'permission.id', '=', 'grant.permission_id')
                ->where('assignment.model_id', $user->getKey())
                ->where('assignment.model_type', $user->getMorphClass())
                ->where('assignment.branch_id', $assignmentBranch->getKey())
                ->where('role.scope_type', Role::SCOPE_BRANCH)
                ->where(fn ($query) => $query->whereNull('role.branch_id')->orWhere('role.branch_id', $assignmentBranch->getKey()))
                ->where('role.guard_name', Permission::GUARD)
                ->where('permission.guard_name', Permission::GUARD)
                ->where('permission.name', $permission)
                ->exists();
        } finally {
            setPermissionsTeamId($previousBranchId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
