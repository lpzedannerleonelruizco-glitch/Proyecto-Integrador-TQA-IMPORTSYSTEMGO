<?php

namespace Tests\Feature\Authorization;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\GlobalUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\BranchContext;
use App\Services\Authorization\BranchMembershipService;
use App\Services\Authorization\GlobalAuthorizationService;
use App\Services\Authorization\RoleAssignmentService;
use App\Services\Authorization\ScopedAuthorizationService;
use Database\Seeders\AuthorizationSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ScopedAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_branch_role_does_not_leak_to_another_branch(): void
    {
        [$user, $firstBranch] = $this->userWithMembership();
        $secondBranch = Branch::factory()->create();
        $this->assignBranchRole($user, $firstBranch, 'jefe-tienda');

        $context = app(BranchContext::class);
        $context->activate($user, $firstBranch);
        $authorization = app(ScopedAuthorizationService::class);

        $this->assertTrue($authorization->allows($user, 'organization.employees.manage', $firstBranch));
        $this->assertFalse($authorization->allows($user, 'organization.employees.manage', $secondBranch));
    }

    public function test_branch_role_is_denied_without_an_active_validated_context(): void
    {
        [$user, $branch] = $this->userWithMembership();
        $this->assignBranchRole($user, $branch, 'jefe-tienda');

        $this->assertFalse(
            app(ScopedAuthorizationService::class)
                ->allows($user, 'organization.employees.manage', $branch)
        );
    }

    public function test_switching_branch_clears_previously_loaded_permissions(): void
    {
        [$user, $firstBranch] = $this->userWithMembership();
        $secondBranch = Branch::factory()->create();
        BranchUserMembership::factory()->for($user)->for($secondBranch)->create();
        $this->assignBranchRole($user, $firstBranch, 'jefe-tienda');
        $this->assignBranchRole($user, $secondBranch, 'vendedor');

        $context = app(BranchContext::class);
        $authorization = app(ScopedAuthorizationService::class);

        $context->activate($user, $firstBranch);
        $this->assertTrue($authorization->allows($user, 'organization.employees.manage'));

        $context->clear($user);
        $context->activate($user, $secondBranch);
        $this->assertFalse($authorization->allows($user, 'organization.employees.manage'));
    }

    public function test_global_role_grants_only_its_explicit_permissions(): void
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $role = $this->role('jefe-operaciones');
        GlobalUserRole::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'starts_at' => now(),
        ]);

        $authorization = app(GlobalAuthorizationService::class);

        $this->assertTrue($authorization->allows($user, 'organization.branches.view'));
        $this->assertFalse($authorization->allows($user, 'organization.branches.manage'));
        $this->assertFalse(app(ScopedAuthorizationService::class)->allows($user, 'organization.branches.manage', $branch));
    }

    public function test_branch_role_cannot_escalate_to_a_global_role(): void
    {
        [$actor, $branch] = $this->userWithMembership();
        $subject = User::factory()->create();
        $localAdministrator = Role::create([
            'branch_id' => null,
            'name' => 'administrador-local-de-roles',
            'guard_name' => Permission::GUARD,
            'scope_type' => Role::SCOPE_BRANCH,
        ]);
        $localAdministrator->syncPermissions(['security.roles.manage']);
        $this->assignRoleModelToBranch($actor, $branch, $localAdministrator);
        app(BranchContext::class)->activate($actor, $branch);

        $this->expectException(AuthorizationException::class);
        app(RoleAssignmentService::class)->assignGlobalRole($actor, $subject, $this->role('administrador-general'));
    }

    public function test_direct_user_permissions_are_rejected(): void
    {
        $this->expectException(LogicException::class);
        User::factory()->create()->givePermissionTo('organization.branches.view');
    }

    public function test_revoking_membership_removes_primary_branch_and_branch_roles_immediately(): void
    {
        [$subject, $branch, $membership] = $this->userWithMembership(true);
        $actor = $this->globalAdministrator();
        $this->assignBranchRole($subject, $branch, 'jefe-tienda');
        $subject->forceFill(['primary_branch_id' => $branch->id])->save();

        app(BranchMembershipService::class)->revoke($membership, $actor, 'Fin de asignación');

        $this->assertNull($subject->fresh()->primary_branch_id);
        $this->assertDatabaseMissing('model_has_roles', [
            'branch_id' => $branch->id,
            'model_id' => $subject->id,
            'model_type' => User::class,
        ]);

        $this->expectException(AuthorizationException::class);
        app(BranchContext::class)->activate($subject->fresh(), $branch);
    }

    public function test_membership_service_rejects_overlapping_periods(): void
    {
        $actor = $this->globalAdministrator();
        $subject = User::factory()->create();
        $branch = Branch::factory()->create();
        $service = app(BranchMembershipService::class);

        $service->grant($subject, $branch, $actor, now()->subDay());

        $this->expectException(DomainException::class);
        $service->grant($subject, $branch, $actor, now());
    }

    public function test_membership_with_scheduled_expiry_can_be_revoked_immediately(): void
    {
        [$subject, $branch, $membership] = $this->userWithMembership(true);
        $membership->update(['ends_at' => now()->addMonth()]);
        $actor = $this->globalAdministrator();
        app(BranchMembershipService::class)->revoke($membership, $actor, 'Revocación anticipada');
        $this->assertSame($actor->id, $membership->fresh()->ended_by);
        $this->assertFalse(BranchUserMembership::whereKey($membership->id)->activeAt(now())->exists());
    }

    public function test_future_membership_cancellation_preserves_valid_dates_without_future_access(): void
    {
        $actor = $this->globalAdministrator();
        $subject = User::factory()->create();
        $branch = Branch::factory()->create();
        $membership = app(BranchMembershipService::class)->grant($subject, $branch, $actor, now()->addWeek());
        app(BranchMembershipService::class)->revoke($membership, $actor, 'Asignación cancelada');
        $this->assertTrue($membership->fresh()->ends_at->greaterThan($membership->fresh()->starts_at));
        $this->assertFalse(BranchUserMembership::whereKey($membership->id)->activeAt(now()->addWeek())->exists());
    }

    public function test_user_has_only_one_primary_branch_value(): void
    {
        $user = User::factory()->create();
        $first = Branch::factory()->create();
        $second = Branch::factory()->create();
        BranchUserMembership::factory()->for($user)->for($first)->create();
        BranchUserMembership::factory()->for($user)->for($second)->create();
        $service = app(BranchMembershipService::class);

        $service->setPrimaryBranch($user, $first, $user);
        $service->setPrimaryBranch($user->fresh(), $second, $user->fresh());

        $this->assertSame($second->id, $user->fresh()->primary_branch_id);
    }

    private function userWithMembership(bool $returnMembership = false): array
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $membership = BranchUserMembership::factory()->for($user)->for($branch)->create();

        return $returnMembership ? [$user, $branch, $membership] : [$user, $branch];
    }

    private function globalAdministrator(): User
    {
        $user = User::factory()->create();
        GlobalUserRole::query()->create([
            'user_id' => $user->id,
            'role_id' => $this->role('administrador-general')->id,
            'starts_at' => now(),
        ]);

        return $user;
    }

    private function assignBranchRole(User $user, Branch $branch, string $roleName): void
    {
        $this->assignRoleModelToBranch($user, $branch, $this->role($roleName));
    }

    private function assignRoleModelToBranch(User $user, Branch $branch, Role $role): void
    {
        $previousBranchId = getPermissionsTeamId();
        setPermissionsTeamId($branch->id);
        $user->unsetRelation('roles')->assignRole($role);
        setPermissionsTeamId($previousBranchId);
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    private function role(string $name): Role
    {
        return Role::query()->whereNull('branch_id')->where('name', $name)->firstOrFail();
    }
}
