<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplenishmentDecisionRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Models\MlModelVersion;
use App\Models\ReplenishmentRecommendation;
use App\Services\Authorization\BranchContext;
use App\Services\MachineLearning\MachineLearningAccessService;
use App\Services\MachineLearning\ReplenishmentDecisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MachineLearningController extends Controller
{
    public function index(Request $request, BranchContext $context, MachineLearningAccessService $access)
    {
        $branch = $context->current();
        abort_unless($branch && $access->canView($request->user(), $branch), 403);
        $branchIds = $access->allowedBranchIds($request->user());
        $model = MlModelVersion::query()->whereIn('status', ['active', 'candidate'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderByDesc('trained_at')->first();
        $modelHistory = MlModelVersion::query()
            ->with('trainer:id,name,email')
            ->whereIn('status', ['active', 'candidate', 'rejected', 'archived'])
            ->orderByDesc('trained_at')
            ->limit(10)
            ->get();
        $recommendations = ReplenishmentRecommendation::query()
            ->with(['branch:id,code,name', 'warehouse:id,code,name', 'product.category:id,name', 'product:id,sku,name,category_id', 'forecast', 'decisionMaker:id,name'])
            ->whereIn('branch_id', $branchIds)
            ->when($request->filled('branch'), fn ($query) => $query->where('branch_id', $request->integer('branch')))
            ->when($request->filled('risk'), fn ($query) => $query->where('risk_level', $request->string('risk')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim($request->string('search')->toString());
                $query->whereHas('product', fn ($product) => $product
                    ->where('sku', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%'));
            })
            ->when($request->filled('category'), fn ($query) => $query->whereHas('product', fn ($product) => $product->where('category_id', $request->integer('category'))))
            ->when($request->input('sort') === 'quantity_asc', fn ($query) => $query->orderBy('recommended_quantity'))
            ->when($request->input('sort') !== 'quantity_asc', fn ($query) => $query->orderByDesc('recommended_quantity'))
            ->orderByRaw("CASE risk_level WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->paginate(20)->withQueryString();
        $summary = [
            'critical' => ReplenishmentRecommendation::whereIn('branch_id', $branchIds)->where('risk_level', 'critical')->where('status', 'pending')->count(),
            'high' => ReplenishmentRecommendation::whereIn('branch_id', $branchIds)->where('risk_level', 'high')->where('status', 'pending')->count(),
            'medium' => ReplenishmentRecommendation::whereIn('branch_id', $branchIds)->where('risk_level', 'medium')->where('status', 'pending')->count(),
            'low' => ReplenishmentRecommendation::whereIn('branch_id', $branchIds)->where('risk_level', 'low')->where('status', 'pending')->count(),
            'pending' => ReplenishmentRecommendation::whereIn('branch_id', $branchIds)->where('status', 'pending')->count(),
            'suggested_units' => (float) ReplenishmentRecommendation::whereIn('branch_id', $branchIds)->where('status', 'pending')->sum('recommended_quantity'),
            'products' => ReplenishmentRecommendation::whereIn('branch_id', $branchIds)->distinct()->count('product_id'),
        ];
        $summary['break_stock'] = ReplenishmentRecommendation::whereIn('branch_id', $branchIds)
            ->whereColumn('physical_quantity_snapshot', '<=', 'reserved_quantity_snapshot')
            ->where('status', 'pending')
            ->distinct()->count('product_id');
        $summary['coverage'] = $model ? (float) ($model->metrics['validation_rows'] ?? 0) : 0;
        $forecastChart = ReplenishmentRecommendation::query()
            ->with(['branch:id,code', 'product:id,name', 'forecast'])
            ->whereIn('branch_id', $branchIds)->orderByDesc('recommended_quantity')->limit(10)->get()
            ->map(fn (ReplenishmentRecommendation $item): array => [
                'label' => $item->branch->code.' · '.$item->product->name,
                'value' => (float) $item->forecast->predicted_quantity,
                'available' => max(0, (float) $item->physical_quantity_snapshot - (float) $item->reserved_quantity_snapshot),
            ])->all();
        $riskChart = collect(['critical', 'high', 'medium', 'low'])->map(fn (string $risk): array => [
            'label' => ucfirst($risk),
            'value' => ReplenishmentRecommendation::whereIn('branch_id', $branchIds)->where('risk_level', $risk)->where('status', 'pending')->count(),
        ])->all();
        $allowedBranches = Branch::query()->whereIn('id', $branchIds)->active()->orderBy('name')->get(['id', 'code', 'name']);
        $branchRiskChart = $allowedBranches->map(fn (Branch $allowed): array => [
            'label' => $allowed->code,
            'value' => ReplenishmentRecommendation::where('branch_id', $allowed->id)->where('risk_level', 'critical')->where('status', 'pending')->count(),
        ])->all();
        $topReplenishmentChart = ReplenishmentRecommendation::query()
            ->with(['product:id,name,sku'])
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'pending')
            ->orderByDesc('recommended_quantity')
            ->limit(8)
            ->get()
            ->map(fn (ReplenishmentRecommendation $item): array => [
                'label' => $item->product->sku,
                'value' => (float) $item->recommended_quantity,
            ])->all();
        $modelComparison = $model ? [
            ['label' => 'Modelo', 'value' => (float) ($model->metrics['wape'] ?? 0) * 100],
            ['label' => 'Línea base', 'value' => (float) ($model->metrics['baseline_wape'] ?? 0) * 100],
        ] : [];
        $categories = Category::query()->orderBy('name')->get(['id', 'name']);
        $simulation = null;
        $simulationItem = null;
        if ($request->filled('simulation_product')) {
            $simulationItem = ReplenishmentRecommendation::query()
                ->with(['product:id,sku,name', 'forecast'])
                ->whereIn('branch_id', $branchIds)
                ->where('product_id', $request->integer('simulation_product'))
                ->when($request->filled('simulation_branch'), fn ($query) => $query->where('branch_id', $request->integer('simulation_branch')))
                ->first();
            if ($simulationItem) {
                $factor = max(0.5, min(2.0, (float) $request->input('simulation_factor', 1)));
                $safetyStock = max(0, (float) $request->input('simulation_safety', $simulationItem->safety_stock));
                $leadDays = max(1, min(90, (int) $request->input('simulation_lead', 14)));
                $demand = (float) $simulationItem->forecast->predicted_quantity * $factor;
                $available = $request->filled('simulation_available')
                    ? max(0, (float) $request->input('simulation_available'))
                    : max(0, (float) $simulationItem->physical_quantity_snapshot - (float) $simulationItem->reserved_quantity_snapshot);
                $demandDuringLead = $demand / 30 * $leadDays;
                $projectedStock = $available - $demandDuringLead;
                $suggested = max(0, ceil($demandDuringLead + $safetyStock - $available));
                $simulation = [
                    'product' => $simulationItem->product,
                    'demand' => $demand,
                    'projected_stock' => $projectedStock,
                    'available' => $available,
                    'safety_stock' => $safetyStock,
                    'suggested' => $suggested,
                ];
            }
        }

        $historyItem = $simulationItem ?? ReplenishmentRecommendation::query()
            ->with(['product:id,name,sku', 'forecast'])
            ->whereIn('branch_id', $branchIds)
            ->orderByDesc('recommended_quantity')
            ->first();
        $demandHistory = [];
        $forecastBand = [];
        if ($historyItem) {
            $demandHistory = DB::table('ml_demand_observations')
                ->where('source', 'synthetic_demo')
                ->where('branch_id', $historyItem->branch_id)
                ->where('product_id', $historyItem->product_id)
                ->orderBy('observed_on')
                ->get(['observed_on', 'quantity'])
                ->map(fn ($row): array => [
                    'label' => (string) $row->observed_on,
                    'value' => (float) $row->quantity,
                ])->all();
            $forecastBand = [[
                'label' => $historyItem->forecast->forecast_date->format('d/m/Y'),
                'value' => (float) $historyItem->forecast->predicted_quantity,
                'lower' => (float) $historyItem->forecast->lower_bound,
                'upper' => (float) $historyItem->forecast->upper_bound,
            ]];
        }

        return view('machine-learning.forecasts', compact(
            'branch',
            'model',
            'modelHistory',
            'recommendations',
            'summary',
            'forecastChart',
            'riskChart',
            'modelComparison',
            'allowedBranches',
            'categories',
            'simulation',
            'branchRiskChart',
            'topReplenishmentChart',
            'demandHistory',
            'forecastBand',
            'historyItem',
            'access',
        ));
    }

    public function decide(
        ReplenishmentDecisionRequest $request,
        ReplenishmentRecommendation $recommendation,
        ReplenishmentDecisionService $decisions,
    ): RedirectResponse {
        $data = $request->validated();
        $decisions->decide($request->user(), $recommendation, $data['decision'], $data['note'] ?? null);

        return back()->with('status', 'La recomendación fue registrada; no se generó una compra automática.');
    }
}
