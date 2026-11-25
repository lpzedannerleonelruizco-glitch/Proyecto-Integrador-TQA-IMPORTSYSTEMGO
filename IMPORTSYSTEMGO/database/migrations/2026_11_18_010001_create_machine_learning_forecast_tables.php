<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_demand_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->date('observed_on');
            $table->decimal('quantity', 12, 2);
            $table->string('source', 32)->index();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->unique(['branch_id', 'product_id', 'observed_on', 'source'], 'ml_observations_scope_date_source_unique');
            $table->index(['observed_on', 'branch_id'], 'ml_observations_date_branch_index');
        });

        Schema::create('ml_model_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('model_type', 64)->index();
            $table->string('algorithm', 120);
            $table->string('status', 24)->index();
            $table->date('trained_from');
            $table->date('trained_until');
            $table->unsignedInteger('training_rows');
            $table->json('metrics');
            $table->json('feature_schema');
            $table->string('artifact_path', 255);
            $table->char('artifact_sha256', 64);
            $table->foreignId('trained_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->timestamp('trained_at');
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->index(['model_type', 'status', 'trained_at'], 'ml_models_type_status_trained_index');
        });

        Schema::create('demand_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ml_model_version_id')->constrained('ml_model_versions')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->date('forecast_date');
            $table->unsignedSmallInteger('horizon_days');
            $table->decimal('predicted_quantity', 12, 2);
            $table->decimal('lower_bound', 12, 2);
            $table->decimal('upper_bound', 12, 2);
            $table->decimal('actual_quantity', 12, 2)->nullable();
            $table->decimal('baseline_quantity', 12, 2);
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->unique(['ml_model_version_id', 'branch_id', 'product_id', 'forecast_date', 'horizon_days'], 'demand_forecasts_model_scope_unique');
            $table->index(['branch_id', 'forecast_date', 'horizon_days'], 'demand_forecasts_branch_date_index');
        });

        Schema::create('replenishment_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demand_forecast_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->decimal('physical_quantity_snapshot', 12, 2);
            $table->decimal('reserved_quantity_snapshot', 12, 2);
            $table->decimal('safety_stock', 12, 2);
            $table->decimal('recommended_quantity', 12, 2);
            $table->string('risk_level', 16)->index();
            $table->string('status', 24)->default('pending')->index();
            $table->json('explanation');
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->unique(['demand_forecast_id', 'warehouse_id'], 'replenishment_forecast_warehouse_unique');
            $table->index(['branch_id', 'status', 'risk_level'], 'replenishment_branch_status_risk_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replenishment_recommendations');
        Schema::dropIfExists('demand_forecasts');
        Schema::dropIfExists('ml_model_versions');
        Schema::dropIfExists('ml_demand_observations');
    }
};
