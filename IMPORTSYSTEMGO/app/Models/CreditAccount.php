<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditAccount extends Model
{
    protected $fillable = ['branch_id', 'sale_id', 'customer_id', 'advisor_user_id', 'credit_number', 'status', 'frequency', 'term_count', 'principal_amount', 'down_payment', 'financed_amount', 'annual_rate', 'outstanding_balance', 'started_at', 'is_demo'];

    protected function casts(): array
    {
        return ['principal_amount' => 'decimal:2', 'down_payment' => 'decimal:2', 'financed_amount' => 'decimal:2', 'annual_rate' => 'decimal:3', 'outstanding_balance' => 'decimal:2', 'started_at' => 'date', 'is_demo' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(CreditInstallment::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_user_id');
    }
}
