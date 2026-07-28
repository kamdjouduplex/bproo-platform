<?php

namespace InovCom\Prescriptions;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class PrescriptionsModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'prescriptions.view', 'name' => 'Voir les ordonnances', 'description' => 'Consulter les ordonnances'],
            ['key' => 'prescriptions.create', 'name' => 'Créer ordonnances', 'description' => 'Saisir des ordonnances'],
            ['key' => 'prescriptions.dispense', 'name' => 'Dispenser', 'description' => 'Dispenser une ordonnance à la vente'],
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
        // Data kept
    }
}
