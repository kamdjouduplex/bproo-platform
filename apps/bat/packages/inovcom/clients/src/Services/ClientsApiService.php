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

    public function createQuickClient(array $data): object
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Le nom du client est obligatoire.');
        }

        $seq = (int) (Client::query()->max('id') ?? 0) + 1;
        $client = Client::create([
            'code' => 'CLI-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
            'name' => $name,
            'type' => 'individual',
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'is_active' => true,
        ]);

        return $client;
    }
}
