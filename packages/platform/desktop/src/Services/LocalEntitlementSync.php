<?php

namespace Bproo\Platform\Desktop\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Align local SQLite subscription window (and optional module entitlements)
 * with the signed licence claims from Control Center.
 */
class LocalEntitlementSync
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function syncFromClaims(array $claims): void
    {
        if (! class_exists(\App\Models\Tenant::class) || ! class_exists(\App\Models\Subscription::class)) {
            return;
        }

        if (! Schema::hasTable('tenants') || ! Schema::hasTable('subscriptions')) {
            return;
        }

        $code = (string) ($claims['tenant_code'] ?? 'pharma');
        /** @var \App\Models\Tenant|null $tenant */
        $tenant = \App\Models\Tenant::query()->where('code', $code)->first()
            ?? \App\Models\Tenant::query()->orderBy('id')->first();

        if (! $tenant) {
            return;
        }

        $expires = $claims['offline_access_expires_at'] ?? null;
        try {
            $periodEnd = is_string($expires) && $expires !== ''
                ? Carbon::parse($expires)->toDateString()
                : now()->addDays(30)->toDateString();
        } catch (\Throwable) {
            $periodEnd = now()->addDays(30)->toDateString();
        }

        $sub = $tenant->subscriptions()->first();
        if (! $sub) {
            return;
        }

        $sub->fill([
            'status' => \App\Models\Subscription::STATUS_ACTIVE,
            'current_period_end' => $periodEnd,
            'grace_ends_at' => $periodEnd,
            'suspended_at' => null,
            'cancelled_at' => null,
            'suspension_reason' => null,
        ])->save();

        $this->syncModulesFromClaims($tenant, $claims);
    }

    /**
     * Apply Control Center module entitlements to the local install.
     * Modules already present in the build can be toggled without a new update package.
     * New module *code* still requires a desktop update build.
     *
     * @param  array<string, mixed>  $claims
     */
    private function syncModulesFromClaims(\App\Models\Tenant $tenant, array $claims): void
    {
        if (! array_key_exists('modules', $claims) || ! is_array($claims['modules'])) {
            return;
        }

        if (! class_exists(\App\Models\Module::class)
            || ! Schema::hasTable('modules')
            || ! Schema::hasTable('tenant_modules')
        ) {
            return;
        }

        $wanted = collect($claims['modules'])
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->unique()
            ->values()
            ->all();

        /** @var \Illuminate\Support\Collection<string, \App\Models\Module> $catalog */
        $catalog = \App\Models\Module::query()->get()->keyBy('key');
        if ($catalog->isEmpty()) {
            return;
        }

        $previouslyEnabled = $tenant->modules()
            ->wherePivot('enabled', true)
            ->pluck('key')
            ->all();

        $registry = class_exists(\App\Services\ModuleRegistry::class)
            ? app(\App\Services\ModuleRegistry::class)
            : null;

        // Install newly entitled modules first (migrations / lifecycle), then flip pivots.
        foreach ($wanted as $key) {
            if (! $catalog->has($key) || in_array($key, $previouslyEnabled, true)) {
                continue;
            }
            if (! $registry) {
                continue;
            }
            try {
                $registry->install($key, $tenant->fresh());
            } catch (\Throwable) {
                // Best-effort: pivot may still enable UI if migrations already ran at bootstrap.
            }
        }

        $sync = [];
        foreach ($catalog as $key => $module) {
            $sync[$module->id] = [
                'enabled' => in_array($key, $wanted, true),
            ];
        }

        if ($sync !== []) {
            $tenant->modules()->syncWithoutDetaching($sync);
        }

        if ($registry) {
            $registry->clearCache($tenant->fresh());
        }
    }
}
