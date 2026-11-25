<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\DatabaseTriggerAudit;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Authorization\BranchContext;
use App\Services\CashService;
use App\Services\InventoryCatalogService;
use App\Services\InventoryOperationsService;
use App\Services\InventoryService;
use App\Services\SaleService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DemoRetailSeeder;
use Database\Seeders\OperationalDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'demo.retail_enabled' => true,
            'demo.database' => $this->app->make('db')->connection()->getDatabaseName(),
        ]);
        $this->seed(AuthorizationSeeder::class);
        $this->seed(DemoRetailSeeder::class);
        $this->seed(OperationalDemoSeeder::class);
        $this->withoutMiddleware(AuthenticateSession::class);
    }

    public function test_administrator_can_create_catalog_and_product_but_cost_is_receipt_controlled(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $admin = User::where('email', 'admin.demo@importsystemgo.example')->firstOrFail();
        $this->actingAs($admin)->withSession(['active_branch_id' => $branch->id]);

        $this->post(route('inventory.taxonomy.store', 'categorias'), [
            'code' => 'DEMO-NEW-CATEGORY', 'name' => 'Categoría de prueba', 'is_active' => '1',
        ])->assertRedirect(route('inventory.taxonomy.index', 'categorias'));
        $this->post(route('inventory.taxonomy.store', 'marcas'), [
            'code' => 'DEMO-NEW-BRAND', 'name' => 'Marca de prueba', 'is_active' => '1',
        ])->assertRedirect(route('inventory.taxonomy.index', 'marcas'));

        $category = Category::where('code', 'DEMO-NEW-CATEGORY')->firstOrFail();
        $brand = Brand::where('code', 'DEMO-NEW-BRAND')->firstOrFail();
        $payload = [
            'sku' => 'TEST-ITEM-001',
            'barcode' => '999900001111',
            'name' => 'Artículo verificable',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'model' => 'M-001',
            'description' => 'Producto de prueba aislada.',
            'list_price' => '150.00',
            'minimum_stock' => '2.00',
            'unit_of_measure' => 'unidad',
            'tracking_mode' => 'quantity',
            'is_active' => '1',
        ];
        $this->post(route('inventory.products.store'), $payload)->assertRedirect();
        $product = Product::where('sku', 'TEST-ITEM-001')->firstOrFail();
        $this->assertSame('150.00', $product->list_price);
        $this->assertSame('0.00', $product->acquisition_cost);

        app(InventoryCatalogService::class)->saveProduct(
            $admin,
            $payload + ['acquisition_cost' => '999.00'],
            $product,
        );
        $this->assertSame('0.00', $product->fresh()->acquisition_cost);

        $updatedPayload = array_replace($payload, ['name' => 'Artículo actualizado']);
        $this->put(route('inventory.products.update', $product), $updatedPayload)->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Artículo actualizado']);

        $this->put(route('inventory.products.update', $product), $payload + ['acquisition_cost' => '0.01'])
            ->assertSessionHasErrors('acquisition_cost');
        $this->post(route('inventory.products.store'), $payload)->assertSessionHasErrors('sku');
        $this->post(route('inventory.products.store'), array_replace($payload, ['list_price' => '-1']))
            ->assertSessionHasErrors('list_price');
        $this->assertDatabaseHas('audit_logs', ['resource_type' => 'products', 'resource_id' => $product->id]);
    }

    public function test_cashier_cannot_create_or_edit_catalog_records(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $cashier = User::where('email', 'cajero.lp01@importsystemgo.example')->firstOrFail();
        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get(route('inventory.products.create'))->assertForbidden();
        $this->get(route('reports.inventory'))->assertForbidden();
    }

    public function test_receipt_is_idempotent_updates_cost_balance_kardex_and_audit(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $warehouse = $branch->warehouses()->active()->firstOrFail();
        $clerk = User::where('email', 'almacen.lp01@importsystemgo.example')->firstOrFail();
        $product = Product::where('sku', 'AUD-SPK-120')->firstOrFail();
        app(BranchContext::class)->activate($clerk, $branch);
        $before = InventoryBalance::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('physical_quantity');

        $movement = app(InventoryService::class)->receive(
            $clerk, $branch, $warehouse, $product, '2.00', 'test:receipt:inventory-management', true,
            '600.00', [], 'DOC-TEST-2026', 'Recepción de prueba',
        );
        $same = app(InventoryService::class)->receive(
            $clerk, $branch, $warehouse, $product, '2.00', 'test:receipt:inventory-management', true,
            '600.00', [], 'DOC-TEST-2026', 'Recepción de prueba',
        );

        $this->assertSame($movement->id, $same->id);
        $this->assertSame(bcadd((string) $before, '2.00', 2), bcadd((string) InventoryBalance::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('physical_quantity'), '0', 2));
        $this->assertSame('600.00', $movement->fresh()->unit_cost);
        $this->assertSame(1, InventoryMovement::where('reference_key', 'test:receipt:inventory-management')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.received', 'resource_id' => $movement->id]);
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->assertDatabaseHas('database_trigger_audits', [
                'event_type' => 'INSERT',
                'table_name' => 'inventory_movements',
                'record_id' => (string) $movement->id,
            ]);
        }
    }

    public function test_serialized_receipt_sale_and_payment_keep_one_unique_identifier(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $warehouse = $branch->warehouses()->active()->firstOrFail();
        $seller = User::where('email', 'vendedor.lp01.01@importsystemgo.example')->firstOrFail();
        $cashier = User::where('email', 'cajero.lp01@importsystemgo.example')->firstOrFail();
        $admin = User::where('email', 'admin.demo@importsystemgo.example')->firstOrFail();
        $category = Category::firstOrFail();
        $brand = Brand::firstOrFail();
        $product = Product::create([
            'category_id' => $category->id, 'brand_id' => $brand->id, 'sku' => 'SERIAL-TEST-001',
            'name' => 'Producto serializado de prueba', 'list_price' => '200.00', 'acquisition_cost' => '100.00',
            'minimum_stock' => '0.00', 'unit_of_measure' => 'unidad', 'tracking_mode' => 'serial',
            'requires_serial' => true, 'is_active' => true, 'is_demo' => true,
        ]);
        app(BranchContext::class)->activate($admin, $branch);
        app(InventoryService::class)->receive($admin, $branch, $warehouse, $product, '1', 'test:serial-receipt', true, '100.00', ['SERIAL-UNIQUE-0001']);
        app(BranchContext::class)->activate($seller, $branch);
        $sale = app(SaleService::class)->createPending($seller, $branch, [['product_id' => $product->id, 'quantity' => 1]], null, true);
        $this->assertDatabaseHas('inventory_serials', ['serial_number' => 'SERIAL-UNIQUE-0001', 'status' => 'reserved']);

        app(BranchContext::class)->activate($cashier, $branch);
        $session = CashSession::where('user_id', $cashier->id)->where('status', 'open')->firstOrFail();
        app(CashService::class)->collect($cashier, $branch, $session, $sale, 'cash', 'test:serial-payment', true);

        $serial = InventorySerial::where('serial_number', 'SERIAL-UNIQUE-0001')->firstOrFail();
        $this->assertSame('sold', $serial->status);
        $this->assertSame($sale->id, $serial->sale_id);
        $this->assertSame(1, $sale->items()->firstOrFail()->serials()->count());
        $this->assertDatabaseHas('inventory_movements', ['product_id' => $product->id, 'type' => 'sale', 'quantity_change' => '-1.00']);
    }

    public function test_transfer_approval_dispatch_and_partial_receipt_change_stock_only_at_each_confirmed_stage(): void
    {
        $sourceBranch = Branch::where('code', 'LP01')->firstOrFail();
        $destinationBranch = Branch::where('code', 'LP02')->firstOrFail();
        $source = $sourceBranch->warehouses()->active()->firstOrFail();
        $destination = $destinationBranch->warehouses()->active()->firstOrFail();
        $manager = User::where('email', 'jefe.lp01@importsystemgo.example')->firstOrFail();
        $operations = User::where('email', 'operaciones.lp@importsystemgo.example')->firstOrFail();
        $sourceClerk = User::where('email', 'almacen.lp01@importsystemgo.example')->firstOrFail();
        $destinationClerk = User::where('email', 'admin.demo@importsystemgo.example')->firstOrFail();
        $product = Product::where('sku', 'AUD-SPK-120')->firstOrFail();
        app(BranchContext::class)->activate($manager, $sourceBranch);
        $sourceBefore = InventoryBalance::where('warehouse_id', $source->id)->where('product_id', $product->id)->value('physical_quantity');
        $destinationBefore = InventoryBalance::where('warehouse_id', $destination->id)->where('product_id', $product->id)->value('physical_quantity');
        $transfer = app(InventoryOperationsService::class)->requestTransfer(
            $manager, $source, $destination, [['product_id' => $product->id, 'quantity' => '2']],
            'Rebalanceo de tienda', 'test-transfer-key-001', true,
        );
        $this->assertSame(bcadd((string) $sourceBefore, '0', 2), bcadd((string) InventoryBalance::where('warehouse_id', $source->id)->where('product_id', $product->id)->value('physical_quantity'), '0', 2));
        $this->assertSame(bcadd((string) $destinationBefore, '0', 2), bcadd((string) InventoryBalance::where('warehouse_id', $destination->id)->where('product_id', $product->id)->value('physical_quantity'), '0', 2));

        app(InventoryOperationsService::class)->approveTransfer($operations, $transfer);
        app(BranchContext::class)->activate($sourceClerk, $sourceBranch);
        app(InventoryOperationsService::class)->dispatchTransfer($sourceClerk, $transfer, true);
        $this->assertSame(bcsub((string) $sourceBefore, '2.00', 2), bcadd((string) InventoryBalance::where('warehouse_id', $source->id)->where('product_id', $product->id)->value('physical_quantity'), '0', 2));
        $this->assertSame(bcadd((string) $destinationBefore, '0', 2), bcadd((string) InventoryBalance::where('warehouse_id', $destination->id)->where('product_id', $product->id)->value('physical_quantity'), '0', 2));

        app(BranchContext::class)->activate($destinationClerk, $destinationBranch);
        $item = $transfer->items()->firstOrFail();
        $receipt = app(InventoryOperationsService::class)->receiveTransfer(
            $destinationClerk, $transfer, [['transfer_item_id' => $item->id, 'quantity' => '1.00']],
            'test-receipt-key-001', 'Recepción parcial', true,
        );
        app(InventoryOperationsService::class)->receiveTransfer(
            $destinationClerk, $transfer, [['transfer_item_id' => $item->id, 'quantity' => '1.00']],
            'test-receipt-key-001', 'Recepción parcial', true,
        );

        $this->assertSame(bcadd((string) $destinationBefore, '1.00', 2), bcadd((string) InventoryBalance::where('warehouse_id', $destination->id)->where('product_id', $product->id)->value('physical_quantity'), '0', 2));
        $this->assertSame('partially_received', InventoryTransfer::findOrFail($transfer->id)->status);
        $this->assertSame(1, $receipt->items()->count());
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->assertDatabaseHas('database_trigger_audits', [
                'event_type' => 'INSERT',
                'table_name' => 'inventory_transfers',
                'record_id' => (string) $transfer->id,
            ]);
            $this->assertDatabaseHas('database_trigger_audits', [
                'event_type' => 'UPDATE',
                'table_name' => 'inventory_transfers',
                'record_id' => (string) $transfer->id,
            ]);
            $this->assertDatabaseHas('database_trigger_audits', [
                'event_type' => 'INSERT',
                'table_name' => 'inventory_transfer_receipts',
                'record_id' => (string) $receipt->id,
            ]);
        }
    }

    public function test_adjustments_require_separate_approval_and_never_allow_negative_stock(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $warehouse = $branch->warehouses()->active()->firstOrFail();
        $manager = User::where('email', 'jefe.lp01@importsystemgo.example')->firstOrFail();
        $operations = User::where('email', 'operaciones.lp@importsystemgo.example')->firstOrFail();
        $product = Product::where('sku', 'AUD-SPK-120')->firstOrFail();
        app(BranchContext::class)->activate($manager, $branch);
        $balance = InventoryBalance::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->firstOrFail();
        $adjustment = app(InventoryOperationsService::class)->requestAdjustment(
            $manager, $warehouse, $product, bcadd((string) $balance->physical_quantity, '1.00', 2),
            'surplus', 'Conteo físico de prueba', 'test-adjustment-key-001', true,
        );
        $this->assertSame(bcadd((string) $balance->physical_quantity, '0', 2), bcadd((string) InventoryBalance::whereKey($balance->id)->value('physical_quantity'), '0', 2));
        app(InventoryOperationsService::class)->approveAdjustment($operations, $adjustment, true);
        $this->assertSame(bcadd((string) $balance->physical_quantity, '1.00', 2), bcadd((string) InventoryBalance::whereKey($balance->id)->value('physical_quantity'), '0', 2));
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->assertDatabaseHas('database_trigger_audits', [
                'event_type' => 'INSERT',
                'table_name' => 'inventory_adjustments',
                'record_id' => (string) $adjustment->id,
            ]);
            $this->assertDatabaseHas('database_trigger_audits', [
                'event_type' => 'UPDATE',
                'table_name' => 'inventory_adjustments',
                'record_id' => (string) $adjustment->id,
            ]);
        }
        $this->expectException(\DomainException::class);
        app(InventoryOperationsService::class)->requestAdjustment(
            $manager, $warehouse, $product, '-1.00', 'shortage', 'No debe aceptar stock negativo',
            'test-adjustment-key-negative', true,
        );
    }

    public function test_transfer_between_areas_is_denied_without_explicit_global_permission(): void
    {
        $sourceBranch = Branch::where('code', 'LP01')->firstOrFail();
        $destinationBranch = Branch::where('code', 'EA01')->firstOrFail();
        $manager = User::where('email', 'jefe.lp01@importsystemgo.example')->firstOrFail();
        $product = Product::where('sku', 'AUD-SPK-120')->firstOrFail();
        app(BranchContext::class)->activate($manager, $sourceBranch);

        $this->expectException(AuthorizationException::class);
        app(InventoryOperationsService::class)->requestTransfer(
            $manager,
            Warehouse::where('branch_id', $sourceBranch->id)->firstOrFail(),
            Warehouse::where('branch_id', $destinationBranch->id)->firstOrFail(),
            [['product_id' => $product->id, 'quantity' => '1']],
            'Intento no autorizado entre ciudades', 'test-cross-area-transfer', true,
        );
    }

    public function test_inventory_report_is_limited_to_the_current_branch_and_its_transfer_activity(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $source = Branch::where('code', 'LP02')->firstOrFail()->warehouses()->active()->firstOrFail();
        $destination = Branch::where('code', 'LP03')->firstOrFail()->warehouses()->active()->firstOrFail();
        InventoryTransfer::create([
            'public_id' => (string) Str::uuid(),
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'requested_by' => User::where('email', 'operaciones.lp@importsystemgo.example')->value('id'),
            'idempotency_key' => 'test-report-hidden-transfer',
            'status' => 'pending_approval',
            'reason' => 'Transferencia de otra sucursal para prueba de alcance',
            'requested_at' => now(),
            'is_demo' => true,
        ]);

        $manager = User::where('email', 'jefe.lp01@importsystemgo.example')->firstOrFail();
        $this->actingAs($manager)->withSession(['active_branch_id' => $branch->id])
            ->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee('LP01')
            ->assertDontSee('LP02')
            ->assertDontSee('LP03');
    }

    public function test_territorial_inventory_report_includes_only_assigned_city(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $operations = User::where('email', 'operaciones.lp@importsystemgo.example')->firstOrFail();

        $this->actingAs($operations)->withSession(['active_branch_id' => $branch->id])
            ->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee('LP01')
            ->assertSee('LP04')
            ->assertDontSee('EA01');
    }

    public function test_inventory_routes_fail_closed_when_required_migrations_are_missing(): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $admin = User::where('email', 'admin.demo@importsystemgo.example')->firstOrFail();
        Schema::dropIfExists('inventory_adjustments');

        $this->actingAs($admin)->withSession(['active_branch_id' => $branch->id])
            ->get(route('inventory.index'))
            ->assertStatus(503)
            ->assertSee('migraciones pendientes');
    }

    public function test_mysql_product_triggers_audit_insert_update_and_transaction_rollback(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Las pruebas de triggers requieren MySQL.');
        }

        $category = Category::firstOrFail();
        $brand = Brand::firstOrFail();
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'TRIGGER-PRODUCT-001',
            'name' => 'Producto de auditoría MySQL',
            'list_price' => '110.00',
            'acquisition_cost' => '55.00',
            'minimum_stock' => '1.00',
            'unit_of_measure' => 'unidad',
            'tracking_mode' => 'quantity',
            'is_active' => true,
            'is_demo' => true,
        ]);

        $this->assertDatabaseHas('database_trigger_audits', [
            'event_type' => 'INSERT',
            'table_name' => 'products',
            'record_id' => (string) $product->id,
        ]);

        $product->update(['acquisition_cost' => '57.50']);
        $updateAudit = DatabaseTriggerAudit::query()
            ->where('event_type', 'UPDATE')
            ->where('table_name', 'products')
            ->where('record_id', (string) $product->id)
            ->firstOrFail();
        $this->assertEquals(57.5, $updateAudit->payload['after']['acquisition_cost']);

        $auditCount = DatabaseTriggerAudit::query()
            ->where('table_name', 'products')
            ->where('record_id', (string) $product->id)
            ->count();
        try {
            DB::transaction(function () use ($product): void {
                $product->update(['acquisition_cost' => '61.00']);
                throw new \RuntimeException('Rollback trigger audit test.');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback trigger audit test.', $exception->getMessage());
        }

        $this->assertSame('57.50', $product->fresh()->acquisition_cost);
        $this->assertSame($auditCount, DatabaseTriggerAudit::query()
            ->where('table_name', 'products')
            ->where('record_id', (string) $product->id)
            ->count());
    }
}
