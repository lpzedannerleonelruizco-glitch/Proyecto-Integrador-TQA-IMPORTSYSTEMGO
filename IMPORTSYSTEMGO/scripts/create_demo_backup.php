<?php

declare(strict_types=1);

use App\Services\Backups\MysqlDumpService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('', ['destination:']);
$destination = $options['destination'] ?? null;
$backupRoot = getenv('LOCALAPPDATA').DIRECTORY_SEPARATOR.'IMPORTSYSTEMGO'.DIRECTORY_SEPARATOR.'backups';
if (! is_string($destination) || ! str_starts_with(strtolower(str_replace('/', '\\', $destination)), strtolower(str_replace('/', '\\', $backupRoot)).'\\')) {
    fwrite(STDERR, "Destino de respaldo no autorizado.\n");
    exit(31);
}
$identity = DB::selectOne('SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name');
if ($identity->database_name !== 'importsystemgo_demo' || $identity->account_name !== 'importsystemgo_demo@localhost') {
    fwrite(STDERR, "Identidad DEMO inesperada.\n");
    exit(32);
}
app(MysqlDumpService::class)->dumpTo($destination);
$contents = file_get_contents($destination);
if ($contents === false || ! str_contains($contents, 'CREATE TABLE') || ! str_contains($contents, 'INSERT INTO `users`')) {
    @unlink($destination);
    fwrite(STDERR, "El respaldo no contiene la estructura y datos esperados.\n");
    exit(33);
}
echo json_encode([
    'database' => $identity->database_name,
    'account' => $identity->account_name,
    'bytes' => filesize($destination),
    'sha256' => hash_file('sha256', $destination),
    'structure_verified' => true,
], JSON_THROW_ON_ERROR).PHP_EOL;
