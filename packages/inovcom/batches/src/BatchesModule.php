<?php

namespace InovCom\Batches;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

class BatchesModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'batches.view', 'name' => 'Voir les lots', 'description' => 'Consulter lots et dates de péremption'],
            ['key' => 'batches.create', 'name' => 'Créer / réceptionner lots', 'description' => 'Enregistrer des lots à la réception'],
            ['key' => 'batches.update', 'name' => 'Modifier les lots', 'description' => 'Corriger la date de péremption d’un lot déjà enregistré'],
            ['key' => 'batches.write_off', 'name' => 'Sortir lots périmés', 'description' => 'Détruire / sortir du stock les lots périmés'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions();
    }

    public function uninstall(object $tenant): void
    {
        // Data kept for audit; optional cleanup
    }

    public static function syncPermissions(): void
    {
        foreach (self::defaultPermissions() as $p) {
            Permission::on('tenant')->updateOrCreate(
                ['key' => $p['key']],
                ['name' => $p['name'], 'description' => $p['description'] ?? null]
            );
        }

        $admin = Role::on('tenant')->where('name', 'admin')->first();
        if ($admin) {
            $ids = Permission::on('tenant')->where('key', 'like', 'batches.%')->pluck('id');
            $admin->permissions()->syncWithoutDetaching($ids);
        }
    }
}
