<?php

namespace InovCom\Clients\Services;

use InovCom\Clients\Models\Client;
use Illuminate\Support\Facades\DB;

/**
 * Génération de code client séquentielle et atomique.
 *
 * Utilise une transaction + verrou (lockForUpdate) sur la dernière ligne afin
 * d'éviter les collisions de code en environnement multi-utilisateurs.
 */
class ClientCodeGenerator
{
    public const PREFIX = 'CLI-';
    public const PAD = 6;

    public function next(): string
    {
        return DB::connection('tenant')->transaction(function () {
            // withTrashed : on tient compte des clients supprimés pour ne pas réutiliser un code.
            $last = Client::withTrashed()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $nextNumber = $last
                ? ((int) preg_replace('/[^0-9]/', '', (string) $last->code)) + 1
                : 1;

            return $this->format($nextNumber);
        });
    }

    /**
     * Aperçu non verrouillé (affichage formulaire avant enregistrement).
     */
    public function preview(): string
    {
        $last = Client::withTrashed()->orderByDesc('id')->first();
        $nextNumber = $last
            ? ((int) preg_replace('/[^0-9]/', '', (string) $last->code)) + 1
            : 1;

        return $this->format($nextNumber);
    }

    private function format(int $number): string
    {
        return self::PREFIX . str_pad((string) $number, self::PAD, '0', STR_PAD_LEFT);
    }
}
