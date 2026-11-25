<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureInventorySchemaReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $tables = [
            'categories', 'brands', 'products', 'inventory_balances', 'inventory_movements',
            'inventory_serials', 'sale_item_serials', 'inventory_transfers', 'inventory_transfer_items',
            'inventory_transfer_receipts', 'inventory_transfer_receipt_items',
            'inventory_transfer_item_serials', 'inventory_transfer_receipt_serials',
            'inventory_adjustments', 'audit_logs', 'database_trigger_audits',
        ];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return $this->unavailable($request);
            }
        }
        foreach ([
            'products' => ['acquisition_cost', 'minimum_stock', 'unit_of_measure', 'model', 'tracking_mode', 'image_path'],
            'inventory_movements' => ['unit_cost', 'source_reference', 'notes'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    return $this->unavailable($request);
                }
            }
        }
        if (DB::connection()->getDriverName() === 'mysql' && ! $this->mysqlTriggersReady()) {
            return $this->unavailable($request);
        }

        return $next($request);
    }

    private function mysqlTriggersReady(): bool
    {
        $required = [
            'products_after_insert_audit',
            'products_after_update_audit',
            'inventory_movements_after_insert_audit',
            'products_inventory_fields_after_update_audit',
            'inventory_transfers_after_insert_audit',
            'inventory_transfers_after_update_audit',
            'inventory_adjustments_after_insert_audit',
            'inventory_adjustments_after_update_audit',
            'inventory_transfer_receipts_after_insert_audit',
            'inventory_transfer_receipt_items_after_insert_audit',
        ];
        $placeholders = implode(',', array_fill(0, count($required), '?'));
        $installed = collect(DB::select(
            "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME IN ({$placeholders})",
            $required,
        ))->pluck('TRIGGER_NAME')->all();

        return count(array_intersect($required, $installed)) === count($required);
    }

    private function unavailable(Request $request): Response
    {
        $message = 'El módulo de inventario requiere migraciones pendientes; no se ejecutan automáticamente.';

        return $request->expectsJson()
            ? response()->json(['message' => $message], 503)
            : response()->view('errors.inventory-schema', ['message' => $message], 503);
    }
}
