<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalUserRole extends Model
{
    protected $fillable = ['user_id', 'role_id', 'starts_at', 'ends_at', 'granted_by', 'ended_by', 'end_reason'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function scopeActiveAt(Builder $query, mixed $moment): Builder
    {
        return $query->whereNull('ended_by')->where('starts_at', '<=', $moment)
            ->where(fn (Builder $builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>', $moment));
    }
}
