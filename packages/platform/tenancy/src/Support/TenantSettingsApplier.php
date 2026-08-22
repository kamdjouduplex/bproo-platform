<?php

namespace App\Support;

use App\Models\Tenant;
use App\Services\TenantCurrencyService;
use Illuminate\Support\Facades\View;

/**
 * Applique locale / devise / fuseau d'un tenant au runtime PHP + config Laravel.
 * Isolé par requête : chaque tenant a ses propres settings en base centrale.
 */
class TenantSettingsApplier
{
    public static function apply(Tenant $tenant): void
    {
        $supported = config('inovcom.supported_locales', ['fr', 'en']);
        if (! is_array($supported) || $supported === []) {
            $supported = ['fr'];
        }

        $enabledRaw = $tenant->getSetting('enabled_locales', $supported);
        if (is_string($enabledRaw)) {
            $decoded = json_decode($enabledRaw, true);
            $enabledRaw = is_array($decoded) ? $decoded : $supported;
        }
        $enabled = is_array($enabledRaw) && $enabledRaw !== [] ? $enabledRaw : $supported;

        $userLocale = null;
        try {
            $user = auth('tenant')->user();
            if ($user && ! empty($user->preferred_locale)) {
                $userLocale = (string) $user->preferred_locale;
            }
        } catch (\Throwable) {
            $userLocale = null;
        }

        $sessionLocale = session('locale');
        $tenantLocale = (string) $tenant->getSetting('locale', config('inovcom.default_locale', 'fr'));

        $locale = (string) ($userLocale ?: $sessionLocale ?: $tenantLocale);
        if (! in_array($locale, $enabled, true)) {
            $locale = in_array($tenantLocale, $enabled, true)
                ? $tenantLocale
                : (string) ($enabled[0] ?? config('inovcom.default_locale', 'fr'));
        }

        try {
            $currency = app(TenantCurrencyService::class)->defaultCode($tenant);
        } catch (\Throwable) {
            $currency = (string) $tenant->getSetting('currency', config('inovcom.default_currency', 'XOF'));
        }
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
        View::share('tenantCurrencyLabel', TenantCurrencyService::displayLabel($currency));
        View::share('tenantTimezone', $timezone);
        View::share('tenantEnabledLocales', $enabled);
    }
}
