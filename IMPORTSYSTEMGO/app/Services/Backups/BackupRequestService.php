<?php

namespace App\Services\Backups;

use App\Jobs\GenerateBackupArchive;
use App\Models\BackupArchive;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BackupRequestService
{
    public function __construct(private readonly BackupAccessService $access) {}

    public function requestFull(User $actor): BackupArchive
    {
        abort_unless($this->access->canCreateFull($actor), 403);

        return $this->queue($actor, 'full', ['type' => 'full']);
    }

    public function requestTerritorial(User $actor): BackupArchive
    {
        abort_unless($this->access->canCreateTerritorial($actor), 403);
        $branchIds = $this->access->allowedBranchIds($actor);
        if ($branchIds === []) {
            abort(403, 'No hay sucursales autorizadas para exportar.');
        }

        return $this->queue($actor, 'territorial', ['type' => 'territorial', 'branch_ids' => $branchIds]);
    }

    private function queue(User $actor, string $kind, array $scope): BackupArchive
    {
        if (! Schema::hasTable('backup_archives') || ! Schema::hasTable('audit_logs')) {
            abort(503, 'El módulo requiere migraciones pendientes; no se ejecutan automáticamente.');
        }

        $throttleKey = 'backup:'.$actor->id;
        $recent = BackupArchive::query()
            ->where('created_by', $actor->id)
            ->where('created_at', '>=', now()->subMinutes(config('backups.minimum_interval_minutes')))
            ->exists();
        if ($recent || RateLimiter::tooManyAttempts($throttleKey, 1)) {
            throw ValidationException::withMessages(['backup' => 'Espera antes de solicitar otro archivo de respaldo.']);
        }

        RateLimiter::hit($throttleKey, config('backups.minimum_interval_minutes') * 60);

        $archive = DB::transaction(function () use ($actor, $kind, $scope): BackupArchive {
            $archive = BackupArchive::create([
                'public_id' => (string) Str::uuid(),
                'kind' => $kind,
                'status' => BackupArchive::STATUS_QUEUED,
                'scope' => $scope,
                'created_by' => $actor->id,
                'expires_at' => now()->addDays(config('backups.retention_days')),
            ]);
            $this->access->audit($actor, 'backup.requested', $archive, [
                'kind' => $kind,
                'scope' => $scope,
                'status' => BackupArchive::STATUS_QUEUED,
            ]);

            return $archive;
        });

        GenerateBackupArchive::dispatch($archive->id)->afterCommit();

        return $archive;
    }
}
