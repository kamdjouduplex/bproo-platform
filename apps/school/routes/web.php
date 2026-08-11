<?php

use App\Http\Controllers\Tenant\AuthController as TenantAuthController;
use App\Livewire\Tenant\Dashboard as TenantDashboard;
use App\Livewire\Tenant\SubscriptionStatus as TenantSubscriptionStatus;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Bproo Pharma (tenant app only)
|--------------------------------------------------------------------------
|
| Admin / Control Center lives in apps/control-center (/admin).
| This app only serves the marketing site and tenant /app space.
|
*/

Route::get('/', function () {
    return view('landing');
})->name('landing');

Route::get('/reservez-demo', function () {
    return view('demo');
})->name('demo');

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
