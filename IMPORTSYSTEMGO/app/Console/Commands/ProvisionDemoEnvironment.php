<?php

namespace App\Console\Commands;

use App\Services\DemoCredentialService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DemoRetailSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProvisionDemoEnvironment extends Command
{
    protected $signature = 'demo:credentials';

    protected $description = 'Aprovisiona datos académicos y muestra una sola vez credenciales DEMO aleatorias.';

    public function handle(DemoCredentialService $credentials): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Este comando requiere una terminal interactiva.');

            return self::FAILURE;
        }
        $identity = DB::selectOne('SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name');
        $expectedDatabase = config('demo.database');
        if (! app()->environment('local') || ! config('demo.retail_enabled')
            || $identity->database_name !== $expectedDatabase
            || $identity->account_name !== 'importsystemgo_demo@localhost') {
            $this->error('Operación bloqueada: use APP_ENV=local, DEMO_RETAIL_ENABLED=true y la conexión exclusiva importsystemgo_demo.');

            return self::FAILURE;
        }
        $this->warn('Base DEMO verificada: '.$identity->database_name.'. Se crearán o actualizarán únicamente registros marcados DEMO.');
        if (! $this->confirm('¿Generar o renovar ahora todas las credenciales DEMO?', false)) {
            return self::FAILURE;
        }
        $rows = DB::transaction(function () use ($credentials): array {
            app(AuthorizationSeeder::class)->run();
            app(DemoRetailSeeder::class)->run();

            return $credentials->rotate();
        }, 3);
        $this->newLine();
        $this->warn('Copia estas credenciales ahora. Se mostrarán una sola vez y no se guardan en texto plano.');
        $this->table(['Correo', 'Contraseña'], array_map(fn (array $row) => [$row['email'], $row['password']], $rows));
        $this->info('Credenciales DEMO renovadas. Las sesiones anteriores quedaron cerradas.');

        return self::SUCCESS;
    }
}
