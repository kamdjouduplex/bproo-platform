<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Desktop update channel (Phase 2)
    |--------------------------------------------------------------------------
    |
    | Additive to SaaS billing and Phase 1 licence. Does not alter
    | SubscriptionService or cloud tenant middleware.
    |
    */

    'signing_key' => env('DESKTOP_UPDATE_SIGNING_KEY', env('DESKTOP_LICENCE_SIGNING_KEY', env('APP_KEY'))),

    /** Filesystem disk for uploaded packages (see filesystems.disks.desktop_updates). */
    'disk' => env('DESKTOP_UPDATE_DISK', 'desktop_updates'),

    /** How long a signed check-manifest stays valid (hours). */
    'manifest_ttl_hours' => (int) env('DESKTOP_UPDATE_MANIFEST_TTL_HOURS', 24),

    /** Default channel when desktop does not specify one. */
    'default_channel' => env('DESKTOP_UPDATE_DEFAULT_CHANNEL', 'stable'),

];
