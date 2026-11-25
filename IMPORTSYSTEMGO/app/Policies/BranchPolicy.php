<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Services\Authorization\ScopedAuthorizationService;

class BranchPolicy
{
    public function __construct(private readonly ScopedAuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->allows($user, 'organization.branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->authorization->allows($user, 'organization.branches.view', $branch);
    }

    public function create(User $user): bool
    {
        return $this->authorization->allows($user, 'organization.branches.manage');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->authorization->allows($user, 'organization.branches.manage', $branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return false;
    }
}
