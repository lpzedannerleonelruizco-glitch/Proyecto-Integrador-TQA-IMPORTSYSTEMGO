<?php

namespace App\Services\Backups;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

class MysqlDumpService
{
    public function dumpTo(string $destination): void
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();
        $allowedDatabases = array_filter([
            config('demo.database'),
            'importsystemgo_testing',
        ]);

        if ($connection->getDriverName() !== 'mysql' || ! in_array($database, $allowedDatabases, true)) {
            throw new RuntimeException('Los respaldos SQL solo están habilitados para DEMO y pruebas aisladas.');
        }

        $configuration = config('database.connections.'.$connection->getName());
        $password = (string) ($configuration['password'] ?? '');
        if ($password === '') {
            throw new RuntimeException('No hay una credencial de base de datos disponible para el respaldo.');
        }

        $arguments = [
            config('backups.mysql_dump_binary'),
            '--host='.(string) ($configuration['host'] ?? '127.0.0.1'),
            '--port='.(string) ($configuration['port'] ?? '3306'),
            '--user='.(string) ($configuration['username'] ?? ''),
            '--single-transaction',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--triggers',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            '--set-gtid-purged=OFF',
            '--result-file',
            $destination,
            $database,
        ];

        $process = new Process(
            $arguments,
            base_path(),
            ['MYSQL_PWD' => $password],
            null,
            config('backups.mysql_dump_timeout_seconds'),
        );
        $process->run();

        if (! $process->isSuccessful() || ! is_file($destination) || filesize($destination) === 0) {
            @unlink($destination);
            throw new RuntimeException('MySQL no pudo completar el respaldo. Verifique el binario y los privilegios requeridos.');
        }
    }
}
