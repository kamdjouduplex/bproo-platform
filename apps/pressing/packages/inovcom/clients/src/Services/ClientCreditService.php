<?php

namespace InovCom\Clients\Services;

use InovCom\Clients\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion de l'encours et du blocage automatique du crédit client.
 *
 * Règle : si l'encours réel dépasse la limite de crédit, le client est bloqué
 * automatiquement (motif système). Le déblocage automatique n'intervient que si
 * le blocage était d'origine système et que l'encours repasse sous la limite ;
 * un blocage manuel reste actif jusqu'à déblocage manuel.
 */
class ClientCreditService
{
    public const AUTO_BLOCK_REASON = 'Dépassement de la limite de crédit (automatique)';

    public function realOutstanding(int $clientId): float
    {
        if (! Schema::connection('tenant')->hasTable('debts')) {
            return 0.0;
        }

        return (float) DB::connection('tenant')->table('debts')
            ->where('client_id', $clientId)
            ->where('balance', '>', 0)
            ->sum('balance');
    }

    /**
     * Réévalue le statut de blocage en fonction de l'encours et de la limite.
     *
     * @return array{outstanding: float, limit: float, exceeded: bool, blocked: bool, changed: bool}
     */
    public function evaluate(Client $client): array
    {
        $outstanding = $this->realOutstanding($client->id);
        $limit = (float) $client->credit_limit;
        $exceeded = $limit > 0 && $outstanding > $limit;
        $changed = false;

        if ($exceeded && ! $client->is_blocked) {
            $this->block($client, self::AUTO_BLOCK_REASON);
            $changed = true;
        } elseif (! $exceeded && $client->is_blocked && $client->block_reason === self::AUTO_BLOCK_REASON) {
            $this->unblock($client);
            $changed = true;
        }

        return [
            'outstanding' => $outstanding,
            'limit' => $limit,
            'exceeded' => $exceeded,
            'blocked' => (bool) $client->is_blocked,
            'changed' => $changed,
        ];
    }

    public function block(Client $client, string $reason, ?int $userId = null): void
    {
        $client->forceFill([
            'is_blocked' => true,
            'block_reason' => $reason,
            'blocked_at' => now(),
            'updated_by' => $userId ?? $client->updated_by,
        ])->save();
    }

    public function unblock(Client $client, ?int $userId = null): void
    {
        $client->forceFill([
            'is_blocked' => false,
            'block_reason' => null,
            'blocked_at' => null,
            'updated_by' => $userId ?? $client->updated_by,
        ])->save();
    }
}
