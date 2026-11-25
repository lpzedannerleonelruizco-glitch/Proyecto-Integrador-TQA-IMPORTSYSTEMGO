<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditInstallment extends Model
{
    protected $fillable = ['credit_account_id', 'installment_number', 'due_date', 'amount_due', 'amount_paid', 'status'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'amount_due' => 'decimal:2', 'amount_paid' => 'decimal:2'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class, 'credit_account_id');
    }
}
