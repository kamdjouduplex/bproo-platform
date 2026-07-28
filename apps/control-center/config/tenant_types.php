<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant types (business profiles)
    |--------------------------------------------------------------------------
    | Each tenant has one type. Type influences which modules are suggested
    | and which default modules are enabled at provisioning. Module variants
    | (e.g. sales vs sales-restaurant) are chosen per tenant; at most one
    | module per "module_family" can be enabled per tenant.
    */
    'types' => [
        'retail' => [
            'label' => 'Boutique / Commerce',
            'description' => 'Vente au détail, POS classique',
        ],
        'pharmacy' => [
            'label' => 'Pharmacie',
            'description' => 'Vente de médicaments, lots, prescriptions',
        ],
        'bakery' => [
            'label' => 'Boulangerie',
            'description' => 'Vente pain et pâtisserie',
        ],
        'restaurant' => [
            'label' => 'Restaurant',
            'description' => 'Tables, commandes, cuisine',
        ],
        'other' => [
            'label' => 'Autre',
            'description' => 'Autre type d\'activité',
        ],
    ],

    'default' => 'retail',
];
