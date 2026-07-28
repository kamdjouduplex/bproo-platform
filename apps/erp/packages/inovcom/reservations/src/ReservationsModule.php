<?php

namespace InovCom\Reservations;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class ReservationsModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'reservations.view', 'name' => 'Voir les réservations', 'description' => 'Consulter les réservations produits'],
            ['key' => 'reservations.create', 'name' => 'Créer des réservations', 'description' => 'Créer des réservations client'],
            ['key' => 'reservations.update', 'name' => 'Modifier les réservations', 'description' => 'Ajouter des lignes sur réservation active'],
            ['key' => 'reservations.cancel', 'name' => 'Annuler des réservations', 'description' => 'Annulation partielle ou totale'],
            ['key' => 'reservations.convert', 'name' => 'Convertir en devis', 'description' => 'Transformer une réservation en devis'],
        ];
    }

    public function install(object $tenant): void
    {
        $existingKeys = Permission::on('tenant')->pluck('id', 'key')->keys()->flip();
        foreach (self::defaultPermissions() as $p) {
            if (!$existingKeys->has($p['key'])) {
                Permission::on('tenant')->create([
                    'key' => $p['key'],
                    'name' => $p['name'],
                    'description' => $p['description'] ?? null,
                ]);
            }
        }
    }

    public function uninstall(object $tenant): void
    {
        //
    }
}
