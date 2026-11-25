<?php

namespace App\Models;

use Database\Factories\BranchUserMembershipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchUserMembership extends Model
{
    /** @use HasFactory<BranchUserMembershipFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'user_id',
        'starts_at',
        'ends_at',
        'granted_by',
        'ended_by',
        'end_reason',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function ender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function scopeActiveAt(Builder $query, mixed $moment): Builder
    {
        return $query->whereNull('ended_by')->where('starts_at', '<=', $moment)
            ->where(fn (Builder $builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>', $moment));
    }
}
