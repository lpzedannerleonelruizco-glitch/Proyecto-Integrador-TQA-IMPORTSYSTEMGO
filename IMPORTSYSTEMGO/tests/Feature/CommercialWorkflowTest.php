<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\CreditAccount;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Authorization\BranchContext;
use App\Services\SaleService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DemoRetailSeeder;
use Database\Seeders\OperationalDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class CommercialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.retail_enabled' => true, 'demo.database' => $this->app->make('db')->connection()->getDatabaseName()]);
        $this->seed(AuthorizationSeeder::class);
        $this->seed(DemoRetailSeeder::class);
        $this->seed(OperationalDemoSeeder::class);
    }

    public function test_operational_demo_is_coherent_and_idempotent(): void
    {
        $counts = [Product::count(), InventoryBalance::count(), Sale::count(), CreditAccount::count(), CashSession::count()];
        $this->seed(OperationalDemoSeeder::class);

        $this->assertSame([12, 84, 15, 1, 7], $counts);
        $this->assertSame($counts, [Product::count(), InventoryBalance::count(), Sale::count(), CreditAccount::count(), CashSession::count()]);
        $this->assertDatabaseHas('purchase_orders', ['order_number' => 'DEMO-OC-LP01-001', 'status' => 'ordered']);
        $this->assertDatabaseHas('credit_accounts', ['credit_number' => 'DEMO-CR-LP01-001', 'term_count' => 6]);
    }

    public function test_role_dashboards_expose_only_authorized_modules(): void
    {
        $this->withoutMiddleware(AuthenticateSession::class);
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $seller = User::where('email', 'vendedor.lp01.01@importsystemgo.example')->firstOrFail();
        $cashier = User::where('email', 'cajero.lp01@importsystemgo.example')->firstOrFail();
        $manager = User::where('email', 'jefe.lp01@importsystemgo.example')->firstOrFail();

        $this->actingAs($seller)->withSession(['active_branch_id' => $branch->id])->get(route('sales.create'))->assertOk();
        $this->actingAs($seller)->withSession(['active_branch_id' => $branch->id])->get(route('cash.index'))->assertForbidden();
        Session::flush();
        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])->get(route('cash.index'))->assertOk();
        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])->get(route('sales.create'))->assertForbidden();
        Session::flush();
        $this->actingAs($manager)->withSession(['active_branch_id' => $branch->id])->get(route('reports.index'))->assertOk();
    }

    public function test_seller_cannot_create_a_sale_in_another_city(): void
    {
        $lp = Branch::where('code', 'LP01')->firstOrFail();
        $ea = Branch::where('code', 'EA01')->firstOrFail();
        $seller = User::where('email', 'vendedor.lp01.01@importsystemgo.example')->firstOrFail();
        $product = Product::firstOrFail();
        app(BranchContext::class)->activate($seller, $lp);

        $this->expectException(AuthorizationException::class);
        app(SaleService::class)->createPending($seller, $ea, [['product_id' => $product->id, 'quantity' => 1]], null, true);
    }

    public function test_reports_and_inventory_are_scoped_to_active_branch(): void
    {
        $branch = Branch::where('code', 'EA02')->firstOrFail();
        $manager = User::where('email', 'jefe.ea02@importsystemgo.example')->firstOrFail();

        $this->actingAs($manager)->withSession(['active_branch_id' => $branch->id])
            ->get(route('inventory.index'))->assertOk()->assertSee('Inventario por almacén')->assertSee('Almacén EA02')->assertDontSee('Almacén LP01');
        $this->actingAs($manager)->withSession(['active_branch_id' => $branch->id])
            ->get(route('reports.index'))->assertOk()->assertSee('Resultados de EA02');
    }

    public function test_specialist_and_supervisor_workspaces_are_reachable_by_permission(): void
    {
        $this->withoutMiddleware(AuthenticateSession::class);
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $cases = [
            ['almacen.lp01@importsystemgo.example', 'inventory.index', 'Inventario por almacén'],
            ['creditos.lp01@importsystemgo.example', 'credits.index', 'Cartera y cuotas'],
            ['compras.lp01@importsystemgo.example', 'purchases.index', 'Órdenes de compra'],
            ['operaciones.lp@importsystemgo.example', 'reports.index', 'Resultados de LP01'],
            ['admin.demo@importsystemgo.example', 'products.index', 'Productos y disponibilidad'],
        ];

        foreach ($cases as [$email, $route, $text]) {
            Session::flush();
            $user = User::where('email', $email)->firstOrFail();
            $this->actingAs($user)->withSession(['active_branch_id' => $branch->id])->get(route($route))->assertOk()->assertSee($text);
        }

        Session::flush();
        $advisor = User::where('email', 'creditos.lp01@importsystemgo.example')->firstOrFail();
        $this->actingAs($advisor)->withSession(['active_branch_id' => $branch->id])->get(route('purchases.index'))->assertForbidden();
    }
}
