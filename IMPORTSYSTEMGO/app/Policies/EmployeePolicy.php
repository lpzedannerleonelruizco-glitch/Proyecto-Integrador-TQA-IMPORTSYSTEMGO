<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Services\Authorization\GlobalAuthorizationService;
use App\Services\Authorization\ScopedAuthorizationService;

class EmployeePolicy
{
    public function __construct(
        private readonly ScopedAuthorizationService $authorization,
        private readonly GlobalAuthorizationService $global,
    ) {}

    public function view(User $user, Employee $employee): bool
    {
        return $employee->homeBranch
            ? $this->authorization->allows($user, 'organization.employees.view', $employee->homeBranch)
            : $this->global->allows($user, 'organization.employees.view');
    }

    public function create(User $user): bool
    {
        return $this->authorization->allows($user, 'organization.employees.manage');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $employee->homeBranch
            ? $this->authorization->allows($user, 'organization.employees.manage', $employee->homeBranch)
            : $this->global->allows($user, 'organization.employees.manage');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return false;
    }
}
