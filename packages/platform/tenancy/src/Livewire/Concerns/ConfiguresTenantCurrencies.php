<?php

namespace App\Livewire\Concerns;

use App\Models\Tenant;
use App\Services\TenantCurrencyService;

/**
 * Shared multi-currency editor for Configuration (tenant apps) and Control Center TenantSettings.
 */
trait ConfiguresTenantCurrencies
{
    /** @var list<string> */
    public array $enabled_currency_codes = [];

    public string $default_currency_code = 'XOF';

    /** @var array<string, string> */
    public array $currency_rates = [];

    protected function loadTenantCurrencies(Tenant $tenant): void
    {
        $svc = app(TenantCurrencyService::class);
        $svc->ensureDefaultRow($tenant);

        $enabled = $svc->enabledFor($tenant);
        $this->enabled_currency_codes = $enabled->pluck('code')->values()->all();
        $this->default_currency_code = $svc->defaultCode($tenant);
        $this->currency_rates = $enabled
            ->mapWithKeys(fn (array $row) => [$row['code'] => (string) $row['exchange_rate_to_default']])
            ->all();

        if (property_exists($this, 'currency')) {
            $this->currency = $this->default_currency_code;
        }
    }

    public function toggleCurrencyCode(string $code): void
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return;
        }

        if (in_array($code, $this->enabled_currency_codes, true)) {
            if (count($this->enabled_currency_codes) <= 1) {
                return;
            }
            $this->enabled_currency_codes = array_values(array_filter(
                $this->enabled_currency_codes,
                fn ($c) => $c !== $code
            ));
            unset($this->currency_rates[$code]);
            if ($this->default_currency_code === $code) {
                $this->default_currency_code = $this->enabled_currency_codes[0];
                $this->currency_rates[$this->default_currency_code] = '1';
            }
        } else {
            $this->enabled_currency_codes[] = $code;
            $this->currency_rates[$code] = $code === $this->default_currency_code ? '1' : ($this->currency_rates[$code] ?? '1');
        }
    }

    public function setDefaultCurrencyCode(string $code): void
    {
        $code = strtoupper(trim($code));
        if (! in_array($code, $this->enabled_currency_codes, true)) {
            return;
        }
        $this->default_currency_code = $code;
        $this->currency_rates[$code] = '1';
        if (property_exists($this, 'currency')) {
            $this->currency = $code;
        }
    }

    /**
     * @return list<array{code:string,is_default:bool,exchange_rate_to_default:float}>
     */
    protected function currencyRowsForSync(): array
    {
        $rows = [];
        foreach ($this->enabled_currency_codes as $code) {
            $code = strtoupper((string) $code);
            $rows[] = [
                'code' => $code,
                'is_default' => $code === strtoupper($this->default_currency_code),
                'exchange_rate_to_default' => (float) ($this->currency_rates[$code] ?? 1),
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
