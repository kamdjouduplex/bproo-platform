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
    // Desktop / offline Officine: compact welcome — keep SaaS marketing landing elsewhere
    if (filter_var(env('DESKTOP_RUNTIME', false), FILTER_VALIDATE_BOOLEAN)) {
        $messages = [
            'Votre officine, prête pour la journée.',
            'Chaque ordonnance compte — on s’occupe du reste.',
            'Stock maîtrisé, clients bien servis.',
            'La pharmacie du quartier, pilotée simplement.',
            'Encaissement fluide, esprit libre pour vos patients.',
            'Un poste local fiable, même sans Internet.',
            'Bienvenue — votre équipe peut commencer.',
            'Moins de paperasse, plus de soin.',
        ];

        $tenantCode = null;
        $statePath = storage_path('app/desktop/state.json');
        if (is_file($statePath)) {
            $state = json_decode((string) file_get_contents($statePath), true);
            $tenantCode = is_array($state)
                ? ($state['licence']['tenant_code'] ?? null)
                : null;
        }
        if (! is_string($tenantCode) || $tenantCode === '') {
            try {
                $tenantCode = \App\Models\Tenant::query()->orderBy('id')->value('code');
            } catch (\Throwable) {
                $tenantCode = null;
            }
        }
        $tenantCode = is_string($tenantCode) && $tenantCode !== '' ? $tenantCode : 'pharma';

        $shopLabel = 'Bproo Pharma Desktop';
        try {
            $tenant = \App\Models\Tenant::query()->where('code', $tenantCode)->first();
            if ($tenant) {
                $shopLabel = $tenant->getSetting('shop_name', $tenant->name) ?: $shopLabel;
            }
        } catch (\Throwable) {
            // SQLite not ready yet — keep default label
        }

        return view('desktop.home', [
            'appName' => config('app.name', 'Bproo Pharma'),
            'message' => $messages[array_rand($messages)],
            'loginUrl' => url('/app/login?tenant='.urlencode($tenantCode)),
            'heroImage' => '/images/login-pharmacy-hero.png?v=20260815',
            'shopLabel' => $shopLabel,
            'appVersion' => env('DESKTOP_APP_VERSION', env('APP_VERSION')),
        ]);
    }

    return view('landing');
})->name('landing');

Route::get('/reservez-demo', function () {
    return view('demo');
})->name('demo');

Route::prefix('app')->middleware(['tenant', 'tenant.active', 'desktop.licence', 'tenant.store'])->group(function () {
    Route::get('/login', [TenantAuthController::class, 'showLogin'])->name('tenant.login');
    Route::post('/login', [TenantAuthController::class, 'login'])->name('tenant.login.submit');
    Route::post('/logout', [TenantAuthController::class, 'logout'])->name('tenant.logout');

    Route::get('/subscription', TenantSubscriptionStatus::class)->name('tenant.subscription');
    Route::get('/licence', function () {
        $guard = app(\Bproo\Platform\Desktop\Services\LicenceGuard::class);
        $result = $guard->evaluate();

        return view('desktop::licence-status', [
            'result' => $result,
        ]);
    })->name('desktop.licence');

    Route::middleware('auth:tenant')->group(function () {
        Route::get('/', TenantDashboard::class)->name('tenant.dashboard');
        /* Items and Configuration routes are registered by inovcom/items and inovcom/configuration packages */
    });
});
