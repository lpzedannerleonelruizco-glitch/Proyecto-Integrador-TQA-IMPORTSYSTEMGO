<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTransfer extends Model
{
    protected $fillable = [
        'public_id', 'source_warehouse_id', 'destination_warehouse_id', 'requested_by',
        'approved_by', 'dispatched_by', 'idempotency_key', 'status', 'reason', 'requested_at',
        'approved_at', 'dispatched_at', 'received_at', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime', 'approved_at' => 'datetime',
            'dispatched_at' => 'datetime', 'received_at' => 'datetime', 'is_demo' => 'boolean',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(InventoryTransferReceipt::class);
    }
}
