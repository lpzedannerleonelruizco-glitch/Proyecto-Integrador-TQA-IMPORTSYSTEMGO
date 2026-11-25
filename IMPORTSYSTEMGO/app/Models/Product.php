<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'brand_id', 'sku', 'barcode', 'name', 'description', 'model', 'image_path',
        'list_price', 'acquisition_cost', 'minimum_stock', 'unit_of_measure',
        'tracking_mode', 'requires_serial', 'is_active', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:2',
            'acquisition_cost' => 'decimal:2',
            'minimum_stock' => 'decimal:2',
            'requires_serial' => 'boolean',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function serials(): HasMany
    {
        return $this->hasMany(InventorySerial::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
