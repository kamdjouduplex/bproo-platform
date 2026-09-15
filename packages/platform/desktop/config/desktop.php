<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Desktop runtime (Phase 4)
    |--------------------------------------------------------------------------
    |
    | Loaded only when DESKTOP_RUNTIME=1. SaaS hosts leave this off.
    |
    */

    'enabled' => (bool) env('DESKTOP_RUNTIME', false),

    'control_center_url' => rtrim((string) env('CONTROL_CENTER_URL', 'http://127.0.0.1:8010'), '/'),

    'product_key' => env('APP_PRODUCT_KEY', env('DESKTOP_PRODUCT_KEY', 'pharma')),

    'update_channel' => env('DESKTOP_UPDATE_CHANNEL', 'stable'),

    'app_version' => env('DESKTOP_APP_VERSION', env('APP_VERSION', '0.1.0')),

    'state_path' => env('DESKTOP_STATE_PATH', 'desktop/state.json'),

    'staging_path' => env('DESKTOP_STAGING_PATH', 'desktop/staging'),

    'rollback_path' => env('DESKTOP_ROLLBACK_PATH', 'desktop/rollback'),

    'packages_path' => env('DESKTOP_PACKAGES_PATH', 'desktop/packages'),

    'healthcheck' => env('DESKTOP_HEALTHCHECK', 'php artisan about'),

    'sync_batch_size' => (int) env('DESKTOP_SYNC_BATCH_SIZE', 50),

];
