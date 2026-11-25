<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemandForecast extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'forecast_date' => 'date',
            'predicted_quantity' => 'decimal:2',
            'lower_bound' => 'decimal:2',
            'upper_bound' => 'decimal:2',
            'actual_quantity' => 'decimal:2',
            'baseline_quantity' => 'decimal:2',
            'generated_at' => 'datetime',
        ];
    }

    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(MlModelVersion::class, 'ml_model_version_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(ReplenishmentRecommendation::class);
    }
}
