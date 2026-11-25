<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\Employee;
use App\Models\GlobalUserRole;
use App\Models\OperationalArea;
use App\Models\User;
use App\Services\Authorization\OperationalAreaAccessService;
use App\Services\DemoCredentialService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DemoRetailSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoRetailSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_retail_seed_creates_expected_distribution(): void
    {
        config(['demo.retail_enabled' => true]);
        $this->seed(AuthorizationSeeder::class);
        $this->seed(DemoRetailSeeder::class);

        $this->assertSame(2, OperationalArea::count());
        $this->assertSame(7, Branch::count());
        $this->assertSame(4, Branch::whereHas('operationalArea', fn ($query) => $query->where('code', 'LP'))->count());
        $this->assertSame(3, Branch::whereHas('operationalArea', fn ($query) => $query->where('code', 'EA'))->count());
        $this->assertSame(41, User::where('email', 'like', '%@importsystemgo.example')->count());
        $this->assertSame(37, Employee::count());
        $this->assertSame(52, BranchUserMembership::count());
        $this->assertSame(2, GlobalUserRole::whereHas('role', fn ($query) => $query->where('name', 'jefe-operaciones'))->count());

        $branchLp01 = Branch::where('code', 'LP01')->firstOrFail();
        $branchEa01 = Branch::where('code', 'EA01')->firstOrFail();
        $opsLp = User::where('email', 'operaciones.lp@importsystemgo.example')->firstOrFail();
        $opsEa = User::where('email', 'operaciones.ea@importsystemgo.example')->firstOrFail();

        $this->assertTrue(app(OperationalAreaAccessService::class)->allowsBranch($opsLp, $branchLp01));
        $this->assertFalse(app(OperationalAreaAccessService::class)->allowsBranch($opsLp, $branchEa01));
        $this->assertTrue(app(OperationalAreaAccessService::class)->allowsBranch($opsEa, $branchEa01));
        $this->assertFalse(app(OperationalAreaAccessService::class)->allowsBranch($opsEa, $branchLp01));
    }

    public function test_demo_retail_seed_is_idempotent(): void
    {
        config(['demo.retail_enabled' => true]);
        $this->seed(AuthorizationSeeder::class);
        $this->seed(DemoRetailSeeder::class);
        $this->seed(DemoRetailSeeder::class);

        $this->assertSame(2, OperationalArea::count());
        $this->assertSame(7, Branch::count());
        $this->assertSame(41, User::where('email', 'like', '%@importsystemgo.example')->count());
        $this->assertSame(37, Employee::count());
        $this->assertSame(52, BranchUserMembership::count());
    }

    public function test_demo_credentials_are_strong_unique_and_only_rotate_marked_accounts(): void
    {
        config(['demo.retail_enabled' => true]);
        $this->seed(AuthorizationSeeder::class);
        $this->seed(DemoRetailSeeder::class);
        $real = User::factory()->create(['email' => 'real@example.test']);
        $realHash = $real->password;
        $before = User::where('is_demo', true)->pluck('password', 'email');

        $credentials = app(DemoCredentialService::class)->rotate();

        $this->assertCount(41, $credentials);
        $this->assertCount(41, array_unique(array_column($credentials, 'password')));
        foreach ($credentials as $credential) {
            $this->assertMatchesRegularExpression('/[A-Z]/', $credential['password']);
            $this->assertMatchesRegularExpression('/[a-z]/', $credential['password']);
            $this->assertMatchesRegularExpression('/[2-9]/', $credential['password']);
            $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $credential['password']);
            $this->assertSame(20, strlen($credential['password']));
            $this->assertNotSame($before[$credential['email']], User::where('email', $credential['email'])->value('password'));
            $this->assertTrue(Hash::check($credential['password'], User::where('email', $credential['email'])->value('password')));
        }
        $this->assertSame($realHash, $real->fresh()->password);
        $this->assertDatabaseCount('audit_logs', 41);
    }

    public function test_each_demo_role_reaches_its_expected_dashboard(): void
    {
        config(['demo.retail_enabled' => true]);
        $this->seed(AuthorizationSeeder::class);
        $this->seed(DemoRetailSeeder::class);

        $expectations = [
            'admin.demo@importsystemgo.example' => 'Administración',
            'operaciones.lp@importsystemgo.example' => 'Supervisión territorial',
            'jefe.lp01@importsystemgo.example' => 'Mi sucursal',
            'vendedor.lp01.01@importsystemgo.example' => 'Ventas',
            'cajero.lp01@importsystemgo.example' => 'Mi caja',
            'almacen.lp01@importsystemgo.example' => 'Inventario',
            'creditos.lp01@importsystemgo.example' => 'Créditos',
            'compras.lp01@importsystemgo.example' => 'Compras',
        ];

        foreach ($expectations as $email => $workspace) {
            $this->app['auth']->forgetGuards();
            $this->flushSession();
            $user = User::where('email', $email)->firstOrFail();
            $this->actingAs($user)
                ->withSession(['active_branch_id' => $user->primary_branch_id])
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee($workspace);
        }
    }
}
