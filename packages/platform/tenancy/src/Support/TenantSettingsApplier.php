<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\View;

/**
 * Applique locale / devise / fuseau d'un tenant au runtime PHP + config Laravel.
 * Isolé par requête : chaque tenant a ses propres settings en base centrale.
 */
class TenantSettingsApplier
{
    public static function apply(Tenant $tenant): void
    {
        $locale = (string) $tenant->getSetting('locale', config('inovcom.default_locale', 'fr'));
        $currency = (string) $tenant->getSetting('currency', config('inovcom.default_currency', 'XOF'));
        $timezone = (string) $tenant->getSetting(
            'timezone',
            config('inovcom.default_timezone', config('app.timezone', 'UTC'))
        );

        if ($timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = config('app.timezone', 'UTC') ?: 'UTC';
        }

        config([
            'app.locale' => $locale,
            'app.timezone' => $timezone,
            'inovcom.currency' => $currency,
        ]);

        app()->setLocale($locale);
        date_default_timezone_set($timezone);

        // Compat éventuelle avec du code qui lit app('tenant').
        app()->instance('tenant', $tenant);

        View::share('tenantCurrency', $currency);
        View::share('tenantTimezone', $timezone);
    }
}
