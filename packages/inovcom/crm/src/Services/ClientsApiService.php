<?php

namespace InovCom\Clients\Services;

use InovCom\Clients\Models\Client;
use InovCom\Kernel\Contracts\ClientsApi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientsApiService implements ClientsApi
{
    public function findClient(int $id): ?object
    {
        return Client::on('tenant')->find($id);
    }

    public function findClientByCode(string $code): ?object
    {
        return Client::on('tenant')
            ->where('code', $code)
            ->first();
    }

    public function getCreditLimit(int $clientId): float
    {
        $client = $this->findClient($clientId);
        return $client ? (float) $client->credit_limit : 0.0;
    }

    public function getCurrentBalance(int $clientId): float
    {
        // Source de vérité : encours réel calculé depuis les dettes ouvertes.
        // Repli sur le solde stocké si le module Dettes n'est pas installé.
        if (Schema::connection('tenant')->hasTable('debts')) {
            $outstanding = DB::connection('tenant')
                ->table('debts')
                ->where('client_id', $clientId)
                ->where('balance', '>', 0)
                ->sum('balance');

            return (float) $outstanding;
        }

        $client = $this->findClient($clientId);
        return $client ? (float) $client->current_balance : 0.0;
    }

    public function canMakePurchase(int $clientId, float $amount): bool
    {
        $client = $this->findClient($clientId);
        
        if (!$client || !$client->is_active) {
            return false;
        }

        // Client bloqué (manuel ou automatique) : aucune vente à crédit autorisée.
        if (!empty($client->is_blocked)) {
            return false;
        }

        $creditLimit = $this->getCreditLimit($clientId);
        $currentBalance = $this->getCurrentBalance($clientId);
        
        // If no credit limit, allow purchase
        if ($creditLimit <= 0) {
            return true;
        }

        // Check if purchase would exceed credit limit
        return ($currentBalance + $amount) <= $creditLimit;
    }

    public function getActiveClients(): Collection
    {
        return Client::on('tenant')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function clientExists(int $id): bool
    {
        return Client::on('tenant')
            ->where('id', $id)
            ->exists();
    }
}
