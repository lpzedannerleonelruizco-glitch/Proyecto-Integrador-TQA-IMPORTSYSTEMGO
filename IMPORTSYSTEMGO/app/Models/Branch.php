<?php

namespace App\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    public const TYPE_CENTRAL = 'central';

    public const TYPE_STORE = 'store';

    protected $fillable = [
        'code',
        'name',
        'type',
        'legal_address',
        'timezone',
        'is_active',
        'operational_area_id',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function operationalArea(): BelongsTo
    {
        return $this->belongsTo(OperationalArea::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BranchUserMembership::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'home_branch_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
