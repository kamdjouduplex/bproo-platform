<?php

use App\Services\TenantCurrencyService;

if (! function_exists('currency_code')) {
    /** Tenant default ISO currency code, or an explicit override. */
    function currency_code(?string $code = null): string
    {
        return TenantCurrencyService::resolveCode($code);
    }
}

if (! function_exists('currency_label')) {
    /**
     * Human label for amounts (USD, EUR, FCFA…).
     * Empty $code → tenant default currency (never hardcodes FCFA when USD is configured).
     */
    function currency_label(?string $code = null): string
    {
        return TenantCurrencyService::displayLabel($code);
    }
}
