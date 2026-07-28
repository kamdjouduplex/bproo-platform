<?php

namespace InovCom\Invoicing;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class InvoicingModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'invoicing.view', 'name' => 'Voir les factures', 'description' => 'Accès liste et détail des factures'],
            ['key' => 'invoicing.create', 'name' => 'Créer des factures', 'description' => 'Créer des factures depuis devis ou manuellement'],
            ['key' => 'invoicing.update', 'name' => 'Modifier les factures', 'description' => 'Modifier les factures brouillon'],
            ['key' => 'invoicing.issue', 'name' => 'Émettre des factures', 'description' => 'Émettre les factures brouillon'],
            ['key' => 'invoicing.cancel', 'name' => 'Annuler des factures', 'description' => 'Annuler des factures non payées'],
            ['key' => 'invoicing.delivery.view', 'name' => 'Voir les livraisons', 'description' => 'Consulter les bons de livraison'],
            ['key' => 'invoicing.delivery.create', 'name' => 'Créer des livraisons', 'description' => 'Préparer un bon de livraison (brouillon)'],
            ['key' => 'invoicing.delivery.confirm', 'name' => 'Valider les livraisons', 'description' => 'Confirmer une livraison (sortie de stock)'],
            ['key' => 'invoicing.collection.view', 'name' => 'Voir les fiches de relance', 'description' => 'Consulter les créances clients échues'],
            ['key' => 'invoicing.collection.export', 'name' => 'Exporter les fiches de relance', 'description' => 'Exporter PDF / Excel des relances'],
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
        //
    }
}
