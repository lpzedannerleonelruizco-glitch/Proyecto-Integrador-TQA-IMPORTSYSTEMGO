<?php

namespace App\Console\Commands;

use Database\Seeders\DemoMachineLearningHistorySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDemoMachineLearningHistory extends Command
{
    protected $signature = 'demo:ml-history';

    protected $description = 'Genera historia sintética, reproducible y separada para entrenar ML en DEMO.';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Este comando requiere confirmación interactiva.');

            return self::FAILURE;
        }
        $identity = DB::selectOne('SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name');
        if (! app()->environment('local') || ! config('demo.retail_enabled')
            || $identity->database_name !== config('demo.database')
            || $identity->account_name !== 'importsystemgo_demo@localhost') {
            $this->error('Operación bloqueada: use exclusivamente importsystemgo_demo@localhost.');

            return self::FAILURE;
        }
        $this->warn('Se crearán aproximadamente 45.360 observaciones sintéticas ML. No son ventas, pagos ni movimientos de inventario.');
        if (! $this->confirm('¿Preparar el historial académico ML?', false)) {
            return self::FAILURE;
        }
        DB::transaction(fn () => app(DemoMachineLearningHistorySeeder::class)->run(), 3);
        $count = DB::table('ml_demand_observations')->where('source', 'synthetic_demo')->count();
        $this->info("Historial DEMO preparado: {$count} observaciones con procedencia synthetic_demo.");

        return self::SUCCESS;
    }
}
