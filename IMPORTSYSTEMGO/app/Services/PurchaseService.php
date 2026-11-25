<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Authorization\ScopedAuthorizationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseService
{
    public function __construct(private readonly ScopedAuthorizationService $authorization) {}

    public function create(User $buyer, Branch $branch, Supplier $supplier, Product $product, int $quantity, string $unitCost, ?string $expectedAt, bool $demo = false): PurchaseOrder
    {
        if (! $this->authorization->allows($buyer, 'purchasing.manage', $branch)) {
            throw new AuthorizationException('No puede crear órdenes en esta sucursal.');
        }
        if (! $supplier->is_active || ! $product->is_active || $quantity < 1 || bccomp($unitCost, '0', 2) !== 1) {
            throw new \InvalidArgumentException('Los datos de compra no son válidos.');
        }

        return DB::transaction(function () use ($buyer, $branch, $supplier, $product, $quantity, $unitCost, $expectedAt, $demo): PurchaseOrder {
            $total = bcmul((string) $quantity, $unitCost, 2);
            $order = PurchaseOrder::create([
                'branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'created_by' => $buyer->id,
                'order_number' => 'OC-'.$branch->code.'-'.Str::upper(Str::ulid()), 'status' => 'ordered',
                'total' => $total, 'expected_at' => $expectedAt, 'is_demo' => $demo,
            ]);
            $order->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => $unitCost]);

            return $order->load('items');
        }, 3);
    }
}
