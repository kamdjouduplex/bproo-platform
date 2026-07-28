<?php

namespace InovCom\Returns;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class ReturnsModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'returns.view', 'name' => 'Voir les retours', 'description' => 'Accès liste et détail des retours clients'],
            ['key' => 'returns.create', 'name' => 'Créer des retours', 'description' => 'Saisir une demande de retour depuis une facture'],
            ['key' => 'returns.request', 'name' => 'Soumettre des retours', 'description' => 'Soumettre une demande de retour pour validation'],
            ['key' => 'returns.approve', 'name' => 'Valider les retours', 'description' => 'Approuver une demande de retour'],
            ['key' => 'returns.reject', 'name' => 'Refuser les retours', 'description' => 'Rejeter une demande de retour'],
            ['key' => 'returns.receive', 'name' => 'Réceptionner les retours', 'description' => 'Marquer la marchandise comme reçue'],
            ['key' => 'returns.inspect', 'name' => 'Contrôler les retours', 'description' => 'Inspecter et réintégrer le stock'],
            ['key' => 'returns.cancel', 'name' => 'Annuler les retours', 'description' => 'Annuler une demande de retour'],
            ['key' => 'returns.admin', 'name' => 'Administrer les retours', 'description' => 'Accès complet au module retours'],

            ['key' => 'credit_notes.view', 'name' => 'Voir les avoirs', 'description' => 'Consulter les avoirs clients'],
            ['key' => 'credit_notes.create', 'name' => 'Créer des avoirs', 'description' => 'Générer un avoir depuis un retour contrôlé'],
            ['key' => 'credit_notes.validate', 'name' => 'Valider les avoirs', 'description' => 'Valider un avoir brouillon'],
            ['key' => 'credit_notes.use', 'name' => 'Utiliser les avoirs', 'description' => 'Imputer un avoir sur une facture'],

            ['key' => 'refunds.view', 'name' => 'Voir les remboursements', 'description' => 'Consulter les remboursements'],
            ['key' => 'refunds.create', 'name' => 'Créer des remboursements', 'description' => 'Émettre un remboursement (caisse / banque)'],
            ['key' => 'refunds.validate', 'name' => 'Valider les remboursements', 'description' => 'Valider et payer un remboursement'],

            ['key' => 'customer_credits.view', 'name' => 'Voir les crédits clients', 'description' => 'Consulter le portefeuille de crédit client'],
            ['key' => 'customer_credits.use', 'name' => 'Utiliser les crédits clients', 'description' => 'Consommer le crédit client'],
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
