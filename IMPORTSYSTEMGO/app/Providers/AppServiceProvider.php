<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Warehouse;
use App\Policies\BranchPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\WarehousePolicy;
use App\Services\Authorization\BranchContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BranchContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
    }
}
