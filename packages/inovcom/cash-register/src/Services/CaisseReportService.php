<?php

namespace InovCom\Caisse\Services;

use Illuminate\Support\Collection;
use InovCom\Caisse\Models\CaisseEntry;
use InovCom\Caisse\Models\CaisseSession;

class CaisseReportService
{
    public function __construct(
        private readonly CaisseService $caisse
    ) {
    }

    /**
     * @return array{
     *     session: CaisseSession,
     *     summary: array<string, float|int>,
     *     entries: Collection,
     *     variance: float|null
     * }
     */
    public function buildSessionReport(CaisseSession $session): array
    {
        $session = $session->load(['opener', 'closer']);
        $summary = $this->caisse->sessionSummary($session);
        $entries = $this->caisse->sessionEntries($session);

        $variance = null;
        if ($session->status === 'closed'
            && $session->closing_amount_counted !== null
            && $session->closing_amount_expected !== null
        ) {
            $variance = (float) $session->closing_amount_counted - (float) $session->closing_amount_expected;
        }

        return [
            'session' => $session,
            'summary' => $summary,
            'entries' => $entries,
            'variance' => $variance,
        ];
    }

    public function entriesQuery(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $search = null,
        ?int $sessionId = null,
        ?string $source = null
    ) {
        return CaisseEntry::query()
            ->with(['session', 'performer'])
            ->where('entry_type', '!=', 'opening_balance')
            ->when($sessionId, fn ($q) => $q->where('caisse_session_id', $sessionId))
            ->when($source && $source !== 'all', fn ($q) => $q->where('source', $source))
            ->when($dateFrom, fn ($q) => $q->whereDate('entry_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('entry_date', '<=', $dateTo))
            ->when($search && trim($search) !== '', function ($q) use ($search) {
                $term = trim($search);
                $q->where(function ($inner) use ($term) {
                    $inner->where('reason', 'like', '%' . $term . '%')
                        ->orWhere('reference_number', 'like', '%' . $term . '%')
                        ->orWhere('entry_type', 'like', '%' . $term . '%');
                });
            });
    }
}
