<?php

namespace Tests\Feature;

use App\Jobs\RunQaExecution;
use App\Mail\QaReportMail;
use App\Models\GlobalUserRole;
use App\Models\QaAiSession;
use App\Models\QaExecution;
use App\Models\QaReport;
use App\Models\QaSuite;
use App\Models\QaTestCase;
use App\Models\User;
use App\Services\Qa\QaAiService;
use App\Services\Qa\QaCredentialService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\QaCenterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QaCenterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seed(AuthorizationSeeder::class);
        $this->seed(QaCenterSeeder::class);
    }

    public function test_catalog_is_seeded_idempotently(): void
    {
        $this->seed(QaCenterSeeder::class);
        $this->assertSame(7, QaSuite::count());
        $this->assertSame(23, QaTestCase::count());
        $this->assertDatabaseMissing('users', ['email' => 'qa.admin@example.test']);
    }

    public function test_qa_routes_require_authorized_administrator(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('qa.dashboard'))->assertForbidden();

        $role = Role::findByName('administrador-general', 'web');
        GlobalUserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'starts_at' => now(),
        ]);
        $this->actingAs($user)->get(route('qa.dashboard'))->assertOk();
        $this->get(route('qa.cases.index'))->assertOk();
        $this->get(route('qa.executions.create'))->assertOk();
    }

    public function test_execution_rejects_invalid_email_and_missing_cases(): void
    {
        $user = User::factory()->create();
        $role = Role::findByName('administrador-general', 'web');
        GlobalUserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'starts_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('qa.executions.store'), [
                'recipient_email' => 'not-an-email',
                'type' => 'functional',
                'case_ids' => [],
            ])
            ->assertSessionHasErrors(['recipient_email', 'case_ids']);
    }

    public function test_execution_rejects_cases_outside_the_selected_type_or_suite(): void
    {
        $user = User::factory()->create();
        $role = Role::findByName('administrador-general', 'web');
        GlobalUserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'starts_at' => now(),
        ]);
        $suite = QaSuite::query()->where('code', 'NON_FUNCTIONAL')->firstOrFail();
        $performanceCase = QaTestCase::query()->where('code', 'TC-NF-001')->firstOrFail();
        $functionalCase = QaTestCase::query()->where('code', 'TC-SMK-001')->firstOrFail();

        $this->actingAs($user)->post(route('qa.executions.store'), [
            'recipient_email' => 'qa.admin@example.test',
            'type' => 'functional',
            'suite_id' => $suite->id,
            'case_ids' => [$performanceCase->id],
        ])->assertSessionHasErrors('case_ids');

        $this->post(route('qa.executions.store'), [
            'recipient_email' => 'qa.admin@example.test',
            'type' => 'controlled_fail',
            'case_ids' => [$functionalCase->id],
        ])->assertSessionHasErrors('case_ids');

        $this->assertSame(0, QaExecution::count());
    }

    public function test_execution_creates_pending_results_and_owner_can_view(): void
    {
        $user = User::factory()->create();
        $role = Role::findByName('administrador-general', 'web');
        GlobalUserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'starts_at' => now(),
        ]);
        $case = QaTestCase::query()->firstOrFail();

        $response = $this->actingAs($user)->post(route('qa.executions.store'), [
            'recipient_email' => 'qa@example.test',
            'type' => 'functional',
            'case_ids' => [$case->id],
        ]);

        $execution = QaExecution::query()->firstOrFail();
        $response->assertRedirect(route('qa.executions.show', $execution));
        $this->assertSame('PENDING', $execution->status);
        $this->assertSame(1, $execution->results()->count());
        $this->assertSame('NOT RUN', $execution->results()->firstOrFail()->status);
        $this->actingAs($user)->get(route('qa.executions.show', $execution))->assertOk();
    }

    public function test_worker_failure_does_not_update_an_execution_outside_the_qa_database(): void
    {
        $execution = QaExecution::query()->create([
            'public_id' => (string) Str::uuid(),
            'owner_id' => User::factory()->create()->id,
            'recipient_email' => 'qa@example.test',
            'type' => 'functional',
            'selected_case_ids' => [],
            'status' => 'RUNNING',
            'progress' => 0,
            'email_status' => 'NOT_SENT',
        ]);

        (new RunQaExecution($execution->id))->failed(new \RuntimeException('test'));

        $this->assertSame('RUNNING', $execution->refresh()->status);
        $this->assertNull($execution->failure_message);
    }

    public function test_owner_cannot_read_another_owner_execution(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $role = Role::findByName('administrador-general', 'web');
        GlobalUserRole::create([
            'user_id' => $owner->id,
            'role_id' => $role->id,
            'starts_at' => now(),
        ]);
        GlobalUserRole::create([
            'user_id' => $other->id,
            'role_id' => $role->id,
            'starts_at' => now(),
        ]);
        $case = QaTestCase::query()->firstOrFail();

        $this->actingAs($owner)->post(route('qa.executions.store'), [
            'recipient_email' => 'qa@example.test',
            'type' => 'functional',
            'case_ids' => [$case->id],
        ]);
        $execution = QaExecution::query()->firstOrFail();
        Session::flush();
        $this->actingAs($other)->get(route('qa.executions.show', $execution))->assertForbidden();
        Session::flush();
        $this->actingAs($other)->get(route('qa.executions.progress', $execution))->assertForbidden();
    }

    public function test_ai_demo_only_serves_prepared_examples_to_authorized_users(): void
    {
        $user = User::factory()->create();
        $input = ['mode' => 'demo', 'prompt' => 'COMO   PRUEBO EL INICIO DE SESION?'];
        $this->actingAs($user)->post(route('qa.ai.store'), $input)->assertForbidden();

        GlobalUserRole::create([
            'user_id' => $user->id,
            'role_id' => Role::findByName('administrador-general', 'web')->id,
            'starts_at' => now(),
        ]);
        $this->actingAs($user)->post(route('qa.ai.store'), $input)
            ->assertRedirect(route('qa.ai').'#demo-ia')
            ->assertSessionHas('qa_demo.answer', fn ($answer) => str_contains($answer, 'credenciales válidas'));
        $this->get(route('qa.ai'))->assertOk()
            ->assertSee('Modo local · respuestas preparadas')
            ->assertSee('Asistente QA')
            ->assertSee('credenciales válidas');

        $this->post(route('qa.ai.store'), ['mode' => 'demo', 'prompt' => '¿Cómo pruebo un correo inválido?'])
            ->assertSessionHas('qa_demo.answer', fn ($answer) => str_contains($answer, 'usuario-sin-arroba'));
        $this->post(route('qa.ai.store'), ['mode' => 'demo', 'prompt' => '¿Cómo pruebo el stock insuficiente?'])
            ->assertSessionHas('qa_demo.answer', fn ($answer) => str_contains($answer, 'solicita 4'));
        $this->post(route('qa.ai.store'), ['mode' => 'demo', 'prompt' => 'Pregunta fuera del catálogo'])
            ->assertSessionHas('qa_demo', ['answer' => null, 'prompt' => null]);

        $this->assertSame(0, QaAiSession::count());
    }

    public function test_ai_without_provider_is_recorded_as_failed_not_as_generated_pass(): void
    {
        config(['qa.ai.provider' => 'none']);
        $user = User::factory()->create();
        $session = app(QaAiService::class)->analyze($user, 'Analizar login incorrecto y formular escenarios Gherkin.');

        $this->assertSame('FAILED', $session->status);
        $this->assertSame('ai_not_configured', $session->error_message);
        $this->assertSame(1, QaAiSession::count());
        $this->assertDatabaseMissing('qa_execution_results', ['status' => 'PASS']);
    }

    public function test_qa_report_email_requires_both_unchanged_attachments_and_counts_blocked_results(): void
    {
        $user = User::factory()->create();
        $case = QaTestCase::query()->firstOrFail();
        $execution = QaExecution::query()->create([
            'public_id' => (string) str()->uuid(),
            'owner_id' => $user->id,
            'recipient_email' => 'qa.admin@example.test',
            'type' => 'functional',
            'selected_case_ids' => [$case->id],
            'status' => 'COMPLETED',
        ]);
        $execution->results()->create([
            'case_id' => $case->id,
            'status' => 'BLOCKED',
        ]);

        $executionDirectory = storage_path('app/private/qa/executions/'.$execution->public_id);
        $reportsDirectory = $executionDirectory.'/reports';
        File::ensureDirectoryExists($reportsDirectory);

        try {
            foreach (['PDF' => 'report.pdf', 'HTML' => 'report.html'] as $format => $filename) {
                $relativePath = 'qa/executions/'.$execution->public_id.'/reports/'.$filename;
                $absolutePath = storage_path('app/private/'.$relativePath);
                file_put_contents($absolutePath, 'fixture '.$format);
                QaReport::query()->create([
                    'execution_id' => $execution->id,
                    'format' => $format,
                    'relative_path' => $relativePath,
                    'sha256' => hash_file('sha256', $absolutePath),
                    'bytes' => filesize($absolutePath),
                ]);
            }

            $mail = new QaReportMail($execution);
            $this->assertCount(2, $mail->attachments());
            $this->assertSame(1, $mail->content()->with['blocked']);
            $this->assertStringContainsString('functional', $mail->envelope()->subject);

            file_put_contents($reportsDirectory.'/report.pdf', 'tampered PDF');
            $this->expectException(\RuntimeException::class);
            $mail->attachments();
        } finally {
            File::deleteDirectory($executionDirectory);
        }
    }

    public function test_qa_credential_service_only_resets_the_authorized_demo_account_without_exposing_the_secret(): void
    {
        $user = User::factory()->create([
            'email' => QaCredentialService::EMAIL,
            'status' => User::STATUS_ACTIVE,
        ]);
        $role = Role::findByName('administrador-general', 'web');
        GlobalUserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'starts_at' => now(),
        ]);
        $password = 'LocalOnly!Qa2026';

        $updated = app(QaCredentialService::class)->reset($password);

        $this->assertTrue(Hash::check($password, $updated->password));
        $this->assertNotNull($updated->password_changed_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $user->id,
            'action' => 'qa.credentials.reset',
        ]);
        $this->assertDatabaseMissing('audit_logs', ['after_values' => $password]);
    }

    public function test_qa_password_command_refuses_the_in_memory_test_database(): void
    {
        $this->artisan('qa:reset-demo-password', ['--from-environment' => true])
            ->expectsOutputToContain('database/qa.sqlite')
            ->assertFailed();
    }
}
