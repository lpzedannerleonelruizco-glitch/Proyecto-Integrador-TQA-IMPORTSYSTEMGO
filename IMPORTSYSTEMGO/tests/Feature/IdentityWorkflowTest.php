<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\Employee;
use App\Models\GlobalUserRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdentityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        $this->administrator = User::factory()->create();
        GlobalUserRole::create(['user_id' => $this->administrator->id, 'role_id' => Role::where('name', 'administrador-general')->firstOrFail()->id, 'starts_at' => now()]);
        $this->actingAs($this->administrator);
    }

    public function test_account_membership_role_and_revocation_flow_is_audited(): void
    {
        $this->get(route('users.index'))->assertOk();
        $this->get(route('users.create'))->assertOk();
        $password = 'Aa1!'.bin2hex(random_bytes(20));
        $this->post(route('users.store'), ['name' => 'Nuevo vendedor', 'email' => 'seller@example.test', 'status' => 'active', 'password' => $password, 'password_confirmation' => $password])->assertRedirect();
        $subject = User::where('email', 'seller@example.test')->firstOrFail();
        $branch = Branch::factory()->create();
        $this->post(route('users.membership', $subject->id), ['branch_id' => $branch->id])->assertRedirect();
        $role = Role::where('name', 'vendedor')->firstOrFail();
        $this->post(route('users.role', $subject->id), ['role_id' => $role->id, 'branch_id' => $branch->id, 'operation' => 'assign'])->assertRedirect();
        $this->assertDatabaseHas('model_has_roles', ['model_id' => $subject->id, 'role_id' => $role->id, 'branch_id' => $branch->id]);
        $this->get(route('users.edit', $subject->id))->assertOk()->assertSee('vendedor');
        $this->post(route('users.role', $subject->id), [
            'role_id' => $role->id,
            'branch_id' => $branch->id,
            'operation' => 'revoke',
            'reason' => 'Fin de autorización',
        ])->assertRedirect();
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $subject->id, 'role_id' => $role->id]);
        $roleAudit = DB::table('audit_logs')->where('action', 'roles.revoked')->firstOrFail();
        $this->assertSame('Fin de autorización', json_decode($roleAudit->after_values, true, flags: JSON_THROW_ON_ERROR)['reason']);
        $membership = $subject->branchMemberships()->firstOrFail();
        $this->post(route('users.membership.revoke', [$subject->id, $membership->id]), ['reason' => 'Fin de asignación'])->assertRedirect();
        $this->assertFalse($subject->branchMemberships()->activeAt(now())->exists());
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $subject->id, 'branch_id' => $branch->id]);
        $this->assertDatabaseCount('audit_logs', 5);
        foreach (DB::table('audit_logs')->pluck('after_values') as $values) {
            $this->assertStringNotContainsString($password, $values);
            $this->assertStringNotContainsString('password', $values);
        }
    }

    public function test_local_user_cannot_administrate_accounts_or_escalate_roles(): void
    {
        $subject = User::factory()->create();
        $this->actingAs($subject);
        $this->get(route('users.index'))->assertForbidden();
        $this->post(route('users.employee', $subject->id), ['employee_id' => 1])->assertForbidden();
        $this->post(route('users.membership', $subject->id), ['branch_id' => 1])->assertForbidden();
        $this->post(route('users.role', $subject->id), ['role_id' => Role::where('name', 'administrador-general')->firstOrFail()->id, 'operation' => 'assign'])->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_self_deactivation_and_self_global_role_revocation_are_denied(): void
    {
        $this->put(route('users.update', $this->administrator->id), ['name' => $this->administrator->name, 'email' => $this->administrator->email, 'status' => 'inactive'])->assertSessionHasErrors('status');
        $this->post(route('users.role', $this->administrator->id), [
            'role_id' => Role::where('name', 'administrador-general')->firstOrFail()->id,
            'operation' => 'revoke',
            'reason' => 'Prueba de revocación propia',
        ])->assertSessionHasErrors('role_id');
        $this->assertSame('active', $this->administrator->fresh()->status);
    }

    public function test_role_requires_correct_scope_and_membership(): void
    {
        $subject = User::factory()->create();
        $branch = Branch::factory()->create();
        $role = Role::where('name', 'vendedor')->firstOrFail();
        $this->post(route('users.role', $subject->id), ['role_id' => $role->id, 'branch_id' => $branch->id, 'operation' => 'assign'])->assertSessionHasErrors('role_id');
        $global = Role::where('name', 'jefe-operaciones')->firstOrFail();
        $this->post(route('users.role', $subject->id), ['role_id' => $global->id, 'branch_id' => $branch->id, 'operation' => 'assign'])->assertSessionHasErrors('role_id');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_employee_link_requires_active_membership_and_is_audited(): void
    {
        $subject = User::factory()->create();
        $branch = Branch::factory()->create();
        $employee = Employee::factory()->create(['home_branch_id' => $branch->id]);

        $this->post(route('users.employee', $subject->id), ['employee_id' => $employee->id])
            ->assertSessionHasErrors('employee_id');
        $this->assertNull($employee->fresh()->user_id);

        BranchUserMembership::factory()->for($subject)->for($branch)->create();
        $this->post(route('users.employee', $subject->id), ['employee_id' => $employee->id])->assertRedirect();
        $this->assertSame($subject->id, $employee->fresh()->user_id);
        $this->get(route('users.edit', $subject->id))->assertOk()->assertSee($employee->employee_code);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $this->administrator->id,
            'action' => 'users.employee_assigned',
            'resource_id' => $subject->id,
        ]);
    }

    public function test_deactivation_revokes_all_access_and_preserves_the_last_administrator(): void
    {
        $subject = User::factory()->create();
        $branch = Branch::factory()->create();
        $membership = BranchUserMembership::factory()->for($subject)->for($branch)->create();
        $subject->forceFill(['primary_branch_id' => $branch->id])->save();
        $localRole = Role::where('name', 'vendedor')->firstOrFail();
        setPermissionsTeamId($branch->id);
        $subject->assignRole($localRole);
        setPermissionsTeamId(null);
        $globalRole = Role::where('name', 'jefe-operaciones')->firstOrFail();
        $globalAssignment = GlobalUserRole::create([
            'user_id' => $subject->id,
            'role_id' => $globalRole->id,
            'starts_at' => now(),
        ]);

        $this->put(route('users.update', $subject->id), [
            'name' => $subject->name,
            'email' => $subject->email,
            'status' => 'inactive',
        ])->assertRedirect();

        $this->assertSame('inactive', $subject->fresh()->status);
        $this->assertNull($subject->fresh()->primary_branch_id);
        $this->assertNotNull($membership->fresh()->ended_by);
        $this->assertNotNull($globalAssignment->fresh()->ended_by);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $subject->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'memberships.revoked', 'resource_id' => $subject->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'global_roles.revoked', 'resource_id' => $subject->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'roles.revoked', 'resource_id' => $subject->id]);
    }

    public function test_manager_cannot_assign_roles_to_themselves(): void
    {
        $role = Role::where('name', 'jefe-operaciones')->firstOrFail();

        $this->post(route('users.role', $this->administrator->id), [
            'role_id' => $role->id,
            'operation' => 'assign',
        ])->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('global_user_roles', [
            'user_id' => $this->administrator->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_role_revocation_requires_an_audit_reason(): void
    {
        $subject = User::factory()->create();
        $role = Role::where('name', 'jefe-operaciones')->firstOrFail();
        GlobalUserRole::create(['user_id' => $subject->id, 'role_id' => $role->id, 'starts_at' => now()]);

        $this->post(route('users.role', $subject->id), [
            'role_id' => $role->id,
            'operation' => 'revoke',
        ])->assertSessionHasErrors('reason');
    }

    public function test_revoking_last_global_administrator_requires_another_assignment(): void
    {
        $manager = User::factory()->create();
        $roleManager = Role::create([
            'name' => 'gestor-roles',
            'scope_type' => Role::SCOPE_GLOBAL,
            'branch_id' => null,
            'guard_name' => 'web',
        ]);
        $roleManager->givePermissionTo('security.roles.manage');
        GlobalUserRole::create(['user_id' => $manager->id, 'role_id' => $roleManager->id, 'starts_at' => now()]);
        $administratorRole = Role::where('name', 'administrador-general')->firstOrFail();
        $assignment = $this->administrator->globalRoleAssignments()->where('role_id', $administratorRole->id)->firstOrFail();

        $this->actingAs($manager)->post(route('users.role', $this->administrator->id), [
            'role_id' => $administratorRole->id,
            'operation' => 'revoke',
            'reason' => 'Rotación administrativa',
        ])->assertSessionHasErrors('role_id');

        $this->assertNull($assignment->fresh()->ended_by);
    }

    public function test_another_user_manager_cannot_disable_the_last_administrator(): void
    {
        $manager = User::factory()->create();
        $role = Role::create(['name' => 'gestor-cuentas', 'scope_type' => 'global', 'branch_id' => null, 'guard_name' => 'web']);
        $role->givePermissionTo('security.users.manage');
        GlobalUserRole::create(['user_id' => $manager->id, 'role_id' => $role->id, 'starts_at' => now()]);
        $this->actingAs($manager)->put(route('users.update', $this->administrator->id), ['name' => $this->administrator->name, 'email' => $this->administrator->email, 'status' => 'inactive'])->assertSessionHasErrors('status');
        $this->assertTrue($this->administrator->fresh()->isActive());
    }
}
