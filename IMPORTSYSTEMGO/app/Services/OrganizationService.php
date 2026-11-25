<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Services\Authorization\BranchContext;
use App\Services\Authorization\GlobalAuthorizationService;
use App\Services\Authorization\OperationalAreaAccessService;
use App\Services\Authorization\ScopedAuthorizationService;
use App\Support\OrganizationResources;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class OrganizationService
{
    public function __construct(
        private readonly BranchContext $context,
        private readonly ScopedAuthorizationService $authorization,
        private readonly GlobalAuthorizationService $global,
        private readonly OperationalAreaAccessService $operationalAreas,
        private readonly IdentityService $identity,
    ) {}

    public function query(User $actor, string $resource, string $ability = 'view'): Builder
    {
        $definition = OrganizationResources::definition($resource);
        abort_unless($this->authorization->allows($actor, "organization.{$resource}.{$ability}"), 403);
        $query = $definition['model']::query();
        if ($resource === 'branches' && $this->global->allows($actor, "organization.branches.{$ability}")) {
            return $this->operationalAreas->constrainBranches($query, $actor);
        }
        $branch = $this->context->current();
        abort_unless($branch, 403);

        return $query->where(match ($resource) {
            'branches' => 'id', 'warehouses' => 'branch_id', 'employees' => 'home_branch_id',
        }, $branch->id);
    }

    public function save(User $actor, string $resource, array $values, ?int $id): Model
    {
        // An unapplied audit migration must not permit unaudited mutations.
        abort_unless(Schema::hasTable('audit_logs'), 503, 'La auditoría requiere aplicar la migración pendiente con autorización.');

        return DB::transaction(function () use ($actor, $resource, $values, $id) {
            $definition = OrganizationResources::definition($resource);
            // Recheck authorization inside the critical operation, not just in the request.
            $query = $this->query($actor, $resource, 'manage');
            $record = $id ? $query->lockForUpdate()->findOrFail($id) : new $definition['model'];
            $branch = $this->context->current();
            if (! $id) {
                if ($resource === 'branches') {
                    abort_unless($this->global->allows($actor, 'organization.branches.manage'), 403);
                } elseif ($resource === 'warehouses') {
                    $record->branch_id = $branch->id;
                } else {
                    $record->home_branch_id = $branch->id;
                }
            }
            if ($id) {
                Gate::forUser($actor)->authorize('update', $record);
            } elseif ($resource === 'warehouses') {
                Gate::forUser($actor)->authorize('create', [$definition['model'], $record]);
            } else {
                Gate::forUser($actor)->authorize('create', $definition['model']);
            }
            $fields = array_keys($definition['fields']);
            $before = $id ? Arr::only($record->getAttributes(), $fields) : null;
            // Whitelist server-defined business fields; never accept ownership or actor IDs.
            $record->forceFill(Arr::only($values, $fields))->save();
            DB::table('audit_logs')->insert([
                'actor_user_id' => $actor->id,
                'branch_id' => $resource === 'branches' ? $record->id : $branch->id,
                'action' => $resource.($id ? '.updated' : '.created'),
                'resource_type' => $resource,
                'resource_id' => $record->id,
                'before_values' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
                'after_values' => json_encode(Arr::only($record->getAttributes(), $fields), JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
            ]);

            if ($resource === 'employees' && $record instanceof Employee && $record->status === Employee::STATUS_TERMINATED) {
                $this->identity->offboardTerminatedEmployee($actor, $record);
            }

            return $record;
        }, 3);
    }
}
