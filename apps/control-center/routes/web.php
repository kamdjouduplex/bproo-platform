<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\ModuleEventsExportController;
use App\Livewire\Admin\ActivitiesIndex;
use App\Livewire\Admin\BillingPayments;
use App\Livewire\Admin\BillingSubscriptions;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\ModuleEvents;
use App\Livewire\Admin\ModulesIndex;
use App\Livewire\Admin\ModuleShow;
use App\Livewire\Admin\OpportunitiesIndex;
use App\Livewire\Admin\PlanForm;
use App\Livewire\Admin\Plans;
use App\Livewire\Admin\ProspectForm;
use App\Livewire\Admin\ProspectsIndex;
use App\Livewire\Admin\TenantHealth;
use App\Livewire\Admin\TenantForm;
use App\Livewire\Admin\TenantModules;
use App\Livewire\Admin\TenantSettings;
use App\Livewire\Admin\TenantShow;
use App\Livewire\Admin\TenantSubscription;
use App\Livewire\Admin\TenantUsers;
use App\Livewire\Admin\Tenants;
use App\Livewire\System\ComingSoon;
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
        Route::get('/modules', ModulesIndex::class)->name('system.modules');
        Route::redirect('/modules/create', '/admin/modules');
        Route::get('/modules/{moduleKey}', ModuleShow::class)
            ->where('moduleKey', '[A-Za-z0-9_\\-]+')
            ->name('system.modules.show');
        Route::redirect('/packages', '/admin/modules')->name('system.packages');
        Route::get('/tenants', Tenants::class)->name('system.tenants');
        Route::get('/tenants/health', TenantHealth::class)->name('system.tenants.health');
        Route::get('/tenants/create', TenantForm::class)->name('system.tenants.create');
        Route::get('/tenants/modules', TenantModules::class)->name('system.tenant.modules');
        Route::get('/tenants/{tenant}', TenantShow::class)->name('system.tenants.show');
        Route::get('/tenants/{tenant}/users', TenantUsers::class)->name('system.tenants.users');
        Route::get('/tenants/{tenant}/edit', TenantForm::class)->name('system.tenants.edit');
        Route::get('/tenants/{tenant}/settings', TenantSettings::class)->name('system.tenants.settings');
        Route::get('/tenants/{tenant}/subscription', TenantSubscription::class)->name('system.tenants.subscription');
        // Clients = Companies (same tenants list)
        Route::redirect('/clients', '/admin/tenants')->name('system.clients');
        Route::get('/prospects', ProspectsIndex::class)->name('system.prospects');
        Route::get('/prospects/create', ProspectForm::class)->name('system.prospects.create');
        Route::get('/prospects/{platformProspect}/edit', ProspectForm::class)->name('system.prospects.edit');
        Route::get('/opportunities', OpportunitiesIndex::class)->name('system.opportunities');
        Route::get('/activities', ActivitiesIndex::class)->name('system.activities');
        // Legacy CRM workspace URLs
        Route::redirect('/crm', '/admin/prospects');
        Route::redirect('/crm/prospects', '/admin/prospects');
        Route::redirect('/crm/opportunities', '/admin/opportunities');
        Route::redirect('/crm/clients', '/admin/tenants');
        Route::redirect('/crm/activities', '/admin/activities');
        Route::get('/users', ComingSoon::class)->name('system.users');
        Route::get('/roles', ComingSoon::class)->name('system.roles');
        Route::redirect('/billing', '/admin/invoices')->name('system.billing');
        Route::get('/invoices', BillingSubscriptions::class)->name('system.invoices');
        Route::get('/payments', BillingPayments::class)->name('system.payments');
        Route::get('/plans', Plans::class)->name('system.plans');
        Route::get('/plans/create', PlanForm::class)->name('system.plans.create');
        Route::get('/plans/{plan}/edit', PlanForm::class)->name('system.plans.edit');
        Route::get('/module-events', ModuleEvents::class)->name('system.module.events');
        Route::get('/module-events/export', ModuleEventsExportController::class)->name('system.module.events.export');
    });
});
