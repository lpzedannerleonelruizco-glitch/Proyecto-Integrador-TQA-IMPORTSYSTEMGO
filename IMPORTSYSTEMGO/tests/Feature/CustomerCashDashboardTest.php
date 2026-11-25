<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\Authorization\BranchContext;
use App\Services\CashService;
use App\Services\DashboardService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DemoRetailSeeder;
use Database\Seeders\OperationalDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Tests\TestCase;

class CustomerCashDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.retail_enabled' => true, 'demo.database' => $this->app->make('db')->connection()->getDatabaseName()]);
        $this->seed(AuthorizationSeeder::class);
        $this->seed(DemoRetailSeeder::class);
        $this->seed(OperationalDemoSeeder::class);
        $this->withoutMiddleware(AuthenticateSession::class);
    }

    public function test_seller_registers_a_customer_quickly_without_forging_branch(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $seller = User::where('email', 'vendedor.lp01.01@importsystemgo.example')->firstOrFail();

        $response = $this->actingAs($seller)->withSession(['active_branch_id' => $branch->id])->postJson(route('customers.quick-store'), [
            'customer_type' => 'natural', 'document_type' => 'ci', 'document_number' => '9988776',
            'full_name' => 'Cliente Rápido Demo', 'phone' => '70001234', 'city' => 'La Paz',
        ]);

        $response->assertCreated()->assertJsonPath('customer.label', 'Cliente Rápido Demo · 9988776');
        $this->assertDatabaseHas('customers', ['branch_id' => $branch->id, 'document_key' => 'ci:9988776:', 'created_by' => $seller->id]);
    }

    public function test_customer_history_does_not_leak_between_branches(): void
    {
        $lp = Branch::where('code', 'LP01')->firstOrFail();
        $ea = Branch::where('code', 'EA01')->firstOrFail();
        $seller = User::where('email', 'vendedor.lp01.01@importsystemgo.example')->firstOrFail();
        $foreignCustomer = Customer::where('branch_id', $ea->id)->firstOrFail();

        $this->actingAs($seller)->withSession(['active_branch_id' => $lp->id])->get(route('customers.show', $foreignCustomer))->assertNotFound();
    }

    public function test_customer_credit_history_is_hidden_without_credit_permission(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $seller = User::where('email', 'vendedor.lp01.01@importsystemgo.example')->firstOrFail();
        $customer = Customer::query()->where('branch_id', $branch->id)->whereHas('credits')->firstOrFail();
        $creditNumber = $customer->credits()->value('credit_number');

        $this->actingAs($seller)->withSession(['active_branch_id' => $branch->id])
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertDontSee($creditNumber)
            ->assertDontSee('Créditos y cuotas');

        $this->actingAs($seller)->withSession(['active_branch_id' => $branch->id])
            ->get(route('customers.index'))
            ->assertOk()
            ->assertDontSee('Saldo crédito');
    }

    public function test_sales_form_exposes_quick_customer_registration_to_authorized_seller(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $seller = User::where('email', 'vendedor.lp01.01@importsystemgo.example')->firstOrFail();

        $this->actingAs($seller)->withSession(['active_branch_id' => $branch->id])
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('Registrar cliente rápido');
    }

    public function test_duplicate_document_is_rejected_and_customer_can_be_edited(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $seller = User::where('email', 'vendedor.lp01.01@importsystemgo.example')->firstOrFail();
        $payload = ['customer_type' => 'natural', 'document_type' => 'ci', 'document_number' => '4455667', 'full_name' => 'Cliente Duplicidad'];

        $created = $this->actingAs($seller)->withSession(['active_branch_id' => $branch->id])->postJson(route('customers.quick-store'), $payload)->assertCreated();
        $this->actingAs($seller)->withSession(['active_branch_id' => $branch->id])->postJson(route('customers.quick-store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('document_number');
        $customer = Customer::findOrFail($created->json('customer.id'));
        $this->actingAs($seller)->withSession(['active_branch_id' => $branch->id])->put(route('customers.update', $customer), array_merge($payload, ['full_name' => 'Cliente Actualizado', 'is_active' => '0']))->assertRedirect(route('customers.show', $customer));
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'full_name' => 'Cliente Actualizado', 'is_active' => false, 'updated_by' => $seller->id]);
    }

    public function test_incompatible_cash_opening_is_blocked(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $cashier = User::where('email', 'cajero.lp01@importsystemgo.example')->firstOrFail();
        $register = CashRegister::where('branch_id', $branch->id)->firstOrFail();
        app(BranchContext::class)->activate($cashier, $branch);

        $this->expectException(\DomainException::class);
        app(CashService::class)->open($cashier, $branch, $register, '100.00', true, 'Intento duplicado');
    }

    public function test_inactive_cash_register_cannot_be_opened(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $cashier = User::where('email', 'cajero.lp01@importsystemgo.example')->firstOrFail();
        $register = CashRegister::where('branch_id', $branch->id)->firstOrFail();
        $register->forceFill(['is_active' => false])->save();
        app(BranchContext::class)->activate($cashier, $branch);

        $this->expectException(AuthorizationException::class);
        app(CashService::class)->open($cashier, $branch, $register, '100.00', true, 'Apertura no permitida');
    }

    public function test_mixed_payment_is_idempotent_and_only_cash_changes_expected_amount(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $cashier = User::where('email', 'cajero.lp01@importsystemgo.example')->firstOrFail();
        $session = CashSession::where('user_id', $cashier->id)->where('status', 'open')->firstOrFail();
        $sale = Sale::where('sale_number', 'DEMO-V-LP01-P01')->firstOrFail();
        app(BranchContext::class)->activate($cashier, $branch);
        $cashAmount = bcdiv((string) $sale->total, '2', 2);
        $qrAmount = bcsub((string) $sale->total, $cashAmount, 2);
        $beforeExpected = (string) $session->expected_amount;
        $lines = [
            ['method' => 'cash', 'amount' => $cashAmount, 'reference' => null, 'idempotency_key' => 'test:mixed:cash'],
            ['method' => 'qr', 'amount' => $qrAmount, 'reference' => 'QR-TEST-001', 'idempotency_key' => 'test:mixed:qr'],
        ];

        app(CashService::class)->collectMany($cashier, $branch, $session, $sale, $lines, true);
        app(CashService::class)->collectMany($cashier, $branch, $session->fresh(), $sale->fresh(), $lines, true);

        $this->assertSame(2, Payment::whereIn('idempotency_key', ['test:mixed:cash', 'test:mixed:qr'])->count());
        $this->assertSame(0, bccomp(bcadd($beforeExpected, $cashAmount, 2), (string) $session->fresh()->expected_amount, 2));
        $this->assertSame('paid', $sale->fresh()->status);
    }

    public function test_reusing_payment_idempotency_key_with_changed_payload_is_rejected(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $cashier = User::where('email', 'cajero.lp01@importsystemgo.example')->firstOrFail();
        $session = CashSession::where('user_id', $cashier->id)->where('status', 'open')->firstOrFail();
        $sale = Sale::where('branch_id', $branch->id)->where('status', 'pending_payment')->firstOrFail();
        app(BranchContext::class)->activate($cashier, $branch);
        $key = 'test:cash:payload-conflict';

        app(CashService::class)->collectMany($cashier, $branch, $session, $sale, [[
            'method' => 'cash', 'amount' => '1.00', 'reference' => null, 'idempotency_key' => $key,
        ]], true);

        $this->expectException(\DomainException::class);
        app(CashService::class)->collectMany($cashier, $branch, $session->fresh(), $sale->fresh(), [[
            'method' => 'cash', 'amount' => '2.00', 'reference' => null, 'idempotency_key' => $key,
        ]], true);
    }

    public function test_full_card_number_is_rejected_as_payment_reference(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $cashier = User::where('email', 'cajero.lp01@importsystemgo.example')->firstOrFail();
        $session = CashSession::where('user_id', $cashier->id)->where('status', 'open')->firstOrFail();
        $sale = Sale::where('branch_id', $branch->id)->where('status', 'pending_payment')->firstOrFail();
        app(BranchContext::class)->activate($cashier, $branch);

        $this->expectException(\InvalidArgumentException::class);
        app(CashService::class)->collectMany($cashier, $branch, $session, $sale, [[
            'method' => 'credit_card', 'amount' => '1.00', 'reference' => '4111 1111 1111 1111',
            'idempotency_key' => 'test:cash:unsafe-reference',
        ]], true);
    }

    public function test_reusing_cash_movement_key_with_changed_payload_is_rejected(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $cashier = User::where('email', 'cajero.lp01@importsystemgo.example')->firstOrFail();
        $session = CashSession::where('user_id', $cashier->id)->where('status', 'open')->firstOrFail();
        app(BranchContext::class)->activate($cashier, $branch);
        $key = 'test:movement:payload-conflict';

        app(CashService::class)->move($cashier, $branch, $session, 'income', '1.00', 'Prueba idempotente', $key, true);

        $this->expectException(\DomainException::class);
        app(CashService::class)->move($cashier, $branch, $session->fresh(), 'income', '2.00', 'Prueba idempotente', $key, true);
    }

    public function test_cash_difference_requires_note_and_manager_approval(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $cashier = User::where('email', 'cajero.lp01@importsystemgo.example')->firstOrFail();
        $manager = User::where('email', 'jefe.lp01@importsystemgo.example')->firstOrFail();
        $session = CashSession::where('user_id', $cashier->id)->where('status', 'open')->firstOrFail();
        app(BranchContext::class)->activate($cashier, $branch);

        $closed = app(CashService::class)->close($cashier, $branch, $session, [['denomination' => '200.00', 'quantity' => 0]], 'Faltante detectado durante el arqueo de prueba.');
        $this->assertSame('pending', $closed->approval_status);
        $this->assertNull($closed->open_user_token);

        app(BranchContext::class)->activate($manager, $branch);
        $approved = app(CashService::class)->approveClosure($manager, $branch, $closed);
        $this->assertSame('approved', $approved->approval_status);
        $this->assertSame($manager->id, $approved->approved_by);
    }

    public function test_cashier_cannot_approve_own_closure_even_if_granted_approval_permission(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $cashier = User::where('email', 'cajero.lp01@importsystemgo.example')->firstOrFail();
        $session = CashSession::where('user_id', $cashier->id)->where('status', 'open')->firstOrFail();
        app(BranchContext::class)->activate($cashier, $branch);
        Role::query()->where('name', 'cajero')->firstOrFail()->givePermissionTo(Permission::query()->where('name', 'cash.approve')->firstOrFail());
        $closed = app(CashService::class)->close($cashier, $branch, $session, [['denomination' => '200.00', 'quantity' => 0]], 'Diferencia para validar segregación.');

        $this->expectException(AuthorizationException::class);
        app(CashService::class)->approveClosure($cashier, $branch, $closed);
    }

    public function test_operations_dashboard_rejects_a_branch_outside_territory(): void
    {
        $lp = Branch::where('code', 'LP01')->firstOrFail();
        $ea = Branch::where('code', 'EA01')->firstOrFail();
        $operations = User::where('email', 'operaciones.lp@importsystemgo.example')->firstOrFail();

        $this->actingAs($operations)->withSession(['active_branch_id' => $lp->id])->get(route('dashboard', ['branch_id' => $ea->id]))->assertForbidden();
    }

    public function test_seller_dashboard_hides_cash_and_credit_metrics_and_shows_attended_customers(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $seller = User::where('email', 'vendedor.lp01.01@importsystemgo.example')->firstOrFail();

        $this->actingAs($seller)->withSession(['active_branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Financiado')
            ->assertDontSee('Cuentas por cobrar')
            ->assertDontSee('Cajas abiertas')
            ->assertSee('Clientes atendidos');
    }

    public function test_cashier_dashboard_aggregates_only_the_cashiers_own_turn(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $cashier = User::where('email', 'cajero.lp01@importsystemgo.example')->firstOrFail();
        app(BranchContext::class)->activate($cashier, $branch);
        $session = CashSession::where('user_id', $cashier->id)->where('status', 'open')->firstOrFail();
        $expectedPayments = (float) Payment::where('cash_session_id', $session->id)->sum('amount');

        $dashboard = app(DashboardService::class)->for($cashier);

        $this->assertSame('cajero', $dashboard['primary']);
        $this->assertNull($dashboard['cashState']);
        $this->assertEqualsWithDelta($expectedPayments, collect($dashboard['charts']['payments'])->sum('value'), 0.01);
        $this->assertContains('Estado del turno', collect($dashboard['metrics'])->pluck('label')->all());
        $this->assertContains('Ventas pendientes', collect($dashboard['metrics'])->pluck('label')->all());
    }
}
