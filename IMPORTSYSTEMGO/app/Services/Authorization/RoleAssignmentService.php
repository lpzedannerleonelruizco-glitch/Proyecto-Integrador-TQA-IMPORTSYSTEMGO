<?php

namespace App\Services\Authorization;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\GlobalUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleAssignmentService
{
    public function __construct(
        private readonly ScopedAuthorizationService $authorization,
        private readonly GlobalAuthorizationService $globalAuthorization,
    ) {}

    public function assignBranchRole(User $actor, User $subject, Branch $branch, Role $role): void
    {
        $this->authorizeGlobal($actor, 'security.roles.manage');
        if ($actor->is($subject)) {
            throw new AuthorizationException;
        }

        if ($role->guard_name !== Permission::GUARD) {
            throw new DomainException('El rol no puede asignarse en esta sucursal.');
        }

        DB::transaction(function () use ($actor, $subject, $branch, $role) {
            $subject = User::query()->lockForUpdate()->findOrFail($subject->getKey());
            $this->authorizeGlobal($actor, 'security.roles.manage');
            if ($actor->is($subject)) {
                throw new AuthorizationException;
            }
            $role = Role::query()->lockForUpdate()->findOrFail($role->getKey());
            if (! $role->isAvailableForBranch($branch)) {
                throw new DomainException('El rol no puede asignarse en esta sucursal.');
            }
            $this->authorizeRoleDelegation($actor, $role);
            $membership = BranchUserMembership::query()
                ->whereBelongsTo($subject)
                ->whereBelongsTo($branch)
                ->activeAt(now())
                ->lockForUpdate()
                ->first();

            if (! $membership || ! $subject->isActive() || ! $branch->is_active) {
                throw new DomainException('Se requiere una membresía vigente para asignar el rol.');
            }

            $this->withBranch($subject, $branch, fn () => $subject->assignRole($role));
        }, 3);
    }

    public function revokeBranchRole(User $actor, User $subject, Branch $branch, Role $role): void
    {
        $this->authorizeGlobal($actor, 'security.roles.manage');
        DB::transaction(function () use ($actor, $subject, $branch, $role) {
            User::query()->lockForUpdate()->findOrFail($subject->getKey());
            $this->authorizeGlobal($actor, 'security.roles.manage');
            $this->withBranch($subject, $branch, fn () => $subject->removeRole($role));
        }, 3);
    }

    public function assignGlobalRole(User $actor, User $subject, Role $role): GlobalUserRole
    {
        $this->authorizeGlobal($actor, 'security.roles.manage');
        if ($actor->is($subject)) {
            throw new AuthorizationException;
        }

        if (! $subject->isActive()) {
            throw new DomainException('No se pueden asignar roles globales a usuarios inactivos.');
        }

        if ($role->guard_name !== Permission::GUARD || ! $role->isGlobal()) {
            throw new DomainException('Solo pueden asignarse globalmente roles globales del guard web.');
        }

        return DB::transaction(function () use ($actor, $subject, $role) {
            $subject = User::query()->lockForUpdate()->findOrFail($subject->getKey());
            $this->authorizeGlobal($actor, 'security.roles.manage');
            if ($actor->is($subject)) {
                throw new AuthorizationException;
            }
            $role = Role::query()->lockForUpdate()->findOrFail($role->getKey());
            if (! $role->isGlobal()) {
                throw new DomainException('Solo pueden asignarse globalmente roles globales.');
            }
            $this->authorizeRoleDelegation($actor, $role);
            if (! $subject->isActive()) {
                throw new DomainException('El usuario debe estar activo.');
            }
            $existing = GlobalUserRole::query()
                ->whereBelongsTo($subject)
                ->whereBelongsTo($role)
                ->whereNull('ends_at')
                ->lockForUpdate()
                ->first();

            return $existing ?? GlobalUserRole::query()->create([
                'user_id' => $subject->getKey(),
                'role_id' => $role->getKey(),
                'starts_at' => now(),
                'granted_by' => $actor->getKey(),
            ]);
        }, 3);
    }

    public function revokeGlobalRole(User $actor, GlobalUserRole $assignment, string $reason): void
    {
        $this->authorizeGlobal($actor, 'security.roles.manage');

        DB::transaction(function () use ($actor, $assignment, $reason) {
            User::query()->lockForUpdate()->findOrFail($assignment->user_id);
            $locked = GlobalUserRole::query()->lockForUpdate()->findOrFail($assignment->getKey());

            $this->authorizeGlobal($actor, 'security.roles.manage');
            if ($locked->ended_by === null && ($locked->ends_at === null || $locked->ends_at->isFuture())) {
                if ($locked->role->name === 'administrador-general' && $locked->role->isGlobal()) {
                    app(AdministratorProtection::class)->ensureRemovable($locked->user, 'role_id');
                }
                if (trim($reason) === '' || mb_strlen($reason) > 255) {
                    throw new DomainException('La revocación requiere un motivo de hasta 255 caracteres.');
                }
                $locked->forceFill(['ends_at' => now()->max($locked->starts_at->copy()->addSecond()), 'ended_by' => $actor->getKey(), 'end_reason' => $reason])->save();
            }
        }, 3);
    }

    private function authorize(User $actor, string $permission, ?Branch $branch = null): void
    {
        if (! $this->authorization->allows($actor, $permission, $branch)) {
            throw new AuthorizationException;
        }
    }

    private function authorizeGlobal(User $actor, string $permission): void
    {
        if (! $this->globalAuthorization->allows($actor, $permission)) {
            throw new AuthorizationException;
        }
    }

    private function authorizeRoleDelegation(User $actor, Role $role): void
    {
        $required = $role->permissions()->where('permissions.guard_name', Permission::GUARD)->pluck('permissions.name')->all();
        if (array_diff($required, $this->globalAuthorization->permissionsFor($actor)) !== []) {
            throw ValidationException::withMessages([
                'role_id' => 'No puedes delegar permisos que no tienes asignados globalmente.',
            ]);
        }
    }

    private function withBranch(User $user, Branch $branch, callable $callback): mixed
    {
        $previousBranchId = getPermissionsTeamId();

        try {
            setPermissionsTeamId($branch->getKey());
            $user->unsetRelation('roles')->unsetRelation('permissions');

            return $callback();
        } finally {
            setPermissionsTeamId($previousBranchId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
