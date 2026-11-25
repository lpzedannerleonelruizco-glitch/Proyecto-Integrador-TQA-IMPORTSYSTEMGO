<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Authorization\ScopedAuthorizationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(private readonly ScopedAuthorizationService $authorization) {}

    public function receive(
        User $actor,
        Branch $branch,
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        string $referenceKey,
        bool $demo = false,
        ?string $unitCost = null,
        array $identifiers = [],
        ?string $sourceReference = null,
        ?string $notes = null,
    ): InventoryMovement {
        if (! $this->authorization->allows($actor, 'inventory.stock.manage', $branch)
            || $warehouse->branch_id !== $branch->id
            || ! $warehouse->is_active) {
            throw new AuthorizationException('No puede modificar este inventario.');
        }
        if (bccomp($quantity, '0', 2) !== 1) {
            throw new \InvalidArgumentException('La cantidad debe ser mayor que cero.');
        }
        if ($unitCost !== null && bccomp($unitCost, '0', 2) < 0) {
            throw new \InvalidArgumentException('El costo unitario no puede ser negativo.');
        }

        return DB::transaction(function () use ($actor, $branch, $warehouse, $product, $quantity, $referenceKey, $demo, $unitCost, $identifiers, $sourceReference, $notes): InventoryMovement {
            $existing = InventoryMovement::where('reference_key', $referenceKey)->first();
            if ($existing) {
                if ($existing->branch_id !== $branch->id
                    || $existing->warehouse_id !== $warehouse->id
                    || $existing->product_id !== $product->id
                    || $existing->actor_user_id !== $actor->id
                    || $existing->type !== 'receipt'
                    || bccomp((string) $existing->quantity_change, $quantity, 2) !== 0
                    || (($existing->unit_cost === null) !== ($unitCost === null))
                    || ($unitCost !== null && bccomp((string) $existing->unit_cost, $unitCost, 2) !== 0)
                    || $existing->source_reference !== $sourceReference
                    || $existing->notes !== $notes) {
                    throw new \DomainException('La referencia de inventario ya pertenece a otra operación.');
                }
                $savedIdentifiers = InventorySerial::query()->where('inventory_movement_id', $existing->id)
                    ->orderBy('serial_number')->pluck('serial_number')->all();
                $submittedIdentifiers = $identifiers;
                sort($submittedIdentifiers);
                if ($savedIdentifiers !== $submittedIdentifiers) {
                    throw new \DomainException('La referencia de inventario ya se usó con otros identificadores.');
                }

                return $existing;
            }
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (! $product->is_active) {
                throw new \DomainException('No se puede recibir un producto inactivo.');
            }
            $serialized = $product->tracking_mode !== 'quantity' || $product->requires_serial;
            if ($serialized) {
                if (bccomp($quantity, (string) (int) $quantity, 2) !== 0 || count($identifiers) !== (int) $quantity) {
                    throw new \InvalidArgumentException('La cantidad debe coincidir con el número de series o IMEI recibidos.');
                }
                $identifiers = array_values(array_unique(array_map('trim', $identifiers)));
                if (count($identifiers) !== (int) $quantity || in_array('', $identifiers, true)) {
                    throw new \InvalidArgumentException('Los identificadores deben ser únicos y no pueden estar vacíos.');
                }
            } elseif ($identifiers !== []) {
                throw new \InvalidArgumentException('Este producto no admite identificadores serializados.');
            }

            DB::table('inventory_balances')->insertOrIgnore([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'physical_quantity' => '0.00',
                'reserved_quantity' => '0.00',
                'version' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $balance = InventoryBalance::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
            $before = (string) $balance->physical_quantity;
            $after = bcadd($before, $quantity, 2);
            $balance->forceFill(['physical_quantity' => $after, 'version' => $balance->version + 1])->save();
            if ($unitCost !== null) {
                $totalQuantity = bcadd($before, $quantity, 2);
                $oldValue = bcmul($before, (string) $product->acquisition_cost, 4);
                $incomingValue = bcmul($quantity, $unitCost, 4);
                $product->forceFill([
                    'acquisition_cost' => bccomp($before, '0', 2) === 0
                        || bccomp((string) $product->acquisition_cost, '0', 2) === 0
                        ? $unitCost
                        : bcdiv(bcadd($oldValue, $incomingValue, 4), $totalQuantity, 2),
                ])->save();
            }
            $movement = InventoryMovement::create([
                'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id,
                'actor_user_id' => $actor->id, 'type' => 'receipt', 'quantity_change' => $quantity,
                'balance_after' => $after, 'unit_cost' => $unitCost, 'reference_key' => $referenceKey,
                'source_reference' => $sourceReference, 'notes' => $notes,
                'occurred_at' => now(), 'is_demo' => $demo,
            ]);
            foreach ($identifiers as $identifier) {
                InventorySerial::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'inventory_movement_id' => $movement->id,
                    'serial_number' => $identifier,
                    'imei1' => $product->tracking_mode === 'imei' ? $identifier : null,
                    'status' => 'available',
                    'received_at' => now(),
                ]);
            }

            DB::table('audit_logs')->insert([
                'actor_user_id' => $actor->id,
                'branch_id' => $branch->id,
                'action' => 'inventory.received',
                'resource_type' => 'inventory_movements',
                'resource_id' => $movement->id,
                'before_values' => json_encode(['physical_quantity' => $before], JSON_THROW_ON_ERROR),
                'after_values' => json_encode([
                    'physical_quantity' => $after,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
            ]);

            return $movement;
        }, 3);
    }
}
