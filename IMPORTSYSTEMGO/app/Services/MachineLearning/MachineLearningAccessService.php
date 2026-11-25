<?php

namespace App\Services\MachineLearning;

use App\Models\Branch;
use App\Models\User;
use App\Services\Authorization\GlobalAuthorizationService;
use App\Services\Authorization\OperationalAreaAccessService;
use App\Services\Authorization\ScopedAuthorizationService;

class MachineLearningAccessService
{
    public function __construct(
        private readonly ScopedAuthorizationService $scoped,
        private readonly GlobalAuthorizationService $global,
        private readonly OperationalAreaAccessService $areas,
    ) {}

    public function canTrain(User $user): bool
    {
        return $this->global->allows($user, 'ml.models.manage')
            && ! $this->areas->isTerritoriallyBound($user);
    }

    public function canView(User $user, Branch $branch): bool
    {
        return $this->areas->allowsBranch($user, $branch)
            && $this->scoped->allows($user, 'ml.forecasts.view', $branch);
    }

    public function canDecide(User $user, Branch $branch): bool
    {
        return $this->areas->allowsBranch($user, $branch)
            && $this->scoped->allows($user, 'ml.replenishment.decide', $branch);
    }

    /** @return list<int> */
    public function allowedBranchIds(User $user): array
    {
        return $this->areas->constrainBranches(Branch::query()->active(), $user)->get()
            ->filter(fn (Branch $branch): bool => $this->canView($user, $branch))
            ->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }
}
