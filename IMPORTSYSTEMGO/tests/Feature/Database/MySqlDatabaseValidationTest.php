<?php

namespace Tests\Feature\Database;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlDatabaseValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Esta comprobacion requiere MySQL.');
        }

        $identity = DB::selectOne('SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name');

        $this->assertSame('importsystemgo_testing', $identity->database_name);
        $this->assertSame('importsystemgo_test@localhost', $identity->account_name);
    }

    public function test_mysql_exposes_expected_generated_columns_and_enforced_checks(): void
    {
        $generatedColumns = DB::table('information_schema.columns')
            ->where('table_schema', 'importsystemgo_testing')
            ->whereIn('column_name', ['active_membership', 'scope_branch_id', 'active_assignment'])
            ->where('extra', 'like', '%STORED GENERATED%')
            ->selectRaw('column_name AS generated_name')
            ->pluck('generated_name')
            ->sort()
            ->values()
            ->all();

        $checks = DB::table('information_schema.table_constraints')
            ->where('constraint_schema', 'importsystemgo_testing')
            ->where('constraint_type', 'CHECK')
            ->where('enforced', 'YES')
            ->selectRaw('constraint_name AS check_name')
            ->pluck('check_name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['active_assignment', 'active_membership', 'scope_branch_id'], $generatedColumns);
        $this->assertEqualsCanonicalizing([
            'branch_memberships_valid_period',
            'employees_valid_employment_dates',
            'global_roles_valid_period',
            'roles_valid_scope',
        ], $checks);
    }

    public function test_mysql_rejects_invalid_temporal_periods(): void
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $moment = now();

        try {
            DB::table('branch_user_memberships')->insert([
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'starts_at' => $moment,
                'ends_at' => $moment,
                'created_at' => $moment,
                'updated_at' => $moment,
            ]);
            $this->fail('MySQL acepto una membresia con periodo invalido.');
        } catch (QueryException) {
            $this->assertDatabaseCount('branch_user_memberships', 0);
        }

        $this->seed(AuthorizationSeeder::class);
        $role = Role::query()->where('name', 'administrador-general')->firstOrFail();

        $this->expectException(QueryException::class);
        DB::table('global_user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'starts_at' => $moment,
            'ends_at' => $moment,
            'created_at' => $moment,
            'updated_at' => $moment,
        ]);
    }

    public function test_mysql_rejects_invalid_employment_dates_and_role_scopes(): void
    {
        try {
            DB::table('employees')->insert([
                'employee_code' => 'INVALID-DATES',
                'first_name' => 'Prueba',
                'last_name' => 'Invalida',
                'status' => 'terminated',
                'hired_at' => '2026-10-21',
                'terminated_at' => '2026-10-20',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('MySQL acepto fechas laborales invalidas.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('employees', ['employee_code' => 'INVALID-DATES']);
        }

        $this->expectException(QueryException::class);
        DB::table('roles')->insert([
            'branch_id' => Branch::factory()->create()->id,
            'name' => 'global-invalido',
            'guard_name' => Permission::GUARD,
            'scope_type' => Role::SCOPE_GLOBAL,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_testing_account_cannot_read_the_primary_database(): void
    {
        $this->expectException(QueryException::class);
        DB::select('SELECT COUNT(*) FROM importsystemgo.users');
    }
}
