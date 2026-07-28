<?php

return [
    'nav' => [
        'admin' => 'Admin',
        'packages' => 'Packages',
        'modules' => 'Modules',
        'activation' => 'Activation',
        'health' => 'Health',
        'events' => 'Events',
        'vendors' => 'Vendors',
        'logout' => 'Log out',
        'dashboard' => 'Dashboard',
    ],
    'actions' => [
        'install' => 'Install',
        'uninstall' => 'Uninstall',
        'sync_modules' => 'Sync modules',
        'select_vendor' => 'Select a vendor',
        'search_module' => 'Search a module...',
    ],
    'status' => [
        'active_core' => 'Active (core)',
        'installed' => 'Installed',
        'not_installed' => 'Not installed',
        'in_progress' => 'In progress…',
    ],
    'packages' => [
        'title' => 'Packages — modules per vendor',
        'select_vendor_hint' => 'Select a vendor to install or uninstall modules.',
        'queue_hint' => 'Install and uninstall run in the background (queue). Run :command to process jobs.',
    ],
];
