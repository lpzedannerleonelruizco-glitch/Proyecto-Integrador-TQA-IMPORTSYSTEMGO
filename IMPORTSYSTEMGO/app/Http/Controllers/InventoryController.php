<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryAdjustmentRequest;
use App\Http\Requests\InventoryProductRequest;
use App\Http\Requests\InventoryReceiptRequest;
use App\Http\Requests\InventoryTransferRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryAdjustment;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Authorization\BranchContext;
use App\Services\Authorization\GlobalAuthorizationService;
use App\Services\Authorization\ScopedAuthorizationService;
use App\Services\InventoryCatalogService;
use App\Services\InventoryOperationsService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(
        private readonly BranchContext $context,
        private readonly ScopedAuthorizationService $authorization,
        private readonly GlobalAuthorizationService $globalAuthorization,
    ) {}

    public function index(Request $request)
    {
        $branch = $this->branch($request, 'inventory.stock.view');
        $warehouseIds = $branch->warehouses()->active()->pluck('id');
        $balances = InventoryBalance::query()->whereIn('warehouse_id', $warehouseIds);
        $metrics = [
            'products' => Product::where('is_active', true)->count(),
            'physical' => (string) (clone $balances)->sum('physical_quantity'),
            'available' => (string) (clone $balances)->selectRaw('COALESCE(SUM(physical_quantity - reserved_quantity), 0) as aggregate')->value('aggregate'),
            'value' => (string) DB::table('inventory_balances')
                ->join('products', 'products.id', '=', 'inventory_balances.product_id')
                ->whereIn('inventory_balances.warehouse_id', $warehouseIds)
                ->selectRaw('COALESCE(SUM(inventory_balances.physical_quantity * products.acquisition_cost), 0) as aggregate')
                ->value('aggregate'),
            'empty' => (clone $balances)->where('physical_quantity', '<=', 0)->count(),
        ];
        $lowStock = DB::table('products')
            ->leftJoin('inventory_balances', 'inventory_balances.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->where(function ($query) use ($warehouseIds) {
                $query->whereIn('inventory_balances.warehouse_id', $warehouseIds)
                    ->orWhereNull('inventory_balances.id');
            })
            ->groupBy('products.id', 'products.sku', 'products.name', 'products.minimum_stock')
            ->havingRaw('COALESCE(SUM(inventory_balances.physical_quantity - inventory_balances.reserved_quantity), 0) <= products.minimum_stock')
            ->select('products.id', 'products.sku', 'products.name', 'products.minimum_stock')
            ->orderBy('products.name')->limit(8)->get();
        $categoryTotals = DB::table('inventory_balances')
            ->join('products', 'products.id', '=', 'inventory_balances.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('inventory_balances.warehouse_id', $warehouseIds)
            ->select('categories.name', DB::raw('SUM(inventory_balances.physical_quantity) as quantity'))
            ->groupBy('categories.id', 'categories.name')->orderByDesc('quantity')->limit(6)->get();
        $balancesPage = (clone $balances)->with(['product.brand', 'warehouse'])->orderBy('physical_quantity')->paginate(15)->withQueryString();
        $movements = InventoryMovement::query()->where('branch_id', $branch->id)
            ->with(['product', 'warehouse', 'actor'])->latest('occurred_at')->limit(10)->get();

        return view('retail.inventory', compact('branch', 'balancesPage', 'movements', 'metrics', 'lowStock', 'categoryTotals'));
    }

    public function products(Request $request)
    {
        $branch = $this->branch($request, 'catalog.products.view');
        $warehouseIds = $branch->warehouses()->active()->pluck('id');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
        ]);
        $canManage = $this->globalAuthorization->allows($request->user(), 'catalog.products.manage');
        $products = Product::query()->with(['category', 'brand'])
            ->when(! $canManage || ($filters['status'] ?? 'active') === 'active', fn ($query) => $query->where('is_active', true))
            ->when($canManage && ($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $term = '%'.$term.'%';
                $query->where(fn ($nested) => $nested->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)->orWhere('barcode', 'like', $term));
            })
            ->when($filters['category_id'] ?? null, fn ($query, int $id) => $query->where('category_id', $id))
            ->when($filters['brand_id'] ?? null, fn ($query, int $id) => $query->where('brand_id', $id))
            ->withSum(['inventoryBalances as physical_stock' => fn ($query) => $query->whereIn('warehouse_id', $warehouseIds)], 'physical_quantity')
            ->withSum(['inventoryBalances as reserved_stock' => fn ($query) => $query->whereIn('warehouse_id', $warehouseIds)], 'reserved_quantity')
            ->orderBy('name')->paginate(15)->withQueryString();
        $categories = Category::query()->where('is_active', true)->orderBy('name')->get();
        $brands = Brand::query()->where('is_active', true)->orderBy('name')->get();

        return view('retail.inventory-products', compact('branch', 'products', 'categories', 'brands', 'canManage'));
    }

    public function createProduct(Request $request)
    {
        abort_unless($this->globalAuthorization->allows($request->user(), 'catalog.products.manage'), 403);
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $brands = Brand::where('is_active', true)->orderBy('name')->get();

        return view('retail.inventory-product-form', ['product' => new Product, 'categories' => $categories, 'brands' => $brands]);
    }

    public function storeProduct(InventoryProductRequest $request, InventoryCatalogService $catalog)
    {
        $product = $catalog->saveProduct($request->user(), $request->validated(), image: $request->file('image'));

        return redirect()->route('inventory.products.show', $product)->with('status', 'Producto registrado correctamente.');
    }

    public function showProduct(Request $request, Product $product)
    {
        $branch = $this->branch($request, 'catalog.products.view');
        abort_unless($product->is_active || $this->globalAuthorization->allows($request->user(), 'catalog.products.manage'), 404);
        $warehouseIds = $branch->warehouses()->active()->pluck('id');
        $product->load(['category', 'brand']);
        $balances = InventoryBalance::query()->where('product_id', $product->id)->whereIn('warehouse_id', $warehouseIds)
            ->with('warehouse')->orderBy('warehouse_id')->get();
        $movements = InventoryMovement::query()->where('product_id', $product->id)->whereIn('warehouse_id', $warehouseIds)
            ->with(['warehouse', 'actor'])->latest('occurred_at')->paginate(15);

        return view('retail.inventory-product-show', compact('branch', 'product', 'balances', 'movements'));
    }

    public function editProduct(Request $request, Product $product)
    {
        abort_unless($this->globalAuthorization->allows($request->user(), 'catalog.products.manage'), 403);
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $brands = Brand::where('is_active', true)->orderBy('name')->get();

        return view('retail.inventory-product-form', compact('product', 'categories', 'brands'));
    }

    public function updateProduct(InventoryProductRequest $request, Product $product, InventoryCatalogService $catalog)
    {
        $catalog->saveProduct($request->user(), $request->validated(), $product, $request->file('image'));

        return redirect()->route('inventory.products.show', $product)->with('status', 'Producto actualizado correctamente.');
    }

    public function toggleProduct(Request $request, Product $product, InventoryCatalogService $catalog)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $catalog->setProductActive($request->user(), $product, (bool) $data['is_active']);

        return back()->with('status', $data['is_active'] ? 'Producto activado.' : 'Producto desactivado.');
    }

    public function productImage(Request $request, Product $product)
    {
        $this->branch($request, 'catalog.products.view');
        abort_unless($product->is_active || $this->globalAuthorization->allows($request->user(), 'catalog.products.manage'), 404);
        abort_unless($product->image_path && Storage::disk('local')->exists($product->image_path), 404);
        $contents = Storage::disk('local')->get($product->image_path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response($contents, 200, ['Content-Type' => $mime, 'Cache-Control' => 'private, max-age=300']);
    }

    public function taxonomy(Request $request, string $type)
    {
        $this->authorizeCatalog($request);
        $type = $this->taxonomyType($type);
        $model = $type === 'categories' ? Category::class : Brand::class;
        $records = $model::query()->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request): void {
            $term = '%'.$request->string('q')->trim().'%';
            $query->where(fn ($nested) => $nested->where('name', 'like', $term)->orWhere('code', 'like', $term));
        })->orderBy('name')->paginate(20)->withQueryString();

        return view('retail.inventory-taxonomy', compact('type', 'records'));
    }

    public function createTaxonomy(Request $request, string $type)
    {
        $this->authorizeCatalog($request);
        $type = $this->taxonomyType($type);

        return view('retail.inventory-taxonomy-form', ['type' => $type, 'record' => null]);
    }

    public function storeTaxonomy(Request $request, string $type, InventoryCatalogService $catalog)
    {
        $this->authorizeCatalog($request);
        $type = $this->taxonomyType($type);
        $data = $this->taxonomyData($request, $type);
        $catalog->saveTaxonomy($request->user(), $type, $data);

        return redirect()->route('inventory.taxonomy.index', $this->taxonomyRouteType($type))->with('status', 'Registro de catálogo creado.');
    }

    public function editTaxonomy(Request $request, string $type, int $record)
    {
        $this->authorizeCatalog($request);
        $type = $this->taxonomyType($type);
        $model = $type === 'categories' ? Category::class : Brand::class;

        return view('retail.inventory-taxonomy-form', ['type' => $type, 'record' => $model::findOrFail($record)]);
    }

    public function updateTaxonomy(Request $request, string $type, int $record, InventoryCatalogService $catalog)
    {
        $this->authorizeCatalog($request);
        $type = $this->taxonomyType($type);
        $data = $this->taxonomyData($request, $type, $record);
        $model = $type === 'categories' ? Category::class : Brand::class;
        $catalog->saveTaxonomy($request->user(), $type, $data, $model::findOrFail($record));

        return redirect()->route('inventory.taxonomy.index', $this->taxonomyRouteType($type))->with('status', 'Registro de catálogo actualizado.');
    }

    public function createReceipt(Request $request)
    {
        $branch = $this->branch($request, 'inventory.stock.manage');
        $warehouses = $branch->warehouses()->active()->orderBy('name')->get();
        $products = Product::active()->with('brand')->orderBy('name')->get();

        return view('retail.inventory-receipt', compact('branch', 'warehouses', 'products'));
    }

    public function storeReceipt(InventoryReceiptRequest $request, InventoryService $inventory)
    {
        $branch = $this->branch($request, 'inventory.stock.manage');
        $data = $request->validated();
        $warehouse = Warehouse::query()->whereKey($data['warehouse_id'])->whereBelongsTo($branch)->firstOrFail();
        $product = Product::findOrFail($data['product_id']);
        $key = 'receipt:'.$warehouse->id.':'.hash('sha256', mb_strtolower(trim($data['reference'])));
        $identifiers = preg_split('/\R/u', (string) ($data['identifiers_text'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $inventory->receive(
            $request->user(), $branch, $warehouse, $product, (string) $data['quantity'], $key,
            (bool) config('demo.retail_enabled'), isset($data['unit_cost']) ? (string) $data['unit_cost'] : null,
            array_map('trim', $identifiers ?: []), $data['reference'], $data['observations'] ?? null,
        );

        return redirect()->route('inventory.index')->with('status', 'Ingreso confirmado; el saldo y el kardex fueron actualizados.');
    }

    public function transfers(Request $request)
    {
        $branch = $this->branch($request, 'inventory.stock.view');
        $warehouseIds = $branch->warehouses()->pluck('id');
        $transfers = InventoryTransfer::query()
            ->where(fn ($query) => $query->whereIn('source_warehouse_id', $warehouseIds)->orWhereIn('destination_warehouse_id', $warehouseIds))
            ->with(['source.branch', 'destination.branch', 'requester', 'items.product', 'items.serials'])
            ->latest()->paginate(15);
        $canRequest = $this->authorization->allows($request->user(), 'inventory.transfers.request', $branch);

        return view('retail.inventory-transfers', compact('branch', 'transfers', 'canRequest'));
    }

    public function createTransfer(Request $request)
    {
        $branch = $this->branch($request, 'inventory.transfers.request');
        $warehouses = Warehouse::query()->where('is_active', true)->with('branch')
            ->whereHas('branch', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();

        return view('retail.inventory-transfer-form', compact('branch', 'warehouses', 'products'));
    }

    public function storeTransfer(InventoryTransferRequest $request, InventoryOperationsService $operations)
    {
        $data = $request->validated();
        $transfer = $operations->requestTransfer(
            $request->user(),
            Warehouse::findOrFail($data['source_warehouse_id']),
            Warehouse::findOrFail($data['destination_warehouse_id']),
            $data['items'],
            $data['reason'],
            $data['idempotency_key'],
            (bool) config('demo.retail_enabled'),
        );

        return redirect()->route('inventory.transfers.index')->with('status', 'Transferencia solicitada para aprobación.');
    }

    public function approveTransfer(Request $request, InventoryTransfer $transfer, InventoryOperationsService $operations)
    {
        $operations->approveTransfer($request->user(), $transfer);

        return back()->with('status', 'Transferencia aprobada.');
    }

    public function rejectTransfer(Request $request, InventoryTransfer $transfer, InventoryOperationsService $operations)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $operations->rejectTransfer($request->user(), $transfer, $data['reason']);

        return back()->with('status', 'Transferencia rechazada con motivo auditado.');
    }

    public function cancelTransfer(Request $request, InventoryTransfer $transfer, InventoryOperationsService $operations)
    {
        $operations->cancelTransfer($request->user(), $transfer);

        return back()->with('status', 'Transferencia cancelada.');
    }

    public function dispatchTransfer(Request $request, InventoryTransfer $transfer, InventoryOperationsService $operations)
    {
        $operations->dispatchTransfer($request->user(), $transfer, (bool) config('demo.retail_enabled'));

        return back()->with('status', 'Transferencia despachada; el stock destino aún no fue incrementado.');
    }

    public function receiveTransfer(Request $request, InventoryTransfer $transfer, InventoryOperationsService $operations)
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'alpha_dash:ascii', 'max:96'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.transfer_item_id' => ['required', 'integer', 'distinct', 'exists:inventory_transfer_items,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'items.*.identifiers_text' => ['nullable', 'string', 'max:200000'],
        ]);
        $data['items'] = array_values(array_filter(
            $data['items'],
            fn (array $item): bool => isset($item['quantity']) && bccomp((string) $item['quantity'], '0', 2) === 1,
        ));
        if ($data['items'] === []) {
            throw ValidationException::withMessages(['items' => 'Registra al menos una cantidad recibida mayor que cero.']);
        }
        $data['items'] = array_map(function (array $item): array {
            $item['identifiers'] = array_map(
                'trim',
                preg_split('/\R/u', (string) ($item['identifiers_text'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            );
            unset($item['identifiers_text']);

            return $item;
        }, $data['items']);
        $operations->receiveTransfer(
            $request->user(), $transfer, $data['items'], $data['idempotency_key'],
            $data['note'] ?? null, (bool) config('demo.retail_enabled'),
        );

        return back()->with('status', 'Recepción aplicada al almacén destino.');
    }

    public function adjustments(Request $request)
    {
        $branch = $this->branch($request, 'inventory.stock.view');
        $warehouseIds = $branch->warehouses()->pluck('id');
        $adjustments = InventoryAdjustment::query()->whereIn('warehouse_id', $warehouseIds)
            ->with(['warehouse', 'product'])->latest()->paginate(20);
        $canRequest = $this->authorization->allows($request->user(), 'inventory.adjustments.request', $branch);

        return view('retail.inventory-adjustments', compact('branch', 'adjustments', 'canRequest'));
    }

    public function createAdjustment(Request $request)
    {
        $branch = $this->branch($request, 'inventory.adjustments.request');
        $warehouses = $branch->warehouses()->active()->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();

        return view('retail.inventory-adjustment-form', compact('branch', 'warehouses', 'products'));
    }

    public function storeAdjustment(InventoryAdjustmentRequest $request, InventoryOperationsService $operations)
    {
        $data = $request->validated();
        $branch = $this->branch($request, 'inventory.adjustments.request');
        $warehouse = Warehouse::whereKey($data['warehouse_id'])->whereBelongsTo($branch)->firstOrFail();
        $adjustment = $operations->requestAdjustment(
            $request->user(), $warehouse, Product::findOrFail($data['product_id']),
            (string) $data['quantity_after'], $data['reason_code'], $data['reason'],
            $data['idempotency_key'], (bool) config('demo.retail_enabled'),
        );

        return redirect()->route('inventory.adjustments.index')->with(
            'status',
            $adjustment->wasRecentlyCreated ? 'Ajuste solicitado; requiere aprobación antes de modificar existencias.' : 'La solicitud ya existía y no se duplicó.',
        );
    }

    public function approveAdjustment(Request $request, InventoryAdjustment $adjustment, InventoryOperationsService $operations)
    {
        $operations->approveAdjustment($request->user(), $adjustment, (bool) config('demo.retail_enabled'));

        return back()->with('status', 'Ajuste aprobado y registrado en kardex.');
    }

    public function toggleTaxonomy(Request $request, string $type, int $record, InventoryCatalogService $catalog)
    {
        $this->authorizeCatalog($request);
        $type = $this->taxonomyType($type);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $model = $type === 'categories' ? Category::class : Brand::class;
        $catalog->saveTaxonomy($request->user(), $type, $data + [
            'code' => ($model::findOrFail($record))->code,
            'name' => ($model::findOrFail($record))->name,
        ], $model::findOrFail($record));

        return back()->with('status', $data['is_active'] ? 'Registro activado.' : 'Registro desactivado.');
    }

    private function branch(Request $request, string $permission)
    {
        $branch = $this->context->current();
        abort_unless($branch && $this->authorization->allows($request->user(), $permission, $branch), 403);

        return $branch;
    }

    private function authorizeCatalog(Request $request): void
    {
        abort_unless($this->globalAuthorization->allows($request->user(), 'catalog.products.manage'), 403);
    }

    private function taxonomyData(Request $request, string $type, ?int $recordId = null): array
    {
        $type = $this->taxonomyType($type);
        $table = $type;
        $data = $request->validate([
            'code' => ['required', 'alpha_dash:ascii', 'max:32', Rule::unique($table, 'code')->ignore($recordId)],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        return $data;
    }

    private function taxonomyType(string $type): string
    {
        return match ($type) {
            'categories', 'categorias' => 'categories',
            'brands', 'marcas' => 'brands',
            default => abort(404),
        };
    }

    private function taxonomyRouteType(string $type): string
    {
        return $type === 'categories' ? 'categorias' : 'marcas';
    }
}
