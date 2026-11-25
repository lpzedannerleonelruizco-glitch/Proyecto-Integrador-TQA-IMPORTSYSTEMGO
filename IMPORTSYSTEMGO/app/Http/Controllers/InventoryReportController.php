<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Authorization\BranchContext;
use App\Services\Authorization\GlobalAuthorizationService;
use App\Services\Authorization\OperationalAreaAccessService;
use App\Services\Authorization\ScopedAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryReportController extends Controller
{
    public function __construct(
        private readonly BranchContext $context,
        private readonly ScopedAuthorizationService $authorization,
        private readonly GlobalAuthorizationService $globalAuthorization,
        private readonly OperationalAreaAccessService $areas,
    ) {}

    public function index(Request $request)
    {
        [$branch, $branchIds, $filters, $data] = $this->reportData($request);
        $canExport = $this->globalAuthorization->allows($request->user(), 'reports.export')
            || $this->authorization->allows($request->user(), 'reports.export');

        return view('retail.inventory-report', compact('branch', 'branchIds', 'filters', 'canExport') + $data);
    }

    public function print(Request $request)
    {
        abort_unless($this->globalAuthorization->allows($request->user(), 'reports.export')
            || $this->authorization->allows($request->user(), 'reports.export'), 403);
        [$branch, $branchIds, $filters, $data] = $this->reportData($request);

        return view('retail.inventory-report-print', compact('branch', 'branchIds', 'filters') + $data);
    }

    public function export(Request $request, string $format): StreamedResponse|Response
    {
        abort_unless(in_array($format, ['csv', 'xls'], true), 404);
        abort_unless($this->globalAuthorization->allows($request->user(), 'reports.export')
            || $this->authorization->allows($request->user(), 'reports.export'), 403);
        [$branch, $branchIds, $filters, $data] = $this->reportData($request);
        $rows = $data['inventoryRows']->map(fn ($row) => [
            $row->branch_code,
            $row->warehouse_name,
            $row->sku,
            $row->product_name,
            $row->category_name,
            $row->brand_name,
            $row->physical_quantity,
            $row->reserved_quantity,
            $row->available_quantity,
            $row->minimum_stock,
            $row->acquisition_cost,
            $row->inventory_value,
        ]);
        $filename = 'importsystemgo-inventory-'.now()->format('Ymd-His');

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($rows): void {
                $output = fopen('php://output', 'wb');
                if ($output === false) {
                    throw new \RuntimeException('No se pudo abrir la salida CSV.');
                }
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, ['Sucursal', 'Almacén', 'SKU', 'Producto', 'Categoría', 'Marca', 'Físico', 'Reservado', 'Disponible', 'Mínimo', 'Costo unitario', 'Valor inventario']);
                foreach ($rows as $row) {
                    fputcsv($output, array_map([$this, 'safeCsvCell'], $row->all()));
                }
                fclose($output);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $html = '<html><head><meta charset="utf-8"><style>table{border-collapse:collapse}th,td{border:1px solid #999;padding:5px}th{background:#e9eef5}</style></head><body><table><thead><tr>';
        foreach (['Sucursal', 'Almacén', 'SKU', 'Producto', 'Categoría', 'Marca', 'Físico', 'Reservado', 'Disponible', 'Mínimo', 'Costo unitario', 'Valor inventario'] as $heading) {
            $html .= '<th>'.e($heading).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>'.e((string) $cell).'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xls"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array{Branch, list<int>, array<string, string|null>, array<string, mixed>} */
    private function reportData(Request $request): array
    {
        $branch = $this->context->current();
        abort_unless($branch && $this->authorization->allows($request->user(), 'reports.inventory.view', $branch), 403);
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sku' => ['nullable', 'string', 'max:48'],
        ]);
        $isGlobal = $this->globalAuthorization->allows($request->user(), 'reports.inventory.view', $branch);
        $branches = Branch::query()->where('is_active', true);
        if ($isGlobal) {
            $branches = $this->areas->constrainBranches($branches, $request->user());
            $branchIds = $branches->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            $branchIds = [$branch->id];
        }
        if ($branchIds === []) {
            abort(403, 'No hay sucursales autorizadas para este reporte.');
        }
        $warehouseIds = DB::table('warehouses')->whereIn('branch_id', $branchIds)->pluck('id');
        $base = DB::table('inventory_balances')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
            ->join('branches', 'branches.id', '=', 'warehouses.branch_id')
            ->join('products', 'products.id', '=', 'inventory_balances.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->whereIn('branches.id', $branchIds)
            ->when($filters['sku'] ?? null, fn ($query, string $sku) => $query->where('products.sku', $sku));
        $inventoryRows = (clone $base)
            ->selectRaw('branches.code as branch_code, warehouses.name as warehouse_name, products.sku, products.name as product_name, categories.name as category_name, brands.name as brand_name, inventory_balances.physical_quantity, inventory_balances.reserved_quantity, (inventory_balances.physical_quantity - inventory_balances.reserved_quantity) as available_quantity, products.minimum_stock, products.acquisition_cost, (inventory_balances.physical_quantity * products.acquisition_cost) as inventory_value')
            ->orderBy('branches.code')->orderBy('warehouses.name')->orderBy('products.sku')
            ->limit(10000)->get();
        $aggregate = DB::table('inventory_balances')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
            ->join('products', 'products.id', '=', 'inventory_balances.product_id')
            ->whereIn('warehouses.id', $warehouseIds)
            ->selectRaw('COUNT(DISTINCT products.id) as product_count, COALESCE(SUM(inventory_balances.physical_quantity), 0) as physical, COALESCE(SUM(inventory_balances.physical_quantity - inventory_balances.reserved_quantity), 0) as available, COALESCE(SUM(inventory_balances.physical_quantity * products.acquisition_cost), 0) as value')
            ->first();
        $lowStock = (clone $base)->whereRaw('(inventory_balances.physical_quantity - inventory_balances.reserved_quantity) <= products.minimum_stock')->count();
        $outOfStock = (clone $base)->where('inventory_balances.physical_quantity', '<=', 0)->count();
        $movementQuery = DB::table('inventory_movements')
            ->join('branches', 'branches.id', '=', 'inventory_movements.branch_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_movements.warehouse_id')
            ->join('products', 'products.id', '=', 'inventory_movements.product_id')
            ->whereIn('branches.id', $branchIds)
            ->when($filters['sku'] ?? null, fn ($query, string $sku) => $query->where('products.sku', $sku))
            ->when($filters['from'] ?? null, fn ($query, string $date) => $query->whereDate('inventory_movements.occurred_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, string $date) => $query->whereDate('inventory_movements.occurred_at', '<=', $date));
        $movements = (clone $movementQuery)->select(
            'branches.code as branch_code', 'warehouses.name as warehouse_name', 'products.sku',
            'products.name as product_name', 'inventory_movements.type', 'inventory_movements.quantity_change',
            'inventory_movements.balance_after', 'inventory_movements.source_reference', 'inventory_movements.occurred_at',
        )->orderByDesc('inventory_movements.occurred_at')->limit(100)->get();
        $topProducts = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereIn('sales.branch_id', $branchIds)->where('sales.status', 'paid')
            ->when($filters['sku'] ?? null, fn ($query, string $sku) => $query->where('products.sku', $sku))
            ->when($filters['from'] ?? null, fn ($query, string $date) => $query->whereDate('sales.sold_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, string $date) => $query->whereDate('sales.sold_at', '<=', $date))
            ->select('products.sku', 'products.name')
            ->selectRaw('SUM(sale_items.quantity) as quantity_sold, SUM(sale_items.line_total) as revenue')
            ->groupBy('products.id', 'products.sku', 'products.name')->orderByDesc('quantity_sold')->limit(10)->get();
        $transferAreaBranchIds = $branchIds;
        $transferEndpointBranchIds = $isGlobal ? $branchIds : [$branch->id];
        if (! $isGlobal && $this->authorization->allows($request->user(), 'inventory.transfers.same_area', $branch)
            && $branch->operational_area_id !== null) {
            $transferAreaBranchIds = Branch::query()->where('operational_area_id', $branch->operational_area_id)
                ->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        $transferQuery = DB::table('inventory_transfers')
            ->join('warehouses as source', 'source.id', '=', 'inventory_transfers.source_warehouse_id')
            ->join('branches as source_branch', 'source_branch.id', '=', 'source.branch_id')
            ->join('warehouses as destination', 'destination.id', '=', 'inventory_transfers.destination_warehouse_id')
            ->join('branches as destination_branch', 'destination_branch.id', '=', 'destination.branch_id')
            ->whereIn('source_branch.id', $transferAreaBranchIds)
            ->whereIn('destination_branch.id', $transferAreaBranchIds)
            ->where(function ($query) use ($transferEndpointBranchIds): void {
                $query->whereIn('source_branch.id', $transferEndpointBranchIds)
                    ->orWhereIn('destination_branch.id', $transferEndpointBranchIds);
            })
            ->when($filters['from'] ?? null, fn ($query, string $date) => $query->whereDate('inventory_transfers.requested_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, string $date) => $query->whereDate('inventory_transfers.requested_at', '<=', $date));
        $transfers = (clone $transferQuery)
            ->select('inventory_transfers.id', 'source_branch.code as source_code', 'source.name as source_name',
                'destination_branch.code as destination_code', 'destination.name as destination_name',
                'inventory_transfers.status', 'inventory_transfers.requested_at')
            ->orderByDesc('inventory_transfers.requested_at')->limit(100)->get();
        $adjustments = DB::table('inventory_adjustments')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_adjustments.warehouse_id')
            ->join('branches', 'branches.id', '=', 'warehouses.branch_id')
            ->join('products', 'products.id', '=', 'inventory_adjustments.product_id')
            ->whereIn('branches.id', $branchIds)
            ->when($filters['sku'] ?? null, fn ($query, string $sku) => $query->where('products.sku', $sku))
            ->when($filters['from'] ?? null, fn ($query, string $date) => $query->whereDate('inventory_adjustments.requested_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, string $date) => $query->whereDate('inventory_adjustments.requested_at', '<=', $date))
            ->select('branches.code as branch_code', 'warehouses.name as warehouse_name', 'products.sku',
                'inventory_adjustments.quantity_before', 'inventory_adjustments.quantity_after',
                'inventory_adjustments.quantity_change', 'inventory_adjustments.reason_code',
                'inventory_adjustments.status', 'inventory_adjustments.requested_at')
            ->orderByDesc('inventory_adjustments.requested_at')->limit(100)->get();

        return [$branch, $branchIds, $filters, [
            'inventoryRows' => $inventoryRows,
            'metrics' => [
                'products' => (int) ($aggregate->product_count ?? 0),
                'physical' => (string) ($aggregate->physical ?? '0'),
                'available' => (string) ($aggregate->available ?? '0'),
                'value' => (string) ($aggregate->value ?? '0'),
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
            ],
            'movements' => $movements,
            'topProducts' => $topProducts,
            'transfers' => $transfers,
            'adjustments' => $adjustments,
        ]];
    }

    private function safeCsvCell(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^\s*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
