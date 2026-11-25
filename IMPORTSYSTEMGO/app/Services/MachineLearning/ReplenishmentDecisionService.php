<?php

namespace App\Services\MachineLearning;

use App\Models\ReplenishmentRecommendation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplenishmentDecisionService
{
    public function __construct(private readonly MachineLearningAccessService $access) {}

    public function decide(User $actor, ReplenishmentRecommendation $recommendation, string $decision, ?string $note): void
    {
        if (! $this->access->canDecide($actor, $recommendation->branch)) {
            throw new AuthorizationException('No puede decidir recomendaciones de esta sucursal.');
        }
        if ($recommendation->forecast->modelVersion->status !== 'active') {
            throw ValidationException::withMessages(['decision' => 'Solo se pueden decidir recomendaciones de un modelo activo que superó la línea base.']);
        }
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['decision' => 'La decisión no es válida.']);
        }
        DB::transaction(function () use ($actor, $recommendation, $decision, $note): void {
            $locked = ReplenishmentRecommendation::query()->lockForUpdate()->findOrFail($recommendation->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'La recomendación ya fue decidida.']);
            }
            $locked->forceFill([
                'status' => $decision,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ])->save();
            DB::table('audit_logs')->insert([
                'actor_user_id' => $actor->id,
                'branch_id' => $locked->branch_id,
                'action' => 'ml.replenishment.'.$decision,
                'resource_type' => 'replenishment_recommendations',
                'resource_id' => $locked->id,
                'before_values' => json_encode(['status' => 'pending'], JSON_THROW_ON_ERROR),
                'after_values' => json_encode(['status' => $decision, 'decision_note' => $note], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
            ]);
        }, 3);
    }
}
