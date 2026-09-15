<?php

namespace Bproo\Platform\Desktop\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Align local SQLite subscription window with signed licence offline expiry.
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
    }
}
