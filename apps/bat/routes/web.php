<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\ModuleEventsExportController;
use App\Http\Controllers\Tenant\AuthController as TenantAuthController;
use App\Livewire\Admin\ModuleForm;
use App\Livewire\Tenant\AuditLog as TenantAuditLog;
use App\Livewire\Tenant\Dashboard as TenantDashboard;
use App\Livewire\Tenant\Reports as TenantReports;
use App\Livewire\Tenant\ServicesHub;
use App\Livewire\Admin\ModuleMarketplace;
use App\Livewire\Admin\Modules;
use App\Livewire\Admin\TenantHealth;
use App\Livewire\Admin\TenantForm;
use App\Livewire\Admin\ModuleEvents;
use App\Livewire\Admin\TenantModules;
use App\Livewire\Admin\TenantSettings;
use App\Livewire\Admin\Analytics;
use App\Livewire\Admin\Tenants;
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
    return auth()->check()
        ? redirect()->route('system.dashboard')
        : redirect()->route('system.login');
});

Route::get('/locale/{locale}', function (string $locale) {
    $locale = in_array($locale, ['fr', 'en'], true) ? $locale : 'fr';
    session()->put('locale', $locale);
    return redirect()->back();
})->name('locale.switch');

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('system.login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('system.login.submit');
    });

    Route::middleware('auth')->group(function () {
        Route::view('/', 'system.dashboard')->name('system.dashboard');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('system.logout');
        Route::get('/packages', ModuleMarketplace::class)->name('system.packages');
        Route::get('/modules', Modules::class)->name('system.modules');
        Route::get('/modules/create', ModuleForm::class)->name('system.modules.create');
        Route::get('/modules/{module}/edit', ModuleForm::class)->name('system.modules.edit');
        Route::get('/tenants', Tenants::class)->name('system.tenants');
        Route::get('/tenants/health', TenantHealth::class)->name('system.tenants.health');
        Route::get('/tenants/create', TenantForm::class)->name('system.tenants.create');
        Route::get('/tenants/{tenant}/edit', TenantForm::class)->name('system.tenants.edit');
        Route::get('/tenants/{tenant}/settings', TenantSettings::class)->name('system.tenants.settings');
        Route::get('/tenants/modules', TenantModules::class)->name('system.tenant.modules');
        Route::get('/analytics', Analytics::class)->name('system.analytics');
        Route::get('/module-events', ModuleEvents::class)->name('system.module.events');
        Route::get('/module-events/export', ModuleEventsExportController::class)->name('system.module.events.export');
    });
});

Route::prefix('app')->middleware('tenant')->group(function () {
    Route::get('/login', [TenantAuthController::class, 'showLogin'])->name('tenant.login');
    Route::post('/login', [TenantAuthController::class, 'login'])->name('tenant.login.submit');
    Route::post('/logout', [TenantAuthController::class, 'logout'])->name('tenant.logout');

    Route::middleware(['auth:tenant', 'tenant.plan', 'tenant.active'])->group(function () {
        Route::get('/', TenantDashboard::class)->name('tenant.dashboard');
        Route::get('/services', ServicesHub::class)->name('tenant.services.index');
        Route::get('/reports', TenantReports::class)->name('tenant.reports');
        Route::get('/audit', TenantAuditLog::class)->name('tenant.audit');
        /* Clients, Items and Configuration routes are registered by their packages */
    });
});
