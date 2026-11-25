<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MachineLearning\DemandForecastTrainingService;
use App\Services\MachineLearning\MachineLearningAccessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrainDemandForecast extends Command
{
    protected $signature = 'ml:demand:train {--actor-email=admin.demo@importsystemgo.example}';

    protected $description = 'Entrena y versiona el pronóstico de demanda sin entregar credenciales de MySQL a Python.';

    public function handle(DemandForecastTrainingService $training, MachineLearningAccessService $access): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Este comando requiere confirmación interactiva.');

            return self::FAILURE;
        }
        foreach (['ml_model_versions', 'demand_forecasts', 'replenishment_recommendations'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error('La migración del módulo ML todavía no fue aplicada en esta base.');

                return self::FAILURE;
            }
        }
        $actor = User::query()->where('email', $this->option('actor-email'))->where('status', User::STATUS_ACTIVE)->first();
        if (! $actor || ! $access->canTrain($actor)) {
            $this->error('El actor indicado no tiene autorización global para entrenar modelos.');

            return self::FAILURE;
        }
        $identity = DB::selectOne('SELECT DATABASE() AS database_name');
        $this->warn('Base verificada: '.$identity->database_name.'. Se leerán ventas y se escribirán únicamente versiones, pronósticos y recomendaciones ML.');
        if (! $this->confirm('¿Entrenar ahora el modelo de demanda?', false)) {
            return self::FAILURE;
        }
        $model = $training->train($actor);
        $this->info("Modelo {$model->public_id} entrenado como {$model->status}.");
        $this->table(['Métrica', 'Valor'], collect($model->metrics)->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'sí' : 'no') : $value])->values()->all());

        return self::SUCCESS;
    }
}
