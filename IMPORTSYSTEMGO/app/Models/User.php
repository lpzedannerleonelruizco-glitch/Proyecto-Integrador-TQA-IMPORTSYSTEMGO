<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LogicException;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected string $guard_name = 'web';

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function primaryBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'primary_branch_id');
    }

    public function branchMemberships(): HasMany
    {
        return $this->hasMany(BranchUserMembership::class);
    }

    public function globalRoleAssignments(): HasMany
    {
        return $this->hasMany(GlobalUserRole::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Direct permissions are intentionally disabled; permissions must come from roles. */
    public function givePermissionTo(...$permissions): static
    {
        throw new LogicException('Los permisos directos a usuarios están deshabilitados.');
    }

    public function syncPermissions(...$permissions): static
    {
        throw new LogicException('Los permisos directos a usuarios están deshabilitados.');
    }

    public function revokePermissionTo($permission): static
    {
        throw new LogicException('Los permisos directos a usuarios están deshabilitados.');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }
}
