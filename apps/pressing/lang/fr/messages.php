<?php

return [
    'nav' => [
        'admin' => 'Admin',
        'packages' => 'Packages',
        'modules' => 'Modules',
        'activation' => 'Activation',
        'health' => 'Santé',
        'events' => 'Events',
        'vendors' => 'Vendeurs',
        'logout' => 'Déconnexion',
        'dashboard' => 'Tableau de bord',
    ],
    'actions' => [
        'install' => 'Installer',
        'uninstall' => 'Désinstaller',
        'sync_modules' => 'Synchroniser les modules',
        'select_vendor' => 'Sélectionner un vendeur',
        'search_module' => 'Rechercher un module...',
    ],
    'status' => [
        'active_core' => 'Actif (core)',
        'installed' => 'Installé',
        'not_installed' => 'Non installé',
        'in_progress' => 'En cours…',
    ],
    'packages' => [
        'title' => 'Packages — modules par vendeur',
        'select_vendor_hint' => 'Sélectionnez un vendeur pour installer ou désinstaller des modules.',
        'queue_hint' => 'L’installation et la désinstallation sont exécutées en arrière-plan (file d’attente). Lancez :command pour traiter les jobs.',
    ],
];
