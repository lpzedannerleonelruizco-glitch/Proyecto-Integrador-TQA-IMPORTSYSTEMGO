<?php

namespace App\Console\Commands;

use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DemoRetailSeeder;
use Database\Seeders\OperationalDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDemoEnvironment extends Command
{
    protected $signature = 'demo:seed {--force : Confirma la insercion autorizada en la base DEMO}';

    protected $description = 'Inserta transaccionalmente el catalogo academico, sin generar credenciales.';

    public function handle(): int
    {
        $identity = DB::selectOne('SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name');
        if (! $this->option('force')
            || ! app()->environment('local')
            || ! config('demo.retail_enabled')
            || $identity->database_name !== 'importsystemgo_demo'
            || $identity->database_name !== config('demo.database')
            || $identity->account_name !== 'importsystemgo_demo@localhost') {
            $this->error('Insercion bloqueada: la identidad o configuracion DEMO no coincide.');

            return self::FAILURE;
        }

        DB::transaction(function (): void {
            app(AuthorizationSeeder::class)->run();
            app(DemoRetailSeeder::class)->run();
            app(OperationalDemoSeeder::class)->run();
        }, 3);

        $this->info('Catalogo y operaciones DEMO insertados de forma transaccional. No se generaron ni mostraron contrasenas.');

        return self::SUCCESS;
    }
}
