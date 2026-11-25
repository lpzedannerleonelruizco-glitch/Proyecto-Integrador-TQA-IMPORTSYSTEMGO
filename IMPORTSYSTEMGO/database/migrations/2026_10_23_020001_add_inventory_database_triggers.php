<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        if (! Schema::hasTable('database_trigger_audits')) {
            throw new RuntimeException('Aplique primero la migración de auditoría de base de datos antes de instalar triggers.');
        }

        foreach ($this->definitions() as $definition) {
            DB::unprepared($definition);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        foreach ([
            'products_inventory_fields_after_update_audit',
            'inventory_transfers_after_insert_audit',
            'inventory_transfers_after_update_audit',
            'inventory_adjustments_after_insert_audit',
            'inventory_adjustments_after_update_audit',
            'inventory_transfer_receipts_after_insert_audit',
            'inventory_transfer_receipt_items_after_insert_audit',
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
        }
    }

    /** @return list<string> */
    private function definitions(): array
    {
        return [
            <<<'SQL'
CREATE TRIGGER products_inventory_fields_after_update_audit
AFTER UPDATE ON products FOR EACH ROW
BEGIN
    IF (
        NOT (OLD.category_id <=> NEW.category_id)
        OR NOT (OLD.brand_id <=> NEW.brand_id)
        OR NOT (OLD.description <=> NEW.description)
        OR NOT (OLD.acquisition_cost <=> NEW.acquisition_cost)
        OR NOT (OLD.minimum_stock <=> NEW.minimum_stock)
        OR NOT (OLD.unit_of_measure <=> NEW.unit_of_measure)
        OR NOT (OLD.model <=> NEW.model)
        OR NOT (OLD.tracking_mode <=> NEW.tracking_mode)
        OR NOT (OLD.image_path <=> NEW.image_path)
    )
        AND OLD.sku <=> NEW.sku
        AND OLD.name <=> NEW.name
        AND OLD.list_price <=> NEW.list_price
        AND OLD.is_active <=> NEW.is_active THEN
        INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
        VALUES ('UPDATE', 'products', CAST(NEW.id AS CHAR), NULL,
            JSON_OBJECT(
                'before', JSON_OBJECT(
                    'category_id', OLD.category_id, 'brand_id', OLD.brand_id, 'description', OLD.description,
                    'acquisition_cost', OLD.acquisition_cost, 'minimum_stock', OLD.minimum_stock,
                    'unit_of_measure', OLD.unit_of_measure, 'model', OLD.model,
                    'tracking_mode', OLD.tracking_mode, 'image_path', OLD.image_path
                ),
                'after', JSON_OBJECT(
                    'category_id', NEW.category_id, 'brand_id', NEW.brand_id, 'description', NEW.description,
                    'acquisition_cost', NEW.acquisition_cost, 'minimum_stock', NEW.minimum_stock,
                    'unit_of_measure', NEW.unit_of_measure, 'model', NEW.model,
                    'tracking_mode', NEW.tracking_mode, 'image_path', NEW.image_path
                )
            ),
            'mysql_trigger', CURRENT_TIMESTAMP);
    END IF;
END
SQL,
            <<<'SQL'
CREATE TRIGGER inventory_transfers_after_insert_audit
AFTER INSERT ON inventory_transfers FOR EACH ROW
INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
SELECT 'INSERT', 'inventory_transfers', CAST(NEW.id AS CHAR), source_branch.id,
    JSON_OBJECT('source_warehouse_id', NEW.source_warehouse_id, 'destination_warehouse_id', NEW.destination_warehouse_id,
        'requested_by', NEW.requested_by, 'status', NEW.status, 'reason', NEW.reason),
    'mysql_trigger', CURRENT_TIMESTAMP
FROM warehouses source_warehouse
JOIN branches source_branch ON source_branch.id = source_warehouse.branch_id
WHERE source_warehouse.id = NEW.source_warehouse_id
SQL,
            <<<'SQL'
CREATE TRIGGER inventory_transfers_after_update_audit
AFTER UPDATE ON inventory_transfers FOR EACH ROW
BEGIN
    IF NOT (OLD.status <=> NEW.status) THEN
        INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
        SELECT 'UPDATE', 'inventory_transfers', CAST(NEW.id AS CHAR), source_branch.id,
            JSON_OBJECT('before', JSON_OBJECT('status', OLD.status),
                'after', JSON_OBJECT('status', NEW.status, 'approved_by', NEW.approved_by,
                    'dispatched_by', NEW.dispatched_by, 'received_at', NEW.received_at)),
            'mysql_trigger', CURRENT_TIMESTAMP
        FROM warehouses source_warehouse
        JOIN branches source_branch ON source_branch.id = source_warehouse.branch_id
        WHERE source_warehouse.id = NEW.source_warehouse_id;
    END IF;
END
SQL,
            <<<'SQL'
CREATE TRIGGER inventory_adjustments_after_insert_audit
AFTER INSERT ON inventory_adjustments FOR EACH ROW
INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
SELECT 'INSERT', 'inventory_adjustments', CAST(NEW.id AS CHAR), branch.id,
    JSON_OBJECT('warehouse_id', NEW.warehouse_id, 'product_id', NEW.product_id, 'requested_by', NEW.requested_by,
        'quantity_before', NEW.quantity_before, 'quantity_after', NEW.quantity_after,
        'quantity_change', NEW.quantity_change, 'reason_code', NEW.reason_code, 'reason', NEW.reason),
    'mysql_trigger', CURRENT_TIMESTAMP
FROM warehouses
JOIN branches branch ON branch.id = warehouses.branch_id
WHERE warehouses.id = NEW.warehouse_id
SQL,
            <<<'SQL'
CREATE TRIGGER inventory_adjustments_after_update_audit
AFTER UPDATE ON inventory_adjustments FOR EACH ROW
BEGIN
    IF NOT (OLD.status <=> NEW.status) THEN
        INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
        SELECT 'UPDATE', 'inventory_adjustments', CAST(NEW.id AS CHAR), branch.id,
            JSON_OBJECT('before', JSON_OBJECT('status', OLD.status),
                'after', JSON_OBJECT('status', NEW.status, 'approved_by', NEW.approved_by,
                    'quantity_after', NEW.quantity_after)),
            'mysql_trigger', CURRENT_TIMESTAMP
        FROM warehouses
        JOIN branches branch ON branch.id = warehouses.branch_id
        WHERE warehouses.id = NEW.warehouse_id;
    END IF;
END
SQL,
            <<<'SQL'
CREATE TRIGGER inventory_transfer_receipts_after_insert_audit
AFTER INSERT ON inventory_transfer_receipts FOR EACH ROW
INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
SELECT 'INSERT', 'inventory_transfer_receipts', CAST(NEW.id AS CHAR), destination_branch.id,
    JSON_OBJECT('transfer_id', NEW.inventory_transfer_id, 'received_by', NEW.received_by,
        'received_at', NEW.received_at, 'note', NEW.note),
    'mysql_trigger', CURRENT_TIMESTAMP
FROM inventory_transfers transfer
JOIN warehouses destination_warehouse ON destination_warehouse.id = transfer.destination_warehouse_id
JOIN branches destination_branch ON destination_branch.id = destination_warehouse.branch_id
WHERE transfer.id = NEW.inventory_transfer_id
SQL,
            <<<'SQL'
CREATE TRIGGER inventory_transfer_receipt_items_after_insert_audit
AFTER INSERT ON inventory_transfer_receipt_items FOR EACH ROW
INSERT INTO database_trigger_audits (event_type, table_name, record_id, branch_id, payload, source, occurred_at)
SELECT 'INSERT', 'inventory_transfer_receipt_items', CAST(NEW.id AS CHAR), destination_branch.id,
    JSON_OBJECT('receipt_id', NEW.inventory_transfer_receipt_id,
        'transfer_item_id', NEW.inventory_transfer_item_id, 'quantity', NEW.quantity),
    'mysql_trigger', CURRENT_TIMESTAMP
FROM inventory_transfer_receipts receipt
JOIN inventory_transfers transfer ON transfer.id = receipt.inventory_transfer_id
JOIN warehouses destination_warehouse ON destination_warehouse.id = transfer.destination_warehouse_id
JOIN branches destination_branch ON destination_branch.id = destination_warehouse.branch_id
WHERE receipt.id = NEW.inventory_transfer_receipt_id
SQL,
        ];
    }
};
