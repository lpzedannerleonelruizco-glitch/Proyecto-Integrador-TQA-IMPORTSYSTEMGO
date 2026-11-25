<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'branch_id', 'warehouse_id', 'product_id', 'actor_user_id', 'type', 'quantity_change',
        'balance_after', 'unit_cost', 'reference_key', 'source_reference', 'notes', 'occurred_at', 'is_demo',
    ];

    protected function casts(): array
    {
        return ['quantity_change' => 'decimal:2', 'balance_after' => 'decimal:2', 'unit_cost' => 'decimal:2', 'occurred_at' => 'datetime', 'is_demo' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
