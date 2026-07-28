<?php

namespace InovCom\Clients\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Balance âgée : ventile l'encours d'un client par tranches d'ancienneté
 * à partir des dettes ouvertes (source : table debts).
 */
class ClientAgingService
{
    public function available(): bool
    {
        return Schema::connection('tenant')->hasTable('debts');
    }

    /**
     * @return array{current: float, d30: float, d60: float, d90: float, over90: float, total: float}
     */
    public function forClient(int $clientId): array
    {
        $buckets = $this->emptyBuckets();

        if (! $this->available()) {
            return $buckets;
        }

        $today = Carbon::today();

        $rows = DB::connection('tenant')->table('debts')
            ->where('client_id', $clientId)
            ->where('balance', '>', 0)
            ->get(['balance', 'due_date']);

        foreach ($rows as $row) {
            $balance = (float) $row->balance;
            $buckets['total'] += $balance;

            if ($row->due_date === null) {
                $buckets['current'] += $balance;
                continue;
            }

            $daysOverdue = $today->diffInDays(Carbon::parse($row->due_date), false) * -1;
            // daysOverdue > 0 => en retard de N jours ; <= 0 => non échu.

            if ($daysOverdue <= 0) {
                $buckets['current'] += $balance;
            } elseif ($daysOverdue <= 30) {
                $buckets['d30'] += $balance;
            } elseif ($daysOverdue <= 60) {
                $buckets['d60'] += $balance;
            } elseif ($daysOverdue <= 90) {
                $buckets['d90'] += $balance;
            } else {
                $buckets['over90'] += $balance;
            }
        }

        return $buckets;
    }

    /**
     * @return array<string, string>  Libellés des tranches pour l'affichage.
     */
    public function labels(): array
    {
        return [
            'current' => 'Non échu',
            'd30' => '1-30 j',
            'd60' => '31-60 j',
            'd90' => '61-90 j',
            'over90' => '+90 j',
        ];
    }

    private function emptyBuckets(): array
    {
        return [
            'current' => 0.0,
            'd30' => 0.0,
            'd60' => 0.0,
            'd90' => 0.0,
            'over90' => 0.0,
            'total' => 0.0,
        ];
    }
}
