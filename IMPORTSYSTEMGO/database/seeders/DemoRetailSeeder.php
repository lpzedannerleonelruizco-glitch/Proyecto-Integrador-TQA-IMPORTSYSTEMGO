<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\Employee;
use App\Models\GlobalUserRole;
use App\Models\OperationalArea;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DemoCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DemoRetailSeeder extends Seeder
{
    public function run(): void
    {
        $this->guardEnvironment();
        DB::transaction(function (): void {
            $areas = $this->areas();
            $branches = $this->branches($areas);
            $this->warehouses($branches);
            foreach (DemoCatalog::identities() as $email => $identity) {
                $this->identity($email, $identity, $areas, $branches);
            }
        }, 3);
    }

    private function guardEnvironment(): void
    {
        $database = DB::connection()->getDatabaseName();
        if (! app()->environment(['local', 'testing'])
            || ! config('demo.retail_enabled')
            || ($database !== config('demo.database') && ! app()->environment('testing'))) {
            throw new \RuntimeException('Datos DEMO bloqueados: entorno o base no autorizados.');
        }
        foreach (['operational_areas', 'operational_area_assignments', 'branches', 'users', 'employees'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException('DemoRetailSeeder requiere el esquema DEMO completo.');
            }
        }
        if (Role::count() === 0) {
            throw new \RuntimeException('Debe existir el catálogo de autorización.');
        }
    }

    private function areas(): array
    {
        $result = [];
        foreach (['LP' => 'La Paz', 'EA' => 'El Alto'] as $code => $name) {
            $area = OperationalArea::where('code', $code)->first();
            if ($area && ! $area->is_demo) {
                throw new \RuntimeException("Conflicto: el área {$code} existe y no está marcada como DEMO.");
            }
            $result[$code] = $area ?? OperationalArea::create(['code' => $code, 'name' => $name, 'is_active' => true, 'is_demo' => true]);
        }

        return $result;
    }

    private function branches(array $areas): array
    {
        $result = [];
        foreach (DemoCatalog::branches() as $code => $definition) {
            $branch = Branch::where('code', $code)->first();
            if ($branch && ! $branch->is_demo) {
                throw new \RuntimeException("Conflicto: la sucursal {$code} no es DEMO; no fue modificada.");
            }
            $branch ??= new Branch;
            $branch->forceFill([
                'code' => $code, 'name' => "IMPORTSYSTEMGO {$definition['name']}", 'type' => Branch::TYPE_STORE,
                'timezone' => 'America/La_Paz', 'is_active' => true, 'is_demo' => true,
                'operational_area_id' => $areas[$definition['area']]->id,
            ])->save();
            $result[$code] = $branch;
        }

        return $result;
    }

    private function warehouses(array $branches): void
    {
        foreach ($branches as $code => $branch) {
            $warehouse = Warehouse::where('code', "WH-{$code}")->first();
            if ($warehouse && ! $warehouse->is_demo) {
                throw new \RuntimeException("Conflicto: el almacén WH-{$code} no es DEMO.");
            }
            $warehouse ??= new Warehouse;
            $warehouse->forceFill(['branch_id' => $branch->id, 'code' => "WH-{$code}", 'name' => "Almacén {$code}", 'type' => 'general', 'is_active' => true, 'is_demo' => true])->save();
        }
    }

    private function identity(string $email, array $identity, array $areas, array $branches): void
    {
        $user = User::where('email', $email)->first();
        if ($user && ! $user->is_demo) {
            throw new \RuntimeException("Conflicto: {$email} existe y no es una cuenta DEMO.");
        }
        if (! $user) {
            $user = new User;
            $user->forceFill(['name' => $identity['name'], 'email' => $email, 'password' => Hash::make(bin2hex(random_bytes(48))), 'status' => User::STATUS_ACTIVE, 'is_demo' => true, 'email_verified_at' => now()])->save();
        }
        $branch = $identity['branch'] ? $branches[$identity['branch']] : null;
        if ($identity['role'] === 'administrador-general') {
            foreach ($branches as $memberBranch) {
                $this->membership($user, $memberBranch);
            }
            $this->globalRole($user, $identity['role']);
        } elseif ($identity['role'] === 'jefe-operaciones') {
            foreach ($branches as $code => $memberBranch) {
                if (str_starts_with($code, $identity['area'])) {
                    $this->membership($user, $memberBranch);
                }
            }
            $this->globalRole($user, $identity['role']);
            DB::table('operational_area_assignments')->updateOrInsert(
                ['user_id' => $user->id, 'operational_area_id' => $areas[$identity['area']]->id],
                ['granted_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]
            );
        } else {
            $this->membership($user, $branch);
            $previous = getPermissionsTeamId();
            try {
                setPermissionsTeamId($branch->id);
                $user->unsetRelation('roles')->assignRole(Role::where('name', $identity['role'])->where('scope_type', 'branch')->firstOrFail());
            } finally {
                setPermissionsTeamId($previous);
                $user->unsetRelation('roles')->unsetRelation('permissions');
            }
        }
        if ($identity['employee']) {
            $employee = Employee::where('employee_code', $identity['employee_code'])->first();
            if ($employee && ! $employee->is_demo) {
                throw new \RuntimeException("Conflicto: el empleado {$identity['employee_code']} no es DEMO.");
            }
            $parts = explode(' ', $identity['name'], 2);
            $employee ??= new Employee;
            $employee->forceFill([
                'user_id' => $user->id, 'employee_code' => $identity['employee_code'], 'first_name' => $parts[0],
                'last_name' => $parts[1] ?? 'Demo', 'home_branch_id' => $branch->id,
                'status' => Employee::STATUS_ACTIVE, 'hired_at' => '2026-01-05', 'is_demo' => true,
            ])->save();
        }
    }

    private function membership(User $user, Branch $branch): void
    {
        BranchUserMembership::firstOrCreate(['user_id' => $user->id, 'branch_id' => $branch->id], ['starts_at' => now(), 'granted_by' => $user->id]);
        if (! $user->primary_branch_id) {
            $user->forceFill(['primary_branch_id' => $branch->id])->save();
        }
    }

    private function globalRole(User $user, string $roleName): void
    {
        $role = Role::where('name', $roleName)->where('scope_type', 'global')->firstOrFail();
        GlobalUserRole::firstOrCreate(['user_id' => $user->id, 'role_id' => $role->id], ['starts_at' => now(), 'granted_by' => $user->id]);
    }
}
