<?php

namespace InovCom\Clients\Services;

use InovCom\Clients\Models\Client;
use InovCom\Kernel\Contracts\ClientsApi;
use Illuminate\Support\Collection;

class ClientsApiService implements ClientsApi
{
    public function findClient(int $id): ?object
    {
        return Client::find($id);
    }

    public function findClientByCode(string $code): ?object
    {
        return Client::where('code', $code)->first();
    }

    public function getCreditLimit(int $clientId): float
    {
        $client = Client::find($clientId);

        return $client ? (float) $client->credit_limit : 0.0;
    }

    public function getCurrentBalance(int $clientId): float
    {
        // Placeholder: balance would come from Facturation module / ledger
        return 0.0;
    }

    public function canMakePurchase(int $clientId, float $amount): bool
    {
        $limit = $this->getCreditLimit($clientId);
        $balance = $this->getCurrentBalance($clientId);

        return ($balance + $amount) <= $limit;
    }

    public function getActiveClients(): Collection
    {
        return Client::active()->ordered()->get();
    }

    public function clientExists(int $id): bool
    {
        return Client::where('id', $id)->exists();
    }
}
