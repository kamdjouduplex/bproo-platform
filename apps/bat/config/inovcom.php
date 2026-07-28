<?php

return [
    'default_currency' => 'XOF',
    'supported_locales' => ['fr', 'en'],
    'default_locale' => 'fr',
    'invoice_prefixes' => [
        'invoice'     => 'FAC',
        'proforma'    => 'PRO',
        'credit_note' => 'AV',
    ],
    'invoice_number_padding' => 5,
    'tenant' => [
        'database_prefix' => 'inovcom_tenant_',
        'database_driver' => 'pgsql',
    ],
    'ui' => [
        'primary_color' => '#3fa796',
        'accent_color' => '#1f6f8b',
    ],
    'module_routes' => [],
    'module_lifecycles' => [],
    'marketplace' => [
        'enabled' => env('MODULE_MARKETPLACE_ENABLED', false),
        'url' => env('MODULE_MARKETPLACE_URL', 'https://marketplace.inovcom.com'),
    ],
    'version' => env('APP_VERSION', '1.0.0'),
    'version' => env('APP_VERSION', '1.0.0'),
];
