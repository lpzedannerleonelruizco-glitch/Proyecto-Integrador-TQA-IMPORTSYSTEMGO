<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('end_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'starts_at', 'ends_at'], 'global_roles_user_period_index');
            $table->index(['role_id', 'starts_at', 'ends_at'], 'global_roles_role_period_index');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE global_user_roles ADD CONSTRAINT global_roles_valid_period CHECK (ends_at IS NULL OR ends_at > starts_at)');
        }

        $this->addSingleOpenAssignmentConstraint();
    }

    public function down(): void
    {
        Schema::dropIfExists('global_user_roles');
    }

    private function addSingleOpenAssignmentConstraint(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE global_user_roles ADD COLUMN active_assignment TINYINT GENERATED ALWAYS AS (CASE WHEN ends_at IS NULL THEN 1 ELSE NULL END) STORED');
            DB::statement('CREATE UNIQUE INDEX global_roles_one_open_unique ON global_user_roles (user_id, role_id, active_assignment)');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX global_roles_one_open_unique ON global_user_roles (user_id, role_id) WHERE ends_at IS NULL');
        }
    }
};
