<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MlModelVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'trained_from' => 'date',
            'trained_until' => 'date',
            'metrics' => 'array',
            'feature_schema' => 'array',
            'trained_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trained_by');
    }

    public function forecasts(): HasMany
    {
        return $this->hasMany(DemandForecast::class);
    }
}
