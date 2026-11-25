<?php

namespace App\Services\Authorization;

use App\Models\Branch;
use App\Models\BranchUserMembership;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class BranchMembershipService
{
    public function __construct(private readonly ScopedAuthorizationService $authorization) {}

    public function grant(
        User $subject,
        Branch $branch,
        User $actor,
        CarbonInterface $startsAt,
        ?CarbonInterface $endsAt = null,
    ): BranchUserMembership {
        if (! $this->authorization->allows($actor, 'security.users.manage', $branch)) {
            throw new AuthorizationException;
        }

        if (! $subject->isActive() || ! $branch->is_active) {
            throw new DomainException('El usuario y la sucursal deben estar activos.');
        }

        if ($endsAt !== null && $endsAt->lessThanOrEqualTo($startsAt)) {
            throw new DomainException('La finalización debe ser posterior al inicio.');
        }

        return DB::transaction(function () use ($subject, $branch, $actor, $startsAt, $endsAt) {
            // A stable parent lock serializes grants even when no membership exists yet.
            $subject = User::query()->lockForUpdate()->findOrFail($subject->getKey());
            if (! $subject->isActive() || ! $branch->fresh()?->is_active || ! $this->authorization->allows($actor, 'security.users.manage', $branch)) {
                throw new AuthorizationException;
            }
            $overlap = BranchUserMembership::query()
                ->whereNull('ended_by')
                ->whereBelongsTo($subject)
                ->whereBelongsTo($branch)
                ->lockForUpdate()
                ->where(function ($query) use ($endsAt) {
                    if ($endsAt !== null) {
                        $query->where('starts_at', '<', $endsAt);
                    }
                })
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $startsAt))
                ->exists();

            if ($overlap) {
                throw new DomainException('La membresía se solapa con otra vigencia.');
            }

            return BranchUserMembership::query()->create([
                'user_id' => $subject->getKey(),
                'branch_id' => $branch->getKey(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'granted_by' => $actor->getKey(),
            ]);
        }, 3);
    }

    public function setPrimaryBranch(User $subject, Branch $branch, User $actor): void
    {
        if ($actor->isNot($subject) && ! $this->authorization->allows($actor, 'security.users.manage', $branch)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($subject, $branch) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($subject->getKey());
            if (! $lockedUser->isActive() || ! $branch->fresh()?->is_active) {
                throw new DomainException('El usuario y la sucursal deben estar activos.');
            }
            $hasMembership = BranchUserMembership::query()
                ->whereBelongsTo($lockedUser, 'user')
                ->whereBelongsTo($branch)
                ->activeAt(now())
                ->lockForUpdate()
                ->exists();

            if (! $hasMembership) {
                throw new DomainException('La sucursal principal requiere una membresía vigente.');
            }

            $lockedUser->forceFill(['primary_branch_id' => $branch->getKey()])->save();
        }, 3);
    }

    public function revoke(BranchUserMembership $membership, User $actor, string $reason): void
    {
        if (! $this->authorization->allows($actor, 'security.users.manage', $membership->branch)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($membership, $actor, $reason) {
            User::query()->lockForUpdate()->findOrFail($membership->user_id);
            $locked = BranchUserMembership::query()->lockForUpdate()->findOrFail($membership->getKey());

            if ($locked->ended_by !== null || ($locked->ends_at !== null && $locked->ends_at->lessThanOrEqualTo(now()))) {
                return;
            }

            if (! $this->authorization->allows($actor, 'security.users.manage', $locked->branch)) {
                throw new AuthorizationException;
            }
            if (trim($reason) === '' || mb_strlen($reason) > 255) {
                throw new DomainException('La revocación requiere un motivo de hasta 255 caracteres.');
            }
            // ended_by revokes immediately; keep end > start, including future assignments.
            $now = now()->max($locked->starts_at->copy()->addSecond());
            $locked->forceFill(['ends_at' => $now, 'ended_by' => $actor->getKey(), 'end_reason' => $reason])->save();

            User::query()
                ->whereKey($locked->user_id)
                ->where('primary_branch_id', $locked->branch_id)
                ->update(['primary_branch_id' => null]);

            DB::table(config('permission.table_names.model_has_roles'))
                ->where(config('permission.column_names.team_foreign_key'), $locked->branch_id)
                ->where(config('permission.column_names.model_morph_key'), $locked->user_id)
                ->where('model_type', User::class)
                ->delete();
        }, 3);

        $membership->user?->unsetRelation('roles')->unsetRelation('permissions');
    }
}
