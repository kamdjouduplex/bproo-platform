<?php

namespace App\Providers;

use App\Livewire\Tenant\NotificationBell;
use App\Services\TenantManager;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManager::class, function () {
            return new TenantManager();
        });
    }

    public function boot(): void
    {
        // Register tenant-scoped Livewire components that live in the main app
        // (not in packages) so they are always available.
        Livewire::component('tenant.notification-bell', NotificationBell::class);
    }
}
