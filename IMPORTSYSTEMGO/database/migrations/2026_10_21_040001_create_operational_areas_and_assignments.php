<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_areas', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->index();
            $table->foreignId('operational_area_id')->nullable()
                ->constrained('operational_areas')
                ->restrictOnUpdate()
                ->restrictOnDelete();
            $table->index(['operational_area_id', 'is_active'], 'branches_area_active_index');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->index();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->index();
        });

        Schema::create('operational_area_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_area_id')->constrained('operational_areas')
                ->restrictOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')
                ->restrictOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')
                ->restrictOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'operational_area_id'], 'operational_area_user_unique');
            $table->index(['operational_area_id', 'user_id'], 'operational_area_assignments_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_area_assignments');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex('branches_area_active_index');
            $table->dropConstrainedForeignId('operational_area_id');
            $table->dropColumn('is_demo');
        });

        Schema::dropIfExists('operational_areas');
    }
};
