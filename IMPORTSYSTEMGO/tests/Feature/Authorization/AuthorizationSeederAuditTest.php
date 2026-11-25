<?php

namespace Tests\Feature\Authorization;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorizationSeederAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_catalog_synchronization_is_audited_and_idempotent(): void
    {
        $actor = User::factory()->create(['email' => 'admin.demo@importsystemgo.example']);
        $actor->forceFill(['is_demo' => true, 'status' => User::STATUS_ACTIVE])->save();
        app()->detectEnvironment(fn (): string => 'local');
        config([
            'demo.retail_enabled' => true,
            'demo.database' => DB::connection()->getDatabaseName(),
        ]);

        $seeder = app(AuthorizationSeeder::class);
        $seeder->run();

        $this->assertDatabaseHas('permissions', ['name' => 'cash.approve']);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $actor->id,
            'action' => 'authorization.permission.created',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $actor->id,
            'action' => 'authorization.role_permissions.synchronized',
            'resource_type' => 'roles',
        ]);

        $auditCount = DB::table('audit_logs')->count();
        $seeder->run();

        $this->assertSame($auditCount, DB::table('audit_logs')->count());
        $this->assertTrue(Role::where('name', 'jefe-tienda')->firstOrFail()->hasPermissionTo('cash.approve'));
    }
}
