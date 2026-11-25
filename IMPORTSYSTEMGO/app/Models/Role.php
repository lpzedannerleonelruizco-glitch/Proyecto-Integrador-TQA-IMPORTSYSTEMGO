<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_BRANCH = 'branch';

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function globalAssignments(): HasMany
    {
        return $this->hasMany(GlobalUserRole::class);
    }

    public function isGlobal(): bool
    {
        return $this->scope_type === self::SCOPE_GLOBAL && $this->branch_id === null;
    }

    public function isAvailableForBranch(Branch $branch): bool
    {
        return $this->scope_type === self::SCOPE_BRANCH
            && ($this->branch_id === null || $this->branch_id === $branch->getKey());
    }
}
