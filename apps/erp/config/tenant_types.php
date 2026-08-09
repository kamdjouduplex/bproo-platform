<?php

return [
    'local_app_key' => env('APP_PRODUCT_KEY', 'erp'),
    'provision_secret' => env('TENANT_PROVISION_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Product apps (tenant "type")
    |--------------------------------------------------------------------------
    | Each company belongs to one product app. That choice controls which
    | application users log into, which modules are suggested/enabled, and
    | whether multi-store setup applies.
    */
    'types' => [
        'erp' => [
            'label' => 'ERP / POS',
            'description' => 'Boutique, stock, ventes, facturation — application ERP.',
            'app_key' => 'erp',
            'login_path' => '/app/login',
            'base_url' => env('PRODUCT_ERP_URL', 'http://127.0.0.1:8000'),
            'supports_multi_store' => true,
            'db_prefix' => 'erp',
        ],
        'pharma' => [
            'label' => 'Bproo Pharma',
            'description' => 'Pharmacie — POS, lots, ordonnances — application Pharma.',
            'app_key' => 'pharma',
            'login_path' => '/app/login',
            'base_url' => env('PRODUCT_PHARMA_URL', 'http://127.0.0.1:8003'),
            'supports_multi_store' => true,
            'db_prefix' => 'pharma',
        ],
        'pressing' => [
            'label' => 'Pressing',
            'description' => 'Réception, production, livraisons — application Pressing.',
            'app_key' => 'pressing',
            'login_path' => '/app/login',
            'base_url' => env('PRODUCT_PRESSING_URL', 'http://127.0.0.1:8001'),
            'supports_multi_store' => false,
            'db_prefix' => 'pressing',
        ],
        'bat' => [
            'label' => 'BAT / BTP',
            'description' => 'Chantiers, devis, maintenance — application BAT.',
            'app_key' => 'bat',
            'login_path' => '/app/login',
            'base_url' => env('PRODUCT_BAT_URL', 'http://127.0.0.1:8002'),
            'supports_multi_store' => false,
            'db_prefix' => 'bat',
        ],
    ],

    'default' => 'erp',

    'legacy_aliases' => [
        'retail' => 'erp',
        'pharmacy' => 'pharma',
        'bakery' => 'erp',
        'restaurant' => 'erp',
        'other' => 'erp',
    ],
];
