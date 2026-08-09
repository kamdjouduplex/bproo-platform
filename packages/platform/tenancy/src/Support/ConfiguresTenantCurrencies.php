<?php

namespace App\Support;

use App\Models\Tenant;
use App\Services\TenantCurrencyService;

/**
 * Shared multi-currency editor for Configuration (tenant apps) and Control Center TenantSettings.
 * No FX conversion: each currency is enabled independently and accounted separately.
 */
trait ConfiguresTenantCurrencies
{
    /** @var list<string> */
    public array $enabled_currency_codes = [];

    public string $default_currency_code = 'XOF';

    protected function loadTenantCurrencies(Tenant $tenant): void
    {
        $svc = app(TenantCurrencyService::class);
        $svc->ensureDefaultRow($tenant);

        $enabled = $svc->enabledFor($tenant);
        $this->enabled_currency_codes = $enabled->pluck('code')->values()->all();
        $this->default_currency_code = $svc->defaultCode($tenant);

        if (property_exists($this, 'currency')) {
            $this->currency = $this->default_currency_code;
        }
    }

    public function updatedEnabledCurrencyCodes(): void
    {
        $codes = collect($this->enabled_currency_codes)
            ->map(fn ($c) => strtoupper(trim((string) $c)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($codes === []) {
            $codes = [$this->default_currency_code ?: 'XOF'];
        }

        $this->enabled_currency_codes = $codes;

        if (! in_array(strtoupper($this->default_currency_code), $codes, true)) {
            $this->default_currency_code = $codes[0];
        }

        if (property_exists($this, 'currency')) {
            $this->currency = $this->default_currency_code;
        }
    }

    public function updatedDefaultCurrencyCode(?string $value = null): void
    {
        $code = strtoupper(trim((string) ($value ?: $this->default_currency_code)));
        if (! in_array($code, array_map('strtoupper', $this->enabled_currency_codes), true)) {
            $this->enabled_currency_codes[] = $code;
            $this->enabled_currency_codes = array_values(array_unique($this->enabled_currency_codes));
        }
        $this->default_currency_code = $code;
        if (property_exists($this, 'currency')) {
            $this->currency = $code;
        }
    }

    /**
     * @return list<array{code:string,is_default:bool}>
     */
    protected function currencyRowsForSync(): array
    {
        $rows = [];
        foreach ($this->enabled_currency_codes as $code) {
            $code = strtoupper((string) $code);
            if ($code === '') {
                continue;
            }
            $rows[] = [
                'code' => $code,
                'is_default' => $code === strtoupper($this->default_currency_code),
            ];
        }

        return $rows;
    }

    protected function persistTenantCurrencies(Tenant $tenant): void
    {
        app(TenantCurrencyService::class)->syncEnabled($tenant, $this->currencyRowsForSync());
        $this->loadTenantCurrencies($tenant->fresh());
    }

    protected function currencyCatalogForView()
    {
        return app(TenantCurrencyService::class)->catalog(true);
    }
}
