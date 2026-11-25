<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Services\Authorization\ScopedAuthorizationService;

class CustomerPolicy
{
    public function __construct(private readonly ScopedAuthorizationService $authorization) {}

    public function view(User $user, Customer $customer): bool
    {
        return $this->authorization->allows($user, 'customers.view', $customer->branch);
    }

    public function create(User $user): bool
    {
        return $this->authorization->allows($user, 'customers.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->authorization->allows($user, 'customers.manage', $customer->branch);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return false;
    }
}
