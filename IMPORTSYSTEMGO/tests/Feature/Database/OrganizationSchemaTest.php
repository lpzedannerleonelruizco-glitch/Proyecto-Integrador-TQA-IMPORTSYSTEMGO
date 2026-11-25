<?php

namespace Tests\Feature\Database;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundational_tables_and_columns_are_defined(): void
    {
        $this->assertTrue(Schema::hasColumns('branches', ['code', 'name', 'type', 'timezone', 'is_active']));
        $this->assertTrue(Schema::hasColumns('warehouses', ['branch_id', 'code', 'name', 'type', 'is_active']));
        $this->assertTrue(Schema::hasColumns('users', ['status', 'primary_branch_id', 'last_login_at', 'password_changed_at']));
        $this->assertTrue(Schema::hasColumns('employees', ['user_id', 'employee_code', 'home_branch_id', 'status']));
        $this->assertTrue(Schema::hasColumns('branch_user_memberships', ['branch_id', 'user_id', 'starts_at', 'ends_at']));
        $this->assertTrue(Schema::hasColumns('roles', ['branch_id', 'name', 'guard_name', 'scope_type']));
        $this->assertTrue(Schema::hasTable('global_user_roles'));
    }

    public function test_database_rejects_two_open_memberships_for_the_same_user_and_branch(): void
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        BranchUserMembership::factory()->for($user)->for($branch)->create();

        $this->expectException(QueryException::class);
        BranchUserMembership::factory()->for($user)->for($branch)->create();
    }

    public function test_database_rejects_duplicate_global_role_names_despite_null_branch(): void
    {
        $this->seed(AuthorizationSeeder::class);

        $this->expectException(QueryException::class);
        DB::table('roles')->insert([
            'branch_id' => null,
            'name' => 'administrador-general',
            'guard_name' => Permission::GUARD,
            'scope_type' => Role::SCOPE_GLOBAL,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_authorization_seeder_is_idempotent(): void
    {
        $this->seed(AuthorizationSeeder::class);
        $permissionCount = DB::table('permissions')->count();
        $roleCount = DB::table('roles')->count();
        $rolePermissionCount = DB::table('role_has_permissions')->count();

        $this->seed(AuthorizationSeeder::class);

        $this->assertSame($permissionCount, DB::table('permissions')->count());
        $this->assertSame($roleCount, DB::table('roles')->count());
        $this->assertSame($rolePermissionCount, DB::table('role_has_permissions')->count());
    }
}
