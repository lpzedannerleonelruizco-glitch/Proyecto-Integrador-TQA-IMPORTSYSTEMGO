<?php

namespace App\Http\Controllers;

use App\Models\InventoryBalance;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\Authorization\BranchContext;
use App\Services\Authorization\ScopedAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __invoke(Request $request, BranchContext $context, ScopedAuthorizationService $authorization)
    {
        $branch = $context->current();
        abort_unless($branch && $authorization->allows($request->user(), 'reports.branch.view', $branch), 403);

        $salesQuery = Sale::query()->whereBelongsTo($branch)->where('status', 'paid');
        $metrics = [
            'sales' => (clone $salesQuery)->count(),
            'revenue' => (string) (clone $salesQuery)->sum('total'),
            'average' => (string) ((clone $salesQuery)->avg('total') ?? 0),
            'low_stock' => InventoryBalance::query()->whereIn('warehouse_id', $branch->warehouses()->pluck('id'))->whereRaw('(physical_quantity - reserved_quantity) <= 5')->count(),
        ];
        $dailySales = (clone $salesQuery)->selectRaw('DATE(sold_at) as sale_date, COUNT(*) as sale_count, SUM(total) as total')
            ->where('sold_at', '>=', now()->subDays(6)->startOfDay())->groupBy(DB::raw('DATE(sold_at)'))->orderBy('sale_date')->get();
        $sellerRanking = (clone $salesQuery)->selectRaw('salesperson_user_id, COUNT(*) as sale_count, SUM(total) as total')
            ->with('salesperson:id,name')->groupBy('salesperson_user_id')->orderByDesc('total')->limit(8)->get();
        $paymentMix = Payment::query()->whereHas('sale', fn ($query) => $query->where('branch_id', $branch->id))
            ->selectRaw('method, COUNT(*) as payment_count, SUM(amount) as total')->groupBy('method')->orderByDesc('total')->get();

        return view('retail.reports', compact('branch', 'metrics', 'dailySales', 'sellerRanking', 'paymentMix'));
    }
}
