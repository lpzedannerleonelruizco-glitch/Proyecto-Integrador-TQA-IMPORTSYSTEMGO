<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Authorization\BranchContext;
use App\Services\CashService;
use App\Services\CreditService;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->guardEnvironment();
        DB::transaction(function (): void {
            $catalog = $this->catalog();
            $suppliers = $this->suppliers();
            $admin = User::where('email', 'admin.demo@importsystemgo.example')->firstOrFail();
            foreach (Branch::where('is_demo', true)->orderBy('code')->get() as $branch) {
                $this->seedBranch($branch, $admin, $catalog);
            }
            $this->seedCredit($catalog->first());
            $this->seedPurchase($suppliers->first(), $catalog->last());
        }, 3);
    }

    private function guardEnvironment(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('demo.retail_enabled')) {
            throw new \RuntimeException('Datos operativos DEMO bloqueados fuera de un entorno autorizado.');
        }
        if (! app()->environment('testing') && DB::connection()->getDatabaseName() !== config('demo.database')) {
            throw new \RuntimeException('La conexión no apunta a la base DEMO autorizada.');
        }
        foreach (['products', 'inventory_balances', 'sales', 'cash_sessions', 'credit_accounts', 'purchase_orders'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException("Falta la tabla operativa {$table}.");
            }
        }
    }

    private function catalog()
    {
        $categories = collect([
            'AUDIO' => 'Audio y sonido', 'TV' => 'Televisores', 'COMPUTO' => 'Computación',
            'GAMING' => 'Gaming', 'REDES' => 'Redes y energía',
        ])->mapWithKeys(fn (string $name, string $code) => [$code => Category::firstOrCreate(['code' => $code], ['name' => $name, 'is_demo' => true])]);
        $brands = collect(['SONIQ' => 'SoniqPro', 'NOVA' => 'NovaTech', 'PULSO' => 'Pulso Audio', 'VERTEX' => 'Vertex Digital'])
            ->mapWithKeys(fn (string $name, string $code) => [$code => Brand::firstOrCreate(['code' => $code], ['name' => $name, 'is_demo' => true])]);
        $definitions = [
            ['AUD-SPK-120', 'Parlante activo 12 pulgadas', 'AUDIO', 'SONIQ', '1899.00'],
            ['AUD-SPK-150', 'Parlante activo 15 pulgadas', 'AUDIO', 'SONIQ', '2499.00'],
            ['AUD-SBR-500', 'Barra de sonido 5.1', 'AUDIO', 'PULSO', '1590.00'],
            ['AUD-MIX-008', 'Consola mezcladora 8 canales', 'AUDIO', 'PULSO', '1240.00'],
            ['AUD-MIC-200', 'Micrófono inalámbrico dual', 'AUDIO', 'SONIQ', '690.00'],
            ['AUD-SUB-180', 'Subwoofer amplificado 18 pulgadas', 'AUDIO', 'PULSO', '3290.00'],
            ['TV-LED-055', 'Televisor 55 pulgadas 4K', 'TV', 'VERTEX', '4290.00'],
            ['PC-NBK-514', 'Notebook 15 pulgadas i5', 'COMPUTO', 'NOVA', '5890.00'],
            ['PROY-4200', 'Proyector 4200 lúmenes', 'COMPUTO', 'VERTEX', '3790.00'],
            ['GAME-CON-1T', 'Consola de videojuegos 1 TB', 'GAMING', 'NOVA', '5190.00'],
            ['NET-RTR-AX', 'Router Wi-Fi 6 AX3000', 'REDES', 'NOVA', '790.00'],
            ['UPS-1500VA', 'UPS interactiva 1500 VA', 'REDES', 'VERTEX', '1190.00'],
        ];

        return collect($definitions)->map(function (array $row) use ($categories, $brands) {
            return Product::firstOrCreate(['sku' => $row[0]], [
                'category_id' => $categories[$row[2]]->id, 'brand_id' => $brands[$row[3]]->id,
                'name' => $row[1], 'description' => 'Artículo ficticio para demostración académica.',
                'list_price' => $row[4], 'is_active' => true, 'is_demo' => true,
            ]);
        });
    }

    private function suppliers()
    {
        return collect([
            ['DEMO-AUDIO', 'Distribuidora Andina de Audio'],
            ['DEMO-TECH', 'Tecnología Altiplano'],
            ['DEMO-HOME', 'Comercial Hogar Digital'],
        ])->map(fn (array $row) => Supplier::firstOrCreate(['code' => $row[0]], ['name' => $row[1], 'contact_name' => 'Contacto Demo', 'phone' => '70000000', 'is_active' => true, 'is_demo' => true]));
    }

    private function seedBranch(Branch $branch, User $admin, $products): void
    {
        $context = app(BranchContext::class);
        $warehouse = $branch->warehouses()->active()->firstOrFail();
        $context->activate($admin, $branch);
        foreach ($products as $index => $product) {
            app(InventoryService::class)->receive($admin, $branch, $warehouse, $product, (string) (12 + (($branch->id + $index) % 19)), "demo:opening:{$branch->code}:{$product->sku}", true);
        }
        CashRegister::firstOrCreate(['branch_id' => $branch->id, 'code' => 'CAJA-01'], ['name' => 'Caja principal', 'is_active' => true, 'is_demo' => true]);
        $customers = collect(range(1, 3))->map(fn (int $number) => Customer::firstOrCreate(
            ['branch_id' => $branch->id, 'document_number' => "DEMO-{$branch->code}-{$number}"],
            ['customer_type' => 'natural', 'document_type' => 'other', 'document_key' => 'other:demo-'.strtolower($branch->code)."-{$number}:", 'full_name' => "Cliente Demo {$branch->code} {$number}", 'phone' => '7000000'.$number, 'city' => str_starts_with($branch->code, 'LP') ? 'La Paz' : 'El Alto', 'is_demo' => true]
        ));
        $seller = User::where('email', 'vendedor.'.strtolower($branch->code).'.01@importsystemgo.example')->firstOrFail();
        $cashier = User::where('email', 'cajero.'.strtolower($branch->code).'@importsystemgo.example')->firstOrFail();
        $paidNumber = "DEMO-V-{$branch->code}-001";
        if (! Sale::where('sale_number', $paidNumber)->exists()) {
            $context->activate($seller, $branch);
            $sale = app(SaleService::class)->createPending($seller, $branch, [['product_id' => $products[($branch->id + 1) % $products->count()]->id, 'quantity' => 1]], $customers->first(), true);
            $sale->forceFill(['sale_number' => $paidNumber, 'sold_at' => now()->subDays($branch->id % 6)])->save();
            $context->activate($cashier, $branch);
            $register = CashRegister::where('branch_id', $branch->id)->where('code', 'CAJA-01')->firstOrFail();
            $session = CashSession::where('cash_register_id', $register->id)->where('status', 'open')->first();
            $session ??= app(CashService::class)->open($cashier, $branch, $register, '200.00', true);
            $method = ['cash', 'qr', 'card'][$branch->id % 3];
            app(CashService::class)->collect($cashier, $branch, $session, $sale, $method, "demo:payment:{$branch->code}:001", true, null, $method === 'cash' ? null : "DEMO-REF-{$branch->code}");
        }
        $pendingNumber = "DEMO-V-{$branch->code}-P01";
        if (! Sale::where('sale_number', $pendingNumber)->exists()) {
            $context->activate($seller, $branch);
            $sale = app(SaleService::class)->createPending($seller, $branch, [['product_id' => $products[($branch->id + 3) % $products->count()]->id, 'quantity' => 1]], $customers->last(), true);
            $sale->forceFill(['sale_number' => $pendingNumber])->save();
        }
        $period = ['period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->endOfMonth()->toDateString()];
        DB::table('sales_goals')->updateOrInsert(
            ['branch_id' => $branch->id, 'scope_key' => 'branch'] + $period,
            ['user_id' => null, 'target_amount' => '85000.00', 'is_demo' => true, 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('sales_goals')->updateOrInsert(
            ['branch_id' => $branch->id, 'scope_key' => "user:{$seller->id}"] + $period,
            ['user_id' => $seller->id, 'target_amount' => '25000.00', 'is_demo' => true, 'created_at' => now(), 'updated_at' => now()]
        );
        $context->clear($admin);
    }

    private function seedCredit(Product $product): void
    {
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $number = 'DEMO-V-LP01-C01';
        if (Sale::where('sale_number', $number)->exists()) {
            return;
        }
        $context = app(BranchContext::class);
        $seller = User::where('email', 'vendedor.lp01.01@importsystemgo.example')->firstOrFail();
        $advisor = User::where('email', 'creditos.lp01@importsystemgo.example')->firstOrFail();
        $customer = Customer::where('branch_id', $branch->id)->firstOrFail();
        $context->activate($seller, $branch);
        $sale = app(SaleService::class)->createPending($seller, $branch, [['product_id' => $product->id, 'quantity' => 1]], $customer, true);
        $sale->forceFill(['sale_number' => $number, 'sold_at' => now()->subDay()])->save();
        $context->activate($advisor, $branch);
        $credit = app(CreditService::class)->approve($advisor, $branch, $sale, 6, true);
        $credit->forceFill(['credit_number' => 'DEMO-CR-LP01-001'])->save();
        $context->clear($advisor);
    }

    private function seedPurchase(Supplier $supplier, Product $product): void
    {
        $number = 'DEMO-OC-LP01-001';
        if (PurchaseOrder::where('order_number', $number)->exists()) {
            return;
        }
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $buyer = User::where('email', 'compras.lp01@importsystemgo.example')->firstOrFail();
        $context = app(BranchContext::class);
        $context->activate($buyer, $branch);
        $order = app(PurchaseService::class)->create($buyer, $branch, $supplier, $product, 10, '850.00', today()->addWeek()->toDateString(), true);
        $order->forceFill(['order_number' => $number])->save();
        $context->clear($buyer);
    }
}
