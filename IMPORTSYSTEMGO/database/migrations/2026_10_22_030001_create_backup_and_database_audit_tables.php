<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_archives', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('kind', 24);
            $table->string('status', 24)->index();
            $table->json('scope')->nullable();
            $table->json('record_summary')->nullable();
            $table->string('storage_path', 512)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedBigInteger('archive_bytes')->default(0);
            $table->string('failure_code', 64)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('last_verification_ok')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['created_by', 'created_at'], 'backup_archives_actor_date_index');
            $table->index(['kind', 'status', 'created_at'], 'backup_archives_kind_status_date_index');
        });

        Schema::create('database_trigger_audits', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 8);
            $table->string('table_name', 64);
            $table->string('record_id', 64);
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->json('payload');
            $table->string('source', 24)->default('mysql_trigger');
            $table->timestamp('occurred_at')->useCurrent();
            $table->index(['branch_id', 'occurred_at'], 'db_trigger_audits_branch_date_index');
            $table->index(['table_name', 'occurred_at'], 'db_trigger_audits_table_date_index');
            $table->index(['event_type', 'occurred_at'], 'db_trigger_audits_event_date_index');
        });

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->triggerDefinitions() as $definition) {
            DB::unprepared($definition);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            foreach ([
                'products_after_insert_audit',
                'products_after_update_audit',
                'inventory_movements_after_insert_audit',
                'sales_after_insert_audit',
                'sales_after_update_audit',
                'payments_after_insert_audit',
                'cash_sessions_after_insert_audit',
                'cash_sessions_after_update_audit',
                'cash_movements_after_insert_audit',
            ] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
            }
        }

        Schema::dropIfExists('database_trigger_audits');
        Schema::dropIfExists('backup_archives');
    }

    /** @return list<string> */
    private function triggerDefinitions(): array
    {
        return [
            <<<'SQL'
CREATE TRIGGER products_after_insert_audit
AFTER INSERT ON products FOR EACH ROW
INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
VALUES ('INSERT', 'products', CAST(NEW.id AS CHAR), NULL,
    JSON_OBJECT('sku', NEW.sku, 'name', NEW.name, 'list_price', NEW.list_price, 'is_active', NEW.is_active),
    'mysql_trigger', CURRENT_TIMESTAMP)
SQL,
            <<<'SQL'
CREATE TRIGGER products_after_update_audit
AFTER UPDATE ON products FOR EACH ROW
BEGIN
    IF NOT (OLD.sku <=> NEW.sku)
        OR NOT (OLD.name <=> NEW.name)
        OR NOT (OLD.list_price <=> NEW.list_price)
        OR NOT (OLD.is_active <=> NEW.is_active) THEN
        INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
        VALUES ('UPDATE', 'products', CAST(NEW.id AS CHAR), NULL,
            JSON_OBJECT(
                'before', JSON_OBJECT('sku', OLD.sku, 'name', OLD.name, 'list_price', OLD.list_price, 'is_active', OLD.is_active),
                'after', JSON_OBJECT('sku', NEW.sku, 'name', NEW.name, 'list_price', NEW.list_price, 'is_active', NEW.is_active)
            ),
            'mysql_trigger', CURRENT_TIMESTAMP);
    END IF;
END
SQL,
            <<<'SQL'
CREATE TRIGGER inventory_movements_after_insert_audit
AFTER INSERT ON inventory_movements FOR EACH ROW
INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
VALUES ('INSERT', 'inventory_movements', CAST(NEW.id AS CHAR), NEW.branch_id,
    JSON_OBJECT('warehouse_id', NEW.warehouse_id, 'product_id', NEW.product_id, 'type', NEW.type,
        'quantity_change', NEW.quantity_change, 'balance_after', NEW.balance_after),
    'mysql_trigger', CURRENT_TIMESTAMP)
SQL,
            <<<'SQL'
CREATE TRIGGER sales_after_insert_audit
AFTER INSERT ON sales FOR EACH ROW
INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
VALUES ('INSERT', 'sales', CAST(NEW.id AS CHAR), NEW.branch_id,
    JSON_OBJECT('sale_number', NEW.sale_number, 'status', NEW.status, 'subtotal', NEW.subtotal,
        'discount_total', NEW.discount_total, 'total', NEW.total),
    'mysql_trigger', CURRENT_TIMESTAMP)
SQL,
            <<<'SQL'
CREATE TRIGGER sales_after_update_audit
AFTER UPDATE ON sales FOR EACH ROW
BEGIN
    IF NOT (OLD.status <=> NEW.status) THEN
        INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
        VALUES ('UPDATE', 'sales', CAST(NEW.id AS CHAR), NEW.branch_id,
            JSON_OBJECT('before', JSON_OBJECT('status', OLD.status), 'after', JSON_OBJECT('status', NEW.status)),
            'mysql_trigger', CURRENT_TIMESTAMP);
    END IF;
END
SQL,
            <<<'SQL'
CREATE TRIGGER payments_after_insert_audit
AFTER INSERT ON payments FOR EACH ROW
INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
SELECT 'INSERT', 'payments', CAST(NEW.id AS CHAR), sales.branch_id,
    JSON_OBJECT('sale_id', NEW.sale_id, 'method', NEW.method, 'amount', NEW.amount),
    'mysql_trigger', CURRENT_TIMESTAMP
FROM sales WHERE sales.id = NEW.sale_id
SQL,
            <<<'SQL'
CREATE TRIGGER cash_sessions_after_insert_audit
AFTER INSERT ON cash_sessions FOR EACH ROW
INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
SELECT 'INSERT', 'cash_sessions', CAST(NEW.id AS CHAR), cash_registers.branch_id,
    JSON_OBJECT('status', NEW.status, 'opening_amount', NEW.opening_amount),
    'mysql_trigger', CURRENT_TIMESTAMP
FROM cash_registers WHERE cash_registers.id = NEW.cash_register_id
SQL,
            <<<'SQL'
CREATE TRIGGER cash_sessions_after_update_audit
AFTER UPDATE ON cash_sessions FOR EACH ROW
BEGIN
    IF NOT (OLD.status <=> NEW.status) THEN
        INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
        SELECT 'UPDATE', 'cash_sessions', CAST(NEW.id AS CHAR), cash_registers.branch_id,
            JSON_OBJECT('before', JSON_OBJECT('status', OLD.status), 'after', JSON_OBJECT('status', NEW.status)),
            'mysql_trigger', CURRENT_TIMESTAMP
        FROM cash_registers WHERE cash_registers.id = NEW.cash_register_id;
    END IF;
END
SQL,
            <<<'SQL'
CREATE TRIGGER cash_movements_after_insert_audit
AFTER INSERT ON cash_movements FOR EACH ROW
INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
VALUES ('INSERT', 'cash_movements', CAST(NEW.id AS CHAR), NEW.branch_id,
    JSON_OBJECT('cash_session_id', NEW.cash_session_id, 'type', NEW.type, 'amount', NEW.amount),
    'mysql_trigger', CURRENT_TIMESTAMP)
SQL,
        ];
    }
};
