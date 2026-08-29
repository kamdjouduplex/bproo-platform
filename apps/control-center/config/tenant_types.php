<?php

return [
    /*
    |--------------------------------------------------------------------------
    | This host's product key (erp|pharma|pressing|bat|control-center)
    |--------------------------------------------------------------------------
    | When it matches tenant_types.types.{type}.app_key, provisioning runs locally.
    | Otherwise Control Center (etc.) delegates to PRODUCT_*_URL.
    */
    'local_app_key' => env('APP_PRODUCT_KEY'),

    /*
    | Shared secret for POST /internal/tenants/provision (same value on all apps).
    */
    'provision_secret' => env('TENANT_PROVISION_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Product apps (tenant "type")
    |--------------------------------------------------------------------------
    | Each company belongs to one product app. That choice controls which
    | application users log into, which modules are suggested/enabled, and
    | whether multi-store setup applies.
    |
    | Add a new vertical here when you ship a new host app — then create
    | tenants with that type from Control Center.
    */
    'types' => [
        'erp' => [
            'label' => 'ERP / POS',
            'description' => 'Boutique, stock, ventes, facturation — application ERP.',
            'icon' => 'shopping-cart',
            'app_key' => 'erp',
            'login_path' => '/app/login',
            'base_url' => env('PRODUCT_ERP_URL', 'http://127.0.0.1:8000'),
            'supports_multi_store' => true,
            'db_prefix' => 'erp',
        ],
        'pharma' => [
            'label' => 'Bproo Pharma',
            'description' => 'Pharmacie — POS, lots, ordonnances — application Pharma.',
            'icon' => 'beaker',
            'app_key' => 'pharma',
            'login_path' => '/app/login',
            'base_url' => env('PRODUCT_PHARMA_URL', 'http://127.0.0.1:8003'),
            'supports_multi_store' => true,
            'db_prefix' => 'pharma',
        ],
        'school' => [
            'label' => 'Bproo School',
            'description' => 'École — années académiques, étudiants, inscriptions, notes, examens, paiements, cartes ID — application School.',
            'icon' => 'academic-cap',
            'app_key' => 'school',
            'login_path' => '/app/login',
            'base_url' => env('PRODUCT_SCHOOL_URL', 'http://127.0.0.1:8000'),
            'supports_multi_store' => true,
            'db_prefix' => 'school',
        ],
        'pressing' => [
            'label' => 'Pressing',
            'description' => 'Réception, production, livraisons — application Pressing.',
            'icon' => 'paint-brush',
            'app_key' => 'pressing',
            'login_path' => '/app/login',
            'base_url' => env('PRODUCT_PRESSING_URL', 'http://127.0.0.1:8001'),
            'supports_multi_store' => false,
            'db_prefix' => 'pressing',
        ],
        'bat' => [
            'label' => 'BAT / BTP',
            'description' => 'Chantiers, devis, maintenance — application BAT.',
            'icon' => 'building-office',
            'app_key' => 'bat',
            'login_path' => '/app/login',
            'base_url' => env('PRODUCT_BAT_URL', 'http://127.0.0.1:8002'),
            'supports_multi_store' => false,
            'db_prefix' => 'bat',
        ],
    ],

    'default' => 'erp',

    /*
    |--------------------------------------------------------------------------
    | Legacy activity labels → product app
    |--------------------------------------------------------------------------
    | Older installs used retail/pharmacy/… as "type". Map them to apps.
    */
    'legacy_aliases' => [
        'retail' => 'erp',
        'pharmacy' => 'pharma',
        'bakery' => 'erp',
        'restaurant' => 'erp',
        'other' => 'erp',
    ],
];
