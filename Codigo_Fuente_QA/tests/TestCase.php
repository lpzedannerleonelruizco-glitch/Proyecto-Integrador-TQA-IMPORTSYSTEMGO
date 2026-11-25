<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // This runs BEFORE setUpTraits / RefreshDatabase, using Laravel's effective connection.
        if (! $app->environment('testing')) {
            throw new \RuntimeException('Las pruebas requieren APP_ENV=testing.');
        }
        $connection = $app->make('db')->connection();
        if ($connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:') {
            return $app;
        }
        if ($connection->getDriverName() === 'mysql') {
            foreach ([$connection->getPdo(), $connection->getReadPdo()] as $pdo) {
                $identity = $pdo->query('SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name')->fetch(\PDO::FETCH_ASSOC);
                if ($identity['database_name'] !== 'importsystemgo_testing' || $identity['account_name'] !== 'importsystemgo_test@localhost') {
                    throw new \RuntimeException('Pruebas bloqueadas: identidad efectiva no autorizada.');
                }
            }

            return $app;
        }
        throw new \RuntimeException('Pruebas bloqueadas: conexión no aislada.');
    }
}
