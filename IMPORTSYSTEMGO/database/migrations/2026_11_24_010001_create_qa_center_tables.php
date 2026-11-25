<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_suites', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->string('type', 32);
            $table->string('category', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('qa_test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suite_id')->nullable()->constrained('qa_suites')->nullOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name', 160);
            $table->string('category', 32);
            $table->string('case_type', 32);
            $table->string('executor', 32);
            $table->text('objective');
            $table->text('preconditions')->nullable();
            $table->json('test_data')->nullable();
            $table->json('validations')->nullable();
            $table->json('expected_result')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('qa_executions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('recipient_email', 254);
            $table->string('type', 32);
            $table->foreignId('suite_id')->nullable()->constrained('qa_suites')->nullOnDelete();
            $table->json('selected_case_ids');
            $table->string('status', 32)->default('PENDING')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('progress')->default(0);
            $table->string('email_status', 32)->default('NOT_SENT');
            $table->text('failure_message')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'created_at'], 'qa_executions_owner_date');
        });

        Schema::create('qa_execution_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained('qa_executions')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('qa_test_cases')->restrictOnDelete();
            $table->string('status', 32)->default('NOT RUN');
            $table->text('objective')->nullable();
            $table->text('preconditions')->nullable();
            $table->text('expected_result')->nullable();
            $table->text('obtained_result')->nullable();
            $table->text('observations')->nullable();
            $table->text('error_message')->nullable();
            $table->string('failure_step', 160)->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('validations')->nullable();
            $table->timestamps();
            $table->unique(['execution_id', 'case_id'], 'qa_execution_case_unique');
        });

        Schema::create('qa_execution_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained('qa_execution_results')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('status', 32);
            $table->text('obtained_result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('qa_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained('qa_executions')->cascadeOnDelete();
            $table->foreignId('result_id')->nullable()->constrained('qa_execution_results')->nullOnDelete();
            $table->string('kind', 32);
            $table->string('description', 255);
            $table->string('relative_path', 512);
            $table->char('sha256', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('qa_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained('qa_executions')->cascadeOnDelete();
            $table->string('format', 16);
            $table->string('relative_path', 512);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->timestamps();
            $table->unique(['execution_id', 'format'], 'qa_report_format_unique');
        });

        Schema::create('qa_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained('qa_executions')->cascadeOnDelete();
            $table->string('recipient_email', 254);
            $table->string('status', 32);
            $table->unsignedInteger('attempt')->default(1);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('qa_ai_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('model', 120)->nullable();
            $table->text('prompt');
            $table->longText('response')->nullable();
            $table->string('status', 32)->default('PENDING');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_ai_sessions');
        Schema::dropIfExists('qa_email_logs');
        Schema::dropIfExists('qa_reports');
        Schema::dropIfExists('qa_evidences');
        Schema::dropIfExists('qa_execution_steps');
        Schema::dropIfExists('qa_execution_results');
        Schema::dropIfExists('qa_executions');
        Schema::dropIfExists('qa_test_cases');
        Schema::dropIfExists('qa_suites');
    }
};
