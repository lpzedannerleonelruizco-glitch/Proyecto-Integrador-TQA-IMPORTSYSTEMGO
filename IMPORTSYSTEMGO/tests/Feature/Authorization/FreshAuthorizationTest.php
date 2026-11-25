<?php

namespace Tests\Feature\Authorization;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\BranchContext;
use App\Services\Authorization\ScopedAuthorizationService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FreshAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_revocation_is_observed_without_reloading_actor_or_permission_cache(): void
    {
        $this->seed(AuthorizationSeeder::class);
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        BranchUserMembership::factory()->for($user)->for($branch)->create();
        app(BranchContext::class)->activate($user, $branch);
        $role = Role::where('name', 'jefe-tienda')->firstOrFail();
        $user->assignRole($role);
        $authorization = app(ScopedAuthorizationService::class);
        $this->assertTrue($authorization->allows($user, 'organization.employees.manage'));
        DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
        $this->assertFalse($authorization->allows($user, 'organization.employees.manage'));
    }

    public function test_direct_database_grants_and_inactive_branches_do_not_bypass_authorization(): void
    {
        $this->seed(AuthorizationSeeder::class);
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        BranchUserMembership::factory()->for($user)->for($branch)->create();
        app(BranchContext::class)->activate($user, $branch);
        $permission = Permission::where('name', 'organization.employees.manage')->firstOrFail();
        DB::table('model_has_permissions')->insert(['permission_id' => $permission->id, 'model_type' => $user->getMorphClass(), 'model_id' => $user->id, 'branch_id' => $branch->id]);
        $authorization = app(ScopedAuthorizationService::class);
        $this->assertFalse($authorization->allows($user, $permission->name));
        $user->assignRole(Role::where('name', 'jefe-tienda')->firstOrFail());
        $this->assertTrue($authorization->allows($user, $permission->name));
        Branch::whereKey($branch->id)->update(['is_active' => false]);
        $this->assertFalse($authorization->allows($user, $permission->name));
    }
}
