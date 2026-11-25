<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\Employee;
use App\Models\GlobalUserRole;
use App\Models\OperationalArea;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        $this->branch = Branch::factory()->create();
    }

    private function login(string $roleName): User
    {
        $user = User::factory()->create();
        BranchUserMembership::factory()->for($user)->for($this->branch)->create();
        $role = Role::where('name', $roleName)->firstOrFail();
        if ($role->isGlobal()) {
            GlobalUserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'starts_at' => now()]);
            if ($roleName === 'jefe-operaciones') {
                $area = OperationalArea::create(['code' => 'TEST', 'name' => 'Área de prueba', 'is_active' => true]);
                $this->branch->forceFill(['operational_area_id' => $area->id])->save();
                DB::table('operational_area_assignments')->insert(['user_id' => $user->id, 'operational_area_id' => $area->id, 'created_at' => now(), 'updated_at' => now()]);
            }
        } else {
            setPermissionsTeamId($this->branch->id);
            $user->assignRole($role);
            setPermissionsTeamId(null);
        }
        $this->actingAs($user)->withSession(['active_branch_id' => $this->branch->id, 'password_hash_web' => $user->password]);

        return $user;
    }

    public function test_administrator_creates_branch_and_warehouse_with_atomic_audit(): void
    {
        $actor = $this->login('administrador-general');
        $this->get('/organizacion/branches/crear')->assertOk();
        $this->post('/organizacion/branches', ['code' => 'NEW', 'name' => 'Sucursal nueva', 'type' => 'store', 'timezone' => 'America/La_Paz', 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('branches', ['code' => 'NEW']);
        $this->post('/organizacion/warehouses', ['code' => 'WH-NEW', 'name' => 'Almacén nuevo', 'type' => 'general', 'is_active' => 1])->assertRedirect();
        $warehouse = Warehouse::where('code', 'WH-NEW')->firstOrFail();
        $this->assertSame($this->branch->id, $warehouse->branch_id);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $actor->id, 'action' => 'warehouses.created', 'resource_id' => $warehouse->id]);
        $this->get('/organizacion/warehouses')->assertOk()->assertSee('Almacén nuevo');
        $this->get('/organizacion/warehouses/'.$warehouse->id.'/editar')->assertOk();
        $this->put('/organizacion/warehouses/'.$warehouse->id, ['code' => 'WH-NEW', 'name' => 'Almacén archivado', 'type' => 'general', 'is_active' => 0])->assertRedirect();
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'warehouses.updated', 'resource_id' => $warehouse->id]);
    }

    public function test_store_manager_creates_employee_without_user_but_cannot_access_other_branch(): void
    {
        $this->login('jefe-tienda');
        $otherBranch = Branch::factory()->create();
        $other = Employee::factory()->create(['home_branch_id' => $otherBranch->id, 'last_name' => 'ApellidoRestringido']);
        $payload = ['employee_code' => 'EMP-NEW', 'first_name' => 'María', 'last_name' => 'Prueba', 'status' => 'active', 'hired_at' => '2026-10-01'];
        $this->post('/organizacion/employees', $payload)->assertRedirect();
        $this->assertDatabaseHas('employees', ['employee_code' => 'EMP-NEW', 'user_id' => null, 'home_branch_id' => $this->branch->id]);
        $this->get('/organizacion/employees')->assertOk()->assertSee('María')->assertDontSee('ApellidoRestringido');
        $this->get('/organizacion/employees/'.$other->id.'/editar')->assertNotFound();
        $this->put('/organizacion/employees/'.$other->id, $payload)->assertNotFound();
        $this->post('/organizacion/branches', ['code' => 'UNAUTHORIZED'])->assertForbidden();
    }

    public function test_ownership_fields_cannot_be_forged(): void
    {
        $this->login('administrador-general');
        $this->post('/organizacion/warehouses', ['code' => 'WH', 'name' => 'Test', 'type' => 'general', 'is_active' => 1, 'branch_id' => Branch::factory()->create()->id])
            ->assertSessionHasErrors('branch_id');
        $this->assertDatabaseCount('warehouses', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_missing_audit_schema_blocks_changes(): void
    {
        $this->login('administrador-general');
        // Simulate the missing deployment without DDL or MySQL implicit commits.
        Schema::shouldReceive('hasTable')->with('audit_logs')->once()->andReturn(false);
        $this->post('/organizacion/warehouses', ['code' => 'WH', 'name' => 'Test', 'type' => 'general', 'is_active' => 1])->assertStatus(503);
        $this->assertDatabaseCount('warehouses', 0);
    }

    public function test_salesperson_cannot_read_employees_and_readonly_global_role_cannot_write(): void
    {
        $this->login('vendedor');
        $this->get('/organizacion/employees')->assertForbidden();
        $this->login('jefe-operaciones');
        $this->get('/organizacion/branches')->assertOk();
        $this->post('/organizacion/warehouses', ['code' => 'WH'])->assertForbidden();
    }

    public function test_employment_dates_and_audit_payload_are_validated(): void
    {
        $this->login('jefe-tienda');
        $payload = ['employee_code' => 'EMP', 'first_name' => 'Test', 'last_name' => 'Employee', 'status' => 'terminated', 'hired_at' => '2026-10-10', 'terminated_at' => '2026-10-01'];
        $this->post('/organizacion/employees', $payload)->assertSessionHasErrors('terminated_at');
        $this->assertDatabaseCount('employees', 0);
        $payload['terminated_at'] = '2026-10-20';
        $this->post('/organizacion/employees', $payload + ['password' => 'must-not-be-persisted'])->assertRedirect();
        $audit = DB::table('audit_logs')->first();
        $this->assertStringNotContainsString('password', $audit->after_values);
        $this->assertNull($audit->before_values);
    }

    public function test_terminating_employee_disables_the_linked_account_and_revokes_access(): void
    {
        $actor = $this->login('jefe-tienda');
        $subject = User::factory()->create();
        $membership = BranchUserMembership::factory()->for($subject)->for($this->branch)->create();
        $subject->forceFill(['primary_branch_id' => $this->branch->id])->save();
        setPermissionsTeamId($this->branch->id);
        $subject->assignRole(Role::where('name', 'vendedor')->firstOrFail());
        setPermissionsTeamId(null);
        $employee = Employee::factory()->create([
            'user_id' => $subject->id,
            'home_branch_id' => $this->branch->id,
        ]);

        $this->put('/organizacion/employees/'.$employee->id, [
            'employee_code' => $employee->employee_code,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'status' => 'terminated',
            'hired_at' => $employee->hired_at->format('Y-m-d'),
            'terminated_at' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertSame('terminated', $employee->fresh()->status);
        $this->assertSame('inactive', $subject->fresh()->status);
        $this->assertNull($subject->fresh()->primary_branch_id);
        $this->assertNotNull($membership->fresh()->ended_by);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $subject->id]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $actor->id,
            'action' => 'users.deactivated_due_to_termination',
            'resource_id' => $subject->id,
        ]);
    }
}
