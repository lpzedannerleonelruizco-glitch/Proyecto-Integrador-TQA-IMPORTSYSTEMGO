<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 32)->default('active')->index()->after('password');
            $table->foreignId('primary_branch_id')->nullable()->after('status')->constrained('branches')->restrictOnDelete();
            $table->timestamp('last_login_at')->nullable()->after('primary_branch_id');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_branch_id');
            $table->dropColumn(['status', 'last_login_at', 'password_changed_at']);
        });
    }
};
