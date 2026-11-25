<?php

namespace App\Console\Commands;

use App\Support\DemoCatalog;
use Illuminate\Console\Command;

class PreviewDemoEnvironment extends Command
{
    protected $signature = 'demo:preview';

    protected $description = 'Muestra el catalogo DEMO previsto sin conectarse ni escribir en la base de datos.';

    public function handle(): int
    {
        $identities = DemoCatalog::identities();
        $employees = count(array_filter($identities, fn (array $identity): bool => $identity['employee']));

        $this->components->info('Vista previa de datos academicos (solo lectura)');
        $this->table(
            ['Codigo', 'Sucursal', 'Area', 'Almacen'],
            collect(DemoCatalog::branches())->map(
                fn (array $branch, string $code): array => [$code, $branch['name'], $branch['area'], "WH-{$code}"]
            )->values()->all(),
        );
        $this->table(
            ['Correo', 'Nombre', 'Rol', 'Sucursal/area', 'Empleado'],
            collect($identities)->map(
                fn (array $identity, string $email): array => [
                    $email,
                    $identity['name'],
                    $identity['role'],
                    $identity['branch'] ?? $identity['area'] ?? 'Todas',
                    $identity['employee'] ? 'Si' : 'No',
                ]
            )->values()->all(),
        );
        $this->line(sprintf(
            'Previstos: 2 areas, %d sucursales, %d almacenes, %d empleados y %d cuentas DEMO.',
            count(DemoCatalog::branches()),
            count(DemoCatalog::branches()),
            $employees,
            count($identities),
        ));
        $this->warn('No se escribio ningun registro ni se genero ninguna contrasena.');

        return self::SUCCESS;
    }
}
