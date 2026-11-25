<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('acquisition_cost', 12, 2)->default(0)->after('list_price');
            $table->decimal('minimum_stock', 12, 2)->default(0)->after('acquisition_cost');
            $table->string('unit_of_measure', 24)->default('unidad')->after('minimum_stock');
            $table->string('model', 100)->nullable()->after('unit_of_measure');
            $table->string('tracking_mode', 16)->default('quantity')->after('requires_serial');
            $table->string('image_path', 512)->nullable()->after('description');
        });
        DB::table('products')->where('requires_serial', true)->update(['tracking_mode' => 'serial']);

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->nullable()->after('balance_after');
            $table->string('source_reference', 120)->nullable()->after('reference_key');
            $table->string('notes', 500)->nullable()->after('source_reference');
        });

        Schema::create('inventory_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->string('serial_number', 100)->unique();
            $table->string('imei1', 32)->nullable()->unique();
            $table->string('imei2', 32)->nullable()->unique();
            $table->string('status', 20)->default('available')->index();
            $table->foreignId('sale_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'product_id', 'status'], 'inventory_serial_stock_index');
        });

        Schema::create('sale_item_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('inventory_serial_id')->unique()->constrained('inventory_serials')->restrictOnUpdate()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->string('idempotency_key', 96)->unique();
            $table->string('status', 24)->default('pending_approval')->index();
            $table->string('reason', 500);
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->index(['source_warehouse_id', 'status'], 'inventory_transfers_source_status_index');
            $table->index(['destination_warehouse_id', 'status'], 'inventory_transfers_destination_status_index');
        });

        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transfer_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->decimal('requested_quantity', 12, 2);
            $table->decimal('dispatched_quantity', 12, 2)->default(0);
            $table->decimal('received_quantity', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['inventory_transfer_id', 'product_id'], 'inventory_transfer_item_product_unique');
        });

        Schema::create('inventory_transfer_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transfer_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->string('idempotency_key', 96)->unique();
            $table->string('note', 500)->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
        });

        Schema::create('inventory_transfer_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transfer_receipt_id')->constrained(indexName: 'itr_items_receipt_fk')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('inventory_transfer_item_id')->constrained(indexName: 'itr_items_transfer_item_fk')->restrictOnUpdate()->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->timestamps();
            $table->unique(['inventory_transfer_receipt_id', 'inventory_transfer_item_id'], 'transfer_receipt_item_unique');
        });

        Schema::create('inventory_transfer_item_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transfer_item_id')->constrained(indexName: 'itis_transfer_item_fk')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('inventory_serial_id')->unique()->constrained('inventory_serials')->restrictOnUpdate()->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_transfer_receipt_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transfer_receipt_item_id')->constrained(indexName: 'itrs_receipt_item_fk')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('inventory_serial_id')->unique()->constrained('inventory_serials')->restrictOnUpdate()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->string('reason_code', 32);
            $table->string('reason', 500);
            $table->decimal('quantity_before', 12, 2);
            $table->decimal('quantity_after', 12, 2);
            $table->decimal('quantity_change', 12, 2);
            $table->string('idempotency_key', 96)->unique();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->index(['warehouse_id', 'status'], 'inventory_adjustments_warehouse_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_transfer_receipt_serials');
        Schema::dropIfExists('inventory_transfer_item_serials');
        Schema::dropIfExists('inventory_transfer_receipt_items');
        Schema::dropIfExists('inventory_transfer_receipts');
        Schema::dropIfExists('inventory_transfer_items');
        Schema::dropIfExists('inventory_transfers');
        Schema::dropIfExists('sale_item_serials');
        Schema::dropIfExists('inventory_serials');
        Schema::table('inventory_movements', fn (Blueprint $table) => $table->dropColumn(['unit_cost', 'source_reference', 'notes']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn([
            'acquisition_cost', 'minimum_stock', 'unit_of_measure', 'model', 'tracking_mode', 'image_path',
        ]));
    }
};
