<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\ReplenishmentRecommendation;
use App\Models\Sale;
use App\Models\User;
use App\Services\MachineLearning\DemandForecastTrainingService;
use App\Services\MachineLearning\PythonDemandTrainer;
use App\Services\MachineLearning\ReplenishmentDecisionService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DemoMachineLearningHistorySeeder;
use Database\Seeders\DemoRetailSeeder;
use Database\Seeders\OperationalDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MachineLearningForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.retail_enabled' => true, 'demo.database' => $this->app->make('db')->connection()->getDatabaseName()]);
        $this->seed(AuthorizationSeeder::class);
        $this->seed(DemoRetailSeeder::class);
        $this->seed(OperationalDemoSeeder::class);
        $this->app->instance(PythonDemandTrainer::class, new class extends PythonDemandTrainer
        {
            public function train(string $input, string $output, string $artifact): array
            {
                file_put_contents($artifact, 'verified-test-model');
                $payload = json_decode((string) file_get_contents($input), true, flags: JSON_THROW_ON_ERROR);

                return [
                    'algorithm' => 'HistGradientBoostingRegressor',
                    'trained_from' => '2025-01-01',
                    'trained_until' => '2026-10-31',
                    'training_rows' => 4200,
                    'metrics' => ['mae' => 1.2, 'wape' => 0.12, 'bias' => -0.1, 'baseline_wape' => 0.20, 'validation_rows' => 840, 'champion' => true],
                    'feature_schema' => ['branch_id', 'product_id', 'lag_7', 'rolling_mean_28'],
                    'artifact_sha256' => hash_file('sha256', $artifact),
                    'predictions' => collect($payload['series'])->map(fn (array $series): array => [
                        'branch_id' => $series['branch_id'],
                        'product_id' => $series['product_id'],
                        'predicted_quantity' => 18.0,
                        'lower_bound' => 13.0,
                        'upper_bound' => 24.0,
                        'baseline_quantity' => 15.0,
                    ])->all(),
                ];
            }
        });
    }

    public function test_ml_schema_is_versioned_and_model_is_persisted_with_integrity_metadata(): void
    {
        $this->assertTrue(Schema::hasTable('ml_model_versions'));
        $this->assertTrue(Schema::hasTable('demand_forecasts'));
        $this->assertTrue(Schema::hasTable('replenishment_recommendations'));
        $admin = User::where('email', 'admin.demo@importsystemgo.example')->firstOrFail();

        $model = app(DemandForecastTrainingService::class)->train($admin);

        $this->assertSame('active', $model->status);
        $this->assertSame('HistGradientBoostingRegressor', $model->algorithm);
        $this->assertSame(84, $model->forecasts()->count());
        $this->assertSame(84, ReplenishmentRecommendation::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'ml.demand_model.trained', 'resource_id' => $model->id]);
    }

    public function test_synthetic_demo_history_is_separate_reproducible_and_idempotent(): void
    {
        $this->seed(DemoMachineLearningHistorySeeder::class);
        $count = DB::table('ml_demand_observations')->where('source', 'synthetic_demo')->count();
        $this->assertSame(45360, $count);
        $this->seed(DemoMachineLearningHistorySeeder::class);
        $this->assertSame($count, DB::table('ml_demand_observations')->where('source', 'synthetic_demo')->count());
        $this->assertSame(15, Sale::count());
        $this->assertSame(84, InventoryBalance::count());
    }

    public function test_authorized_manager_can_view_forecasts_but_seller_cannot(): void
    {
        $this->withoutMiddleware(AuthenticateSession::class);
        $admin = User::where('email', 'admin.demo@importsystemgo.example')->firstOrFail();
        app(DemandForecastTrainingService::class)->train($admin);
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $manager = User::where('email', 'jefe.lp01@importsystemgo.example')->firstOrFail();
        $seller = User::where('email', 'vendedor.lp01.01@importsystemgo.example')->firstOrFail();

        $this->actingAs($manager)->withSession(['active_branch_id' => $branch->id])
            ->get(route('ml.forecasts.index'))->assertOk()->assertSee('Inteligencia de demanda')->assertSee('LP01')->assertDontSee('EA01');
        $this->actingAs($seller)->withSession(['active_branch_id' => $branch->id])
            ->get(route('ml.forecasts.index'))->assertForbidden();
    }

    public function test_territorial_manager_cannot_see_another_city_forecasts(): void
    {
        $this->withoutMiddleware(AuthenticateSession::class);
        $admin = User::where('email', 'admin.demo@importsystemgo.example')->firstOrFail();
        app(DemandForecastTrainingService::class)->train($admin);
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $operations = User::where('email', 'operaciones.lp@importsystemgo.example')->firstOrFail();

        $this->actingAs($operations)->withSession(['active_branch_id' => $branch->id])
            ->get(route('ml.forecasts.index'))->assertOk()->assertSee('LP01')->assertDontSee('EA01');
    }

    public function test_recommendation_requires_authorized_human_decision_and_is_idempotent(): void
    {
        $this->withoutMiddleware(AuthenticateSession::class);
        $admin = User::where('email', 'admin.demo@importsystemgo.example')->firstOrFail();
        app(DemandForecastTrainingService::class)->train($admin);
        $recommendation = ReplenishmentRecommendation::whereHas('branch', fn ($query) => $query->where('code', 'LP01'))->firstOrFail();
        $branch = $recommendation->branch;
        $buyer = User::where('email', 'compras.lp01@importsystemgo.example')->firstOrFail();

        $this->actingAs($buyer)->withSession(['active_branch_id' => $branch->id])
            ->patch(route('ml.recommendations.decide', $recommendation), ['decision' => 'approved', 'note' => 'Revisado para la demostración'])
            ->assertRedirect();
        $this->assertDatabaseHas('replenishment_recommendations', ['id' => $recommendation->id, 'status' => 'approved', 'decided_by' => $buyer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ml.replenishment.approved', 'resource_id' => $recommendation->id]);

        $this->actingAs($buyer)->withSession(['active_branch_id' => $branch->id])
            ->patch(route('ml.recommendations.decide', $recommendation), ['decision' => 'approved'])
            ->assertSessionHasErrors('decision');
    }

    public function test_buyer_cannot_decide_recommendation_from_another_branch(): void
    {
        $admin = User::where('email', 'admin.demo@importsystemgo.example')->firstOrFail();
        app(DemandForecastTrainingService::class)->train($admin);
        $recommendation = ReplenishmentRecommendation::whereHas('branch', fn ($query) => $query->where('code', 'EA01'))->firstOrFail();
        $buyer = User::where('email', 'compras.lp01@importsystemgo.example')->firstOrFail();

        $this->expectException(AuthorizationException::class);
        app(ReplenishmentDecisionService::class)
            ->decide($buyer, $recommendation, 'approved', null);
    }

    public function test_missing_active_branch_redirects_to_selector_with_clear_message(): void
    {
        $this->withoutMiddleware(AuthenticateSession::class);
        $manager = User::where('email', 'jefe.lp01@importsystemgo.example')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('ml.forecasts.index'))
            ->assertRedirect(route('branches.select'))
            ->assertSessionHas('status', 'Selecciona una sucursal para consultar los pronósticos.');
    }

    public function test_professional_view_and_simulator_are_read_only(): void
    {
        $this->withoutMiddleware(AuthenticateSession::class);
        $admin = User::where('email', 'admin.demo@importsystemgo.example')->firstOrFail();
        app(DemandForecastTrainingService::class)->train($admin);
        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $manager = User::where('email', 'jefe.lp01@importsystemgo.example')->firstOrFail();
        $recommendation = ReplenishmentRecommendation::where('branch_id', $branch->id)->firstOrFail();
        $beforeRecommendations = ReplenishmentRecommendation::count();
        $beforeForecasts = DB::table('demand_forecasts')->count();
        $beforeAudit = DB::table('audit_logs')->count();

        $response = $this->actingAs($manager)->withSession(['active_branch_id' => $branch->id])
            ->get(route('ml.forecasts.index', [
                'simulation_product' => $recommendation->product_id,
                'simulation_branch' => $branch->id,
                'simulation_factor' => 1.2,
                'simulation_safety' => 5,
                'simulation_lead' => 14,
                'simulation_available' => 20,
                'sort' => 'quantity_asc',
            ]));

        $response->assertOk()
            ->assertSee('Inteligencia de demanda')
            ->assertSee('Datos académicos DEMO')
            ->assertSee('LP01')
            ->assertSee('Cómo funciona')
            ->assertSee('Simulador ¿qué pasaría si?')
            ->assertSee('Esta es una simulación académica')
            ->assertSee('Historial de modelos')
            ->assertSee('Detalle');

        $this->assertSame($beforeRecommendations, ReplenishmentRecommendation::count());
        $this->assertSame($beforeForecasts, DB::table('demand_forecasts')->count());
        $this->assertSame($beforeAudit, DB::table('audit_logs')->count());
    }

    public function test_user_without_valid_membership_gets_forbidden_instead_of_redirect(): void
    {
        $this->withoutMiddleware(AuthenticateSession::class);
        $seller = User::where('email', 'vendedor.lp01.01@importsystemgo.example')->firstOrFail();

        foreach ($seller->branchMemberships as $membership) {
            $membership->forceFill(['ended_by' => $seller->id, 'ends_at' => now(), 'end_reason' => 'Prueba de expiración'])->save();
        }

        $this->actingAs($seller)
            ->get(route('ml.forecasts.index'))
            ->assertForbidden();
    }
}
