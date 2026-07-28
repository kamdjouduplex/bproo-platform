<?php

namespace InovCom\Clients\Services;

use InovCom\Clients\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Détection et fusion de clients en doublon.
 *
 * Détection par téléphone normalisé, NIU et nom normalisé.
 * Fusion : réaffecte les enregistrements liés vers un client cible puis archive
 * (soft delete) les doublons.
 */
class ClientDuplicateService
{
    /**
     * Tables référençant client_id susceptibles d'être réaffectées lors d'une fusion.
     *
     * @var list<string>
     */
    private const CHILD_TABLES = [
        'contacts',
        'addresses',
        'credit_limits',
        'client_notes',
        'client_documents',
        'client_reminders',
        'debts',
        'sales',
        'invoices',
        'quotations',
        'delivery_notes',
    ];

    /**
     * @return list<array{reason: string, key: string, clients: Collection}>
     */
    public function findGroups(): array
    {
        $clients = Client::query()
            ->select(['id', 'code', 'name', 'phone', 'niu', 'email', 'created_at'])
            ->orderBy('id')
            ->get();

        $byPhone = [];
        $byNiu = [];
        $byName = [];

        foreach ($clients as $client) {
            $phone = preg_replace('/[^0-9]/', '', (string) $client->phone);
            if (strlen($phone) >= 6) {
                $byPhone[$phone][] = $client;
            }

            $niu = strtoupper(trim((string) $client->niu));
            if ($niu !== '') {
                $byNiu[$niu][] = $client;
            }

            $name = strtolower(trim(preg_replace('/\s+/', ' ', (string) $client->name)));
            if ($name !== '') {
                $byName[$name][] = $client;
            }
        }

        $groups = [];
        $this->collectGroups($groups, $byNiu, 'NIU identique');
        $this->collectGroups($groups, $byPhone, 'Téléphone identique');
        $this->collectGroups($groups, $byName, 'Nom identique');

        return $groups;
    }

    /**
     * @param  list<array{reason: string, key: string, clients: Collection}>  $groups
     * @param  array<string, array>  $buckets
     */
    private function collectGroups(array &$groups, array $buckets, string $reason): void
    {
        $seen = $this->signaturesOf($groups);

        foreach ($buckets as $key => $items) {
            if (count($items) < 2) {
                continue;
            }

            $signature = collect($items)->pluck('id')->sort()->implode('-');
            if (in_array($signature, $seen, true)) {
                continue; // évite de répéter un même ensemble déjà détecté autrement
            }
            $seen[] = $signature;

            $groups[] = [
                'reason' => $reason,
                'key' => (string) $key,
                'clients' => collect($items),
            ];
        }
    }

    /**
     * @param  list<array{reason: string, key: string, clients: Collection}>  $groups
     * @return list<string>
     */
    private function signaturesOf(array $groups): array
    {
        return array_map(
            fn ($g) => $g['clients']->pluck('id')->sort()->implode('-'),
            $groups
        );
    }

    public function countGroups(): int
    {
        return count($this->findGroups());
    }

    /**
     * Fusionne les doublons dans le client cible.
     *
     * @param  list<int>  $sourceIds
     */
    public function merge(int $targetId, array $sourceIds): void
    {
        $sourceIds = array_values(array_filter($sourceIds, fn ($id) => (int) $id !== $targetId));
        if ($sourceIds === []) {
            return;
        }

        DB::connection('tenant')->transaction(function () use ($targetId, $sourceIds) {
            foreach (self::CHILD_TABLES as $table) {
                if (! Schema::connection('tenant')->hasTable($table)) {
                    continue;
                }
                if (! Schema::connection('tenant')->hasColumn($table, 'client_id')) {
                    continue;
                }

                DB::connection('tenant')->table($table)
                    ->whereIn('client_id', $sourceIds)
                    ->update(['client_id' => $targetId]);
            }

            // Archive les doublons (soft delete).
            Client::whereIn('id', $sourceIds)->each(function (Client $client) {
                $client->update(['is_active' => false]);
                $client->delete();
            });
        });
    }
}
