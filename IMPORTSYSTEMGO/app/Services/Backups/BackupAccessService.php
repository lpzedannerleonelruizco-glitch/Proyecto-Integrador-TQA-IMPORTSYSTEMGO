<?php

namespace App\Services\Backups;

use App\Models\BackupArchive;
use App\Models\Branch;
use App\Models\User;
use App\Services\Authorization\GlobalAuthorizationService;
use App\Services\Authorization\OperationalAreaAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BackupAccessService
{
    public function __construct(
        private readonly GlobalAuthorizationService $global,
        private readonly OperationalAreaAccessService $areas,
    ) {}

    public function canView(User $user): bool
    {
        return $this->global->allows($user, 'backup.view');
    }

    public function canCreateFull(User $user): bool
    {
        return $this->global->allows($user, 'backup.create')
            && ! $this->areas->isTerritoriallyBound($user);
    }

    public function canCreateTerritorial(User $user): bool
    {
        return $this->global->allows($user, 'backup.territorial_export');
    }

    public function canVerify(User $user): bool
    {
        return $this->global->allows($user, 'backup.verify')
            && ! $this->areas->isTerritoriallyBound($user);
    }

    public function canDownload(User $user, BackupArchive $archive): bool
    {
        if (! $this->global->allows($user, 'backup.download') || $archive->status !== BackupArchive::STATUS_COMPLETED) {
            return false;
        }
        if ($archive->kind === 'full') {
            return ! $this->areas->isTerritoriallyBound($user)
                && $this->global->allows($user, 'backup.create');
        }
        if ($archive->kind !== 'territorial') {
            return false;
        }

        return $this->canUseTerritorialScope($user, $archive);
    }

    public function canUseTerritorialScope(User $user, BackupArchive $archive): bool
    {
        if (! $this->canCreateTerritorial($user)) {
            return false;
        }
        $allowed = $this->allowedBranchIds($user);
        $scope = array_map('intval', $archive->scope['branch_ids'] ?? []);

        return $scope !== [] && array_diff($scope, $allowed) === [];
    }

    /** @return list<int> */
    public function allowedBranchIds(User $user): array
    {
        return $this->areas->constrainBranches(Branch::query()->where('is_active', true), $user)
            ->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @return Builder<BackupArchive> */
    public function visibleArchives(User $user)
    {
        $query = BackupArchive::query()->with('creator:id,name,email')->orderByDesc('created_at');
        if ($this->areas->isTerritoriallyBound($user)) {
            $query->where('created_by', $user->id)->where('kind', 'territorial');
        }

        return $query;
    }

    public function audit(User $actor, string $action, BackupArchive $archive, array $details = []): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actor->id,
            'branch_id' => null,
            'action' => $action,
            'resource_type' => 'backup_archives',
            'resource_id' => $archive->id,
            'before_values' => null,
            'after_values' => json_encode($details, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
        ]);
    }
}
