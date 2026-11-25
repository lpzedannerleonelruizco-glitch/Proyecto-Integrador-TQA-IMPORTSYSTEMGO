<?php

// Fail before Laravel or RefreshDatabase boots if cached configuration could override isolation.
if (is_file(__DIR__.'/../bootstrap/cache/config.php')) {
    throw new RuntimeException('Pruebas bloqueadas: retire la configuración cacheada antes de continuar.');
}
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require __DIR__.'/../vendor/autoload.php';
