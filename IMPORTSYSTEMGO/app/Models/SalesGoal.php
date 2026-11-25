<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesGoal extends Model
{
    protected $fillable = ['branch_id', 'user_id', 'scope_key', 'period_start', 'period_end', 'target_amount', 'is_demo'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'target_amount' => 'decimal:2', 'is_demo' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
