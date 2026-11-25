<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTransferReceipt extends Model
{
    protected $fillable = ['inventory_transfer_id', 'received_by', 'idempotency_key', 'note', 'received_at'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'inventory_transfer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferReceiptItem::class);
    }
}
