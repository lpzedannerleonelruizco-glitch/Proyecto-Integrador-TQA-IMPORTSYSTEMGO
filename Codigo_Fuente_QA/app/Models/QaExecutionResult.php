<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QaExecutionResult extends Model
{
    protected $table = 'qa_execution_results';

    protected $fillable = [
        'execution_id', 'case_id', 'status', 'objective', 'preconditions',
        'expected_result', 'obtained_result', 'observations', 'error_message',
        'failure_step', 'duration_ms', 'validations',
    ];

    protected function casts(): array
    {
        return ['validations' => 'array'];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(QaExecution::class, 'execution_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(QaTestCase::class, 'case_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(QaExecutionStep::class, 'result_id')->orderBy('sequence');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(QaEvidence::class, 'result_id');
    }
}
