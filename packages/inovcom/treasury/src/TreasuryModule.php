<?php

namespace InovCom\Treasury;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

class TreasuryModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'treasury.view', 'name' => 'Voir la prévision de trésorerie', 'description' => 'Consulter l\'échéancier des dépenses'],
            ['key' => 'treasury.create', 'name' => 'Saisir des dépenses prévisionnelles', 'description' => 'Ajouter des engagements futurs'],
            ['key' => 'treasury.update', 'name' => 'Modifier les prévisions', 'description' => 'Modifier ou marquer payées les échéances'],
            ['key' => 'treasury.delete', 'name' => 'Annuler une prévision', 'description' => 'Annuler un engagement prévisionnel'],
            ['key' => 'treasury.manage_settings', 'name' => 'Paramétrer les alertes de trésorerie', 'description' => 'Seuils d\'urgence et délais de notification'],
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

        $admin = Role::on('tenant')->where('name', 'admin')->first();
        if ($admin) {
            $admin->permissions()->syncWithoutDetaching(
                Permission::on('tenant')
                    ->whereIn('key', array_column(self::defaultPermissions(), 'key'))
                    ->pluck('id')
                    ->all()
            );
        }
    }

    public function uninstall(object $tenant): void
    {
        //
    }
}
