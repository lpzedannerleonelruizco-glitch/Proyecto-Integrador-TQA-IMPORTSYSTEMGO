<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QaExecutionStep extends Model
{
    protected $table = 'qa_execution_steps';

    protected $fillable = ['result_id', 'sequence', 'name', 'description', 'status', 'obtained_result', 'error_message'];

    public function result(): BelongsTo
    {
        return $this->belongsTo(QaExecutionResult::class, 'result_id');
    }
}
