<?php

namespace App\Services\Backups;

use App\Models\BackupArchive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class BackupArchiveBuilder
{
    public function __construct(private readonly MysqlDumpService $mysqlDump) {}

    /** @return array{storage_path: string, sha256: string, archive_bytes: int, record_summary: array<string, int>} */
    public function build(BackupArchive $archive): array
    {
        $directory = storage_path('app/private/'.config('backups.directory'));
        File::ensureDirectoryExists($directory, 0700, true);
        $baseName = $archive->public_id;
        $workingDirectory = $directory.DIRECTORY_SEPARATOR.'.'.$baseName;
        File::ensureDirectoryExists($workingDirectory, 0700, true);
        $zipPath = $directory.DIRECTORY_SEPARATOR.$baseName.'.zip';
        $temporaryFiles = [];

        try {
            $manifest = [
                'application' => 'IMPORTSYSTEMGO',
                'format_version' => 1,
                'created_at' => now()->toIso8601String(),
                'kind' => $archive->kind,
                'scope' => $archive->scope,
                'created_by' => $archive->created_by,
            ];
            $summary = [];
            $checksums = [];

            if ($archive->kind === 'full') {
                $sqlPath = $workingDirectory.DIRECTORY_SEPARATOR.'database.sql';
                $this->mysqlDump->dumpTo($sqlPath);
                $temporaryFiles[] = $sqlPath;
                $manifest['database'] = DB::connection()->getDatabaseName();
                $checksums['database.sql'] = hash_file('sha256', $sqlPath);
                $manifest['objects'] = $this->databaseObjectSummary();
            } elseif ($archive->kind === 'territorial') {
                $summary = $this->writeTerritorialDatasets($archive, $workingDirectory, $checksums);
                $manifest['scope']['branch_codes'] = DB::table('branches')
                    ->whereIn('id', $archive->scope['branch_ids'])
                    ->orderBy('code')->pluck('code')->all();
                $manifest['record_summary'] = $summary;
            } else {
                throw new RuntimeException('Tipo de respaldo no admitido.');
            }

            $manifestPath = $workingDirectory.DIRECTORY_SEPARATOR.'manifest.json';
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $checksums['manifest.json'] = hash_file('sha256', $manifestPath);
            $checksumPath = $workingDirectory.DIRECTORY_SEPARATOR.'SHA256SUMS.txt';
            ksort($checksums);
            file_put_contents($checksumPath, collect($checksums)
                ->map(fn (string $hash, string $name): string => $hash.'  '.$name)
                ->implode("\n")."\n");

            if ($archive->kind === 'territorial') {
                $restorePath = $workingDirectory.DIRECTORY_SEPARATOR.'README.txt';
                file_put_contents($restorePath, "IMPORTSYSTEMGO - EXPORTACIÓN TERRITORIAL\nEste archivo contiene datos de negocio minimizados en CSV. No es un respaldo SQL ni se puede restaurar como una base de datos.\nCompruebe SHA256SUMS.txt antes de usar los archivos.\n");
            } else {
                $restorePath = $workingDirectory.DIRECTORY_SEPARATOR.'RESTORE.txt';
                file_put_contents($restorePath, "IMPORTSYSTEMGO - RESPALDO SQL COMPLETO\nRestaure exclusivamente en una instancia MySQL aislada y desechable. Revise y aprovisione previamente un esquema vacío; no ejecute este archivo sobre una base existente. El respaldo puede contener datos personales y hashes de contraseñas.\nVerifique SHA256SUMS.txt antes de restaurar.\n");
            }

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo crear el archivo ZIP privado.');
            }
            foreach (File::files($workingDirectory) as $file) {
                $zip->addFile($file->getPathname(), $file->getFilename());
            }
            if (! $zip->close()) {
                throw new RuntimeException('No se pudo finalizar el archivo ZIP.');
            }

            $archiveBytes = filesize($zipPath);
            if ($archiveBytes === false || $archiveBytes > config('backups.maximum_archive_bytes')) {
                throw new RuntimeException('El respaldo supera el límite de tamaño configurado.');
            }

            return [
                'storage_path' => config('backups.directory').'/'.$baseName.'.zip',
                'sha256' => hash_file('sha256', $zipPath),
                'archive_bytes' => $archiveBytes,
                'record_summary' => $summary,
            ];
        } catch (\Throwable $exception) {
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
            throw $exception;
        } finally {
            foreach ($temporaryFiles as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            File::deleteDirectory($workingDirectory);
        }
    }

    /** @return array<string, int> */
    private function writeTerritorialDatasets(BackupArchive $archive, string $directory, array &$checksums): array
    {
        $branchIds = array_map('intval', $archive->scope['branch_ids'] ?? []);
        if ($branchIds === []) {
            throw new RuntimeException('La exportación territorial requiere sucursales autorizadas.');
        }

        $datasets = [
            'branches.csv' => [
                ['code', 'name', 'operational_area'],
                DB::table('branches')->leftJoin('operational_areas', 'operational_areas.id', '=', 'branches.operational_area_id')
                    ->whereIn('branches.id', $branchIds)
                    ->select('branches.code', 'branches.name', 'operational_areas.name as operational_area')
                    ->orderBy('branches.code'),
            ],
            'products.csv' => [
                ['sku', 'name', 'category', 'brand', 'list_price', 'is_active'],
                DB::table('products')->join('inventory_balances', 'inventory_balances.product_id', '=', 'products.id')
                    ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
                    ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                    ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
                    ->whereIn('warehouses.branch_id', $branchIds)
                    ->select('products.sku', 'products.name', 'categories.name as category', 'brands.name as brand', 'products.list_price', 'products.is_active')
                    ->distinct()->orderBy('products.sku'),
            ],
            'inventory.csv' => [
                ['branch_code', 'warehouse_code', 'sku', 'physical_quantity', 'reserved_quantity', 'available_quantity'],
                DB::table('inventory_balances')->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
                    ->join('branches', 'branches.id', '=', 'warehouses.branch_id')
                    ->join('products', 'products.id', '=', 'inventory_balances.product_id')
                    ->whereIn('branches.id', $branchIds)
                    ->selectRaw('branches.code as branch_code, warehouses.code as warehouse_code, products.sku, inventory_balances.physical_quantity, inventory_balances.reserved_quantity, inventory_balances.physical_quantity - inventory_balances.reserved_quantity as available_quantity')
                    ->orderBy('branches.code')->orderBy('warehouses.code')->orderBy('products.sku'),
            ],
            'sales.csv' => [
                ['sale_number', 'branch_code', 'sold_at', 'status', 'subtotal', 'discount_total', 'total'],
                DB::table('sales')->join('branches', 'branches.id', '=', 'sales.branch_id')
                    ->whereIn('sales.branch_id', $branchIds)
                    ->select('sales.sale_number', 'branches.code as branch_code', 'sales.sold_at', 'sales.status', 'sales.subtotal', 'sales.discount_total', 'sales.total')
                    ->orderBy('sales.sold_at')->orderBy('sales.id'),
            ],
            'sale_items.csv' => [
                ['sale_number', 'sku', 'product_name', 'quantity', 'unit_price', 'discount_amount', 'line_total'],
                DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                    ->join('products', 'products.id', '=', 'sale_items.product_id')
                    ->whereIn('sales.branch_id', $branchIds)
                    ->select('sales.sale_number', 'products.sku', 'products.name as product_name', 'sale_items.quantity', 'sale_items.unit_price', 'sale_items.discount_amount', 'sale_items.line_total')
                    ->orderBy('sales.sale_number')->orderBy('sale_items.id'),
            ],
            'payments.csv' => [
                ['sale_number', 'method', 'amount', 'paid_at'],
                DB::table('payments')->join('sales', 'sales.id', '=', 'payments.sale_id')
                    ->whereIn('sales.branch_id', $branchIds)
                    ->select('sales.sale_number', 'payments.method', 'payments.amount', 'payments.paid_at')
                    ->orderBy('payments.paid_at')->orderBy('payments.id'),
            ],
            'cash_sessions.csv' => [
                ['branch_code', 'status', 'opening_amount', 'expected_amount', 'declared_amount', 'difference_amount', 'opened_at', 'closed_at'],
                DB::table('cash_sessions')->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
                    ->join('branches', 'branches.id', '=', 'cash_registers.branch_id')
                    ->whereIn('branches.id', $branchIds)
                    ->select('branches.code as branch_code', 'cash_sessions.status', 'cash_sessions.opening_amount', 'cash_sessions.expected_amount', 'cash_sessions.declared_amount', 'cash_sessions.difference_amount', 'cash_sessions.opened_at', 'cash_sessions.closed_at')
                    ->orderBy('cash_sessions.opened_at')->orderBy('cash_sessions.id'),
            ],
            'inventory_movements.csv' => [
                ['branch_code', 'warehouse_code', 'sku', 'type', 'quantity_change', 'balance_after', 'occurred_at'],
                DB::table('inventory_movements')->join('branches', 'branches.id', '=', 'inventory_movements.branch_id')
                    ->join('warehouses', 'warehouses.id', '=', 'inventory_movements.warehouse_id')
                    ->join('products', 'products.id', '=', 'inventory_movements.product_id')
                    ->whereIn('inventory_movements.branch_id', $branchIds)
                    ->select('branches.code as branch_code', 'warehouses.code as warehouse_code', 'products.sku', 'inventory_movements.type', 'inventory_movements.quantity_change', 'inventory_movements.balance_after', 'inventory_movements.occurred_at')
                    ->orderBy('inventory_movements.occurred_at')->orderBy('inventory_movements.id'),
            ],
        ];

        $summary = [];
        foreach ($datasets as $filename => [$headers, $query]) {
            $path = $directory.DIRECTORY_SEPARATOR.$filename;
            $handle = fopen($path, 'wb');
            if ($handle === false) {
                throw new RuntimeException('No se pudo crear un conjunto territorial temporal.');
            }
            fputcsv($handle, $headers);
            $count = 0;
            foreach ($query->cursor() as $row) {
                $values = array_map(fn (mixed $value): string => $this->safeCsvValue((string) ($value ?? '')), array_values((array) $row));
                fputcsv($handle, $values);
                $count++;
            }
            fclose($handle);
            $summary[$filename] = $count;
            $checksums[$filename] = hash_file('sha256', $path);
        }

        return $summary;
    }

    private function databaseObjectSummary(): array
    {
        return [
            'triggers' => (int) DB::selectOne('SELECT COUNT(*) AS aggregate FROM information_schema.triggers WHERE trigger_schema = DATABASE()')->aggregate,
            'routines' => (int) DB::selectOne('SELECT COUNT(*) AS aggregate FROM information_schema.routines WHERE routine_schema = DATABASE()')->aggregate,
            'events' => (int) DB::selectOne('SELECT COUNT(*) AS aggregate FROM information_schema.events WHERE event_schema = DATABASE()')->aggregate,
        ];
    }

    private function safeCsvValue(string $value): string
    {
        if (preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
