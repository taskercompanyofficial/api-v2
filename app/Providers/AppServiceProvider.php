<?php

namespace App\Providers;

use App\Models\WorkOrder;
use App\Observers\WorkOrderObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Work Order Observer for automatic history tracking
        WorkOrder::observe(WorkOrderObserver::class);
    }
}
