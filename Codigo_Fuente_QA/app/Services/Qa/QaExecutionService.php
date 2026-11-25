<?php

namespace App\Services\Qa;

use App\Models\QaExecution;
use App\Models\QaSuite;
use App\Models\QaTestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QaExecutionService
{
    public function create(User $owner, array $validated): QaExecution
    {
        $caseIds = collect($validated['case_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $type = $validated['type'];
        $suiteId = $validated['suite_id'] ?? null;

        if ($suiteId && ! QaSuite::query()->whereKey($suiteId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['suite_id' => 'La suite seleccionada no está activa.']);
        }

        $query = QaTestCase::query()->where('is_active', true)->whereIn('id', $caseIds);
        if ($suiteId) {
            $query->where('suite_id', $suiteId);
        }

        match ($type) {
            'functional' => $query->whereIn('case_type', ['functional', 'integration', 'e2e', 'security']),
            'non_functional' => $query->where('case_type', 'performance'),
            'regression' => $query->where('category', 'regression'),
            'controlled_fail' => $query->where('code', 'TC-SAN-003'),
            default => null,
        };

        $allowed = $query->pluck('id')->all();
        if (count($allowed) !== count($caseIds)) {
            throw ValidationException::withMessages([
                'case_ids' => 'Los casos deben estar activos y coincidir con el tipo y la suite seleccionados.',
            ]);
        }

        if ($type === 'controlled_fail' && count($caseIds) !== 1) {
            throw ValidationException::withMessages([
                'case_ids' => 'La demostración FAIL CONTROLADO ejecuta únicamente el caso de validación HTML5.',
            ]);
        }

        return DB::transaction(function () use ($owner, $validated, $caseIds): QaExecution {
            $execution = QaExecution::query()->create([
                'public_id' => (string) Str::uuid(),
                'owner_id' => $owner->id,
                'recipient_email' => strtolower(trim($validated['recipient_email'])),
                'type' => $validated['type'],
                'suite_id' => $validated['suite_id'] ?? null,
                'selected_case_ids' => $caseIds,
                'status' => 'PENDING',
                'progress' => 0,
                'email_status' => 'NOT_SENT',
            ]);

            foreach (QaTestCase::query()->whereIn('id', $caseIds)->orderBy('code')->get() as $case) {
                $execution->results()->create([
                    'case_id' => $case->id,
                    'status' => 'NOT RUN',
                    'objective' => $case->objective,
                    'preconditions' => $case->preconditions,
                    'expected_result' => json_encode($case->expected_result),
                    'validations' => $case->validations,
                ]);
            }

            return $execution;
        });
    }
}
