<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    protected $fillable = [
        'public_id', 'warehouse_id', 'product_id', 'requested_by', 'approved_by',
        'status', 'reason_code', 'reason', 'quantity_before', 'quantity_after',
        'quantity_change', 'idempotency_key', 'requested_at', 'approved_at', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'quantity_before' => 'decimal:2', 'quantity_after' => 'decimal:2',
            'quantity_change' => 'decimal:2', 'requested_at' => 'datetime',
            'approved_at' => 'datetime', 'is_demo' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
