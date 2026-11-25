<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InventoryTransferReceiptItem extends Model
{
    protected $fillable = ['inventory_transfer_receipt_id', 'inventory_transfer_item_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function transferItem(): BelongsTo
    {
        return $this->belongsTo(InventoryTransferItem::class, 'inventory_transfer_item_id');
    }

    public function serials(): BelongsToMany
    {
        return $this->belongsToMany(InventorySerial::class, 'inventory_transfer_receipt_serials', 'inventory_transfer_receipt_item_id', 'inventory_serial_id')
            ->withTimestamps();
    }
}
