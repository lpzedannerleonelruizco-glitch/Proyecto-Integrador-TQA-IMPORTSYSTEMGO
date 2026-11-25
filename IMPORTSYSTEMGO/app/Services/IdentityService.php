<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\Employee;
use App\Models\GlobalUserRole;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\AdministratorProtection;
use App\Services\Authorization\BranchMembershipService;
use App\Services\Authorization\GlobalAuthorizationService;
use App\Services\Authorization\RoleAssignmentService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class IdentityService
{
    public function __construct(private readonly GlobalAuthorizationService $authorization) {}

    public function authorize(User $actor, string $permission): void
    {
        abort_unless($this->authorization->allows($actor, $permission), 403);
    }

    private function ready(): void
    {
        abort_unless(Schema::hasTable('audit_logs'), 503, 'La auditoría requiere una migración autorizada.');
    }

    private function audit(User $actor, User $subject, string $action, array $data, ?int $branchId = null): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actor->id, 'branch_id' => $branchId, 'action' => $action,
            'resource_type' => 'users', 'resource_id' => $subject->id,
            'before_values' => null, 'after_values' => json_encode($data, JSON_THROW_ON_ERROR), 'occurred_at' => now(),
        ]);
    }

    public function save(User $actor, array $data, ?int $id = null): User
    {
        $this->ready();

        return DB::transaction(function () use ($actor, $data, $id) {
            $this->authorize($actor, 'security.users.manage');
            $user = $id ? User::query()->lockForUpdate()->findOrFail($id) : new User;
            if ($id === $actor->id && $data['status'] !== User::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['status' => 'No puedes desactivar tu propia cuenta.']);
            }
            if ($id && $data['status'] !== User::STATUS_ACTIVE) {
                app(AdministratorProtection::class)->ensureRemovable($user);
            }
            $before = $id ? Arr::only($user->getAttributes(), ['name', 'email', 'status', 'primary_branch_id']) : null;
            if ($id && $user->isActive() && $data['status'] !== User::STATUS_ACTIVE) {
                $this->revokeAccess($actor, $user, 'Cuenta desactivada');
            }
            $user->forceFill(Arr::only($data, ['name', 'email', 'status']));
            if (! $id) {
                $user->password = $data['password'];
            }
            $user->save();
            $this->audit($actor, $user, $id ? 'users.updated' : 'users.created', [
                'before' => $before,
                'after' => Arr::only($user->getAttributes(), ['name', 'email', 'status', 'primary_branch_id']),
            ]);

            return $user;
        }, 3);
    }

    public function linkEmployee(User $actor, User $subject, int $employeeId): void
    {
        $this->ready();
        DB::transaction(function () use ($actor, $subject, $employeeId) {
            $this->authorize($actor, 'security.users.manage');
            $lockedUser = User::query()->lockForUpdate()->findOrFail($subject->getKey());
            $employee = Employee::query()->lockForUpdate()->findOrFail($employeeId);
            $branch = $employee->home_branch_id
                ? Branch::query()->lockForUpdate()->find($employee->home_branch_id)
                : null;

            if (! $lockedUser->isActive()) {
                throw ValidationException::withMessages(['employee_id' => 'No se puede vincular un empleado a una cuenta inactiva.']);
            }
            if ($lockedUser->employee()->exists()) {
                throw ValidationException::withMessages(['employee_id' => 'La cuenta ya está vinculada a un empleado.']);
            }
            if ($employee->user_id !== null || $employee->status !== Employee::STATUS_ACTIVE || ! $branch?->is_active) {
                throw ValidationException::withMessages(['employee_id' => 'El empleado debe estar activo, sin cuenta vinculada y asignado a una sucursal activa.']);
            }
            if (! BranchUserMembership::query()->whereBelongsTo($lockedUser)->whereBelongsTo($branch)->activeAt(now())->exists()) {
                throw ValidationException::withMessages(['employee_id' => 'Asigna primero una membresía vigente en la sucursal del empleado.']);
            }

            $employee->forceFill(['user_id' => $lockedUser->id])->save();
            $this->audit($actor, $lockedUser, 'users.employee_assigned', [
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
            ], $branch->id);
        }, 3);
    }

    public function grantMembership(User $actor, User $subject, Branch $branch): void
    {
        $this->ready();
        DB::transaction(function () use ($actor, $subject, $branch) {
            $this->authorize($actor, 'security.users.manage');
            try {
                $membership = app(BranchMembershipService::class)->grant($subject, $branch, $actor, now());
            } catch (\DomainException) {
                throw ValidationException::withMessages(['branch_id' => 'La membresía ya existe o el usuario/sucursal no está activo.']);
            }
            $this->audit($actor, $subject, 'memberships.granted', ['membership_id' => $membership->id], $branch->id);
        }, 3);
    }

    public function revokeMembership(User $actor, User $subject, int $membershipId, string $reason): void
    {
        $this->ready();
        DB::transaction(function () use ($actor, $subject, $membershipId, $reason) {
            $this->authorize($actor, 'security.users.manage');
            $membership = $subject->branchMemberships()->findOrFail($membershipId);
            app(BranchMembershipService::class)->revoke($membership, $actor, $reason);
            $this->audit($actor, $subject, 'memberships.revoked', ['membership_id' => $membership->id, 'reason' => $reason], $membership->branch_id);
        }, 3);
    }

    public function changeRole(User $actor, User $subject, Role $role, ?Branch $branch, bool $revoke, ?string $reason = null): void
    {
        $this->ready();
        DB::transaction(function () use ($actor, $subject, $role, $branch, $revoke, $reason) {
            $this->authorize($actor, 'security.roles.manage');
            if (! $revoke && $subject->is($actor)) {
                throw ValidationException::withMessages(['role_id' => 'No puedes asignarte roles a ti mismo. Otro administrador debe hacerlo.']);
            }
            $roles = app(RoleAssignmentService::class);
            if ($revoke && $subject->is($actor) && $role->isGlobal()) {
                throw ValidationException::withMessages(['role_id' => 'No puedes retirar tus propios roles globales. Otro administrador debe hacerlo.']);
            }
            try {
                if ($role->isGlobal()) {
                    if ($branch !== null) {
                        throw new \DomainException;
                    }
                    if ($revoke) {
                        $assignment = GlobalUserRole::query()->where('user_id', $subject->id)->where('role_id', $role->id)
                            ->whereNull('ended_by')->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                            ->orderByDesc('starts_at')->firstOrFail();
                        $roles->revokeGlobalRole($actor, $assignment, $reason ?? 'Revocación administrativa');
                    } else {
                        $roles->assignGlobalRole($actor, $subject, $role);
                    }
                } else {
                    if (! $branch) {
                        throw new \DomainException;
                    }
                    $revoke ? $roles->revokeBranchRole($actor, $subject, $branch, $role) : $roles->assignBranchRole($actor, $subject, $branch, $role);
                }
            } catch (\DomainException) {
                throw ValidationException::withMessages(['role_id' => 'Rol/ámbito incompatible o membresía no vigente.']);
            }
            $this->audit($actor, $subject, $revoke ? 'roles.revoked' : 'roles.assigned', array_filter([
                'role_id' => $role->id,
                'reason' => $revoke ? $reason : null,
            ]), $branch?->id);
        }, 3);
    }

    public function offboardTerminatedEmployee(User $actor, Employee $employee): void
    {
        $this->ready();
        DB::transaction(function () use ($actor, $employee) {
            $lockedEmployee = Employee::query()->lockForUpdate()->findOrFail($employee->getKey());
            if ($lockedEmployee->status !== Employee::STATUS_TERMINATED || $lockedEmployee->user_id === null) {
                return;
            }

            $subject = User::query()->lockForUpdate()->findOrFail($lockedEmployee->user_id);
            if ($subject->isActive()) {
                app(AdministratorProtection::class)->ensureRemovable($subject);
            }
            $this->revokeAccess($actor, $subject, 'Empleado dado de baja');

            if ($subject->isActive()) {
                $subject->forceFill(['status' => User::STATUS_INACTIVE])->save();
                $this->audit($actor, $subject, 'users.deactivated_due_to_termination', [
                    'employee_id' => $lockedEmployee->id,
                ], $lockedEmployee->home_branch_id);
            }
        }, 3);
    }

    private function revokeAccess(User $actor, User $subject, string $reason): void
    {
        $now = now();
        $memberships = BranchUserMembership::query()->whereBelongsTo($subject)
            ->whereNull('ended_by')
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->lockForUpdate()->get();

        foreach ($memberships as $membership) {
            $endsAt = $now->copy()->max($membership->starts_at->copy()->addSecond());
            $membership->forceFill([
                'ends_at' => $endsAt,
                'ended_by' => $actor->id,
                'end_reason' => $reason,
            ])->save();
            $this->audit($actor, $subject, 'memberships.revoked', [
                'membership_id' => $membership->id,
                'reason' => $reason,
            ], $membership->branch_id);
        }

        $tableNames = config('permission.table_names');
        $columns = config('permission.column_names');
        $roleTable = $tableNames['model_has_roles'];
        $modelKey = $columns['model_morph_key'];
        $roleKey = $columns['role_pivot_key'] ?? 'role_id';
        $branchKey = $columns['team_foreign_key'];
        $modelType = $subject->getMorphClass();
        $assignments = DB::table($roleTable)->where($modelKey, $subject->id)->where('model_type', $modelType)
            ->lockForUpdate()->get([$branchKey, $roleKey]);

        foreach ($assignments as $assignment) {
            DB::table($roleTable)->where($modelKey, $subject->id)->where('model_type', $modelType)
                ->where($branchKey, $assignment->{$branchKey})->where($roleKey, $assignment->{$roleKey})->delete();
            $this->audit($actor, $subject, 'roles.revoked', [
                'role_id' => $assignment->{$roleKey},
                'reason' => $reason,
            ], (int) $assignment->{$branchKey});
        }

        $permissionTable = $tableNames['model_has_permissions'];
        $permissionKey = $columns['permission_pivot_key'] ?? 'permission_id';
        $directPermissions = DB::table($permissionTable)->where($modelKey, $subject->id)->where('model_type', $modelType)
            ->lockForUpdate()->get([$branchKey, $permissionKey]);

        foreach ($directPermissions as $permission) {
            DB::table($permissionTable)->where($modelKey, $subject->id)->where('model_type', $modelType)
                ->where($branchKey, $permission->{$branchKey})->where($permissionKey, $permission->{$permissionKey})->delete();
            $this->audit($actor, $subject, 'permissions.revoked', [
                'permission_id' => $permission->{$permissionKey},
                'reason' => $reason,
            ], (int) $permission->{$branchKey});
        }

        $globalAssignments = GlobalUserRole::query()->whereBelongsTo($subject)->whereNull('ended_by')
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->lockForUpdate()->get();

        foreach ($globalAssignments as $assignment) {
            $endsAt = $now->copy()->max($assignment->starts_at->copy()->addSecond());
            $assignment->forceFill([
                'ends_at' => $endsAt,
                'ended_by' => $actor->id,
                'end_reason' => $reason,
            ])->save();
            $this->audit($actor, $subject, 'global_roles.revoked', [
                'role_id' => $assignment->role_id,
                'assignment_id' => $assignment->id,
                'reason' => $reason,
            ]);
        }

        if ($subject->primary_branch_id !== null) {
            $subject->forceFill(['primary_branch_id' => null])->save();
        }
    }
}
