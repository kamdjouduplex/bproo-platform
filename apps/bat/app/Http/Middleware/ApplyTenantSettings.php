<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class ApplyTenantSettings
{
    public function handle(Request $request, Closure $next)
    {
        /** @var Tenant|null $tenant */
        $tenant = app(TenantManager::class)->tenant();

        $locale = session('locale');
        if (!$locale && $tenant) {
            $locale = (string) $tenant->getSetting('locale', config('inovcom.default_locale', 'fr'));
        }
        if (!$locale) {
            $locale = config('app.locale', 'fr');
        }
        $locale = in_array($locale, ['fr', 'en'], true) ? $locale : 'fr';
        session()->put('locale', $locale);
        config(['app.locale' => $locale]);
        app()->setLocale($locale);

        if ($tenant) {
            $currency = (string) $tenant->getSetting('currency', config('inovcom.default_currency', 'XOF'));
            $timezone = (string) $tenant->getSetting('timezone', config('app.timezone', 'UTC'));
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
            config(['inovcom.currency' => $currency]);
            View::share('tenantCurrency', $currency);
        }

        return $next($request);
    }
}
