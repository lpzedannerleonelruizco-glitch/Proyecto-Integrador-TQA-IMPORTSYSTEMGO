<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnUpdate()->restrictOnDelete();
            $table->string('action', 80);
            $table->string('resource_type', 80);
            $table->unsignedBigInteger('resource_id');
            $table->json('before_values')->nullable();
            $table->json('after_values');
            $table->timestamp('occurred_at');
            $table->index(['branch_id', 'occurred_at']);
            $table->index(['resource_type', 'resource_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
