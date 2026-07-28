<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\ModuleEventsExportController;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\ModuleMarketplace;
use App\Livewire\Admin\PlanForm;
use App\Livewire\Admin\Plans;
use App\Livewire\Admin\TenantHealth;
use App\Livewire\Admin\TenantForm;
use App\Livewire\Admin\ModuleEvents;
use App\Livewire\Admin\TenantModules;
use App\Livewire\Admin\TenantSettings;
use App\Livewire\Admin\TenantSubscription;
use App\Livewire\Admin\Tenants;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bproo Control Center — ops control plane only
|--------------------------------------------------------------------------
|
| Shares landlord DB schema with product hosts. Product /admin remains
| available until ops cut over fully (M4b parallel run).
|
*/

Route::redirect('/', '/admin')->name('home');

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('system.login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('system.login.submit');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/', AdminDashboard::class)->name('system.dashboard');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('system.logout');
        Route::get('/packages', ModuleMarketplace::class)->name('system.packages');
        Route::redirect('/modules', '/admin/packages');
        Route::redirect('/modules/create', '/admin/packages');
        Route::get('/modules/{module}/edit', fn () => redirect()->route('system.packages'));
        Route::get('/tenants', Tenants::class)->name('system.tenants');
        Route::get('/tenants/health', TenantHealth::class)->name('system.tenants.health');
        Route::get('/tenants/create', TenantForm::class)->name('system.tenants.create');
        Route::get('/tenants/{tenant}/edit', TenantForm::class)->name('system.tenants.edit');
        Route::get('/tenants/{tenant}/settings', TenantSettings::class)->name('system.tenants.settings');
        Route::get('/tenants/{tenant}/subscription', TenantSubscription::class)->name('system.tenants.subscription');
        Route::get('/tenants/modules', TenantModules::class)->name('system.tenant.modules');
        Route::get('/plans', Plans::class)->name('system.plans');
        Route::get('/plans/create', PlanForm::class)->name('system.plans.create');
        Route::get('/plans/{plan}/edit', PlanForm::class)->name('system.plans.edit');
        Route::get('/module-events', ModuleEvents::class)->name('system.module.events');
        Route::get('/module-events/export', ModuleEventsExportController::class)->name('system.module.events.export');
    });
});
