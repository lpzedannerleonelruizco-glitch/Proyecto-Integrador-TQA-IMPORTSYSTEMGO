<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\InventoryAdjustment;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\InventoryTransferReceipt;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Authorization\BranchContext;
use App\Services\Authorization\GlobalAuthorizationService;
use App\Services\Authorization\ScopedAuthorizationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryOperationsService
{
    public function __construct(
        private readonly ScopedAuthorizationService $authorization,
        private readonly GlobalAuthorizationService $globalAuthorization,
        private readonly BranchContext $context,
    ) {}

    /** @param array<int, array{product_id:int, quantity:string|int}> $items */
    public function requestTransfer(User $actor, Warehouse $source, Warehouse $destination, array $items, string $reason, string $idempotencyKey, bool $demo = false): InventoryTransfer
    {
        $sourceBranch = $source->branch()->firstOrFail();
        $destinationBranch = $destination->branch()->firstOrFail();
        $this->authorizeWarehouse($actor, 'inventory.transfers.request', $source);
        $this->assertDestinationAccess($actor, $sourceBranch, $destinationBranch);

        if (! $source->is_active || ! $destination->is_active || $source->is($destination) || trim($reason) === '') {
            throw new \InvalidArgumentException('Origen, destino o motivo de transferencia inválido.');
        }
        if ($sourceBranch->operational_area_id !== $destinationBranch->operational_area_id
            && ! $this->globalAuthorization->allows($actor, 'inventory.transfers.cross_area')) {
            throw new AuthorizationException('La transferencia entre áreas requiere autorización global explícita.');
        }
        $grouped = collect($items)->groupBy('product_id')->map(function ($rows): string {
            return $rows->reduce(fn (string $sum, array $row): string => bcadd($sum, (string) $row['quantity'], 2), '0.00');
        });
        if ($grouped->isEmpty() || $grouped->contains(fn (string $quantity): bool => bccomp($quantity, '0', 2) !== 1)) {
            throw new \InvalidArgumentException('La transferencia requiere productos y cantidades positivas.');
        }

        return DB::transaction(function () use ($actor, $source, $destination, $sourceBranch, $grouped, $reason, $idempotencyKey, $demo): InventoryTransfer {
            $existing = InventoryTransfer::query()->where('idempotency_key', $idempotencyKey)->with('items')->first();
            if ($existing) {
                $saved = $existing->items->sortBy('product_id')->mapWithKeys(fn ($item) => [$item->product_id => (string) $item->requested_quantity])->all();
                $submitted = $grouped->sortKeys()->all();
                if ($existing->source_warehouse_id !== $source->id
                    || $existing->destination_warehouse_id !== $destination->id
                    || $existing->requested_by !== $actor->id
                    || $existing->reason !== trim($reason)
                    || $saved !== $submitted) {
                    throw new \DomainException('La clave de transferencia ya se utilizó con otros datos.');
                }

                return $existing;
            }
            $products = Product::query()->whereIn('id', $grouped->keys())->where('is_active', true)->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== $grouped->count()) {
                throw new \InvalidArgumentException('Uno o más productos no están activos.');
            }
            $transfer = InventoryTransfer::create([
                'public_id' => (string) Str::uuid(),
                'source_warehouse_id' => $source->id,
                'destination_warehouse_id' => $destination->id,
                'requested_by' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'status' => 'pending_approval',
                'reason' => trim($reason),
                'requested_at' => now(),
                'is_demo' => $demo,
            ]);
            foreach ($grouped as $productId => $quantity) {
                InventoryTransferItem::create([
                    'inventory_transfer_id' => $transfer->id,
                    'product_id' => $productId,
                    'requested_quantity' => $quantity,
                ]);
            }

            $this->audit($actor, $sourceBranch, 'inventory.transfer.requested', $transfer, [
                'source_warehouse_id' => $source->id,
                'destination_warehouse_id' => $destination->id,
                'items' => $grouped->all(),
            ]);

            return $transfer->load('items.product', 'source', 'destination');
        }, 3);
    }

    public function approveTransfer(User $actor, InventoryTransfer $transfer): InventoryTransfer
    {
        return DB::transaction(function () use ($actor, $transfer): InventoryTransfer {
            $transfer = InventoryTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $source = $transfer->source()->firstOrFail();
            $this->authorizeWarehouse($actor, 'inventory.transfers.approve', $source);
            if ($transfer->status !== 'pending_approval' || $transfer->requested_by === $actor->id) {
                throw new \DomainException('La transferencia no puede aprobarse por este usuario o estado.');
            }
            if ($source->branch?->operational_area_id !== $transfer->destination()->firstOrFail()->branch?->operational_area_id
                && ! $this->globalAuthorization->allows($actor, 'inventory.transfers.cross_area')) {
                throw new AuthorizationException('No puede aprobar transferencias entre áreas.');
            }
            $transfer->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->audit($actor, $source->branch, 'inventory.transfer.approved', $transfer, ['status' => 'approved']);

            return $transfer->fresh(['items.product', 'source', 'destination']);
        }, 3);
    }

    public function rejectTransfer(User $actor, InventoryTransfer $transfer, string $reason): InventoryTransfer
    {
        return DB::transaction(function () use ($actor, $transfer, $reason): InventoryTransfer {
            $transfer = InventoryTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $source = $transfer->source()->firstOrFail();
            $this->authorizeWarehouse($actor, 'inventory.transfers.approve', $source);
            if ($transfer->status !== 'pending_approval' || $transfer->requested_by === $actor->id || trim($reason) === '') {
                throw new \DomainException('La transferencia no puede rechazarse en su estado actual.');
            }
            $transfer->forceFill(['status' => 'rejected', 'reason' => $transfer->reason."\nRechazo: ".trim($reason)])->save();
            $this->audit($actor, $source->branch, 'inventory.transfer.rejected', $transfer, ['reason' => trim($reason)]);

            return $transfer->fresh();
        }, 3);
    }

    public function cancelTransfer(User $actor, InventoryTransfer $transfer): InventoryTransfer
    {
        return DB::transaction(function () use ($actor, $transfer): InventoryTransfer {
            $transfer = InventoryTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $source = $transfer->source()->firstOrFail();
            $this->authorizeWarehouse($actor, 'inventory.transfers.request', $source);
            if ($transfer->requested_by !== $actor->id || ! in_array($transfer->status, ['pending_approval', 'approved'], true)) {
                throw new \DomainException('La transferencia no puede cancelarse después del despacho.');
            }
            $transfer->forceFill(['status' => 'cancelled'])->save();
            $this->audit($actor, $source->branch, 'inventory.transfer.cancelled', $transfer, ['status' => 'cancelled']);

            return $transfer->fresh();
        }, 3);
    }

    public function dispatchTransfer(User $actor, InventoryTransfer $transfer, bool $demo = false): InventoryTransfer
    {
        return DB::transaction(function () use ($actor, $transfer, $demo): InventoryTransfer {
            $transfer = InventoryTransfer::query()->whereKey($transfer->id)->lockForUpdate()->with('items')->firstOrFail();
            $source = $transfer->source()->firstOrFail();
            $branch = $source->branch()->firstOrFail();
            $this->authorizeWarehouse($actor, 'inventory.transfers.dispatch', $source);
            if ($transfer->status !== 'approved') {
                throw new \DomainException('Solo se pueden despachar transferencias aprobadas.');
            }
            foreach ($transfer->items as $item) {
                $balance = $this->lockedBalance($source, $item->product_id);
                $available = bcsub((string) $balance->physical_quantity, (string) $balance->reserved_quantity, 2);
                if (bccomp($available, (string) $item->requested_quantity, 2) < 0) {
                    throw new \DomainException("Stock disponible insuficiente para {$item->product->sku}.");
                }
                $product = Product::query()->whereKey($item->product_id)->firstOrFail();
                $serialized = $product->tracking_mode !== 'quantity' || $product->requires_serial;
                if ($serialized) {
                    if (bccomp((string) $item->requested_quantity, (string) (int) $item->requested_quantity, 2) !== 0) {
                        throw new \DomainException("La cantidad serializada de {$product->sku} debe ser entera.");
                    }
                    $serials = InventorySerial::query()
                        ->where('warehouse_id', $source->id)
                        ->where('product_id', $product->id)
                        ->where('status', 'available')
                        ->orderBy('id')
                        ->limit((int) $item->requested_quantity)
                        ->lockForUpdate()
                        ->get();
                    if ($serials->count() !== (int) $item->requested_quantity) {
                        throw new \DomainException("No hay suficientes series disponibles para {$product->sku}.");
                    }
                    $item->serials()->attach($serials->modelKeys());
                    InventorySerial::query()->whereIn('id', $serials->modelKeys())->update([
                        'warehouse_id' => null, 'status' => 'in_transfer', 'updated_at' => now(),
                    ]);
                }
                $after = bcsub((string) $balance->physical_quantity, (string) $item->requested_quantity, 2);
                $balance->forceFill([
                    'physical_quantity' => $after,
                    'version' => $balance->version + 1,
                ])->save();
                $item->forceFill(['dispatched_quantity' => $item->requested_quantity])->save();
                $this->movement(
                    $actor, $branch, $source, $item->product_id,
                    bcmul((string) $item->requested_quantity, '-1', 2), $after,
                    "transfer:{$transfer->id}:dispatch:{$item->product_id}", 'transfer_dispatch', $demo,
                );
            }
            $transfer->forceFill(['status' => 'in_transit', 'dispatched_by' => $actor->id, 'dispatched_at' => now()])->save();
            $this->audit($actor, $branch, 'inventory.transfer.dispatched', $transfer, ['status' => 'in_transit']);

            return $transfer->fresh(['items.product', 'source', 'destination']);
        }, 3);
    }

    /**
     * @param  array<int, array{transfer_item_id:int, quantity:string|int}>  $items
     */
    public function receiveTransfer(User $actor, InventoryTransfer $transfer, array $items, string $idempotencyKey, ?string $note = null, bool $demo = false): InventoryTransferReceipt
    {
        return DB::transaction(function () use ($actor, $transfer, $items, $idempotencyKey, $note, $demo): InventoryTransferReceipt {
            $transfer = InventoryTransfer::query()->whereKey($transfer->id)->lockForUpdate()->with('items')->firstOrFail();
            $destination = $transfer->destination()->firstOrFail();
            $branch = $destination->branch()->firstOrFail();
            $this->authorizeWarehouse($actor, 'inventory.transfers.receive', $destination);
            $normalized = collect($items)->keyBy('transfer_item_id');
            if ($normalized->isEmpty()) {
                throw new \InvalidArgumentException('Debe registrar al menos una línea recibida.');
            }
            $existing = InventoryTransferReceipt::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $sameTransfer = $existing->inventory_transfer_id === $transfer->id;
                $submitted = $normalized->sortKeys()->map(fn (array $row, $id) => [
                    (int) $id,
                    bcadd((string) $row['quantity'], '0', 2),
                    collect($row['identifiers'] ?? [])->sort()->values()->all(),
                ])->values()->all();
                $saved = $existing->items()->with('serials')->orderBy('inventory_transfer_item_id')
                    ->get()->map(fn ($row) => [
                        (int) $row->inventory_transfer_item_id,
                        bcadd((string) $row->quantity, '0', 2),
                        $row->serials->pluck('serial_number')->sort()->values()->all(),
                    ])->all();
                if (! $sameTransfer || $existing->received_by !== $actor->id || $saved !== $submitted) {
                    throw new \DomainException('La clave de recepción ya se usó con otros datos.');
                }

                return $existing->load('items');
            }
            if (! in_array($transfer->status, ['in_transit', 'partially_received'], true)) {
                throw new \DomainException('La transferencia no está disponible para recepción.');
            }
            $transferItems = $transfer->items->keyBy('id');
            foreach ($normalized as $itemId => $row) {
                $item = $transferItems->get($itemId);
                $quantity = (string) ($row['quantity'] ?? '0');
                if (! $item || bccomp($quantity, '0', 2) !== 1) {
                    throw new \InvalidArgumentException('La recepción incluye líneas o cantidades inválidas.');
                }
                $remaining = bcsub((string) $item->dispatched_quantity, (string) $item->received_quantity, 2);
                if (bccomp($quantity, $remaining, 2) > 0) {
                    throw new \DomainException('La cantidad recibida supera lo despachado.');
                }
                $product = $item->product;
                if ($product->tracking_mode !== 'quantity' || $product->requires_serial) {
                    $identifiers = array_values(array_unique(array_map('trim', $row['identifiers'] ?? [])));
                    if (count($identifiers) !== (int) $quantity || in_array('', $identifiers, true)) {
                        throw new \InvalidArgumentException('Escanea una serie o IMEI distinto por cada unidad recibida.');
                    }
                    $serialIds = $item->serials()->where('status', 'in_transfer')
                        ->whereNull('inventory_transfer_item_serials.received_at')
                        ->whereIn('serial_number', $identifiers)
                        ->lockForUpdate()->pluck('inventory_serials.id');
                    if ($serialIds->count() !== count($identifiers)) {
                        throw new \DomainException('Una serie recibida no pertenece a esta transferencia o ya fue recibida.');
                    }
                    $row['serial_ids'] = $serialIds->all();
                } elseif (! empty($row['identifiers'])) {
                    throw new \InvalidArgumentException('El producto no admite identificadores serializados.');
                }
            }

            $receipt = InventoryTransferReceipt::create([
                'inventory_transfer_id' => $transfer->id,
                'received_by' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'note' => $note,
                'received_at' => now(),
            ]);
            foreach ($normalized as $itemId => $row) {
                $item = InventoryTransferItem::query()->whereKey($itemId)->lockForUpdate()->firstOrFail();
                $quantity = (string) $row['quantity'];
                $balance = $this->lockedBalance($destination, $item->product_id);
                $after = bcadd((string) $balance->physical_quantity, $quantity, 2);
                $balance->forceFill([
                    'physical_quantity' => $after,
                    'version' => $balance->version + 1,
                ])->save();
                $item->forceFill([
                    'received_quantity' => bcadd((string) $item->received_quantity, $quantity, 2),
                ])->save();
                $receiptItem = $receipt->items()->create(['inventory_transfer_item_id' => $item->id, 'quantity' => $quantity]);
                if (! empty($row['serial_ids'])) {
                    $receiptItem->serials()->attach($row['serial_ids']);
                    DB::table('inventory_transfer_item_serials')
                        ->where('inventory_transfer_item_id', $item->id)
                        ->whereIn('inventory_serial_id', $row['serial_ids'])
                        ->update(['received_at' => now(), 'updated_at' => now()]);
                    InventorySerial::query()->whereIn('id', $row['serial_ids'])->update([
                        'warehouse_id' => $destination->id,
                        'status' => 'available',
                        'updated_at' => now(),
                    ]);
                }
                $this->movement(
                    $actor, $branch, $destination, $item->product_id, $quantity, $after,
                    "transfer:{$transfer->id}:receive:{$receipt->id}:{$item->product_id}", 'transfer_receipt', $demo,
                );
            }
            $complete = $transfer->items()->whereColumn('received_quantity', '<', 'dispatched_quantity')->doesntExist();
            $transfer->forceFill([
                'status' => $complete ? 'received' : 'partially_received',
                'received_at' => $complete ? now() : null,
            ])->save();
            $this->audit($actor, $branch, 'inventory.transfer.received', $transfer, [
                'receipt_id' => $receipt->id,
                'status' => $transfer->status,
            ]);

            return $receipt->load('items');
        }, 3);
    }

    public function requestAdjustment(User $actor, Warehouse $warehouse, Product $product, string $quantityAfter, string $reasonCode, string $reason, string $idempotencyKey, bool $demo = false): InventoryAdjustment
    {
        $branch = $warehouse->branch()->firstOrFail();
        $this->authorizeWarehouse($actor, 'inventory.adjustments.request', $warehouse);
        if (! $warehouse->is_active || ! $product->is_active || ! in_array($reasonCode, ['surplus', 'shortage', 'damaged', 'loss', 'count_correction'], true) || trim($reason) === '') {
            throw new \InvalidArgumentException('Datos de ajuste no válidos.');
        }
        if (bccomp($quantityAfter, '0', 2) < 0) {
            throw new \DomainException('Un ajuste no puede dejar stock negativo.');
        }
        if ($product->tracking_mode !== 'quantity' || $product->requires_serial) {
            throw new \DomainException('Los ajustes de productos serializados requieren escaneo individual; no se permite alterar su saldo agregado.');
        }

        return DB::transaction(function () use ($actor, $warehouse, $product, $quantityAfter, $reasonCode, $reason, $idempotencyKey, $demo, $branch): InventoryAdjustment {
            $existing = InventoryAdjustment::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->warehouse_id !== $warehouse->id
                    || $existing->product_id !== $product->id
                    || $existing->requested_by !== $actor->id
                    || bccomp((string) $existing->quantity_after, $quantityAfter, 2) !== 0
                    || $existing->reason_code !== $reasonCode
                    || $existing->reason !== trim($reason)) {
                    throw new \DomainException('La clave de idempotencia pertenece a otro ajuste.');
                }

                return $existing;
            }
            $balance = $this->lockedBalance($warehouse, $product->id);
            $before = (string) $balance->physical_quantity;
            $change = bcsub($quantityAfter, $before, 2);
            if (bccomp($change, '0', 2) === 0) {
                throw new \DomainException('La cantidad contada coincide con el saldo; no se requiere ajuste.');
            }
            $adjustment = InventoryAdjustment::create([
                'public_id' => (string) Str::uuid(),
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'requested_by' => $actor->id,
                'status' => 'pending',
                'reason_code' => $reasonCode,
                'reason' => trim($reason),
                'quantity_before' => $before,
                'quantity_after' => $quantityAfter,
                'quantity_change' => $change,
                'idempotency_key' => $idempotencyKey,
                'requested_at' => now(),
                'is_demo' => $demo,
            ]);
            $this->audit($actor, $branch, 'inventory.adjustment.requested', $adjustment, [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity_before' => $before,
                'quantity_after' => $quantityAfter,
                'quantity_change' => $change,
                'reason_code' => $reasonCode,
            ]);

            return $adjustment;
        }, 3);
    }

    public function approveAdjustment(User $actor, InventoryAdjustment $adjustment, bool $demo = false): InventoryAdjustment
    {
        return DB::transaction(function () use ($actor, $adjustment, $demo): InventoryAdjustment {
            $adjustment = InventoryAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            $warehouse = $adjustment->warehouse()->firstOrFail();
            $branch = $warehouse->branch()->firstOrFail();
            $this->authorizeWarehouse($actor, 'inventory.adjustments.approve', $warehouse);
            if ($adjustment->status !== 'pending' || $adjustment->requested_by === $actor->id) {
                throw new \DomainException('El ajuste requiere un aprobador distinto y estar pendiente.');
            }
            $balance = $this->lockedBalance($warehouse, $adjustment->product_id);
            if (bccomp((string) $balance->physical_quantity, (string) $adjustment->quantity_before, 2) !== 0) {
                throw new \DomainException('El saldo cambió desde el conteo; solicite un ajuste actualizado.');
            }
            $balance->forceFill([
                'physical_quantity' => $adjustment->quantity_after,
                'version' => $balance->version + 1,
            ])->save();
            $adjustment->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->movement(
                $actor, $branch, $warehouse, $adjustment->product_id, (string) $adjustment->quantity_change,
                (string) $adjustment->quantity_after, "adjustment:{$adjustment->id}", 'adjustment', $demo,
            );
            $this->audit($actor, $branch, 'inventory.adjustment.approved', $adjustment, [
                'quantity_before' => $adjustment->quantity_before,
                'quantity_after' => $adjustment->quantity_after,
                'quantity_change' => $adjustment->quantity_change,
            ]);

            return $adjustment->fresh();
        }, 3);
    }

    private function authorizeWarehouse(User $actor, string $permission, Warehouse $warehouse): void
    {
        if (! $warehouse->is_active || ! $this->authorization->allows($actor, $permission, $warehouse->branch()->first())) {
            throw new AuthorizationException('No tiene autorización para operar este almacén.');
        }
    }

    private function assertDestinationAccess(User $actor, Branch $sourceBranch, Branch $destinationBranch): void
    {
        if ($this->authorization->allows($actor, 'inventory.stock.view', $destinationBranch)) {
            return;
        }
        $current = $this->context->current();
        $mayTransferWithinArea = $current
            && $current->is($sourceBranch)
            && $current->operational_area_id !== null
            && $current->operational_area_id === $destinationBranch->operational_area_id
            && $this->authorization->allows($actor, 'inventory.transfers.same_area', $current);
        if (! $mayTransferWithinArea) {
            throw new AuthorizationException('No tiene acceso a la sucursal destino.');
        }
    }

    private function lockedBalance(Warehouse $warehouse, int $productId): InventoryBalance
    {
        DB::table('inventory_balances')->insertOrIgnore([
            'warehouse_id' => $warehouse->id,
            'product_id' => $productId,
            'physical_quantity' => '0.00',
            'reserved_quantity' => '0.00',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return InventoryBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function movement(
        User $actor,
        Branch $branch,
        Warehouse $warehouse,
        int $productId,
        string $quantityChange,
        string $balanceAfter,
        string $reference,
        string $type,
        bool $demo,
    ): InventoryMovement {
        return InventoryMovement::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $productId,
            'actor_user_id' => $actor->id,
            'type' => $type,
            'quantity_change' => $quantityChange,
            'balance_after' => $balanceAfter,
            'reference_key' => $reference,
            'occurred_at' => now(),
            'is_demo' => $demo,
        ]);
    }

    private function audit(User $actor, Branch $branch, string $action, InventoryTransfer|InventoryAdjustment $resource, array $after): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actor->id,
            'branch_id' => $branch->id,
            'action' => $action,
            'resource_type' => $resource instanceof InventoryTransfer ? 'inventory_transfers' : 'inventory_adjustments',
            'resource_id' => $resource->id,
            'before_values' => null,
            'after_values' => json_encode($after, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
        ]);
    }
}
