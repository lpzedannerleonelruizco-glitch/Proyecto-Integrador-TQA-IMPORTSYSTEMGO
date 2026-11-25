<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveCashClosureRequest;
use App\Http\Requests\ApproveCreditRequest;
use App\Http\Requests\CashMovementRequest;
use App\Http\Requests\CloseCashSessionRequest;
use App\Http\Requests\CollectPaymentRequest;
use App\Http\Requests\OpenCashSessionRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\Authorization\BranchContext;
use App\Services\Authorization\ScopedAuthorizationService;
use App\Services\CashService;
use App\Services\CreditService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Http\Request;

class RetailController extends Controller
{
    public function __construct(
        private readonly BranchContext $context,
        private readonly ScopedAuthorizationService $authorization,
    ) {}

    public function products(Request $request)
    {
        $branch = $this->branch($request, 'catalog.products.view');
        $warehouseIds = $branch->warehouses()->active()->pluck('id');
        $products = Product::query()->active()->with(['category', 'brand'])
            ->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(fn ($nested) => $nested->where('name', 'like', $term)->orWhere('sku', 'like', $term)->orWhere('barcode', 'like', $term));
            })
            ->withSum(['inventoryBalances as physical_stock' => fn ($query) => $query->whereIn('warehouse_id', $warehouseIds)], 'physical_quantity')
            ->withSum(['inventoryBalances as reserved_stock' => fn ($query) => $query->whereIn('warehouse_id', $warehouseIds)], 'reserved_quantity')
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('retail.products', compact('branch', 'products'));
    }

    public function inventory(Request $request)
    {
        $branch = $this->branch($request, 'inventory.stock.view');
        $warehouseIds = $branch->warehouses()->active()->pluck('id');
        $balances = InventoryBalance::query()->whereIn('warehouse_id', $warehouseIds)
            ->with(['product.brand', 'warehouse'])->orderBy('physical_quantity')->paginate(15);
        $movements = InventoryMovement::query()->where('branch_id', $branch->id)
            ->with(['product', 'warehouse', 'actor'])->latest('occurred_at')->limit(12)->get();

        return view('retail.inventory', compact('branch', 'balances', 'movements'));
    }

    public function sales(Request $request)
    {
        $branch = $this->branch($request, 'sales.view');
        $sales = Sale::query()->whereBelongsTo($branch)->with(['customer', 'salesperson', 'payments'])
            ->latest('sold_at')->paginate(15);

        return view('retail.sales', compact('branch', 'sales'));
    }

    public function createSale(Request $request)
    {
        $branch = $this->branch($request, 'sales.create');
        $warehouseIds = $branch->warehouses()->active()->pluck('id');
        $products = Product::query()->active()->with('brand')
            ->whereHas('inventoryBalances', fn ($query) => $query->whereIn('warehouse_id', $warehouseIds)->whereColumn('physical_quantity', '>', 'reserved_quantity'))
            ->orderBy('name')->get();
        $customers = Customer::query()->whereBelongsTo($branch)->where('is_active', true)->orderBy('full_name')->get();

        return view('retail.sale-create', compact('branch', 'products', 'customers'));
    }

    public function storeSale(StoreSaleRequest $request, SaleService $sales)
    {
        $branch = $this->branch($request, 'sales.create');
        $data = $request->validated();
        $customer = isset($data['customer_id']) ? Customer::findOrFail($data['customer_id']) : null;
        $sale = $sales->createPending($request->user(), $branch, $data['items'], $customer, (bool) config('demo.retail_enabled'));

        return redirect()->route('sales.index')->with('status', "Venta {$sale->sale_number} enviada a caja.");
    }

    public function cash(Request $request)
    {
        $branch = $this->branch($request, 'cash.view');
        $registers = CashRegister::query()->whereBelongsTo($branch)->where('is_active', true)->get();
        $session = CashSession::query()->where('user_id', $request->user()->id)->where('status', 'open')->with(['register', 'payments.sale', 'movements'])->first();
        $pendingSales = Sale::query()->whereBelongsTo($branch)->where('status', 'pending_payment')->with(['customer', 'salesperson', 'payments', 'items.product'])->oldest('sold_at')->get();
        $denominations = config('cash.denominations');

        return view('retail.cash', compact('branch', 'registers', 'session', 'pendingSales', 'denominations'));
    }

    public function openCash(OpenCashSessionRequest $request, CashService $cash)
    {
        $branch = $this->branch($request, 'cash.manage');
        $register = CashRegister::findOrFail($request->integer('cash_register_id'));
        $cash->open($request->user(), $branch, $register, (string) $request->input('opening_amount'), (bool) config('demo.retail_enabled'), $request->string('opening_note')->toString() ?: null);

        return back()->with('status', 'Caja abierta correctamente.');
    }

    public function collect(CollectPaymentRequest $request, CashService $cash)
    {
        $branch = $this->branch($request, 'payments.collect');
        $session = CashSession::query()->where('user_id', $request->user()->id)->where('status', 'open')->firstOrFail();
        $sale = Sale::findOrFail($request->integer('sale_id'));
        $cash->collectMany($request->user(), $branch, $session, $sale, $request->validated('payments'), (bool) config('demo.retail_enabled'));

        $sale->refresh();

        return back()->with('status', $sale->status === 'paid' ? "Venta {$sale->sale_number} cobrada completamente." : "Abono registrado en {$sale->sale_number}.");
    }

    public function closeCash(CloseCashSessionRequest $request, CashService $cash)
    {
        $branch = $this->branch($request, 'cash.manage');
        $session = CashSession::query()->where('user_id', $request->user()->id)->where('status', 'open')->firstOrFail();
        $cash->close($request->user(), $branch, $session, $request->validated('counts'), $request->string('closing_note')->toString() ?: null);

        return back()->with('status', 'Caja cerrada correctamente.');
    }

    public function cashMovement(CashMovementRequest $request, CashService $cash)
    {
        $branch = $this->branch($request, 'cash.manage');
        $session = CashSession::query()->where('user_id', $request->user()->id)->where('status', 'open')->firstOrFail();
        $cash->move($request->user(), $branch, $session, $request->string('type')->toString(), (string) $request->input('amount'), $request->string('reason')->toString(), $request->string('idempotency_key')->toString(), (bool) config('demo.retail_enabled'));

        return back()->with('status', 'Movimiento extraordinario registrado.');
    }

    public function cashSupervision(Request $request)
    {
        $branch = $this->branch($request, 'cash.approve');
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'status' => ['nullable', 'in:open,closed,pending,approved']]);
        $sessions = CashSession::query()->whereHas('register', fn ($query) => $query->where('branch_id', $branch->id))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('opened_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('opened_at', '<=', $date))
            ->when(($filters['status'] ?? null) === 'open', fn ($query) => $query->where('status', 'open'))
            ->when(($filters['status'] ?? null) === 'closed', fn ($query) => $query->where('status', 'closed'))
            ->when(in_array($filters['status'] ?? null, ['pending', 'approved'], true), fn ($query) => $query->where('approval_status', $filters['status']))
            ->with(['register', 'user', 'approver'])->latest('opened_at')->paginate(20)->withQueryString();

        return view('retail.cash-supervision', compact('branch', 'sessions'));
    }

    public function approveCash(ApproveCashClosureRequest $request, CashSession $session, CashService $cash)
    {
        $branch = $this->branch($request, 'cash.approve');
        $cash->approveClosure($request->user(), $branch, $session);

        return back()->with('status', 'Cierre aprobado correctamente.');
    }

    public function cashReceipt(Request $request, CashSession $session)
    {
        $branch = $this->branch($request, 'cash.view');
        abort_unless($session->register()->value('branch_id') === $branch->id, 403);
        abort_unless($session->user_id === $request->user()->id || $this->authorization->allows($request->user(), 'cash.approve', $branch), 403);
        abort_if($session->status !== 'closed', 404);
        $session->load(['register', 'user', 'payments.sale', 'movements', 'countDetails', 'approver']);

        return view('retail.cash-receipt', compact('branch', 'session'));
    }

    public function credits(Request $request)
    {
        $branch = $this->branch($request, 'credits.view');
        $credits = CreditAccount::query()->whereBelongsTo($branch)->with(['customer', 'advisor', 'installments'])->latest()->paginate(15);

        $pendingSales = Sale::query()->whereBelongsTo($branch)->where('status', 'pending_payment')->whereNotNull('customer_id')->with('customer')->get();

        return view('retail.credits', compact('branch', 'credits', 'pendingSales'));
    }

    public function approveCredit(ApproveCreditRequest $request, CreditService $credits)
    {
        $branch = $this->branch($request, 'credits.manage');
        $sale = Sale::findOrFail($request->integer('sale_id'));
        $account = $credits->approve($request->user(), $branch, $sale, $request->integer('term_count'), (bool) config('demo.retail_enabled'));

        return back()->with('status', "Crédito {$account->credit_number} aprobado.");
    }

    public function purchases(Request $request)
    {
        $branch = $this->branch($request, 'purchasing.view');
        $orders = PurchaseOrder::query()->whereBelongsTo($branch)->with(['supplier', 'creator', 'items'])->latest()->paginate(15);
        $suppliers = Supplier::query()->where('is_active', true)->orderBy('name')->get();
        $products = Product::query()->active()->orderBy('name')->get();

        return view('retail.purchases', compact('branch', 'orders', 'suppliers', 'products'));
    }

    public function storePurchase(StorePurchaseOrderRequest $request, PurchaseService $purchases)
    {
        $branch = $this->branch($request, 'purchasing.manage');
        $data = $request->validated();
        $order = $purchases->create($request->user(), $branch, Supplier::findOrFail($data['supplier_id']), Product::findOrFail($data['product_id']), (int) $data['quantity'], (string) $data['unit_cost'], $data['expected_at'] ?? null, (bool) config('demo.retail_enabled'));

        return back()->with('status', "Orden {$order->order_number} creada.");
    }

    private function branch(Request $request, string $permission)
    {
        $branch = $this->context->current();
        abort_unless($branch && $this->authorization->allows($request->user(), $permission, $branch), 403);

        return $branch;
    }
}
