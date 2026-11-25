<?php

namespace App\Services\MachineLearning;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemandDatasetService
{
    /** @return array{metadata: array{source: string}, series: array<int, array<string, int|string>>, observations: array<int, array<string, int|string|float>>} */
    public function build(): array
    {
        $series = DB::table('branches')
            ->crossJoin('products')
            ->where('branches.is_active', true)
            ->where('products.is_active', true)
            ->select([
                'branches.id as branch_id', 'branches.code as branch_code',
                'products.id as product_id', 'products.sku',
                'products.category_id', 'products.brand_id',
            ])->orderBy('branches.id')->orderBy('products.id')->get()
            ->map(fn ($row) => [
                'branch_id' => (int) $row->branch_id,
                'branch_code' => (string) $row->branch_code,
                'product_id' => (int) $row->product_id,
                'sku' => (string) $row->sku,
                'category_id' => (int) $row->category_id,
                'brand_id' => (int) $row->brand_id,
            ])->all();

        $useDemoHistory = app()->environment('local') && config('demo.retail_enabled')
            && Schema::hasTable('ml_demand_observations')
            && DB::table('ml_demand_observations')->where('source', 'synthetic_demo')->exists();
        $observations = ($useDemoHistory
            ? DB::table('ml_demand_observations')->where('source', 'synthetic_demo')
                ->selectRaw('branch_id, product_id, observed_on as sale_date, quantity')
            : DB::table('sales')->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
                ->whereIn('sales.status', ['paid', 'credit'])
                ->selectRaw('sales.branch_id, sale_items.product_id, DATE(sales.sold_at) as sale_date, SUM(sale_items.quantity) as quantity')
                ->groupBy('sales.branch_id', 'sale_items.product_id', DB::raw('DATE(sales.sold_at)')))
            ->orderBy('sale_date')->orderBy('branch_id')->orderBy('product_id')->get()
            ->map(fn ($row) => [
                'branch_id' => (int) $row->branch_id,
                'product_id' => (int) $row->product_id,
                'date' => (string) $row->sale_date,
                'quantity' => (float) $row->quantity,
            ])->all();

        return ['metadata' => ['source' => $useDemoHistory ? 'synthetic_demo' : 'sales_aggregate'], ...compact('series', 'observations')];
    }
}
