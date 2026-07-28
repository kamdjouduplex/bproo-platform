<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\ModuleEventsExportController;
use App\Http\Controllers\Tenant\AuthController as TenantAuthController;
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
use App\Livewire\Tenant\Dashboard as TenantDashboard;
use App\Livewire\Tenant\SubscriptionStatus as TenantSubscriptionStatus;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('landing');
})->name('landing');

Route::get('/reservez-demo', function () {
    return view('demo');
})->name('demo');

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('system.login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('system.login.submit');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/', AdminDashboard::class)->name('system.dashboard');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('system.logout');
        Route::get('/packages', ModuleMarketplace::class)->name('system.packages');
        // Ancien registre technique /admin/modules → redirige vers Packages
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

Route::prefix('app')->middleware(['tenant', 'tenant.active', 'tenant.store'])->group(function () {
    Route::get('/login', [TenantAuthController::class, 'showLogin'])->name('tenant.login');
    Route::post('/login', [TenantAuthController::class, 'login'])->name('tenant.login.submit');
    Route::post('/logout', [TenantAuthController::class, 'logout'])->name('tenant.logout');

    Route::get('/subscription', TenantSubscriptionStatus::class)->name('tenant.subscription');

    Route::middleware('auth:tenant')->group(function () {
        Route::get('/', TenantDashboard::class)->name('tenant.dashboard');
        /* Items and Configuration routes are registered by inovcom/items and inovcom/configuration packages */
    });
});
