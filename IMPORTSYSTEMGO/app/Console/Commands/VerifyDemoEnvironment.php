<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\User;
use App\Services\Authorization\OperationalAreaAccessService;
use App\Support\AuthorizationCatalog;
use App\Support\DemoCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerifyDemoEnvironment extends Command
{
    protected $signature = 'demo:verify';

    protected $description = 'Verifica en solo lectura los datos, relaciones y aislamiento del entorno DEMO.';

    public function handle(OperationalAreaAccessService $areaAccess): int
    {
        $identity = DB::selectOne('SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name');
        if ($identity->database_name !== 'importsystemgo_demo'
            || $identity->account_name !== 'importsystemgo_demo@localhost') {
            $this->error('Verificacion bloqueada: identidad MySQL DEMO inesperada.');

            return self::FAILURE;
        }

        $checks = [
            'areas' => DB::table('operational_areas')->where('is_demo', true)->count() === 2,
            'branches' => DB::table('branches')->where('is_demo', true)->count() === 7,
            'warehouses' => DB::table('warehouses')->where('is_demo', true)->count() === 7,
            'employees' => DB::table('employees')->where('is_demo', true)->count() === 37,
            'users' => DB::table('users')->where('is_demo', true)->count() === 41,
            'roles' => DB::table('roles')->count() === 8,
            'permissions' => DB::table('permissions')->count() === count(AuthorizationCatalog::permissions()),
            'memberships' => DB::table('branch_user_memberships')->count() === 52,
            'local_roles' => DB::table('model_has_roles')->count() === 38,
            'global_roles' => DB::table('global_user_roles')->count() === 3,
            'territorial_assignments' => DB::table('operational_area_assignments')->count() === 2,
            'no_direct_permissions' => DB::table('model_has_permissions')->count() === 0,
            'catalog_emails' => User::whereIn('email', array_keys(DemoCatalog::identities()))->where('is_demo', true)->count() === 41,
            'permission_catalog' => DB::table('permissions')->whereIn('name', AuthorizationCatalog::permissions())->count() === count(AuthorizationCatalog::permissions()),
            'membership_uniqueness' => DB::table('branch_user_memberships')->count() === DB::table('branch_user_memberships')->distinct()->count(['user_id', 'branch_id']),
            'all_accounts_active' => User::where('is_demo', true)->where('status', User::STATUS_ACTIVE)->count() === 41,
            'credentials_rotated' => User::where('is_demo', true)->whereNotNull('password_changed_at')->count() === 41,
            'current_hash_format' => User::where('is_demo', true)->get(['password'])->every(fn (User $user): bool => ! Hash::needsRehash($user->password)),
            'credential_audits' => DB::table('audit_logs')->where('action', 'demo.credentials.rotated')->count() >= 41,
            'categories' => DB::table('categories')->where('is_demo', true)->count() === 5,
            'brands' => DB::table('brands')->where('is_demo', true)->count() === 4,
            'products' => DB::table('products')->where('is_demo', true)->count() === 12,
            'inventory_balances' => DB::table('inventory_balances')->count() === 84,
            'customers' => DB::table('customers')->where('is_demo', true)->count() === 21,
            'cash_registers' => DB::table('cash_registers')->where('is_demo', true)->count() === 7,
            'cash_sessions' => DB::table('cash_sessions')->where('is_demo', true)->count() === 7,
            'sales' => DB::table('sales')->where('is_demo', true)->count() === 15,
            'payments' => DB::table('payments')->where('is_demo', true)->count() === 7,
            'inventory_movements' => DB::table('inventory_movements')->where('is_demo', true)->count() === 107,
            'suppliers' => DB::table('suppliers')->where('is_demo', true)->count() === 3,
            'purchase_orders' => DB::table('purchase_orders')->where('is_demo', true)->count() === 1,
            'credits' => DB::table('credit_accounts')->where('is_demo', true)->count() === 1,
            'credit_installments' => DB::table('credit_installments')->count() === 6,
            'inventory_non_negative' => ! DB::table('inventory_balances')->whereRaw('physical_quantity < 0 OR reserved_quantity < 0 OR reserved_quantity > physical_quantity')->exists(),
            'sale_totals_match_items' => ! DB::table('sales')->leftJoinSub(
                DB::table('sale_items')->selectRaw('sale_id, SUM(line_total) AS item_total')->groupBy('sale_id'),
                'items', 'items.sale_id', '=', 'sales.id'
            )->whereRaw('ABS(sales.total - COALESCE(items.item_total, 0)) > 0.009')->exists(),
            'paid_sales_match_payments' => ! DB::table('sales')->leftJoinSub(
                DB::table('payments')->selectRaw('sale_id, SUM(amount) AS paid_total')->groupBy('sale_id'),
                'paid', 'paid.sale_id', '=', 'sales.id'
            )->where('sales.status', 'paid')->whereRaw('ABS(sales.total - COALESCE(paid.paid_total, 0)) > 0.009')->exists(),
            'cash_expected_matches_payments' => ! DB::table('cash_sessions')->leftJoinSub(
                DB::table('payments')->selectRaw('cash_session_id, SUM(amount) AS paid_total')->groupBy('cash_session_id'),
                'paid', 'paid.cash_session_id', '=', 'cash_sessions.id'
            )->whereRaw('ABS(cash_sessions.expected_amount - (cash_sessions.opening_amount + COALESCE(paid.paid_total, 0))) > 0.009')->exists(),
            'credit_schedule_matches_balance' => ! DB::table('credit_accounts')->leftJoinSub(
                DB::table('credit_installments')->selectRaw('credit_account_id, SUM(amount_due) AS schedule_total')->groupBy('credit_account_id'),
                'schedule', 'schedule.credit_account_id', '=', 'credit_accounts.id'
            )->whereRaw('ABS(credit_accounts.financed_amount - COALESCE(schedule.schedule_total, 0)) > 0.009')->exists(),
            'purchase_totals_match_items' => ! DB::table('purchase_orders')->leftJoinSub(
                DB::table('purchase_order_items')->selectRaw('purchase_order_id, SUM(quantity * unit_cost) AS item_total')->groupBy('purchase_order_id'),
                'items', 'items.purchase_order_id', '=', 'purchase_orders.id'
            )->whereRaw('ABS(purchase_orders.total - COALESCE(items.item_total, 0)) > 0.009')->exists(),
        ];

        $lp01 = Branch::where('code', 'LP01')->firstOrFail();
        $ea01 = Branch::where('code', 'EA01')->firstOrFail();
        $opsLp = User::where('email', 'operaciones.lp@importsystemgo.example')->firstOrFail();
        $opsEa = User::where('email', 'operaciones.ea@importsystemgo.example')->firstOrFail();
        $checks['la_paz_isolation'] = $areaAccess->allowsBranch($opsLp, $lp01) && ! $areaAccess->allowsBranch($opsLp, $ea01);
        $checks['el_alto_isolation'] = $areaAccess->allowsBranch($opsEa, $ea01) && ! $areaAccess->allowsBranch($opsEa, $lp01);

        $this->table(
            ['Comprobacion', 'Resultado'],
            collect($checks)->map(fn (bool $passed, string $name): array => [$name, $passed ? 'OK' : 'FALLO'])->values()->all(),
        );

        if (in_array(false, $checks, true)) {
            $this->error('La verificacion DEMO detecto discrepancias.');

            return self::FAILURE;
        }

        $this->info('Todos los datos y relaciones DEMO son correctos. Verificacion de solo lectura.');

        return self::SUCCESS;
    }
}
