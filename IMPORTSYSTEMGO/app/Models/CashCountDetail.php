<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashCountDetail extends Model
{
    protected $fillable = ['cash_session_id', 'denomination', 'quantity', 'subtotal'];

    protected function casts(): array
    {
        return ['denomination' => 'decimal:2', 'quantity' => 'integer', 'subtotal' => 'decimal:2'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }
}
