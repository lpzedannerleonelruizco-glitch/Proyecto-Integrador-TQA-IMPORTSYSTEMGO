<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QaExecution extends Model
{
    protected $table = 'qa_executions';

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected $fillable = [
        'public_id', 'owner_id', 'recipient_email', 'type', 'suite_id',
        'selected_case_ids', 'status', 'started_at', 'completed_at',
        'duration_ms', 'progress', 'email_status', 'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'selected_case_ids' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function suite(): BelongsTo
    {
        return $this->belongsTo(QaSuite::class, 'suite_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(QaExecutionResult::class, 'execution_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(QaEvidence::class, 'execution_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(QaReport::class, 'execution_id');
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(QaEmailLog::class, 'execution_id');
    }
}
