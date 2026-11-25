<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    protected $fillable = [
        'cash_register_id', 'user_id', 'status', 'opening_amount', 'opening_note', 'expected_amount',
        'declared_amount', 'difference_amount', 'closing_note', 'approval_status', 'closed_by',
        'approved_by', 'approved_at', 'open_register_token', 'open_user_token', 'opened_at', 'closed_at', 'is_demo',
    ];

    protected function casts(): array
    {
        return ['opening_amount' => 'decimal:2', 'expected_amount' => 'decimal:2', 'declared_amount' => 'decimal:2', 'difference_amount' => 'decimal:2', 'opened_at' => 'datetime', 'closed_at' => 'datetime', 'approved_at' => 'datetime', 'is_demo' => 'boolean'];
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function countDetails(): HasMany
    {
        return $this->hasMany(CashCountDetail::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
