<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\BranchContext;
use App\Services\Authorization\ScopedAuthorizationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    public function __construct(private readonly BranchContext $context, private readonly ScopedAuthorizationService $scoped) {}

    public function for(User $user, array $filters = []): array
    {
        $branch = $this->context->current();
        $roles = $this->roles($user, $branch->id);
        $primary = collect(['administrador-general', 'jefe-operaciones', 'jefe-tienda', 'vendedor', 'cajero', 'almacenero', 'asesor-creditos', 'encargado-compras'])
            ->first(fn (string $role) => in_array($role, $roles, true)) ?? 'colaborador';
        $from = CarbonImmutable::parse($filters['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($filters['to'] ?? now()->toDateString())->endOfDay();
        if ($from->diffInDays($to) > 366 || $from->isAfter($to)) {
            throw new \InvalidArgumentException('El rango estadístico no es válido.');
        }

        $availableBranches = Branch::query()->where('is_active', true)->orderBy('code')->get()
            ->filter(fn (Branch $candidate) => $this->scoped->allows($user, 'reports.consolidated.view', $candidate)
                || ($candidate->is($branch) && $this->scoped->allows($user, 'sales.view', $candidate)));
        if ($availableBranches->isEmpty()) {
            $availableBranches = collect([$branch]);
        }
        $selectedBranchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        if ($selectedBranchId && ! $availableBranches->contains('id', $selectedBranchId)) {
            abort(403);
        }
        $branchIds = $selectedBranchId ? [$selectedBranchId] : $availableBranches->pluck('id')->all();
        $branchIdsFor = fn (string $permission): array => $availableBranches
            ->filter(fn (Branch $candidate) => in_array($candidate->id, $branchIds, true)
                && $this->scoped->allows($user, $permission, $candidate))
            ->pluck('id')->all();
        $creditBranchIds = $branchIdsFor('credits.view');
        $customerBranchIds = $branchIdsFor('customers.view');
        $inventoryBranchIds = $branchIdsFor('inventory.stock.view');
        $cashBranchIds = $branchIdsFor('cash.view');
        $cashApprovalBranchIds = $branchIdsFor('cash.approve');
        $salesDetailBranchIds = $branchIdsFor('sales.view');

        $sales = collect();
        $payments = collect();
        $turnPayments = collect();
        $cashSession = null;
        $pendingCashSales = 0;
        $pendingCashAmount = '0.00';
        $canSeeSales = $this->scoped->allows($user, 'sales.view', $branch) || $this->scoped->allows($user, 'payments.collect', $branch);
        if (Schema::hasTable('sales') && $canSeeSales) {
            $sales = DB::table('sales')->whereIn('branch_id', $branchIds)->whereBetween('sold_at', [$from, $to])
                ->when($primary === 'vendedor', fn ($query) => $query->where('salesperson_user_id', $user->id))->get();
            $payments = DB::table('payments')->join('sales', 'sales.id', '=', 'payments.sale_id')
                ->whereIn('sales.branch_id', $branchIds)->whereBetween('payments.paid_at', [$from, $to])
                ->when($primary === 'vendedor', fn ($query) => $query->where('sales.salesperson_user_id', $user->id))
                ->select('payments.*', 'sales.branch_id')->get();
        }

        $sold = $this->money($sales->sum('total'));
        $collected = $this->money($payments->sum('amount'));
        $pending = $this->money($sales->where('status', 'pending_payment')->sum('total') - $payments->whereIn('sale_id', $sales->where('status', 'pending_payment')->pluck('id'))->sum('amount'));
        $financed = Schema::hasTable('credit_accounts') && $creditBranchIds
            ? $this->money(DB::table('credit_accounts')->whereIn('branch_id', $creditBranchIds)->whereBetween('started_at', [$from->toDateString(), $to->toDateString()])->sum('financed_amount'))
            : '0.00';
        $previousSold = 0.0;
        if ($canSeeSales && Schema::hasTable('sales')) {
            $days = $from->diffInDays($to) + 1;
            $previousTo = $from->subSecond();
            $previousFrom = $previousTo->subDays($days - 1)->startOfDay();
            $previousSold = (float) DB::table('sales')->whereIn('branch_id', $branchIds)->whereBetween('sold_at', [$previousFrom, $previousTo])
                ->when($primary === 'vendedor', fn ($query) => $query->where('salesperson_user_id', $user->id))->sum('total');
        }
        $variation = $previousSold > 0 ? round((((float) $sold - $previousSold) / $previousSold) * 100, 1) : null;
        $metrics = [
            ['label' => 'Vendido', 'value' => 'Bs '.number_format((float) $sold, 2), 'detail' => $sales->count().' operaciones'.($variation !== null ? ' · '.($variation >= 0 ? '+' : '').$variation.'% vs. anterior' : ''), 'tone' => 'primary'],
            ['label' => 'Cobrado', 'value' => 'Bs '.number_format((float) $collected, 2), 'detail' => $payments->count().' pagos', 'tone' => 'success'],
            ['label' => 'Pendiente', 'value' => 'Bs '.number_format(max(0, (float) $pending), 2), 'detail' => 'Cobros por completar', 'tone' => 'warning'],
        ];
        if ($creditBranchIds) {
            $metrics[] = ['label' => 'Financiado', 'value' => 'Bs '.number_format((float) $financed, 2), 'detail' => 'Créditos del periodo', 'tone' => 'info'];
        }
        if ($primary === 'vendedor') {
            $attendedCustomerCount = $sales->whereNotNull('customer_id')->pluck('customer_id')->unique()->count();
            $metrics[] = ['label' => 'Clientes atendidos', 'value' => (string) $attendedCustomerCount, 'detail' => 'Clientes con ventas en el periodo', 'tone' => 'info'];
        }
        if (in_array($primary, ['administrador-general', 'jefe-operaciones', 'jefe-tienda'], true)) {
            $ticket = $sales->count() ? ((float) $sales->sum('total') / $sales->count()) : 0;
            $metrics[] = ['label' => 'Ticket promedio', 'value' => 'Bs '.number_format($ticket, 2), 'detail' => 'Promedio por transacción', 'tone' => 'primary'];
            if ($creditBranchIds) {
                $receivable = DB::table('credit_accounts')->whereIn('branch_id', $creditBranchIds)->sum('outstanding_balance');
                $metrics[] = ['label' => 'Cuentas por cobrar', 'value' => 'Bs '.number_format((float) $receivable, 2), 'detail' => 'Saldo vigente de créditos', 'tone' => 'warning'];
            }
        }
        if ($primary === 'cajero') {
            $cashSession = DB::table('cash_sessions')->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
                ->where('cash_sessions.user_id', $user->id)->where('cash_registers.branch_id', $branch->id)
                ->orderByRaw("CASE WHEN cash_sessions.status = 'open' THEN 0 ELSE 1 END")
                ->orderByDesc('cash_sessions.opened_at')->select('cash_sessions.*')->first();
            $turnPayments = $cashSession ? DB::table('payments')->where('cash_session_id', $cashSession->id)->get() : collect();
            $payments = $turnPayments;
            $pendingCashSales = DB::table('sales')->where('branch_id', $branch->id)->where('status', 'pending_payment')->count();
            $pendingSalesTotal = (string) DB::table('sales')->where('branch_id', $branch->id)->where('status', 'pending_payment')->sum('total');
            $pendingPaymentsTotal = (string) DB::table('payments')->join('sales', 'sales.id', '=', 'payments.sale_id')
                ->where('sales.branch_id', $branch->id)->where('sales.status', 'pending_payment')->sum('payments.amount');
            $pendingCashAmount = $this->money(bcsub($pendingSalesTotal, $pendingPaymentsTotal, 2));
            $metrics = [
                ['label' => 'Estado del turno', 'value' => $cashSession?->status === 'open' ? 'Abierto' : ($cashSession ? 'Cerrado' : 'Sin sesión'), 'detail' => $cashSession?->opened_at ? 'Apertura '.$cashSession->opened_at : 'Sin turno en esta sucursal', 'tone' => 'primary'],
                ['label' => 'Cobros del turno', 'value' => 'Bs '.number_format((float) $turnPayments->sum('amount'), 2), 'detail' => $turnPayments->count().' transacciones', 'tone' => 'primary'],
                ['label' => 'Efectivo', 'value' => 'Bs '.number_format((float) $turnPayments->where('method', 'cash')->sum('amount'), 2), 'detail' => 'Efectivo recibido', 'tone' => 'success'],
                ['label' => 'QR', 'value' => 'Bs '.number_format((float) $turnPayments->where('method', 'qr')->sum('amount'), 2), 'detail' => 'Pagos QR', 'tone' => 'info'],
                ['label' => 'Tarjetas', 'value' => 'Bs '.number_format((float) $turnPayments->whereIn('method', ['card', 'debit_card', 'credit_card'])->sum('amount'), 2), 'detail' => 'Débito y crédito', 'tone' => 'primary'],
                ['label' => 'Transferencias', 'value' => 'Bs '.number_format((float) $turnPayments->where('method', 'transfer')->sum('amount'), 2), 'detail' => 'Conciliación bancaria', 'tone' => 'info'],
                ['label' => 'Efectivo esperado', 'value' => 'Bs '.number_format((float) ($cashSession->expected_amount ?? 0), 2), 'detail' => $cashSession ? 'Sesión abierta' : 'Sin sesión abierta', 'tone' => 'warning'],
                ['label' => 'Ventas pendientes', 'value' => (string) $pendingCashSales, 'detail' => 'Saldo Bs '.number_format((float) $pendingCashAmount, 2), 'tone' => 'warning'],
            ];
            if ($cashSession?->status === 'closed') {
                $metrics[] = ['label' => 'Diferencia del arqueo', 'value' => 'Bs '.number_format((float) $cashSession->difference_amount, 2), 'detail' => 'Último turno cerrado', 'tone' => bccomp((string) $cashSession->difference_amount, '0', 2) === 0 ? 'success' : 'warning'];
            }
        }
        if ($this->scoped->allows($user, 'organization.warehouses.view', $branch)) {
            $metrics[] = ['label' => 'Almacenes activos', 'value' => (string) DB::table('warehouses')->whereIn('branch_id', $branchIds)->where('is_active', true)->count(), 'detail' => 'En el ámbito autorizado', 'tone' => 'info'];
        }

        $daily = $primary === 'cajero'
            ? $turnPayments->groupBy(fn ($payment) => CarbonImmutable::parse($payment->paid_at)->format('Y-m-d'))
                ->map(fn (Collection $rows, string $date) => ['label' => CarbonImmutable::parse($date)->format('d/m'), 'value' => round((float) $rows->sum('amount'), 2)])->values()
            : $sales->groupBy(fn ($sale) => CarbonImmutable::parse($sale->sold_at)->format('Y-m-d'))
                ->map(fn (Collection $rows, string $date) => ['label' => CarbonImmutable::parse($date)->format('d/m'), 'value' => round((float) $rows->sum('total'), 2)])->values();
        $paymentMethods = $payments->groupBy('method')->map(fn (Collection $rows, string $method) => ['label' => $this->paymentLabel($method), 'value' => round((float) $rows->sum('amount'), 2)])->values();
        $branchComparison = $sales->groupBy('branch_id')->map(function (Collection $rows, int $id) use ($availableBranches) {
            $candidate = $availableBranches->firstWhere('id', $id);

            return ['label' => $candidate?->code ?? (string) $id, 'value' => round((float) $rows->sum('total'), 2)];
        })->values();
        $detailedSales = $sales->whereIn('branch_id', $salesDetailBranchIds);
        $topProducts = collect();
        $sellerRanking = collect();
        if ($detailedSales->isNotEmpty() && $primary !== 'cajero') {
            $topProducts = DB::table('sale_items')->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereIn('sale_items.sale_id', $detailedSales->pluck('id'))->select('products.name', DB::raw('SUM(sale_items.quantity) as units'), DB::raw('SUM(sale_items.line_total) as amount'))
                ->groupBy('products.id', 'products.name')->orderByDesc('amount')->limit(6)->get();
            $sellerRanking = DB::table('sales')->join('users', 'users.id', '=', 'sales.salesperson_user_id')
                ->whereIn('sales.id', $detailedSales->pluck('id'))->select('users.name', DB::raw('COUNT(sales.id) as sales_count'), DB::raw('SUM(sales.total) as amount'))
                ->groupBy('users.id', 'users.name')->orderByDesc('amount')->limit(7)->get();
        }
        $lowStock = collect();
        if (Schema::hasTable('inventory_balances') && $inventoryBranchIds) {
            $warehouseIds = DB::table('warehouses')->whereIn('branch_id', $inventoryBranchIds)->where('is_active', true)->pluck('id');
            $lowStock = DB::table('inventory_balances')->join('products', 'products.id', '=', 'inventory_balances.product_id')
                ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')->whereIn('inventory_balances.warehouse_id', $warehouseIds)
                ->whereRaw('(inventory_balances.physical_quantity - inventory_balances.reserved_quantity) <= 5')
                ->select('products.name', 'warehouses.name as warehouse', DB::raw('(inventory_balances.physical_quantity - inventory_balances.reserved_quantity) as available'))->orderBy('available')->limit(8)->get();
        }
        $goal = null;
        if (Schema::hasTable('sales_goals')) {
            $goal = DB::table('sales_goals')->whereIn('branch_id', $branchIds)->where('period_start', '<=', $to->toDateString())->where('period_end', '>=', $from->toDateString())
                ->when($primary === 'vendedor', fn ($query) => $query->where('user_id', $user->id), fn ($query) => $query->whereNull('user_id'))->sum('target_amount');
        }
        if ($goal && in_array($primary, ['vendedor', 'jefe-tienda', 'jefe-operaciones'], true)) {
            $metrics[] = ['label' => 'Cumplimiento de meta', 'value' => min(100, round(((float) $sold / (float) $goal) * 100, 1)).'%', 'detail' => 'Meta Bs '.number_format((float) $goal, 2), 'tone' => 'success'];
        }
        $cashState = null;
        if (Schema::hasTable('cash_sessions') && $cashBranchIds && $primary !== 'cajero') {
            $cashState = DB::table('cash_sessions')->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
                ->whereIn('cash_registers.branch_id', $cashBranchIds)->selectRaw("SUM(CASE WHEN cash_sessions.status = 'open' THEN 1 ELSE 0 END) as open_count")->first();
            $cashState->pending_approval = Schema::hasColumn('cash_sessions', 'approval_status') && $cashApprovalBranchIds
                ? DB::table('cash_sessions')->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')->whereIn('cash_registers.branch_id', $cashApprovalBranchIds)->where('cash_sessions.approval_status', 'pending')->count()
                : null;
        }
        $customerCount = Schema::hasTable('customers') && $customerBranchIds
            ? DB::table('customers')->whereIn('branch_id', $customerBranchIds)->where('is_active', true)->count()
            : null;
        $workspaces = $this->workspaces($primary);
        $charts = ['daily' => $daily, 'payments' => $paymentMethods, 'branches' => $branchComparison];

        return compact('branch', 'roles', 'primary', 'metrics', 'workspaces', 'availableBranches', 'selectedBranchId', 'from', 'to', 'charts', 'topProducts', 'sellerRanking', 'lowStock', 'goal', 'cashState', 'customerCount', 'sold');
    }

    private function roles(User $user, int $branchId): array
    {
        $global = DB::table('global_user_roles as assignment')->join('roles', 'roles.id', '=', 'assignment.role_id')->where('assignment.user_id', $user->id)->whereNull('assignment.ended_by')->where('assignment.starts_at', '<=', now())->where(fn ($query) => $query->whereNull('assignment.ends_at')->orWhere('assignment.ends_at', '>', now()))->pluck('roles.name');
        $local = DB::table('model_has_roles as assignment')->join('roles', 'roles.id', '=', 'assignment.role_id')->where('assignment.model_id', $user->id)->where('assignment.model_type', $user->getMorphClass())->where('assignment.branch_id', $branchId)->where('roles.scope_type', Role::SCOPE_BRANCH)->pluck('roles.name');

        return $global->merge($local)->unique()->values()->all();
    }

    private function workspaces(string $primary): array
    {
        return match ($primary) {
            'administrador-general' => ['Administración' => 'Usuarios, roles y configuración', 'Organización' => 'Sucursales, almacenes y empleados', 'Operación consolidada' => 'Inventarios, ventas y reportes'],
            'jefe-operaciones' => ['Supervisión territorial' => 'Sucursales autorizadas de su ciudad', 'Aprobaciones' => 'Descuentos y diferencias de caja', 'Resultados' => 'Indicadores comerciales e inventarios'],
            'jefe-tienda' => ['Mi sucursal' => 'Equipo, inventario y alertas', 'Resultados comerciales' => 'Ventas y ranking de vendedores', 'Caja' => 'Aperturas, cobros y cierres'],
            'vendedor' => ['Ventas' => 'Nueva venta y ventas propias', 'Clientes' => 'Registro e historial', 'Catálogo' => 'Productos y disponibilidad'],
            'cajero' => ['Mi caja' => 'Apertura, arqueo y cierre', 'Cobros' => 'Pagos parciales y mixtos', 'Historial' => 'Movimientos de la sesión'],
            'almacenero' => ['Inventario' => 'Productos y existencias', 'Movimientos' => 'Kardex y recepciones', 'Transferencias' => 'Envíos y recepciones'],
            'asesor-creditos' => ['Créditos' => 'Solicitudes y evaluaciones', 'Cartera' => 'Cuotas y estados de cuenta', 'Clientes' => 'Historial comercial'],
            'encargado-compras' => ['Compras' => 'Solicitudes y órdenes', 'Proveedores' => 'Catálogo y condiciones', 'Recepciones' => 'Pendientes y recepciones'],
            default => ['Espacio de trabajo' => 'Funciones asignadas por administración'],
        };
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function paymentLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Efectivo', 'qr' => 'QR', 'card', 'debit_card' => 'Débito', 'credit_card' => 'Crédito', 'transfer' => 'Transferencia', default => ucfirst($method)
        };
    }
}
