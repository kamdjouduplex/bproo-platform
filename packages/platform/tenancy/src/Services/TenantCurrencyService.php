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
 * No FX conversion: each currency is accounted separately.
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

    /**
     * Resolve ISO code: explicit code, else tenant default / runtime config.
     */
    public static function resolveCode(?string $code = null): string
    {
        $c = strtoupper(trim((string) $code));
        if ($c !== '') {
            return $c;
        }

        try {
            $tenant = app(TenantManager::class)->tenant();
            if ($tenant) {
                return app(self::class)->defaultCode($tenant);
            }
        } catch (\Throwable) {
            // fall through
        }

        $fromConfig = strtoupper(trim((string) config('inovcom.currency', '')));
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        return strtoupper((string) config('inovcom.default_currency', 'XOF'));
    }

    /** Display label for a code, or the tenant default currency when $code is empty. */
    public static function displayLabel(?string $code = null): string
    {
        $resolved = self::resolveCode($code);
        $label = self::label($resolved);

        return $label !== '' ? $label : $resolved;
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
     * @return Collection<int, array{code:string,name:string,symbol:?string,is_default:bool}>
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
                ])->values();
            }
        }

        $code = strtoupper((string) $tenant->getSetting('currency', config('inovcom.default_currency', 'XOF')));

        return collect([[
            'code' => $code,
            'name' => $code,
            'symbol' => null,
            'is_default' => true,
        ]]);
    }

    public function defaultCode(Tenant $tenant): string
    {
        $enabled = $this->enabledFor($tenant);
        $default = $enabled->firstWhere('is_default', true) ?? $enabled->first();

        return strtoupper((string) ($default['code'] ?? config('inovcom.default_currency', 'XOF')));
    }

    /**
     * @param  list<array{code:string,is_default?:bool}>  $rows
     */
    public function syncEnabled(Tenant $tenant, array $rows): void
    {
        if (! Schema::hasTable('tenant_currencies') || ! Schema::hasTable('platform_currencies')) {
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
            ];
        }

        if ($normalized === []) {
            throw new \InvalidArgumentException('Sélectionnez au moins une devise active.');
        }

        $defaults = collect($normalized)->where('is_default', true);
        if ($defaults->count() !== 1) {
            $first = array_key_first($normalized);
            foreach ($normalized as $c => $_) {
                $normalized[$c]['is_default'] = ($c === $first);
            }
        }

        DB::transaction(function () use ($tenant, $normalized) {
            TenantCurrency::where('tenant_id', $tenant->id)->delete();
            foreach ($normalized as $row) {
                TenantCurrency::create([
                    'tenant_id' => $tenant->id,
                    'currency_code' => $row['code'],
                    'is_default' => $row['is_default'],
                    'is_enabled' => true,
                    'exchange_rate_to_default' => 1,
                ]);
            }

            $default = collect($normalized)->firstWhere('is_default', true);
            $tenant->setSetting('currency', $default['code']);
        });
    }

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
        ]]);
    }
}
