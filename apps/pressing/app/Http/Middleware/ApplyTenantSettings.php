<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class ApplyTenantSettings
{
    public function handle(Request $request, Closure $next)
    {
        /** @var Tenant|null $tenant */
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        $locale = (string) config('inovcom.default_locale', config('app.locale', 'fr'));

        if ($tenant) {
            $locale = (string) $tenant->getSetting('locale', $locale);
            $currency = (string) $tenant->getSetting('currency', config('inovcom.default_currency', 'XOF'));
            $timezone = (string) $tenant->getSetting('timezone', config('app.timezone', 'UTC'));

            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
            config(['inovcom.currency' => $currency]);
            View::share('tenantCurrency', $currency);
        } elseif (session()->has('locale')) {
            $locale = (string) session('locale');
        }

        if (! in_array($locale, ['fr', 'en'], true)) {
            $locale = 'fr';
        }

        config(['app.locale' => $locale]);
        app()->setLocale($locale);

        return $next($request);
    }
}
