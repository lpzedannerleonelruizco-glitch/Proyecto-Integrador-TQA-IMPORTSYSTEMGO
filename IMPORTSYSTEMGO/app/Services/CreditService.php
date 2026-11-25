<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CreditAccount;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\User;
use App\Services\Authorization\ScopedAuthorizationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreditService
{
    public function __construct(private readonly ScopedAuthorizationService $authorization) {}

    public function approve(User $advisor, Branch $branch, Sale $sale, int $terms, bool $demo = false): CreditAccount
    {
        if (! $this->authorization->allows($advisor, 'credits.manage', $branch) || $sale->branch_id !== $branch->id) {
            throw new AuthorizationException('No puede aprobar este crédito.');
        }
        if ($terms < 2 || $terms > 12) {
            throw new \InvalidArgumentException('El plazo debe estar entre 2 y 12 cuotas.');
        }

        return DB::transaction(function () use ($advisor, $branch, $sale, $terms, $demo): CreditAccount {
            $sale = Sale::query()->whereKey($sale->id)->with('items')->lockForUpdate()->firstOrFail();
            if ($sale->status !== 'pending_payment' || ! $sale->customer_id || CreditAccount::where('sale_id', $sale->id)->exists()) {
                throw new \DomainException('La venta debe estar pendiente y tener un cliente identificado.');
            }
            $warehouse = $branch->warehouses()->active()->orderBy('id')->firstOrFail();
            foreach ($sale->items as $item) {
                $balance = InventoryBalance::where('warehouse_id', $warehouse->id)->where('product_id', $item->product_id)->lockForUpdate()->firstOrFail();
                if (bccomp((string) $balance->physical_quantity, (string) $item->quantity, 2) < 0 || bccomp((string) $balance->reserved_quantity, (string) $item->quantity, 2) < 0) {
                    throw new \DomainException('El inventario reservado no es consistente.');
                }
                $physical = bcsub((string) $balance->physical_quantity, (string) $item->quantity, 2);
                $reserved = bcsub((string) $balance->reserved_quantity, (string) $item->quantity, 2);
                $balance->forceFill(['physical_quantity' => $physical, 'reserved_quantity' => $reserved, 'version' => $balance->version + 1])->save();
                InventoryMovement::create([
                    'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $item->product_id,
                    'actor_user_id' => $advisor->id, 'type' => 'credit_sale', 'quantity_change' => bcmul((string) $item->quantity, '-1', 2),
                    'balance_after' => $physical, 'reference_key' => "credit-sale:{$sale->id}:dispatch:{$item->product_id}",
                    'occurred_at' => now(), 'is_demo' => $demo,
                ]);
            }
            $sale->forceFill(['status' => 'credit'])->save();
            $account = CreditAccount::create([
                'branch_id' => $branch->id, 'sale_id' => $sale->id, 'customer_id' => $sale->customer_id,
                'advisor_user_id' => $advisor->id, 'credit_number' => 'CR-'.$branch->code.'-'.Str::upper(Str::ulid()),
                'status' => 'active', 'frequency' => 'monthly', 'term_count' => $terms,
                'principal_amount' => $sale->total, 'down_payment' => '0.00', 'financed_amount' => $sale->total,
                'annual_rate' => '0.000', 'outstanding_balance' => $sale->total, 'started_at' => today(), 'is_demo' => $demo,
            ]);
            $base = bcdiv((string) $sale->total, (string) $terms, 2);
            $allocated = '0.00';
            for ($number = 1; $number <= $terms; $number++) {
                $amount = $number === $terms ? bcsub((string) $sale->total, $allocated, 2) : $base;
                $account->installments()->create(['installment_number' => $number, 'due_date' => today()->addMonths($number), 'amount_due' => $amount]);
                $allocated = bcadd($allocated, $amount, 2);
            }

            return $account->load('installments');
        }, 3);
    }
}
