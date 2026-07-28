<?php

namespace InovCom\InvoicePayments;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class InvoicePaymentsModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'invoice_payments.view', 'name' => 'Voir les paiements factures', 'description' => 'Liste des paiements et statuts factures'],
            ['key' => 'invoice_payments.receive', 'name' => 'Encaisser sur factures', 'description' => 'Enregistrer des paiements sur les factures émises'],
            ['key' => 'invoice_payments.cancel', 'name' => 'Annuler un encaissement', 'description' => 'Annuler un encaissement erroné (traçabilité conservée)'],
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
