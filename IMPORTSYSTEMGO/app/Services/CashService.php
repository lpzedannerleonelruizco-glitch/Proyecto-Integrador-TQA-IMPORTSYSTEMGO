<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashCountDetail;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\Authorization\ScopedAuthorizationService;
use App\Support\PaymentReferenceSafety;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashService
{
    public function __construct(private readonly ScopedAuthorizationService $authorization) {}

    public function open(User $cashier, Branch $branch, CashRegister $register, string $openingAmount, bool $demo = false, ?string $note = null): CashSession
    {
        if (! $this->authorization->allows($cashier, 'cash.manage', $branch)
            || $register->branch_id !== $branch->id
            || ! $register->is_active) {
            throw new AuthorizationException('No puede abrir esta caja.');
        }
        if (bccomp($openingAmount, '0', 2) < 0) {
            throw new \InvalidArgumentException('El fondo inicial no puede ser negativo.');
        }

        return DB::transaction(function () use ($cashier, $register, $openingAmount, $demo, $note): CashSession {
            if (CashSession::where('cash_register_id', $register->id)->where('status', 'open')->lockForUpdate()->exists()
                || CashSession::where('user_id', $cashier->id)->where('status', 'open')->lockForUpdate()->exists()) {
                throw new \DomainException('La caja o el cajero ya tienen una sesión abierta.');
            }

            return CashSession::create([
                'cash_register_id' => $register->id,
                'user_id' => $cashier->id,
                'status' => 'open',
                'opening_amount' => $openingAmount,
                'opening_note' => $note,
                'expected_amount' => $openingAmount,
                'open_register_token' => $register->id,
                'open_user_token' => $cashier->id,
                'opened_at' => now(),
                'is_demo' => $demo,
            ]);
        }, 3);
    }

    public function collect(User $cashier, Branch $branch, CashSession $session, Sale $sale, string $method, string $idempotencyKey, bool $demo = false, ?string $amount = null, ?string $reference = null): Payment
    {
        return $this->collectMany($cashier, $branch, $session, $sale, [[
            'method' => $method,
            'amount' => $amount ?? $this->remainingAmount($sale),
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
        ]], $demo)->firstOrFail();
    }

    public function collectMany(User $cashier, Branch $branch, CashSession $session, Sale $sale, array $lines, bool $demo = false): Collection
    {
        $this->assertCashier($cashier, $branch, $session, 'payments.collect', $sale);

        return DB::transaction(function () use ($cashier, $branch, $session, $sale, $lines, $demo): Collection {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->with('items')->firstOrFail();
            $session = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $keys = collect($lines)->pluck('idempotency_key');
            if ($keys->isEmpty() || $keys->count() !== $keys->unique()->count()) {
                throw new \InvalidArgumentException('Cada línea de pago requiere una clave de idempotencia única.');
            }
            foreach ($lines as $line) {
                if (PaymentReferenceSafety::containsFullCardNumber((string) ($line['reference'] ?? ''))) {
                    throw new \InvalidArgumentException('No se permiten números completos de tarjeta en referencias de pago.');
                }
            }
            $existing = Payment::whereIn('idempotency_key', $keys)->get()->keyBy('idempotency_key');
            $linesByKey = collect($lines)->keyBy('idempotency_key');
            foreach ($existing as $key => $payment) {
                $line = $linesByKey->get($key);
                if ($payment->sale_id !== $sale->id
                    || $payment->cash_session_id !== $session->id
                    || $payment->received_by !== $cashier->id
                    || $payment->method !== $line['method']
                    || bccomp((string) $payment->amount, (string) $line['amount'], 2) !== 0
                    || trim((string) $payment->reference) !== trim((string) ($line['reference'] ?? ''))) {
                    throw new \DomainException('Una clave de idempotencia pertenece a otra operación.');
                }
            }
            $newLines = collect($lines)->reject(fn (array $line) => $existing->has($line['idempotency_key']));
            if ($newLines->isEmpty()) {
                return $keys->map(fn (string $key) => $existing->get($key));
            }
            if ($sale->status !== 'pending_payment' || $session->status !== 'open') {
                throw new \DomainException('La venta o la caja ya no están disponibles para cobro.');
            }

            $allowed = ['cash', 'qr', 'card', 'debit_card', 'credit_card', 'transfer'];
            $paid = (string) Payment::where('sale_id', $sale->id)->sum('amount');
            $remaining = bcsub((string) $sale->total, $paid, 2);
            $incoming = '0.00';
            foreach ($newLines as $line) {
                if (! in_array($line['method'], $allowed, true) || bccomp((string) $line['amount'], '0', 2) !== 1) {
                    throw new \InvalidArgumentException('Método o importe de pago no permitido.');
                }
                if ($line['method'] !== 'cash' && blank($line['reference'] ?? null)) {
                    throw new \InvalidArgumentException('Los pagos no efectivos requieren una referencia.');
                }
                $incoming = bcadd($incoming, (string) $line['amount'], 2);
            }
            if (bccomp($incoming, $remaining, 2) === 1) {
                throw new \DomainException('El pago supera el saldo pendiente de la venta.');
            }

            $created = collect();
            $cashTotal = '0.00';
            foreach ($newLines as $line) {
                $payment = Payment::create([
                    'sale_id' => $sale->id,
                    'cash_session_id' => $session->id,
                    'received_by' => $cashier->id,
                    'method' => $line['method'],
                    'amount' => $line['amount'],
                    'reference' => $line['reference'] ?? null,
                    'idempotency_key' => $line['idempotency_key'],
                    'paid_at' => now(),
                    'is_demo' => $demo,
                ]);
                $created->push($payment);
                if ($line['method'] === 'cash') {
                    $cashTotal = bcadd($cashTotal, (string) $line['amount'], 2);
                }
            }
            if (bccomp($cashTotal, '0', 2) === 1) {
                $session->forceFill(['expected_amount' => bcadd((string) $session->expected_amount, $cashTotal, 2)])->save();
            }

            $newPaid = bcadd($paid, $incoming, 2);
            if (bccomp($newPaid, (string) $sale->total, 2) === 0) {
                $this->dispatchInventory($cashier, $branch, $sale, $demo);
                $sale->forceFill(['status' => 'paid'])->save();
            }
            $this->audit($cashier, $branch, 'sale.payment_registered', 'sales', $sale->id, [
                'status' => $sale->status,
                'amount' => $incoming,
                'methods' => $newLines->pluck('method')->values()->all(),
            ]);

            return $keys->map(fn (string $key) => $existing->get($key) ?? $created->firstWhere('idempotency_key', $key));
        }, 3);
    }

    public function move(User $cashier, Branch $branch, CashSession $session, string $type, string $amount, string $reason, string $key, bool $demo = false): CashMovement
    {
        $this->assertCashier($cashier, $branch, $session, 'cash.manage');
        if (! in_array($type, ['income', 'withdrawal'], true) || bccomp($amount, '0', 2) !== 1) {
            throw new \InvalidArgumentException('Movimiento de caja no permitido.');
        }

        return DB::transaction(function () use ($cashier, $branch, $session, $type, $amount, $reason, $key, $demo): CashMovement {
            $existing = CashMovement::where('reference_key', $key)->first();
            if ($existing) {
                if ($existing->cash_session_id !== $session->id
                    || $existing->branch_id !== $branch->id
                    || $existing->user_id !== $cashier->id
                    || $existing->type !== $type
                    || bccomp((string) $existing->amount, $amount, 2) !== 0
                    || $existing->reason !== $reason) {
                    throw new \DomainException('La clave de idempotencia pertenece a otra operación.');
                }

                return $existing;
            }
            $session = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status !== 'open') {
                throw new \DomainException('La sesión de caja está cerrada.');
            }
            $expected = $type === 'income'
                ? bcadd((string) $session->expected_amount, $amount, 2)
                : bcsub((string) $session->expected_amount, $amount, 2);
            if (bccomp($expected, '0', 2) < 0) {
                throw new \DomainException('El retiro supera el efectivo esperado.');
            }
            $movement = CashMovement::create([
                'branch_id' => $branch->id, 'cash_session_id' => $session->id, 'user_id' => $cashier->id,
                'type' => $type, 'amount' => $amount, 'reason' => $reason, 'reference_key' => $key,
                'occurred_at' => now(), 'is_demo' => $demo,
            ]);
            $session->forceFill(['expected_amount' => $expected])->save();
            $this->audit($cashier, $branch, 'cash.movement_registered', 'cash_sessions', $session->id, ['type' => $type, 'amount' => $amount, 'reason' => $reason]);

            return $movement;
        }, 3);
    }

    public function close(User $cashier, Branch $branch, CashSession $session, array $counts, ?string $note = null): CashSession
    {
        $this->assertCashier($cashier, $branch, $session, 'cash.manage');
        $denominations = collect(config('cash.denominations'))->map(fn ($value) => number_format((float) $value, 2, '.', ''));
        $declared = '0.00';
        foreach ($counts as $count) {
            $normalized = number_format((float) $count['denomination'], 2, '.', '');
            $quantity = $count['quantity'];
            if (! $denominations->contains($normalized) || (int) $quantity < 0) {
                throw new \InvalidArgumentException('El arqueo contiene una denominación inválida.');
            }
            $declared = bcadd($declared, bcmul($normalized, (string) (int) $quantity, 2), 2);
        }

        return DB::transaction(function () use ($cashier, $branch, $session, $counts, $declared, $note): CashSession {
            $session = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status !== 'open') {
                throw new \DomainException('La sesión de caja ya fue cerrada.');
            }
            $difference = bcsub($declared, (string) $session->expected_amount, 2);
            if (bccomp($difference, '0', 2) !== 0 && blank($note)) {
                throw new \DomainException('Debe explicar la diferencia encontrada en el arqueo.');
            }
            $requiresApproval = bccomp(ltrim($difference, '-'), (string) config('cash.approval_variance_threshold'), 2) >= 0;
            foreach ($counts as $count) {
                $quantity = $count['quantity'];
                if ((int) $quantity === 0) {
                    continue;
                }
                $normalized = number_format((float) $count['denomination'], 2, '.', '');
                CashCountDetail::create(['cash_session_id' => $session->id, 'denomination' => $normalized, 'quantity' => $quantity, 'subtotal' => bcmul($normalized, (string) $quantity, 2)]);
            }
            $session->forceFill([
                'status' => 'closed', 'declared_amount' => $declared, 'difference_amount' => $difference,
                'closing_note' => $note, 'approval_status' => $requiresApproval ? 'pending' : 'not_required',
                'closed_by' => $cashier->id, 'closed_at' => now(), 'open_register_token' => null, 'open_user_token' => null,
            ])->save();
            $this->audit($cashier, $branch, 'cash.closed', 'cash_sessions', $session->id, ['declared_amount' => $declared, 'difference_amount' => $difference, 'approval_status' => $session->approval_status]);

            return $session;
        }, 3);
    }

    public function approveClosure(User $approver, Branch $branch, CashSession $session): CashSession
    {
        if (! $this->authorization->allows($approver, 'cash.approve', $branch) || $session->register()->value('branch_id') !== $branch->id) {
            throw new AuthorizationException('No puede aprobar cierres de esta sucursal.');
        }

        return DB::transaction(function () use ($approver, $branch, $session): CashSession {
            $session = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status !== 'closed' || $session->approval_status !== 'pending') {
                throw new \DomainException('El cierre no requiere aprobación.');
            }
            if ($session->user_id === $approver->id) {
                throw new AuthorizationException('El cajero no puede aprobar su propio cierre.');
            }
            $session->forceFill(['approval_status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()])->save();
            $this->audit($approver, $branch, 'cash.closure_approved', 'cash_sessions', $session->id, ['approval_status' => 'approved']);

            return $session;
        }, 3);
    }

    private function remainingAmount(Sale $sale): string
    {
        return bcsub((string) $sale->total, (string) Payment::where('sale_id', $sale->id)->sum('amount'), 2);
    }

    private function assertCashier(User $cashier, Branch $branch, CashSession $session, string $permission, ?Sale $sale = null): void
    {
        if (! $this->authorization->allows($cashier, $permission, $branch)
            || $session->user_id !== $cashier->id
            || $session->status !== 'open'
            || $session->register()->value('branch_id') !== $branch->id
            || ($sale && $sale->branch_id !== $branch->id)) {
            throw new AuthorizationException('No puede operar esta caja.');
        }
    }

    private function dispatchInventory(User $cashier, Branch $branch, Sale $sale, bool $demo): void
    {
        $warehouse = $branch->warehouses()->where('is_active', true)->orderBy('id')->firstOrFail();
        foreach ($sale->items as $item) {
            $balance = InventoryBalance::where('warehouse_id', $warehouse->id)->where('product_id', $item->product_id)->lockForUpdate()->firstOrFail();
            if (bccomp((string) $balance->physical_quantity, (string) $item->quantity, 2) < 0
                || bccomp((string) $balance->reserved_quantity, (string) $item->quantity, 2) < 0) {
                throw new \DomainException('El inventario reservado no es consistente.');
            }
            $physical = bcsub((string) $balance->physical_quantity, (string) $item->quantity, 2);
            $reserved = bcsub((string) $balance->reserved_quantity, (string) $item->quantity, 2);
            $balance->forceFill(['physical_quantity' => $physical, 'reserved_quantity' => $reserved, 'version' => $balance->version + 1])->save();
            if ($item->product->tracking_mode !== 'quantity' || $item->product->requires_serial) {
                $serials = $item->serials()->lockForUpdate()->get();
                if ($serials->count() !== (int) $item->quantity
                    || $serials->contains(fn ($serial) => $serial->status !== 'reserved' || $serial->warehouse_id !== $warehouse->id)) {
                    throw new \DomainException('La reserva de series no es consistente.');
                }
                foreach ($serials as $serial) {
                    $serial->forceFill([
                        'status' => 'sold',
                        'warehouse_id' => null,
                        'sale_id' => $sale->id,
                        'sold_at' => now(),
                    ])->save();
                }
            }
            InventoryMovement::create([
                'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $item->product_id,
                'actor_user_id' => $cashier->id, 'type' => 'sale', 'quantity_change' => bcmul((string) $item->quantity, '-1', 2),
                'balance_after' => $physical, 'reference_key' => "sale:{$sale->id}:dispatch:{$item->product_id}",
                'occurred_at' => now(), 'is_demo' => $demo,
            ]);
        }
    }

    private function audit(User $actor, Branch $branch, string $action, string $type, int $id, array $after): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actor->id, 'branch_id' => $branch->id, 'action' => $action,
            'resource_type' => $type, 'resource_id' => $id, 'after_values' => json_encode($after, JSON_THROW_ON_ERROR), 'occurred_at' => now(),
        ]);
    }
}
