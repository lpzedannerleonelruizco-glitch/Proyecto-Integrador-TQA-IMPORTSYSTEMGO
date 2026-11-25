<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('brand_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->string('sku', 48)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->decimal('list_price', 12, 2);
            $table->boolean('requires_serial')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->index(['category_id', 'brand_id', 'is_active'], 'products_catalog_index');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->string('document_number', 32)->nullable();
            $table->string('full_name', 160);
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->unique(['branch_id', 'document_number'], 'customers_branch_document_unique');
            $table->index(['branch_id', 'full_name'], 'customers_branch_name_index');
        });

        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->unique(['branch_id', 'code'], 'cash_registers_branch_code_unique');
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->string('status', 24)->default('open')->index();
            $table->decimal('opening_amount', 12, 2)->default(0);
            $table->decimal('expected_amount', 12, 2)->default(0);
            $table->decimal('declared_amount', 12, 2)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->index(['cash_register_id', 'status'], 'cash_sessions_register_status_index');
            $table->index(['user_id', 'opened_at'], 'cash_sessions_user_opened_index');
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('salesperson_user_id')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->string('sale_number', 40)->unique();
            $table->string('status', 32)->default('pending_payment')->index();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->timestamp('sold_at');
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->index(['branch_id', 'sold_at'], 'sales_branch_date_index');
            $table->index(['salesperson_user_id', 'sold_at'], 'sales_seller_date_index');
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
            $table->unique(['sale_id', 'product_id'], 'sale_items_sale_product_unique');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->string('method', 24);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 80)->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamp('paid_at');
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->index(['sale_id', 'paid_at'], 'payments_sale_date_index');
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->decimal('physical_quantity', 12, 2)->default(0);
            $table->decimal('reserved_quantity', 12, 2)->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id'], 'inventory_balances_warehouse_product_unique');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->string('type', 32)->index();
            $table->decimal('quantity_change', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reference_key', 96)->unique();
            $table->timestamp('occurred_at');
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->index(['warehouse_id', 'product_id', 'occurred_at'], 'inventory_movements_kardex_index');
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 160);
            $table->string('contact_name', 120)->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->string('order_number', 40)->unique();
            $table->string('status', 32)->default('draft')->index();
            $table->decimal('total', 12, 2);
            $table->date('expected_at')->nullable();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->index(['branch_id', 'created_at'], 'purchase_orders_branch_date_index');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('received_quantity', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->timestamps();
            $table->unique(['purchase_order_id', 'product_id'], 'purchase_items_order_product_unique');
        });

        Schema::create('credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('sale_id')->unique()->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('advisor_user_id')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->string('credit_number', 40)->unique();
            $table->string('status', 24)->default('active')->index();
            $table->string('frequency', 16)->default('monthly');
            $table->unsignedSmallInteger('term_count');
            $table->decimal('principal_amount', 12, 2);
            $table->decimal('down_payment', 12, 2)->default(0);
            $table->decimal('financed_amount', 12, 2);
            $table->decimal('annual_rate', 6, 3)->default(0);
            $table->decimal('outstanding_balance', 12, 2);
            $table->date('started_at');
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->index(['branch_id', 'status'], 'credit_accounts_branch_status_index');
        });

        Schema::create('credit_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_account_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');
            $table->decimal('amount_due', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('status', 24)->default('pending')->index();
            $table->timestamps();
            $table->unique(['credit_account_id', 'installment_number'], 'credit_installments_account_number_unique');
            $table->index(['due_date', 'status'], 'credit_installments_due_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_installments');
        Schema::dropIfExists('credit_accounts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('cash_registers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
