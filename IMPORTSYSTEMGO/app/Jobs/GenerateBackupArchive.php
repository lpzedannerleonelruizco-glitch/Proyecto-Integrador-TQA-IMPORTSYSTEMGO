<?php

namespace App\Jobs;

use App\Models\BackupArchive;
use App\Services\Backups\BackupAccessService;
use App\Services\Backups\BackupArchiveBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateBackupArchive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 960;

    public function __construct(public readonly int $archiveId) {}

    public function handle(BackupArchiveBuilder $builder, BackupAccessService $access): void
    {
        $archive = BackupArchive::query()->findOrFail($this->archiveId);
        if ($archive->status === BackupArchive::STATUS_COMPLETED) {
            return;
        }

        $archive->forceFill([
            'status' => BackupArchive::STATUS_RUNNING,
            'started_at' => $archive->started_at ?? now(),
            'failure_code' => null,
        ])->save();

        try {
            $result = $builder->build($archive->fresh());
            DB::transaction(function () use ($archive, $result, $access): void {
                $archive->forceFill($result + [
                    'status' => BackupArchive::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'failure_code' => null,
                ])->save();
                $access->audit($archive->creator()->firstOrFail(), 'backup.generated', $archive, [
                    'kind' => $archive->kind,
                    'archive_bytes' => $archive->archive_bytes,
                    'sha256' => $archive->sha256,
                ]);
            });
        } catch (Throwable $exception) {
            $archive->forceFill([
                'status' => BackupArchive::STATUS_FAILED,
                'failure_code' => 'generation_failed',
                'completed_at' => now(),
            ])->save();
            throw $exception;
        }
    }
}
