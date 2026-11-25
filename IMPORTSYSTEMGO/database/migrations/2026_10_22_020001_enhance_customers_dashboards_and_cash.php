<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('customer_type', 16)->default('natural')->after('branch_id');
            $table->string('document_type', 16)->nullable()->after('customer_type');
            $table->string('document_complement', 12)->nullable()->after('document_number');
            $table->string('document_key', 128)->nullable()->after('document_complement');
            $table->string('address', 255)->nullable()->after('email');
            $table->string('city', 80)->nullable()->after('address');
            $table->text('notes')->nullable()->after('city');
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
        });

        DB::table('customers')->whereNotNull('document_number')->orderBy('id')->each(function (object $customer): void {
            $type = $customer->document_type ?: 'other';
            $key = mb_strtolower($type.':'.trim((string) $customer->document_number).':'.trim((string) ($customer->document_complement ?? '')));
            DB::table('customers')->where('id', $customer->id)->update(['document_type' => $type, 'document_key' => $key]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_branch_document_unique');
            $table->unique(['branch_id', 'document_key'], 'customers_branch_document_key_unique');
            $table->index(['branch_id', 'customer_type', 'is_active'], 'customers_branch_type_status_index');
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->text('opening_note')->nullable()->after('opening_amount');
            $table->decimal('difference_amount', 12, 2)->default(0)->after('declared_amount');
            $table->text('closing_note')->nullable()->after('difference_amount');
            $table->string('approval_status', 24)->default('not_required')->after('closing_note')->index();
            $table->foreignId('closed_by')->nullable()->after('approval_status')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->after('closed_by')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->unsignedBigInteger('open_register_token')->nullable()->after('approved_at')->unique();
            $table->unsignedBigInteger('open_user_token')->nullable()->after('open_register_token')->unique();
        });

        DB::table('cash_sessions')->where('status', 'open')->orderBy('id')->each(function (object $session): void {
            DB::table('cash_sessions')->where('id', $session->id)->update([
                'open_register_token' => $session->cash_register_id,
                'open_user_token' => $session->user_id,
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['cash_session_id', 'method', 'paid_at'], 'payments_session_method_date_index');
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('cash_session_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->string('type', 16);
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255);
            $table->string('reference_key', 64)->unique();
            $table->timestamp('occurred_at');
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->index(['cash_session_id', 'occurred_at'], 'cash_movements_session_date_index');
            $table->index(['branch_id', 'type', 'occurred_at'], 'cash_movements_branch_type_date_index');
        });

        Schema::create('cash_count_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->decimal('denomination', 8, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
            $table->unique(['cash_session_id', 'denomination'], 'cash_count_session_denomination_unique');
        });

        DB::table('cash_sessions')->orderBy('id')->each(function (object $session): void {
            $cashPayments = DB::table('payments')
                ->where('cash_session_id', $session->id)
                ->where('method', 'cash')
                ->sum('amount');

            DB::table('cash_sessions')->where('id', $session->id)->update([
                'expected_amount' => bcadd((string) $session->opening_amount, (string) $cashPayments, 2),
            ]);
        });

        Schema::create('sales_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->string('scope_key', 32);
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('target_amount', 14, 2);
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->unique(['branch_id', 'scope_key', 'period_start', 'period_end'], 'sales_goals_scope_period_unique');
            $table->index(['period_start', 'period_end'], 'sales_goals_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_goals');
        Schema::dropIfExists('cash_count_details');
        Schema::dropIfExists('cash_movements');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_session_method_date_index');
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
            $table->dropForeign(['approved_by']);
            $table->dropUnique(['open_register_token']);
            $table->dropUnique(['open_user_token']);
            $table->dropColumn([
                'opening_note', 'difference_amount', 'closing_note', 'approval_status', 'closed_by',
                'approved_by', 'approved_at', 'open_register_token', 'open_user_token',
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_branch_document_key_unique');
            $table->dropIndex('customers_branch_type_status_index');
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'customer_type', 'document_type', 'document_complement', 'document_key',
                'address', 'city', 'notes', 'created_by', 'updated_by',
            ]);
            $table->unique(['branch_id', 'document_number'], 'customers_branch_document_unique');
        });
    }
};
