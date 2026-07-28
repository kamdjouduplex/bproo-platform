<?php

namespace InovCom\Debts;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class DebtsModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'debts.view', 'name' => 'Voir les dettes', 'description' => 'Accès liste et détail des dettes'],
            ['key' => 'debts.create', 'name' => 'Créer des dettes', 'description' => 'Créer de nouvelles dettes'],
            ['key' => 'debts.update', 'name' => 'Modifier les dettes', 'description' => 'Modifier les dettes existantes'],
            ['key' => 'debts.delete', 'name' => 'Supprimer des dettes', 'description' => 'Supprimer des dettes'],
            ['key' => 'debts.receive_payment', 'name' => 'Encaisser des paiements', 'description' => 'Enregistrer des paiements sur les dettes'],
            ['key' => 'debts.manage_schedule', 'name' => 'Gérer les échéanciers', 'description' => 'Créer et gérer les échéances de remboursement'],
            ['key' => 'debts.validate', 'name' => 'Valider des dettes', 'description' => 'Valider les dettes avant encaissement'],
        ];
    }

    public function install(object $tenant): void
    {
        foreach (self::defaultPermissions() as $p) {
            Permission::on('tenant')->firstOrCreate(
                ['key' => $p['key']],
                ['name' => $p['name'], 'description' => $p['description'] ?? null]
            );
        }
    }

    public function uninstall(object $tenant): void
    {
        // Optional: soft cleanup
    }
}
