<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupArchive extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'public_id', 'kind', 'status', 'scope', 'record_summary', 'storage_path', 'sha256',
        'archive_bytes', 'failure_code', 'created_by', 'started_at', 'completed_at', 'expires_at',
        'last_verified_at', 'last_verification_ok',
    ];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'record_summary' => 'array',
            'archive_bytes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'last_verification_ok' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
