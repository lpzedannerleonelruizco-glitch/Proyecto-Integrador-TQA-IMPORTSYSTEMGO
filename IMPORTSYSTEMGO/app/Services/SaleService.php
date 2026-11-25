<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Authorization\ScopedAuthorizationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleService
{
    public function __construct(private readonly ScopedAuthorizationService $authorization) {}

    /** @param array<int, array{product_id:int, quantity:int}> $items */
    public function createPending(User $seller, Branch $branch, array $items, ?Customer $customer = null, bool $demo = false): Sale
    {
        if (! $this->authorization->allows($seller, 'sales.create', $branch)) {
            throw new AuthorizationException('No puede registrar ventas en esta sucursal.');
        }
        if ($customer && $customer->branch_id !== $branch->id) {
            throw new AuthorizationException('El cliente no pertenece a la sucursal activa.');
        }
        $grouped = collect($items)->groupBy('product_id')->map(fn ($rows): int => (int) $rows->sum('quantity'));
        if ($grouped->isEmpty() || $grouped->contains(fn (int $quantity): bool => $quantity < 1)) {
            throw new \InvalidArgumentException('La venta requiere cantidades válidas.');
        }

        return DB::transaction(function () use ($seller, $branch, $grouped, $customer, $demo): Sale {
            $warehouse = $branch->warehouses()->where('is_active', true)->orderBy('id')->firstOrFail();
            $products = Product::active()->whereIn('id', $grouped->keys())->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== $grouped->count()) {
                throw new \InvalidArgumentException('Uno o más productos no están disponibles.');
            }
            $subtotal = '0.00';
            $prepared = [];
            foreach ($grouped as $productId => $quantity) {
                $product = $products->get($productId);
                $balance = InventoryBalance::where('warehouse_id', $warehouse->id)->where('product_id', $productId)->lockForUpdate()->first();
                $available = $balance ? bcsub((string) $balance->physical_quantity, (string) $balance->reserved_quantity, 2) : '0.00';
                if (bccomp($available, (string) $quantity, 2) < 0) {
                    throw new \DomainException("Stock insuficiente para {$product->sku}.");
                }
                $serials = collect();
                if ($product->tracking_mode !== 'quantity' || $product->requires_serial) {
                    $serials = InventorySerial::query()
                        ->where('warehouse_id', $warehouse->id)
                        ->where('product_id', $productId)
                        ->where('status', 'available')
                        ->orderBy('id')
                        ->limit($quantity)
                        ->lockForUpdate()
                        ->get();
                    if ($serials->count() !== $quantity) {
                        throw new \DomainException("No hay suficientes series disponibles para {$product->sku}.");
                    }
                }
                $lineTotal = bcmul((string) $product->list_price, (string) $quantity, 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);
                $prepared[] = compact('product', 'balance', 'quantity', 'lineTotal', 'serials');
            }
            $sale = Sale::create([
                'branch_id' => $branch->id, 'customer_id' => $customer?->id, 'salesperson_user_id' => $seller->id,
                'sale_number' => 'V-'.$branch->code.'-'.Str::upper(Str::ulid()), 'status' => 'pending_payment',
                'subtotal' => $subtotal, 'discount_total' => '0.00', 'total' => $subtotal, 'sold_at' => now(), 'is_demo' => $demo,
            ]);
            foreach ($prepared as $row) {
                $saleItem = $sale->items()->create([
                    'product_id' => $row['product']->id, 'quantity' => $row['quantity'],
                    'unit_price' => $row['product']->list_price, 'discount_amount' => '0.00', 'line_total' => $row['lineTotal'],
                ]);
                if ($row['serials']->isNotEmpty()) {
                    $saleItem->serials()->attach($row['serials']->modelKeys());
                    InventorySerial::query()->whereIn('id', $row['serials']->modelKeys())->update([
                        'status' => 'reserved',
                        'updated_at' => now(),
                    ]);
                }
                $reserved = bcadd((string) $row['balance']->reserved_quantity, (string) $row['quantity'], 2);
                $row['balance']->forceFill(['reserved_quantity' => $reserved, 'version' => $row['balance']->version + 1])->save();
                InventoryMovement::create([
                    'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $row['product']->id,
                    'actor_user_id' => $seller->id, 'type' => 'reservation', 'quantity_change' => '0.00',
                    'balance_after' => $row['balance']->physical_quantity, 'reference_key' => "sale:{$sale->id}:reserve:{$row['product']->id}",
                    'occurred_at' => now(), 'is_demo' => $demo,
                ]);
            }
            DB::table('audit_logs')->insert([
                'actor_user_id' => $seller->id, 'branch_id' => $branch->id, 'action' => 'sale.created',
                'resource_type' => 'sales', 'resource_id' => $sale->id, 'before_values' => null,
                'after_values' => json_encode(['status' => 'pending_payment', 'total' => $subtotal], JSON_THROW_ON_ERROR), 'occurred_at' => now(),
            ]);

            return $sale->load('items.product');
        }, 3);
    }
}
