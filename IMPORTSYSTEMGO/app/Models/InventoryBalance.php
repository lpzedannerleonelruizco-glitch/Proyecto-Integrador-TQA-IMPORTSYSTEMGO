<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    protected $fillable = ['warehouse_id', 'product_id'];

    protected function casts(): array
    {
        return ['physical_quantity' => 'decimal:2', 'reserved_quantity' => 'decimal:2'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function available(): string
    {
        return bcsub((string) $this->physical_quantity, (string) $this->reserved_quantity, 2);
    }
}
