<?php

namespace App\Support;

use App\Models\Role;

final class AuthorizationCatalog
{
    /** @return list<string> */
    public static function territorialRoles(): array
    {
        return ['jefe-operaciones'];
    }

    /** @return list<string> */
    public static function permissions(): array
    {
        return [
            'security.users.view',
            'security.users.manage',
            'security.roles.view',
            'security.roles.manage',
            'organization.branches.view',
            'organization.branches.manage',
            'organization.warehouses.view',
            'organization.warehouses.manage',
            'organization.employees.view',
            'organization.employees.manage',
            'catalog.products.view',
            'catalog.products.manage',
            'inventory.movements.view',
            'customers.view',
            'customers.manage',
            'inventory.stock.view',
            'inventory.stock.manage',
            'inventory.adjustments.request',
            'inventory.adjustments.approve',
            'inventory.transfers.request',
            'inventory.transfers.approve',
            'inventory.transfers.dispatch',
            'inventory.transfers.receive',
            'inventory.transfers.cross_area',
            'inventory.transfers.same_area',
            'sales.view',
            'sales.create',
            'cash.view',
            'cash.manage',
            'cash.approve',
            'payments.collect',
            'credits.view',
            'credits.manage',
            'purchasing.view',
            'purchasing.manage',
            'reports.branch.view',
            'reports.consolidated.view',
            'reports.inventory.view',
            'reports.export',
            'ml.forecasts.view',
            'ml.models.manage',
            'ml.replenishment.decide',
            'backup.view',
            'backup.create',
            'backup.territorial_export',
            'backup.verify',
            'backup.download',
            'qa.cases.view',
            'qa.cases.manage',
            'qa.executions.run',
            'qa.executions.view',
            'qa.evidence.view',
            'qa.reports.generate',
            'qa.reports.email',
            'qa.ai.use',
        ];
    }

    /** @return array<string, array{scope: string, permissions: list<string>}> */
    public static function roles(): array
    {
        $all = self::permissions();

        return [
            'administrador-general' => [
                'scope' => Role::SCOPE_GLOBAL,
                'permissions' => $all,
            ],
            'jefe-operaciones' => [
                'scope' => Role::SCOPE_GLOBAL,
                'permissions' => [
                    'organization.branches.view',
                    'organization.warehouses.view',
                    'organization.employees.view',
                    'catalog.products.view',
                    'customers.view',
                    'inventory.stock.view',
                    'inventory.movements.view',
                    'inventory.adjustments.approve',
                    'inventory.transfers.approve',
                    'inventory.transfers.request',
                    'inventory.transfers.same_area',
                    'reports.inventory.view',
                    'reports.export',
                    'backup.view',
                    'backup.territorial_export',
                    'backup.download',
                    'sales.view',
                    'cash.view',
                    'cash.approve',
                    'credits.view',
                    'purchasing.view',
                    'reports.branch.view',
                    'reports.consolidated.view',
                    'ml.forecasts.view',
                    'ml.replenishment.decide',
                ],
            ],
            'jefe-tienda' => [
                'scope' => Role::SCOPE_BRANCH,
                'permissions' => [
                    'organization.branches.view',
                    'organization.warehouses.view',
                    'organization.employees.view',
                    'organization.employees.manage',
                    'catalog.products.view',
                    'customers.view',
                    'customers.manage',
                    'inventory.stock.view',
                    'inventory.movements.view',
                    'inventory.adjustments.request',
                    'inventory.transfers.request',
                    'inventory.transfers.same_area',
                    'reports.inventory.view',
                    'sales.view',
                    'cash.view',
                    'cash.approve',
                    'credits.view',
                    'purchasing.view',
                    'reports.branch.view',
                    'reports.consolidated.view',
                    'ml.forecasts.view',
                ],
            ],
            'vendedor' => [
                'scope' => Role::SCOPE_BRANCH,
                'permissions' => ['organization.branches.view', 'catalog.products.view', 'customers.view', 'customers.manage', 'inventory.stock.view', 'sales.view', 'sales.create'],
            ],
            'cajero' => [
                'scope' => Role::SCOPE_BRANCH,
                'permissions' => ['organization.branches.view', 'catalog.products.view', 'sales.view', 'cash.view', 'cash.manage', 'payments.collect'],
            ],
            'asesor-creditos' => [
                'scope' => Role::SCOPE_BRANCH,
                'permissions' => ['organization.branches.view', 'customers.view', 'sales.view', 'credits.view', 'credits.manage'],
            ],
            'almacenero' => [
                'scope' => Role::SCOPE_BRANCH,
                'permissions' => [
                    'organization.branches.view', 'organization.warehouses.view', 'catalog.products.view',
                    'inventory.stock.view', 'inventory.movements.view', 'inventory.stock.manage',
                    'inventory.adjustments.request', 'inventory.transfers.request',
                    'inventory.transfers.same_area', 'inventory.transfers.dispatch', 'inventory.transfers.receive',
                ],
            ],
            'encargado-compras' => [
                'scope' => Role::SCOPE_BRANCH,
                'permissions' => ['organization.branches.view', 'organization.warehouses.view', 'catalog.products.view', 'inventory.stock.view', 'purchasing.view', 'purchasing.manage', 'ml.forecasts.view', 'ml.replenishment.decide'],
            ],
        ];
    }
}
