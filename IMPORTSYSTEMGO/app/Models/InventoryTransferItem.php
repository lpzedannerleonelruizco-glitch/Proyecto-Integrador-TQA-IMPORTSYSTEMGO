<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InventoryTransferItem extends Model
{
    protected $fillable = ['inventory_transfer_id', 'product_id', 'requested_quantity', 'dispatched_quantity', 'received_quantity'];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:2',
            'dispatched_quantity' => 'decimal:2',
            'received_quantity' => 'decimal:2',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'inventory_transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function serials(): BelongsToMany
    {
        return $this->belongsToMany(InventorySerial::class, 'inventory_transfer_item_serials', 'inventory_transfer_item_id', 'inventory_serial_id')
            ->withPivot('received_at')->withTimestamps();
    }
}
