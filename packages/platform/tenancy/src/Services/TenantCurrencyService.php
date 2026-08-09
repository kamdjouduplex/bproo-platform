<?php

namespace App\Services;

use App\Models\PlatformCurrency;
use App\Models\Tenant;
use App\Models\TenantCurrency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared multi-currency for all product apps + Control Center.
 * Catalog lives in landlord DB; tenants enable 1..N currencies.
 */
class TenantCurrencyService
{
    public static function label(?string $code): string
    {
        $c = strtoupper(trim((string) $code));

        return match ($c) {
            'XOF', 'XAF' => 'FCFA',
            'CDF' => 'FC',
            'USD' => 'USD',
            'EUR' => 'EUR',
            'GNF' => 'GNF',
            '' => '',
            default => $c,
        };
    }

    public function catalog(bool $activeOnly = true): Collection
    {
        if (! Schema::hasTable('platform_currencies')) {
            return collect();
        }

        $q = PlatformCurrency::query()->ordered();
        if ($activeOnly) {
            $q->active();
        }

        return $q->get();
    }

    /**
     * Enabled currencies for a tenant (falls back to single setting currency).
     *
     * @return Collection<int, array{code:string,name:string,symbol:?string,is_default:bool,exchange_rate_to_default:float}>
     */
    public function enabledFor(Tenant $tenant): Collection
    {
        if (Schema::hasTable('tenant_currencies')) {
            $rows = TenantCurrency::query()
                ->with('currency')
                ->where('tenant_id', $tenant->id)
                ->where('is_enabled', true)
                ->orderByDesc('is_default')
                ->orderBy('currency_code')
                ->get();

            if ($rows->isNotEmpty()) {
                return $rows->map(fn (TenantCurrency $row) => [
                    'code' => $row->currency_code,
                    'name' => $row->currency?->name ?? $row->currency_code,
                    'symbol' => $row->currency?->symbol,
                    'is_default' => (bool) $row->is_default,
                    'exchange_rate_to_default' => (float) $row->exchange_rate_to_default,
                ])->values();
            }
        }

        $code = strtoupper((string) $tenant->getSetting('currency', config('inovcom.default_currency', 'XOF')));

        return collect([[
            'code' => $code,
            'name' => $code,
            'symbol' => null,
            'is_default' => true,
            'exchange_rate_to_default' => 1.0,
        ]]);
    }

    public function defaultCode(Tenant $tenant): string
    {
        $enabled = $this->enabledFor($tenant);
        $default = $enabled->firstWhere('is_default', true) ?? $enabled->first();

        return strtoupper((string) ($default['code'] ?? config('inovcom.default_currency', 'XOF')));
    }

    public function rateToDefault(Tenant $tenant, string $currencyCode): float
    {
        $code = strtoupper($currencyCode);
        $row = $this->enabledFor($tenant)->firstWhere('code', $code);
        if (! $row) {
            return 1.0;
        }

        $rate = (float) ($row['exchange_rate_to_default'] ?? 1);

        return $rate > 0 ? $rate : 1.0;
    }

    /**
     * Convert an amount from one enabled currency to another via the default.
     */
    public function convert(Tenant $tenant, float $amount, string $fromCode, string $toCode): float
    {
        $from = strtoupper($fromCode);
        $to = strtoupper($toCode);
        if ($from === $to) {
            return round($amount, 2);
        }

        $inDefault = $amount * $this->rateToDefault($tenant, $from);
        $toRate = $this->rateToDefault($tenant, $to);

        return round($toRate > 0 ? ($inDefault / $toRate) : $inDefault, 2);
    }

    /**
     * Replace enabled currencies for a tenant.
     *
     * @param  list<array{code:string,is_default?:bool,exchange_rate_to_default?:float|string}>  $rows
     */
    public function syncEnabled(Tenant $tenant, array $rows): void
    {
        if (! Schema::hasTable('tenant_currencies') || ! Schema::hasTable('platform_currencies')) {
            // Legacy: only settings key
            $default = collect($rows)->firstWhere('is_default', true) ?? ($rows[0] ?? null);
            if ($default) {
                $tenant->setSetting('currency', strtoupper((string) $default['code']));
            }

            return;
        }

        $normalized = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($code === '' || ! PlatformCurrency::where('code', $code)->where('is_active', true)->exists()) {
                continue;
            }
            $normalized[$code] = [
                'code' => $code,
                'is_default' => (bool) ($row['is_default'] ?? false),
                'exchange_rate_to_default' => max(0.000001, (float) ($row['exchange_rate_to_default'] ?? 1)),
            ];
        }

        if ($normalized === []) {
            throw new \InvalidArgumentException('Sélectionnez au moins une devise active.');
        }

        $defaults = collect($normalized)->where('is_default', true);
        if ($defaults->count() !== 1) {
            // Force first as default
            $first = array_key_first($normalized);
            foreach ($normalized as $c => $_) {
                $normalized[$c]['is_default'] = ($c === $first);
            }
            $normalized[$first]['exchange_rate_to_default'] = 1;
        } else {
            $defaultCode = $defaults->keys()->first();
            $normalized[$defaultCode]['exchange_rate_to_default'] = 1;
        }

        DB::transaction(function () use ($tenant, $normalized) {
            TenantCurrency::where('tenant_id', $tenant->id)->delete();
            foreach ($normalized as $row) {
                TenantCurrency::create([
                    'tenant_id' => $tenant->id,
                    'currency_code' => $row['code'],
                    'is_default' => $row['is_default'],
                    'is_enabled' => true,
                    'exchange_rate_to_default' => $row['exchange_rate_to_default'],
                ]);
            }

            $default = collect($normalized)->firstWhere('is_default', true);
            $tenant->setSetting('currency', $default['code']);
        });
    }

    /**
     * Ensure tenant has at least its settings currency enabled (after provision / migrate).
     */
    public function ensureDefaultRow(Tenant $tenant): void
    {
        if (! Schema::hasTable('tenant_currencies') || ! Schema::hasTable('platform_currencies')) {
            return;
        }

        if (TenantCurrency::where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $code = strtoupper((string) $tenant->getSetting('currency', config('inovcom.default_currency', 'XOF')));
        if (! PlatformCurrency::where('code', $code)->exists()) {
            $code = 'XOF';
        }

        $this->syncEnabled($tenant, [[
            'code' => $code,
            'is_default' => true,
            'exchange_rate_to_default' => 1,
        ]]);
    }
}
