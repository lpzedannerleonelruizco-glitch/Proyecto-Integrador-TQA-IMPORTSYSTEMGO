<?php

namespace App\Jobs;

use App\Mail\QaReportMail;
use App\Models\QaEmailLog;
use App\Models\QaEvidence;
use App\Models\QaExecution;
use App\Models\QaExecutionResult;
use App\Models\QaExecutionStep;
use App\Models\QaReport;
use App\Models\QaTestCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class RunQaExecution implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $executionId) {}

    public function handle(): void
    {
        $this->assertQaDatabase();
        $execution = QaExecution::query()->findOrFail($this->executionId);
        if ($execution->status !== 'PENDING') {
            return;
        }

        $execution->forceFill([
            'status' => 'RUNNING',
            'started_at' => now(),
            'progress' => 0,
            'failure_message' => null,
        ])->save();

        $this->runAndReport($execution);
    }

    public function failed(?Throwable $exception): void
    {
        if (! $this->usesQaDatabase()) {
            return;
        }

        QaExecution::query()->whereKey($this->executionId)->whereIn('status', ['PENDING', 'RUNNING'])->update([
            'status' => 'FAILED',
            'completed_at' => now(),
            'failure_message' => 'infrastructure_error',
            'progress' => 100,
        ]);
    }

    private function runAndReport(QaExecution $execution): void
    {
        $executionDir = storage_path('app/private/qa/executions/'.$execution->public_id);
        File::ensureDirectoryExists($executionDir);
        $manifestPath = $executionDir.'/manifest.json';
        $execution->load(['suite', 'results.case.suite']);
        $manifest = $execution->results->map(function (QaExecutionResult $result): array {
            $case = $result->case;

            return [
                'id' => $case->id,
                'code' => $case->code,
                'name' => $case->name,
                'category' => $case->category,
                'case_type' => $case->case_type,
                'executor' => $case->executor,
                'objective' => $result->objective,
                'preconditions' => $result->preconditions,
                'test_data' => $case->test_data,
                'validations' => $result->validations,
                'expected_result' => json_decode((string) $result->expected_result, true),
                'suite' => $case->suite?->name,
                'automation' => config('qa.automation.'.$case->code, [
                    'status' => 'NOT RUN',
                    'reason' => 'No existe un ejecutor automatizado para este caso.',
                ]),
            ];
        })->values()->all();
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $python = base_path('.venv-qa\\Scripts\\python.exe');
        $runner = base_path('qa_engine\\runner.py');
        $environment = array_filter([
            'PATH' => getenv('PATH') ?: null,
            'SYSTEMROOT' => getenv('SYSTEMROOT') ?: null,
            'WINDIR' => getenv('WINDIR') ?: null,
            'USERPROFILE' => getenv('USERPROFILE') ?: null,
            'LOCALAPPDATA' => getenv('LOCALAPPDATA') ?: null,
            'APPDATA' => getenv('APPDATA') ?: null,
            'HOMEDRIVE' => getenv('HOMEDRIVE') ?: null,
            'HOMEPATH' => getenv('HOMEPATH') ?: null,
            'PROGRAMFILES' => getenv('PROGRAMFILES') ?: null,
            'PROGRAMFILES(X86)' => getenv('PROGRAMFILES(X86)') ?: null,
            'TEMP' => getenv('TEMP') ?: null,
            'TMP' => getenv('TMP') ?: null,
            'APP_CONFIG_CACHE' => getenv('APP_CONFIG_CACHE') ?: null,
            'APP_ROUTES_CACHE' => getenv('APP_ROUTES_CACHE') ?: null,
            'APP_SERVICES_CACHE' => getenv('APP_SERVICES_CACHE') ?: null,
            'APP_EVENTS_CACHE' => getenv('APP_EVENTS_CACHE') ?: null,
            'APP_PACKAGES_CACHE' => getenv('APP_PACKAGES_CACHE') ?: null,
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => database_path('qa.sqlite'),
            'DB_URL' => 'null',
            'QA_BASE_URL' => (string) config('qa.base_url'),
            'QA_E2E_EMAIL' => (string) config('qa.e2e.email'),
            'QA_E2E_PASSWORD' => config('qa.e2e.password'),
            'QA_CONTROLLED_FAIL' => $execution->type === 'controlled_fail' ? '1' : '0',
        ], fn ($value) => $value !== null);
        // Symfony treats false as an explicit exclusion from the child environment.
        $inherited = array_fill_keys(array_keys(getenv() + $_ENV), false);
        $environment += DIRECTORY_SEPARATOR === '\\'
            ? array_diff_ukey($inherited, $environment, 'strcasecmp')
            : array_diff_key($inherited, $environment);
        $process = new Process([
            $python,
            $runner,
            '--execution-id',
            (string) $execution->public_id,
            '--manifest',
            $manifestPath,
            '--execution-dir',
            $executionDir,
            '--base-url',
            (string) config('qa.base_url'),
        ], base_path(), $environment, null, 840);
        $process->mustRun();
        $this->importAndReport($execution);
    }

    private function importAndReport(QaExecution $execution): void
    {
        $executionDir = storage_path('app/private/qa/executions/'.$execution->public_id);
        $resultsPath = $executionDir.'/results.json';
        if (! is_file($resultsPath)) {
            $execution->forceFill([
                'status' => 'FAILED',
                'completed_at' => now(),
                'failure_message' => 'missing_results',
                'progress' => 100,
            ])->save();

            return;
        }

        $payload = json_decode((string) file_get_contents($resultsPath), true, flags: JSON_THROW_ON_ERROR);
        foreach ($payload['results'] ?? [] as $position => $item) {
            $case = QaTestCase::query()->where('code', $item['code'])->first();
            if (! $case) {
                continue;
            }
            $result = QaExecutionResult::query()->where('execution_id', $execution->id)->where('case_id', $case->id)->first();
            if (! $result) {
                continue;
            }
            $result->forceFill([
                'status' => $item['status'],
                'obtained_result' => $item['obtained'] ?? null,
                'observations' => $item['observations'] ?? null,
                'error_message' => $item['error_message'] ?? null,
                'failure_step' => $item['failure_step'] ?? null,
                'duration_ms' => (int) ($item['duration_ms'] ?? 0),
            ])->save();
            $result->steps()->delete();
            foreach ($item['steps'] ?? [] as $sequence => $step) {
                QaExecutionStep::query()->create([
                    'result_id' => $result->id,
                    'sequence' => $sequence + 1,
                    'name' => $step['name'],
                    'description' => $step['description'] ?? null,
                    'status' => $step['status'],
                    'obtained_result' => $step['obtained'] ?? null,
                    'error_message' => $step['error_message'] ?? null,
                ]);
            }

            if (! empty($item['screenshot'])) {
                $screenshot = basename((string) $item['screenshot']);
                $absolutePath = $executionDir.'/screenshots/'.$screenshot;
                if (is_file($absolutePath)) {
                    $relativePath = 'qa/executions/'.$execution->public_id.'/screenshots/'.$screenshot;
                    QaEvidence::query()->updateOrCreate([
                        'execution_id' => $execution->id,
                        'result_id' => $result->id,
                        'relative_path' => $relativePath,
                    ], [
                        'kind' => 'screenshot',
                        'description' => 'Captura real del caso '.$case->code,
                        'sha256' => hash_file('sha256', $absolutePath),
                    ]);
                }
            }
        }

        $started = $execution->started_at?->getTimestampMs() ?? (int) (microtime(true) * 1000);
        $finished = now();
        $reportProcess = new Process([
            base_path('.venv-qa\\Scripts\\python.exe'),
            base_path('qa_engine\\generate_reports.py'),
            '--execution-dir',
            $executionDir,
            '--execution-id',
            (string) $execution->public_id,
            '--recipient',
            $execution->recipient_email,
            '--suite',
            $execution->suite?->name ?: $execution->type,
        ], base_path(), null, null, 120);
        $reportProcess->mustRun();
        $reportPayload = json_decode($reportProcess->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        foreach ([['PDF', $reportPayload['pdf']], ['HTML', $reportPayload['html']]] as [$format, $absolutePath]) {
            $relative = 'qa/executions/'.$execution->public_id.'/reports/'.basename((string) $absolutePath);
            QaReport::query()->updateOrCreate([
                'execution_id' => $execution->id,
                'format' => $format,
            ], [
                'relative_path' => $relative,
                'sha256' => hash_file('sha256', (string) $absolutePath),
                'bytes' => filesize((string) $absolutePath) ?: 0,
            ]);
        }

        $progressCount = $execution->results()->count();
        $execution->forceFill([
            'status' => 'COMPLETED',
            'completed_at' => $finished,
            'duration_ms' => max(0, (int) ($finished->getTimestampMs() - $started)),
            'progress' => $progressCount > 0 ? 100 : 0,
        ])->save();

        $log = QaEmailLog::query()->create([
            'execution_id' => $execution->id,
            'recipient_email' => $execution->recipient_email,
            'status' => 'PENDING',
            'attempt' => 1,
        ]);
        if (config('mail.default') !== 'smtp') {
            $execution->forceFill(['email_status' => 'FAILED'])->save();
            $log->forceFill([
                'status' => 'FAILED',
                'error_message' => 'smtp_transport_required',
            ])->save();

            return;
        }

        try {
            Mail::to($execution->recipient_email)->send(new QaReportMail($execution->fresh()));
            $execution->forceFill(['email_status' => 'SENT'])->save();
            $log->forceFill(['status' => 'SENT', 'sent_at' => now()])->save();
        } catch (Throwable $exception) {
            $execution->forceFill(['email_status' => 'FAILED'])->save();
            $log->forceFill([
                'status' => 'FAILED',
                'error_message' => 'email_delivery_failed: '.class_basename($exception),
            ])->save();
        }
    }

    private function assertQaDatabase(): void
    {
        if (! $this->usesQaDatabase()) {
            throw new RuntimeException('qa_database_mismatch');
        }
    }

    private function usesQaDatabase(): bool
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        $expected = realpath(database_path('qa.sqlite'));
        $actual = realpath($database);

        return config('database.default') === 'sqlite'
            && $expected !== false
            && $actual !== false
            && strcasecmp($actual, $expected) === 0;
    }
}
