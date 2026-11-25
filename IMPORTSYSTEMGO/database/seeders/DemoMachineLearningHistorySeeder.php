<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DemoMachineLearningHistorySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('demo.retail_enabled')
            || DB::connection()->getDatabaseName() !== config('demo.database')) {
            throw new RuntimeException('El historial sintético solo puede generarse en la base DEMO autorizada.');
        }
        if (! Schema::hasTable('ml_demand_observations')) {
            throw new RuntimeException('La migración ML debe aplicarse antes de preparar el historial DEMO.');
        }
        $branches = DB::table('branches')->where('is_demo', true)->where('is_active', true)->orderBy('id')->get(['id', 'code']);
        $products = DB::table('products')->where('is_demo', true)->where('is_active', true)->orderBy('id')->get(['id', 'sku']);
        if ($branches->count() !== 7 || $products->count() < 12) {
            throw new RuntimeException('El catálogo DEMO esperado no está completo.');
        }
        $end = CarbonImmutable::today()->subDay();
        $start = $end->subDays(539);
        $rows = [];
        foreach ($branches->values() as $branchIndex => $branch) {
            foreach ($products->values() as $productIndex => $product) {
                for ($date = $start; $date->lte($end); $date = $date->addDay()) {
                    $base = 0.28 + (($productIndex % 6) * 0.11);
                    $branchFactor = 0.82 + ($branchIndex * 0.07);
                    $weekFactor = $date->isWeekend() ? 1.34 : 1.0;
                    $seasonFactor = in_array($date->month, [11, 12], true) ? 1.55 : (in_array($date->month, [5, 6], true) ? 1.18 : 1.0);
                    $paydayFactor = in_array($date->day, [1, 15, 30, 31], true) ? 1.22 : 1.0;
                    $trendFactor = 1 + ($start->diffInDays($date) / 539 * 0.12);
                    $noise = 0.72 + ((hexdec(substr(hash('sha256', "{$branch->code}|{$product->sku}|{$date->toDateString()}"), 0, 4)) % 57) / 100);
                    $quantity = max(0, round($base * $branchFactor * $weekFactor * $seasonFactor * $paydayFactor * $trendFactor * $noise));
                    $rows[] = [
                        'branch_id' => $branch->id,
                        'product_id' => $product->id,
                        'observed_on' => $date->toDateString(),
                        'quantity' => $quantity,
                        'source' => 'synthetic_demo',
                        'is_demo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    if (count($rows) === 1000) {
                        $this->upsert($rows);
                        $rows = [];
                    }
                }
            }
        }
        if ($rows !== []) {
            $this->upsert($rows);
        }
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function upsert(array $rows): void
    {
        DB::table('ml_demand_observations')->upsert(
            $rows,
            ['branch_id', 'product_id', 'observed_on', 'source'],
            ['quantity', 'is_demo', 'updated_at'],
        );
    }
}
