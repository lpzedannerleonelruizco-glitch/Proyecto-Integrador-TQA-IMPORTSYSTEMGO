<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplenishmentRecommendation extends Model
{
    protected $guarded = ['status', 'decided_by', 'decided_at', 'decision_note'];

    protected function casts(): array
    {
        return [
            'physical_quantity_snapshot' => 'decimal:2',
            'reserved_quantity_snapshot' => 'decimal:2',
            'safety_stock' => 'decimal:2',
            'recommended_quantity' => 'decimal:2',
            'explanation' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(DemandForecast::class, 'demand_forecast_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
