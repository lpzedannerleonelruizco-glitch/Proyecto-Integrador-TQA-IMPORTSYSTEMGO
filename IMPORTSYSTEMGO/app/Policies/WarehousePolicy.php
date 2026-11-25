<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;
use App\Services\Authorization\ScopedAuthorizationService;

class WarehousePolicy
{
    public function __construct(private readonly ScopedAuthorizationService $authorization) {}

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $this->authorization->allows($user, 'organization.warehouses.view', $warehouse->branch);
    }

    public function create(User $user, Warehouse $warehouse): bool
    {
        return $this->authorization->allows($user, 'organization.warehouses.manage', $warehouse->branch);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $this->authorization->allows($user, 'organization.warehouses.manage', $warehouse->branch);
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return false;
    }
}
