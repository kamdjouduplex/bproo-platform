<?php

namespace InovCom\Caisse\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InovCom\Caisse\Models\CaisseEntry;
use InovCom\Caisse\Models\CaisseSession;

/**
 * Moteur de caisse — registre continu (solde global glissant).
 *
 * Principes :
 *  - Toute écriture passe par createEntry() qui calcule le solde global (in - out sur tout l'historique).
 *  - Le solde n'est jamais stocké ailleurs : il découle des mouvements (source unique de vérité).
 *  - Les sessions sont des fenêtres de réconciliation (poste de caisse) : elles n'interrompent
 *    jamais les encaissements automatiques du système.
 *  - recordSystemMovement() capture automatiquement les mouvements espèces du système
 *    (ventes, factures, dettes, dépenses, avoirs) de façon idempotente et non bloquante.
 */
class CaisseService
{
    public function isReady(): bool
    {
        return Schema::connection('tenant')->hasTable('caisse_entries')
            && Schema::connection('tenant')->hasTable('caisse_sessions');
    }

    public function ensureLedgerInitialized(): void
    {
        if (! Schema::connection('tenant')->hasTable('caisse_entries')) {
            return;
        }

        CaisseEntry::query()->firstOrCreate(
            ['entry_type' => 'opening_balance', 'reference_number' => 'INIT'],
            [
                'caisse_session_id' => null,
                'entry_date' => now(),
                'direction' => 'in',
                'source' => 'session',
                'amount' => 0,
                'balance_after' => 0,
                'reason' => 'Initialisation caisse',
                'performed_by' => auth('tenant')->id(),
            ]
        );
    }

    /* ----------------------------------------------------------------------
     | Sessions
     * -------------------------------------------------------------------- */

    public function activeSession(): ?CaisseSession
    {
        if (! $this->isReady()) {
            return null;
        }

        return CaisseSession::query()
            ->where('status', 'open')
            ->latest('id')
            ->first();
    }

    public function hasOpenSession(): bool
    {
        return $this->activeSession() !== null;
    }

    /** @deprecated Utiliser hasOpenSession() — la caisse n'est plus liée au jour calendaire. */
    public function hasOpenSessionForToday(): bool
    {
        return $this->hasOpenSession();
    }

    public function openSession(float $openingAmount, ?string $note = null, ?int $userId = null): CaisseSession
    {
        if (! $this->isReady()) {
            throw new \RuntimeException('La caisse n\'est pas encore initialisée. Exécutez les migrations du tenant puis réessayez.');
        }

        $active = $this->activeSession();
        if ($active) {
            throw new \RuntimeException(
                'Une caisse est déjà ouverte (' . $active->session_number . '). Clôturez-la avant d\'en ouvrir une nouvelle.'
            );
        }

        return DB::connection('tenant')->transaction(function () use ($openingAmount, $note, $userId) {
            $current = $this->globalBalance();

            $session = CaisseSession::create([
                'session_number' => $this->nextSessionNumber(),
                'opened_by' => $userId ?? auth('tenant')->id(),
                'opened_at' => now(),
                'opening_amount' => $openingAmount,
                'status' => 'open',
            ]);

            $delta = round($openingAmount - $current, 2);

            if ($current <= 0.0001 && $openingAmount > 0) {
                // Premier fond de caisse réellement déposé dans le tiroir.
                $this->createEntry(
                    amount: $openingAmount,
                    direction: 'in',
                    type: 'opening_float',
                    reason: $note ?: 'Fond de caisse à l\'ouverture',
                    sessionId: $session->id,
                    referenceType: CaisseSession::class,
                    referenceId: $session->id,
                    referenceNumber: $session->session_number,
                    performedBy: $userId,
                    source: 'session',
                );
            } elseif (abs($delta) >= 0.01) {
                // Le tiroir compté à l'ouverture diffère du solde théorique : on réconcilie en clair.
                $this->createEntry(
                    amount: abs($delta),
                    direction: $delta > 0 ? 'in' : 'out',
                    type: 'opening_adjustment',
                    reason: $note
                        ? ('Écart d\'ouverture — ' . $note)
                        : 'Écart constaté à l\'ouverture (réconciliation)',
                    sessionId: $session->id,
                    referenceType: CaisseSession::class,
                    referenceId: $session->id,
                    referenceNumber: $session->session_number,
                    performedBy: $userId,
                    source: 'session',
                );
            }

            return $session->fresh();
        });
    }

    public function closeSession(float $countedAmount, ?string $note = null, ?int $userId = null): CaisseSession
    {
        if (! $this->isReady()) {
            throw new \RuntimeException('La caisse n\'est pas encore initialisée. Exécutez les migrations du tenant puis réessayez.');
        }

        $session = $this->requireOpenSession();
        $expected = $this->globalBalance();

        $session->fill([
            'status' => 'closed',
            'closed_by' => $userId ?? auth('tenant')->id(),
            'closed_at' => now(),
            'closing_amount_expected' => $expected,
            'closing_amount_counted' => $countedAmount,
            'close_note' => $note,
        ])->save();

        return $session->fresh();
    }

    public function reopenSession(int $sessionId, ?int $userId = null): CaisseSession
    {
        if (! $this->isReady()) {
            throw new \RuntimeException('La caisse n\'est pas encore initialisée. Exécutez les migrations du tenant puis réessayez.');
        }

        $session = CaisseSession::query()->findOrFail($sessionId);

        if ($session->status !== 'closed') {
            throw new \RuntimeException('Seules les sessions clôturées peuvent être rouvertes.');
        }

        if (! $session->opened_at || $session->opened_at->toDateString() !== now()->toDateString()) {
            throw new \RuntimeException('Réouverture impossible : seule une session du jour peut être rouverte.');
        }

        $active = $this->activeSession();
        if ($active && $active->id !== $session->id) {
            throw new \RuntimeException('Une autre caisse est déjà ouverte (' . $active->session_number . ').');
        }

        $session->fill([
            'status' => 'open',
            'closed_by' => null,
            'closed_at' => null,
            'closing_amount_expected' => null,
            'closing_amount_counted' => null,
            'close_note' => null,
        ])->save();

        return $session->fresh();
    }

    public function requireOpenSession(): CaisseSession
    {
        $session = $this->activeSession();

        if (! $session) {
            throw new \RuntimeException(
                'Aucune caisse ouverte. Ouvrez la caisse et saisissez le fond de caisse avant cette opération.'
            );
        }

        return $session;
    }

    /** @deprecated Utiliser requireOpenSession() */
    public function requireOpenSessionForToday(): CaisseSession
    {
        return $this->requireOpenSession();
    }

    public function isActiveSessionOverdue(?CaisseSession $session = null): bool
    {
        $session ??= $this->activeSession();

        if (! $session) {
            return false;
        }

        return $session->opened_at && $session->opened_at->toDateString() < now()->toDateString();
    }

    /* ----------------------------------------------------------------------
     | Soldes
     * -------------------------------------------------------------------- */

    /** Solde global de la caisse (toutes écritures confondues). */
    public function globalBalance(): float
    {
        if (! $this->isReady()) {
            return 0.0;
        }

        $last = CaisseEntry::query()->latest('id')->first();

        return $last ? (float) $last->balance_after : 0.0;
    }

    public function currentBalance(): float
    {
        return $this->globalBalance();
    }

    /** Conservé pour compatibilité : le solde est désormais global, pas par session. */
    public function sessionBalance(CaisseSession $session): float
    {
        return $this->globalBalance();
    }

    /**
     * @return array{
     *     opening_amount: float,
     *     total_in: float,
     *     total_out: float,
     *     movement_count: int,
     *     expected_balance: float
     * }
     */
    public function sessionSummary(CaisseSession $session): array
    {
        $entries = CaisseEntry::query()
            ->where('caisse_session_id', $session->id)
            ->whereNotIn('entry_type', ['opening_balance', 'opening_float'])
            ->get();

        return [
            'opening_amount' => (float) $session->opening_amount,
            'total_in' => (float) $entries->where('direction', 'in')->sum('amount'),
            'total_out' => (float) $entries->where('direction', 'out')->sum('amount'),
            'movement_count' => $entries->count(),
            'expected_balance' => $this->globalBalance(),
        ];
    }

    public function sessionEntries(CaisseSession $session): Collection
    {
        return CaisseEntry::query()
            ->with(['performer'])
            ->where('caisse_session_id', $session->id)
            ->whereNotIn('entry_type', ['opening_balance'])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();
    }

    /* ----------------------------------------------------------------------
     | Mouvements manuels (UI)
     * -------------------------------------------------------------------- */

    public function cashIn(float $amount, string $reason, ?int $userId = null, ?string $referenceNumber = null): CaisseEntry
    {
        return $this->createEntry(
            amount: $amount,
            direction: 'in',
            type: 'cash_in',
            reason: $reason,
            sessionId: $this->activeSession()?->id,
            referenceNumber: $referenceNumber,
            performedBy: $userId,
            source: 'manual',
        );
    }

    public function cashOut(
        float $amount,
        string $reason,
        ?int $userId = null,
        ?string $referenceNumber = null,
        string $source = 'manual',
        string $type = 'cash_out',
        ?string $referenceType = null,
        ?int $referenceId = null,
        bool $enforceBalance = true
    ): CaisseEntry {
        return $this->createEntry(
            amount: $amount,
            direction: 'out',
            type: $type,
            reason: $reason,
            sessionId: $this->activeSession()?->id,
            referenceType: $referenceType,
            referenceId: $referenceId,
            referenceNumber: $referenceNumber,
            performedBy: $userId,
            source: $source,
            enforceBalance: $enforceBalance,
        );
    }

    /* ----------------------------------------------------------------------
     | Auto-capture (mouvements automatiques du système)
     * -------------------------------------------------------------------- */

    /**
     * Enregistre un mouvement de caisse généré par un autre module.
     * Idempotent (clé : reference_type + reference_id + entry_type) et non bloquant :
     * en cas d'incident, ne lève jamais d'exception pour ne pas casser l'opération source.
     */
    public function recordSystemMovement(
        string $direction,
        string $type,
        float $amount,
        string $reason,
        string $source,
        string $referenceType,
        int $referenceId,
        ?string $referenceNumber = null,
        array $metadata = [],
        ?int $userId = null
    ): ?CaisseEntry {
        if (! $this->isReady() || $amount <= 0) {
            return null;
        }

        $existing = $this->findReferencedEntry($referenceType, $referenceId, $type);
        if ($existing) {
            return $existing;
        }

        try {
            return $this->createEntry(
                amount: round($amount, 2),
                direction: $direction,
                type: $type,
                reason: $reason,
                sessionId: $this->activeSession()?->id,
                referenceType: $referenceType,
                referenceId: $referenceId,
                referenceNumber: $referenceNumber,
                performedBy: $userId,
                source: $source,
                metadata: $metadata,
            );
        } catch (\Throwable $e) {
            // Course / doublon : retourner l'écriture déjà posée si elle existe.
            $entry = $this->findReferencedEntry($referenceType, $referenceId, $type);
            if ($entry) {
                return $entry;
            }

            Log::warning('Caisse: échec auto-capture mouvement', [
                'type' => $type,
                'reference' => $referenceType . ':' . $referenceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Contre-passe un mouvement automatique (ex. annulation d'un encaissement de facture).
     * Idempotent et non bloquant.
     */
    public function reverseSystemMovement(
        string $type,
        string $referenceType,
        int $referenceId,
        string $reason,
        ?int $userId = null
    ): ?CaisseEntry {
        if (! $this->isReady()) {
            return null;
        }

        $original = CaisseEntry::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('entry_type', $type)
            ->where('is_reversal', false)
            ->first();

        if (! $original) {
            return null;
        }

        $reversalType = $type . '_reversal';
        $existing = $this->findReferencedEntry($referenceType, $referenceId, $reversalType);
        if ($existing) {
            return $existing;
        }

        try {
            return $this->createEntry(
                amount: (float) $original->amount,
                direction: $original->direction === 'in' ? 'out' : 'in',
                type: $reversalType,
                reason: $reason,
                sessionId: $this->activeSession()?->id,
                referenceType: $referenceType,
                referenceId: $referenceId,
                referenceNumber: $original->reference_number,
                performedBy: $userId,
                source: $original->source ?: 'manual',
                isReversal: true,
                reversedEntryId: $original->id,
            );
        } catch (\Throwable $e) {
            $entry = $this->findReferencedEntry($referenceType, $referenceId, $reversalType);
            if ($entry) {
                return $entry;
            }

            Log::warning('Caisse: échec contre-passation', [
                'type' => $reversalType,
                'reference' => $referenceType . ':' . $referenceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function findReferencedEntry(string $referenceType, int $referenceId, string $type): ?CaisseEntry
    {
        return CaisseEntry::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('entry_type', $type)
            ->first();
    }

    /* ----------------------------------------------------------------------
     | Anciens hooks — neutralisés (remplacés par recordSystemMovement)
     * -------------------------------------------------------------------- */

    /** @deprecated Utiliser recordSystemMovement(). */
    public function registerSaleCashIn(float $amount, object $sale, ?int $userId = null): ?CaisseEntry
    {
        return null;
    }

    /** @deprecated Utiliser recordSystemMovement(). */
    public function registerSaleReturnCashOut(float $amount, object $saleReturn, ?int $userId = null): ?CaisseEntry
    {
        return null;
    }

    /** @deprecated Utiliser recordSystemMovement(). */
    public function registerInvoiceReturnCashOut(float $amount, object $invoiceReturn, ?int $userId = null): ?CaisseEntry
    {
        return null;
    }

    /** @deprecated Utiliser recordSystemMovement(). */
    public function registerExpenseCashOut(float $amount, object $expense, ?int $userId = null): ?CaisseEntry
    {
        return null;
    }

    /* ----------------------------------------------------------------------
     | Écriture bas niveau
     * -------------------------------------------------------------------- */

    private function createEntry(
        float $amount,
        string $direction,
        string $type,
        string $reason,
        ?int $sessionId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceNumber = null,
        ?int $performedBy = null,
        ?string $source = null,
        bool $enforceBalance = false,
        bool $isReversal = false,
        ?int $reversedEntryId = null,
        array $metadata = []
    ): CaisseEntry {
        if (! $this->isReady()) {
            throw new \RuntimeException('La caisse n\'est pas encore initialisée. Exécutez les migrations du tenant puis réessayez.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être supérieur à 0.');
        }

        if (! in_array($direction, ['in', 'out'], true)) {
            throw new \InvalidArgumentException('Direction de mouvement invalide.');
        }

        return DB::connection('tenant')->transaction(function () use (
            $amount,
            $direction,
            $type,
            $reason,
            $sessionId,
            $referenceType,
            $referenceId,
            $referenceNumber,
            $performedBy,
            $source,
            $enforceBalance,
            $isReversal,
            $reversedEntryId,
            $metadata
        ) {
            $currentBalance = $this->balanceBeforeEntry();

            if ($enforceBalance && $direction === 'out' && $amount > $currentBalance + 0.0001) {
                throw new \RuntimeException(
                    'Solde insuffisant dans la caisse. Solde actuel : ' . fmt_money($currentBalance) . ' FCFA.'
                );
            }

            $nextBalance = $direction === 'in'
                ? $currentBalance + $amount
                : $currentBalance - $amount;

            return CaisseEntry::create([
                'caisse_session_id' => $sessionId,
                'entry_date' => now(),
                'entry_type' => $type,
                'source' => $source,
                'direction' => $direction,
                'amount' => $amount,
                'balance_after' => round($nextBalance, 2),
                'is_reversal' => $isReversal,
                'reversed_entry_id' => $reversedEntryId,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_number' => $referenceNumber,
                'performed_by' => $performedBy ?? auth('tenant')->id(),
                'metadata' => $metadata ?: null,
            ]);
        });
    }

    private function balanceBeforeEntry(): float
    {
        $last = CaisseEntry::query()
            ->lockForUpdate()
            ->latest('id')
            ->first();

        return $last ? (float) $last->balance_after : 0.0;
    }

    private function nextSessionNumber(): string
    {
        $year = now()->year;
        $last = CaisseSession::query()
            ->whereYear('opened_at', $year)
            ->latest('id')
            ->first();

        $next = 1;
        if ($last && str_contains((string) $last->session_number, '-')) {
            $parts = explode('-', (string) $last->session_number);
            $lastPart = (int) end($parts);
            if ($lastPart > 0) {
                $next = $lastPart + 1;
            }
        }

        return 'CAI-' . $year . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
