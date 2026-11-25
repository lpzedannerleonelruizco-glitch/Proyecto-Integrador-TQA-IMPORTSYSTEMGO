<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\BranchSelectionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryReportController;
use App\Http\Controllers\MachineLearningController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PasswordRecoveryController;
use App\Http\Controllers\QaCenterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RetailController;
use App\Http\Controllers\RoleAdministrationController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserAdministrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordRecoveryController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordRecoveryController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordRecoveryController::class, 'update'])->middleware('throttle:5,1')->name('password.reset.store');
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
});
Route::middleware(['auth', 'active.user', 'auth.session'])->group(function () {
    Route::prefix('qa')->name('qa.')->group(function () {
        Route::get('/', [QaCenterController::class, 'dashboard'])->name('dashboard');
        Route::get('/nueva-ejecucion', [QaCenterController::class, 'createExecution'])->name('executions.create');
        Route::post('/ejecuciones', [QaCenterController::class, 'storeExecution'])->name('executions.store');
        Route::get('/ejecuciones', [QaCenterController::class, 'history'])->name('executions.history');
        Route::get('/ejecuciones/{execution:public_id}', [QaCenterController::class, 'showExecution'])->name('executions.show');
        Route::get('/ejecuciones/{execution:public_id}/progreso', [QaCenterController::class, 'progress'])->name('executions.progress');
        Route::get('/ejecuciones/{execution:public_id}/reportes/{format}', [QaCenterController::class, 'download'])
            ->whereIn('format', ['pdf', 'html'])->name('reports.download');
        Route::get('/ejecuciones/{execution:public_id}/evidencias/{evidence}', [QaCenterController::class, 'evidence'])
            ->whereNumber('evidence')->name('evidences.show');
        Route::get('/casos', [QaCenterController::class, 'cases'])->name('cases.index');
        Route::get('/ia', [QaCenterController::class, 'ai'])->name('ai');
        Route::post('/ia', [QaCenterController::class, 'aiStore'])->name('ai.store');
    });
    Route::prefix('administracion/usuarios')->group(function () {
        Route::get('/', [UserAdministrationController::class, 'index'])->name('users.index');
        Route::get('/crear', [UserAdministrationController::class, 'create'])->name('users.create');
        Route::post('/', [UserAdministrationController::class, 'store'])->name('users.store');
        Route::get('/{userId}/editar', [UserAdministrationController::class, 'edit'])->whereNumber('userId')->name('users.edit');
        Route::put('/{userId}', [UserAdministrationController::class, 'update'])->whereNumber('userId')->name('users.update');
        Route::post('/{userId}/empleado', [UserAdministrationController::class, 'employee'])->whereNumber('userId')->name('users.employee');
        Route::post('/{userId}/sucursales', [UserAdministrationController::class, 'membership'])->whereNumber('userId')->name('users.membership');
        Route::post('/{userId}/sucursales/{membershipId}/revocar', [UserAdministrationController::class, 'revokeMembership'])->whereNumber(['userId', 'membershipId'])->name('users.membership.revoke');
        Route::post('/{userId}/roles', [UserAdministrationController::class, 'role'])->whereNumber('userId')->name('users.role');
    });
    Route::prefix('administracion/roles')->group(function () {
        Route::get('/', [RoleAdministrationController::class, 'index'])->name('roles.index');
        Route::get('/crear', [RoleAdministrationController::class, 'create'])->name('roles.create');
        Route::post('/', [RoleAdministrationController::class, 'store'])->name('roles.store');
        Route::get('/{roleId}/editar', [RoleAdministrationController::class, 'edit'])->whereNumber('roleId')->name('roles.edit');
        Route::put('/{roleId}', [RoleAdministrationController::class, 'update'])->whereNumber('roleId')->name('roles.update');
    });
    Route::get('/perfil/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/perfil/password', [PasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::prefix('administracion/respaldos')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/completo', [BackupController::class, 'createFull'])->name('backups.full');
        Route::post('/territorial', [BackupController::class, 'createTerritorial'])->name('backups.territorial');
        Route::post('/{archive}/verificar', [BackupController::class, 'verify'])->whereNumber('archive')->name('backups.verify');
        Route::get('/{archive}/descargar', [BackupController::class, 'download'])->whereNumber('archive')->name('backups.download');
    });
    Route::get('/sucursales/seleccionar', [BranchSelectionController::class, 'index'])->name('branches.select');
    Route::post('/sucursales/seleccionar', [BranchSelectionController::class, 'store'])->name('branches.activate');
    Route::get('/dashboard', DashboardController::class)->middleware('branch.context')->name('dashboard');
    Route::middleware('branch.context')->group(function () {
        Route::get('/productos', [InventoryController::class, 'products'])->middleware('inventory.schema')->name('products.index');
        Route::get('/clientes', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/clientes/crear', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/clientes', [CustomerController::class, 'store'])->name('customers.store');
        Route::post('/clientes/registro-rapido', [CustomerController::class, 'quickStore'])->name('customers.quick-store');
        Route::get('/clientes/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/clientes/{customer}/editar', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/clientes/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::get('/inventario', [InventoryController::class, 'index'])->middleware('inventory.schema')->name('inventory.index');
        Route::middleware('inventory.schema')->prefix('inventarios')->name('inventory.')->group(function () {
            Route::get('/productos', [InventoryController::class, 'products'])->name('products.index');
            Route::get('/productos/crear', [InventoryController::class, 'createProduct'])->name('products.create');
            Route::post('/productos', [InventoryController::class, 'storeProduct'])->name('products.store');
            Route::get('/productos/{product}', [InventoryController::class, 'showProduct'])->whereNumber('product')->name('products.show');
            Route::get('/productos/{product}/imagen', [InventoryController::class, 'productImage'])->whereNumber('product')->name('products.image');
            Route::get('/productos/{product}/editar', [InventoryController::class, 'editProduct'])->whereNumber('product')->name('products.edit');
            Route::put('/productos/{product}', [InventoryController::class, 'updateProduct'])->whereNumber('product')->name('products.update');
            Route::patch('/productos/{product}/estado', [InventoryController::class, 'toggleProduct'])->whereNumber('product')->name('products.state');

            Route::get('/{type}', [InventoryController::class, 'taxonomy'])->whereIn('type', ['categorias', 'marcas'])->name('taxonomy.index');
            Route::get('/{type}/crear', [InventoryController::class, 'createTaxonomy'])->whereIn('type', ['categorias', 'marcas'])->name('taxonomy.create');
            Route::post('/{type}', [InventoryController::class, 'storeTaxonomy'])->whereIn('type', ['categorias', 'marcas'])->name('taxonomy.store');
            Route::get('/{type}/{record}/editar', [InventoryController::class, 'editTaxonomy'])->whereIn('type', ['categorias', 'marcas'])->whereNumber('record')->name('taxonomy.edit');
            Route::put('/{type}/{record}', [InventoryController::class, 'updateTaxonomy'])->whereIn('type', ['categorias', 'marcas'])->whereNumber('record')->name('taxonomy.update');
            Route::patch('/{type}/{record}/estado', [InventoryController::class, 'toggleTaxonomy'])->whereIn('type', ['categorias', 'marcas'])->whereNumber('record')->name('taxonomy.state');

            Route::get('/ingresos/crear', [InventoryController::class, 'createReceipt'])->name('receipts.create');
            Route::post('/ingresos', [InventoryController::class, 'storeReceipt'])->name('receipts.store');
            Route::get('/transferencias', [InventoryController::class, 'transfers'])->name('transfers.index');
            Route::get('/transferencias/crear', [InventoryController::class, 'createTransfer'])->name('transfers.create');
            Route::post('/transferencias', [InventoryController::class, 'storeTransfer'])->name('transfers.store');
            Route::post('/transferencias/{transfer}/aprobar', [InventoryController::class, 'approveTransfer'])->whereNumber('transfer')->name('transfers.approve');
            Route::post('/transferencias/{transfer}/rechazar', [InventoryController::class, 'rejectTransfer'])->whereNumber('transfer')->name('transfers.reject');
            Route::post('/transferencias/{transfer}/cancelar', [InventoryController::class, 'cancelTransfer'])->whereNumber('transfer')->name('transfers.cancel');
            Route::post('/transferencias/{transfer}/despachar', [InventoryController::class, 'dispatchTransfer'])->whereNumber('transfer')->name('transfers.dispatch');
            Route::post('/transferencias/{transfer}/recibir', [InventoryController::class, 'receiveTransfer'])->whereNumber('transfer')->name('transfers.receive');
            Route::get('/ajustes', [InventoryController::class, 'adjustments'])->name('adjustments.index');
            Route::get('/ajustes/crear', [InventoryController::class, 'createAdjustment'])->name('adjustments.create');
            Route::post('/ajustes', [InventoryController::class, 'storeAdjustment'])->name('adjustments.store');
            Route::post('/ajustes/{adjustment}/aprobar', [InventoryController::class, 'approveAdjustment'])->whereNumber('adjustment')->name('adjustments.approve');
        });
        Route::get('/ventas', [RetailController::class, 'sales'])->name('sales.index');
        Route::get('/ventas/nueva', [RetailController::class, 'createSale'])->name('sales.create');
        Route::post('/ventas', [RetailController::class, 'storeSale'])->name('sales.store');
        Route::get('/caja', [RetailController::class, 'cash'])->name('cash.index');
        Route::post('/caja/abrir', [RetailController::class, 'openCash'])->name('cash.open');
        Route::post('/caja/cobrar', [RetailController::class, 'collect'])->name('cash.collect');
        Route::post('/caja/movimientos', [RetailController::class, 'cashMovement'])->name('cash.movements.store');
        Route::post('/caja/cerrar', [RetailController::class, 'closeCash'])->name('cash.close');
        Route::get('/caja/supervision', [RetailController::class, 'cashSupervision'])->name('cash.supervision');
        Route::post('/caja/sesiones/{session}/aprobar', [RetailController::class, 'approveCash'])->name('cash.approve');
        Route::get('/caja/sesiones/{session}/comprobante', [RetailController::class, 'cashReceipt'])->name('cash.receipt');
        Route::get('/creditos', [RetailController::class, 'credits'])->name('credits.index');
        Route::post('/creditos/aprobar', [RetailController::class, 'approveCredit'])->name('credits.approve');
        Route::get('/compras', [RetailController::class, 'purchases'])->name('purchases.index');
        Route::post('/compras', [RetailController::class, 'storePurchase'])->name('purchases.store');
        Route::get('/reportes', ReportController::class)->name('reports.index');
        Route::middleware('ml.schema')->prefix('inteligencia')->name('ml.')->group(function () {
            Route::get('/demanda', [MachineLearningController::class, 'index'])->name('forecasts.index');
            Route::patch('/recomendaciones/{recommendation}/decidir', [MachineLearningController::class, 'decide'])
                ->whereNumber('recommendation')->name('recommendations.decide');
        });
        Route::get('/reportes/inventarios', [InventoryReportController::class, 'index'])->middleware('inventory.schema')->name('reports.inventory');
        Route::get('/reportes/inventarios/imprimir', [InventoryReportController::class, 'print'])->middleware('inventory.schema')->name('reports.inventory.print');
        Route::get('/reportes/inventarios/exportar/{format}', [InventoryReportController::class, 'export'])->middleware('inventory.schema')->whereIn('format', ['csv', 'xls'])->name('reports.inventory.export');
    });
    Route::middleware('branch.context')->prefix('organizacion')->whereIn('resource', ['branches', 'warehouses', 'employees'])->group(function () {
        Route::get('/{resource}', [OrganizationController::class, 'index'])->name('organization.index');
        Route::get('/{resource}/crear', [OrganizationController::class, 'create'])->name('organization.create');
        Route::post('/{resource}', [OrganizationController::class, 'store'])->name('organization.store');
        Route::get('/{resource}/{record}/editar', [OrganizationController::class, 'edit'])->whereNumber('record')->name('organization.edit');
        Route::put('/{resource}/{record}', [OrganizationController::class, 'update'])->whereNumber('record')->name('organization.update');
    });
});
