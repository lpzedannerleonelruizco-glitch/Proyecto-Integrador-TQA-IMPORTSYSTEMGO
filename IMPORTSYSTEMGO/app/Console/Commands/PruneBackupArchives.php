<?php

namespace App\Console\Commands;

use App\Models\BackupArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PruneBackupArchives extends Command
{
    protected $signature = 'backups:prune';

    protected $description = 'Elimina archivos de respaldo privados cuya retención ha vencido.';

    public function handle(): int
    {
        $root = realpath(storage_path('app/private/'.config('backups.directory')));
        if (! $root) {
            return self::SUCCESS;
        }

        BackupArchive::query()->where('expires_at', '<=', now())->chunkById(100, function ($archives) use ($root): void {
            foreach ($archives as $archive) {
                if ($archive->storage_path) {
                    $path = realpath(storage_path('app/private/'.$archive->storage_path));
                    if ($path && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path)) {
                        File::delete($path);
                    }
                }
                $archive->delete();
            }
        });

        $this->info('Se purgaron los respaldos vencidos y sus archivos privados.');

        return self::SUCCESS;
    }
}
