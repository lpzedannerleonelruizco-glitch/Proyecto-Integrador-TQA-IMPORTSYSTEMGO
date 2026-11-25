<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_user_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('end_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'branch_id', 'starts_at', 'ends_at'], 'branch_memberships_user_period_index');
            $table->index(['branch_id', 'starts_at', 'ends_at'], 'branch_memberships_branch_period_index');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE branch_user_memberships ADD CONSTRAINT branch_memberships_valid_period CHECK (ends_at IS NULL OR ends_at > starts_at)');
        }

        $this->addSingleOpenMembershipConstraint();
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user_memberships');
    }

    private function addSingleOpenMembershipConstraint(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE branch_user_memberships ADD COLUMN active_membership TINYINT GENERATED ALWAYS AS (CASE WHEN ends_at IS NULL THEN 1 ELSE NULL END) STORED');
            DB::statement('CREATE UNIQUE INDEX branch_memberships_one_open_unique ON branch_user_memberships (user_id, branch_id, active_membership)');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX branch_memberships_one_open_unique ON branch_user_memberships (user_id, branch_id) WHERE ends_at IS NULL');
        }
    }
};
