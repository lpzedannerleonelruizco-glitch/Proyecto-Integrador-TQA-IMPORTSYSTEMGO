<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySerial extends Model
{
    protected $fillable = [
        'product_id', 'warehouse_id', 'inventory_movement_id', 'serial_number', 'imei1', 'imei2',
        'status', 'sale_id', 'received_at', 'sold_at',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'sold_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
