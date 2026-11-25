<?php

namespace Tests\Feature;

use App\Models\GlobalUserRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleAdministrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        $this->administrator = User::factory()->create();
        GlobalUserRole::create([
            'user_id' => $this->administrator->id,
            'role_id' => Role::where('name', 'administrador-general')->firstOrFail()->id,
            'starts_at' => now(),
        ]);
        $this->actingAs($this->administrator);
    }

    public function test_custom_role_creation_and_permission_updates_are_audited(): void
    {
        $permissions = ['organization.employees.view', 'reports.branch.view'];
        $this->post(route('roles.store'), [
            'name' => 'supervisor-logistica',
            'scope_type' => 'branch',
            'branch_id' => '',
            'permissions' => $permissions,
        ])->assertRedirect();

        $role = Role::where('name', 'supervisor-logistica')->firstOrFail();
        $this->assertSame('branch', $role->scope_type);
        $this->assertNull($role->branch_id);
        $this->assertEqualsCanonicalizing($permissions, $role->permissions()->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'roles.created',
            'resource_type' => 'roles',
            'resource_id' => $role->id,
        ]);

        $updatedPermissions = ['organization.employees.view'];
        $this->put(route('roles.update', $role->id), [
            'name' => 'supervisor-logistica',
            'permissions' => $updatedPermissions,
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing($updatedPermissions, $role->fresh()->permissions()->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'roles.updated',
            'resource_type' => 'roles',
            'resource_id' => $role->id,
        ]);
        $this->get(route('roles.index'))->assertOk()->assertSee('supervisor-logistica');
    }

    public function test_system_roles_cannot_be_edited(): void
    {
        $role = Role::where('name', 'administrador-general')->firstOrFail();

        $this->get(route('roles.edit', $role->id))->assertForbidden();
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_role_manager_cannot_delegate_permissions_they_do_not_have(): void
    {
        $manager = $this->roleManager();
        $this->actingAs($manager)->post(route('roles.store'), [
            'name' => 'delegacion-invalida',
            'scope_type' => 'global',
            'permissions' => ['organization.employees.manage'],
        ])->assertSessionHasErrors('permissions');

        $this->assertDatabaseMissing('roles', ['name' => 'delegacion-invalida']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_role_manager_cannot_assign_a_role_with_unheld_permissions(): void
    {
        $manager = $this->roleManager();
        $subject = User::factory()->create();
        $role = Role::where('name', 'jefe-operaciones')->firstOrFail();

        $this->actingAs($manager)->post(route('users.role', $subject->id), [
            'role_id' => $role->id,
            'operation' => 'assign',
        ])->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $subject->id, 'role_id' => $role->id]);
    }

    public function test_roles_route_requires_global_authorization(): void
    {
        $this->actingAs(User::factory()->create())->get(route('roles.index'))->assertForbidden();
    }

    private function roleManager(): User
    {
        $manager = User::factory()->create();
        $role = Role::create([
            'name' => 'gestor-roles-limitado',
            'scope_type' => Role::SCOPE_GLOBAL,
            'branch_id' => null,
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo('security.roles.manage');
        GlobalUserRole::create([
            'user_id' => $manager->id,
            'role_id' => $role->id,
            'starts_at' => now(),
        ]);

        return $manager;
    }
}
