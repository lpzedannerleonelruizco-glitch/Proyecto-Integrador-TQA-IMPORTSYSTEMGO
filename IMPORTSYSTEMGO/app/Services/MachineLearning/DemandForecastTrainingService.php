<?php

namespace App\Services\MachineLearning;

use App\Models\DemandForecast;
use App\Models\InventoryBalance;
use App\Models\MlModelVersion;
use App\Models\ReplenishmentRecommendation;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DemandForecastTrainingService
{
    public function __construct(
        private readonly DemandDatasetService $datasets,
        private readonly PythonDemandTrainer $trainer,
    ) {}

    public function train(User $actor): MlModelVersion
    {
        $jobId = (string) Str::uuid();
        $jobDirectory = "ml/jobs/{$jobId}";
        $artifactRelative = "ml/models/demand-{$jobId}.joblib";
        Storage::disk('local')->makeDirectory($jobDirectory);
        Storage::disk('local')->makeDirectory('ml/models');
        $input = Storage::disk('local')->path("{$jobDirectory}/dataset.json");
        $output = Storage::disk('local')->path("{$jobDirectory}/result.json");
        $artifact = Storage::disk('local')->path($artifactRelative);

        try {
            file_put_contents($input, json_encode($this->datasets->build(), JSON_THROW_ON_ERROR));
            $result = $this->trainer->train($input, $output, $artifact);
            if (! is_file($artifact) || hash_file('sha256', $artifact) !== $result['artifact_sha256']) {
                throw new RuntimeException('El artefacto entrenado no superó la comprobación de integridad.');
            }

            return DB::transaction(function () use ($actor, $result, $artifactRelative): MlModelVersion {
                $active = (bool) ($result['metrics']['champion'] ?? false);
                if ($active) {
                    MlModelVersion::query()->where('model_type', 'demand_forecast')->where('status', 'active')
                        ->update(['status' => 'superseded']);
                }
                $model = MlModelVersion::create([
                    'public_id' => (string) Str::uuid(),
                    'model_type' => 'demand_forecast',
                    'algorithm' => $result['algorithm'],
                    'status' => $active ? 'active' : 'candidate',
                    'trained_from' => $result['trained_from'],
                    'trained_until' => $result['trained_until'],
                    'training_rows' => $result['training_rows'],
                    'metrics' => [...$result['metrics'], 'data_source' => $result['data_source'] ?? 'unknown'],
                    'feature_schema' => $result['feature_schema'],
                    'artifact_path' => $artifactRelative,
                    'artifact_sha256' => $result['artifact_sha256'],
                    'trained_by' => $actor->id,
                    'trained_at' => now(),
                    'is_demo' => $actor->is_demo,
                ]);
                foreach ($result['predictions'] as $prediction) {
                    $this->storePrediction($model, $prediction);
                }
                DB::table('audit_logs')->insert([
                    'actor_user_id' => $actor->id,
                    'branch_id' => null,
                    'action' => 'ml.demand_model.trained',
                    'resource_type' => 'ml_model_versions',
                    'resource_id' => $model->id,
                    'before_values' => null,
                    'after_values' => json_encode([
                        'algorithm' => $model->algorithm,
                        'status' => $model->status,
                        'trained_until' => $model->trained_until->toDateString(),
                        'metrics' => $model->metrics,
                    ], JSON_THROW_ON_ERROR),
                    'occurred_at' => now(),
                ]);

                return $model;
            }, 3);
        } catch (\Throwable $exception) {
            if (is_file($artifact)) {
                unlink($artifact);
            }
            throw $exception;
        } finally {
            Storage::disk('local')->deleteDirectory($jobDirectory);
        }
    }

    /** @param array<string, int|float> $prediction */
    private function storePrediction(MlModelVersion $model, array $prediction): void
    {
        $forecast = DemandForecast::create([
            'ml_model_version_id' => $model->id,
            'branch_id' => $prediction['branch_id'],
            'product_id' => $prediction['product_id'],
            'forecast_date' => now()->addDay()->toDateString(),
            'horizon_days' => config('machine-learning.forecast_horizon_days'),
            'predicted_quantity' => $prediction['predicted_quantity'],
            'lower_bound' => $prediction['lower_bound'],
            'upper_bound' => $prediction['upper_bound'],
            'baseline_quantity' => $prediction['baseline_quantity'],
            'generated_at' => now(),
        ]);
        $warehouse = Warehouse::query()->active()->where('branch_id', $forecast->branch_id)->orderBy('id')->first();
        if (! $warehouse) {
            return;
        }
        $balances = InventoryBalance::query()->where('product_id', $forecast->product_id)
            ->whereIn('warehouse_id', Warehouse::query()->where('branch_id', $forecast->branch_id)->select('id'))->get();
        $physical = (float) $balances->sum('physical_quantity');
        $reserved = (float) $balances->sum('reserved_quantity');
        $available = max(0, $physical - $reserved);
        $predicted = (float) $forecast->predicted_quantity;
        $lower = (float) $forecast->lower_bound;
        $upper = (float) $forecast->upper_bound;
        $safety = max($upper - $predicted, $predicted * 0.15);
        $recommended = max(0, ceil($upper + $safety - $available));
        $risk = $available < $lower ? 'critical' : ($available < $predicted ? 'high' : ($available < $upper ? 'medium' : 'low'));

        ReplenishmentRecommendation::create([
            'demand_forecast_id' => $forecast->id,
            'branch_id' => $forecast->branch_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $forecast->product_id,
            'physical_quantity_snapshot' => $physical,
            'reserved_quantity_snapshot' => $reserved,
            'safety_stock' => round($safety, 2),
            'recommended_quantity' => $recommended,
            'risk_level' => $risk,
            'status' => 'pending',
            'explanation' => [
                'available_quantity' => round($available, 2),
                'forecast_upper_bound' => $upper,
                'rule' => 'max(0, limite_superior + stock_seguridad - disponible)',
                'human_approval_required' => true,
            ],
        ]);
    }
}
