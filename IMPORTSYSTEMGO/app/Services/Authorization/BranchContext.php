<?php

namespace App\Services\Authorization;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class BranchContext
{
    private ?Branch $branch = null;

    public function current(): ?Branch
    {
        return $this->branch;
    }

    public function activate(User $user, Branch $branch): void
    {
        $now = now();
        $hasMembership = BranchUserMembership::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($branch)
            ->activeAt($now)
            ->exists();

        if (! $user->fresh()?->isActive() || ! $branch->fresh()?->is_active || ! $hasMembership) {
            throw new AuthorizationException('La sucursal no está disponible para este usuario.');
        }

        $this->branch = $branch;
        setPermissionsTeamId($branch->getKey());
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    public function clear(?User $user = null): void
    {
        $this->branch = null;
        setPermissionsTeamId(null);
        $user?->unsetRelation('roles')->unsetRelation('permissions');
    }
}
