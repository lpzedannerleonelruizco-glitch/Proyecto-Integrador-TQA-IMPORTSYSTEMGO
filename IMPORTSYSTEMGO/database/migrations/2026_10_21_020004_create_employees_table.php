<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('employee_code', 32)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 150);
            $table->foreignId('home_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->string('status', 32)->default('active');
            $table->date('hired_at')->nullable();
            $table->date('terminated_at')->nullable();
            $table->timestamps();

            $table->index(['home_branch_id', 'status']);
            $table->index(['status', 'last_name']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE employees ADD CONSTRAINT employees_valid_employment_dates CHECK (terminated_at IS NULL OR hired_at IS NULL OR terminated_at >= hired_at)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
