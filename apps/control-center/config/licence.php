<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Desktop / offline licence (Phase 1)
    |--------------------------------------------------------------------------
    |
    | Additive control plane for stand-alone installs. Does not alter SaaS
    | SubscriptionService, grace_ends_at, or EnsureTenantActive.
    |
    */

    'signing_key' => env('DESKTOP_LICENCE_SIGNING_KEY', env('APP_KEY')),

    /** Recommended reconnect interval when online (days). */
    'heartbeat_interval_days' => (int) env('DESKTOP_LICENCE_HEARTBEAT_DAYS', 7),

    /**
     * After last successful heartbeat (or activation), desktop may keep
     * read-write offline access for this many days, then should go read-only.
     */
    'offline_grace_days' => (int) env('DESKTOP_LICENCE_OFFLINE_GRACE_DAYS', 21),

];
