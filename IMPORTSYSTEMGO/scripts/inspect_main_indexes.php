<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$identity = DB::selectOne('SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name');

if ($identity->database_name !== 'importsystemgo' || $identity->account_name !== 'importsystemgo_app@localhost') {
    throw new RuntimeException('La identidad efectiva de MySQL no corresponde a la base principal esperada.');
}

$indexes = DB::table('information_schema.statistics')
    ->where('table_schema', 'importsystemgo')
    ->selectRaw('table_name AS resolved_table_name')
    ->selectRaw('index_name AS resolved_index_name')
    ->selectRaw('non_unique AS resolved_non_unique')
    ->selectRaw('seq_in_index AS resolved_position')
    ->selectRaw('column_name AS resolved_column_name')
    ->orderBy('table_name')
    ->orderBy('index_name')
    ->orderBy('seq_in_index')
    ->get();

foreach ($indexes as $index) {
    echo implode('|', [
        $index->resolved_table_name,
        $index->resolved_index_name,
        (int) $index->resolved_non_unique === 0 ? 'unique' : 'non_unique',
        $index->resolved_position,
        $index->resolved_column_name,
    ]).PHP_EOL;
}
