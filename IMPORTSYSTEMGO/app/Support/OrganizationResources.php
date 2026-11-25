<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Warehouse;

final class OrganizationResources
{
    public static function definition(string $resource): array
    {
        return match ($resource) {
            'branches' => ['model' => Branch::class, 'label' => 'Sucursales', 'fields' => [
                'code' => 'Código', 'name' => 'Nombre', 'type' => 'Tipo', 'legal_address' => 'Dirección', 'timezone' => 'Zona horaria', 'is_active' => 'Estado',
            ], 'options' => ['type' => ['central' => 'Centro logístico', 'store' => 'Sucursal'], 'is_active' => [1 => 'Activo', 0 => 'Inactivo']]],
            'warehouses' => ['model' => Warehouse::class, 'label' => 'Almacenes', 'fields' => [
                'code' => 'Código', 'name' => 'Nombre', 'type' => 'Tipo', 'is_active' => 'Estado',
            ], 'options' => ['is_active' => [1 => 'Activo', 0 => 'Inactivo']]],
            'employees' => ['model' => Employee::class, 'label' => 'Empleados', 'fields' => [
                'employee_code' => 'Código', 'first_name' => 'Nombres', 'last_name' => 'Apellidos', 'status' => 'Estado', 'hired_at' => 'Fecha de ingreso', 'terminated_at' => 'Fecha de desvinculación',
            ], 'options' => ['status' => ['active' => 'Activo', 'inactive' => 'Inactivo', 'terminated' => 'Desvinculado']]],
            default => abort(404),
        };
    }
}
