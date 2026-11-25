<?php

namespace App\Http\Controllers;

use App\Jobs\RunQaExecution;
use App\Models\QaAiSession;
use App\Models\QaEvidence;
use App\Models\QaExecution;
use App\Models\QaSuite;
use App\Models\QaTestCase;
use App\Services\Authorization\GlobalAuthorizationService;
use App\Services\Qa\QaAiDemo;
use App\Services\Qa\QaAiService;
use App\Services\Qa\QaExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QaCenterController extends Controller
{
    public function __construct(private readonly GlobalAuthorizationService $global) {}

    public function dashboard(Request $request)
    {
        abort_unless($this->global->allows($request->user(), 'qa.executions.view'), 403);
        $executions = Schema::hasTable('qa_executions') ? QaExecution::query()->latest('id')->limit(10)->get() : collect();

        return view('qa.dashboard', [
            'executions' => $executions,
            'suiteCount' => Schema::hasTable('qa_suites') ? QaSuite::where('is_active', true)->count() : 0,
            'caseCount' => Schema::hasTable('qa_test_cases') ? QaTestCase::where('is_active', true)->count() : 0,
        ]);
    }

    public function cases(Request $request)
    {
        abort_unless($this->global->allows($request->user(), 'qa.cases.view'), 403);
        $suites = QaSuite::query()->withCount('cases')->where('is_active', true)->orderBy('code')->get();
        $cases = QaTestCase::query()->with('suite')->where('is_active', true)->orderBy('code')->get();

        return view('qa.cases', [
            'suites' => $suites,
            'cases' => $cases,
            'automation' => config('qa.automation', []),
        ]);
    }

    public function createExecution(Request $request)
    {
        abort_unless($this->global->allows($request->user(), 'qa.executions.run'), 403);

        return view('qa.create', [
            'suites' => QaSuite::query()->where('is_active', true)->orderBy('code')->get(),
            'cases' => QaTestCase::query()->with('suite')->where('is_active', true)->orderBy('code')->get(),
            'automation' => config('qa.automation', []),
        ]);
    }

    public function storeExecution(Request $request, QaExecutionService $service)
    {
        abort_unless($this->global->allows($request->user(), 'qa.executions.run'), 403);
        $validated = $request->validate([
            'recipient_email' => ['required', 'email:rfc', 'max:254'],
            'type' => ['required', 'in:functional,non_functional,regression,all,controlled_fail'],
            'suite_id' => ['nullable', 'integer', 'exists:qa_suites,id'],
            'case_ids' => ['required', 'array', 'min:1', 'max:23'],
            'case_ids.*' => ['integer', 'exists:qa_test_cases,id'],
        ]);

        $execution = $service->create($request->user(), $validated);
        RunQaExecution::dispatch($execution->id);

        return redirect()->route('qa.executions.show', $execution)->with('status', 'Ejecución creada y puesta en cola.');
    }

    public function showExecution(Request $request, QaExecution $execution)
    {
        abort_unless($this->global->allows($request->user(), 'qa.executions.view'), 403);
        abort_unless($execution->owner_id === $request->user()->id, 403);
        $execution->load(['suite', 'results.case', 'results.steps', 'results.evidences', 'evidences', 'reports', 'emailLogs']);

        return view('qa.execution', compact('execution'));
    }

    public function progress(Request $request, QaExecution $execution): JsonResponse
    {
        abort_unless($this->global->allows($request->user(), 'qa.executions.view'), 403);
        abort_unless($execution->owner_id === $request->user()->id, 403);

        return response()->json([
            'status' => $execution->status,
            'progress' => $execution->progress,
            'email_status' => $execution->email_status,
            'completed_at' => optional($execution->completed_at)?->toIso8601String(),
            'counts' => [
                'PASS' => $execution->results()->where('status', 'PASS')->count(),
                'FAIL' => $execution->results()->where('status', 'FAIL')->count(),
                'SKIPPED' => $execution->results()->where('status', 'SKIPPED')->count(),
                'BLOCKED' => $execution->results()->where('status', 'BLOCKED')->count(),
                'NOT RUN' => $execution->results()->where('status', 'NOT RUN')->count(),
            ],
        ]);
    }

    public function history(Request $request)
    {
        abort_unless($this->global->allows($request->user(), 'qa.executions.view'), 403);
        $executions = QaExecution::query()->where('owner_id', $request->user()->id)->latest('id')->paginate(15);

        return view('qa.history', compact('executions'));
    }

    public function download(Request $request, QaExecution $execution, string $format): BinaryFileResponse
    {
        abort_unless($this->global->allows($request->user(), 'qa.reports.generate'), 403);
        abort_unless($execution->owner_id === $request->user()->id, 403);
        $report = $execution->reports()->where('format', strtoupper($format))->firstOrFail();
        $path = storage_path('app/private/'.$report->relative_path);
        abort_unless(is_file($path) && hash_file('sha256', $path) === $report->sha256, 409);

        return response()->download($path, basename($report->relative_path));
    }

    public function evidence(Request $request, QaExecution $execution, QaEvidence $evidence): BinaryFileResponse
    {
        abort_unless($this->global->allows($request->user(), 'qa.evidence.view'), 403);
        abort_unless($execution->owner_id === $request->user()->id && $evidence->execution_id === $execution->id, 404);

        $expectedPrefix = 'qa/executions/'.$execution->public_id.'/screenshots/';
        abort_unless(
            $evidence->kind === 'screenshot'
            && str_starts_with($evidence->relative_path, $expectedPrefix)
            && basename($evidence->relative_path) === substr($evidence->relative_path, strlen($expectedPrefix)),
            404,
        );

        $path = storage_path('app/private/'.$evidence->relative_path);
        abort_unless(is_file($path) && hash_equals((string) $evidence->sha256, (string) hash_file('sha256', $path)), 409);

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function ai(Request $request)
    {
        abort_unless($this->global->allows($request->user(), 'qa.ai.use'), 403);
        $sessions = QaAiSession::query()->where('owner_id', $request->user()->id)->latest('id')->limit(10)->get();

        return view('qa.ai', [
            'sessions' => $sessions,
            'provider' => config('qa.ai.provider', 'none'),
            'model' => config('qa.ai.model'),
            'demoQuestions' => array_keys(QaAiDemo::EXAMPLES),
        ]);
    }

    public function aiStore(Request $request, QaAiService $ai, QaAiDemo $demo)
    {
        abort_unless($this->global->allows($request->user(), 'qa.ai.use'), 403);
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'min:10', 'max:8000'],
            'mode' => ['sometimes', 'in:demo'],
        ]);
        if (($validated['mode'] ?? null) === 'demo') {
            $answer = $demo->answer($validated['prompt']);

            return redirect()->route('qa.ai')->withFragment('demo-ia')->with('qa_demo', [
                'answer' => $answer,
                'prompt' => $answer === null ? null : $validated['prompt'],
            ]);
        }

        $session = $ai->analyze($request->user(), $validated['prompt']);

        return redirect()->route('qa.ai')->with(
            $session->status === 'COMPLETED' ? 'status' : 'error',
            $session->status === 'COMPLETED' ? 'Análisis generado por el proveedor configurado.' : 'El proveedor no pudo responder: '.$session->error_message,
        );
    }
}
