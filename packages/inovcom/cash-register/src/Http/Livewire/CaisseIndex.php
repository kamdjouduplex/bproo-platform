<?php

namespace InovCom\Caisse\Http\Livewire;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use InovCom\Caisse\Exports\CaisseExcelExporter;
use InovCom\Caisse\Models\CaisseSession;
use InovCom\Caisse\Services\CaisseReportService;
use InovCom\Caisse\Services\CaisseService;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaisseIndex extends Component
{
    use WithPagination;

    public string $activeTab = 'register'; // register|history|sessions

    public string $entryDateFrom = '';
    public string $entryDateTo = '';
    public string $search = '';
    public string $sourceFilter = 'all';
    public int $perPage = 30;

    public string $openingAmount = '0';
    public string $openingNote = '';
    public string $cashInAmount = '';
    public string $cashInReason = '';
    public string $cashInReference = '';
    public string $cashOutAmount = '';
    public string $cashOutReason = '';
    public string $cashOutReference = '';
    public string $closeCountedAmount = '';
    public string $closeNote = '';

    public function mount(): void
    {
        $this->entryDateFrom = now()->format('Y-m-d');
        $this->entryDateTo = now()->format('Y-m-d');

        $service = app(CaisseService::class);
        if ($service->isReady() && $service->activeSession()) {
            $this->closeCountedAmount = (string) $service->currentBalance();
        }
    }

    public function resetCashInForm(): void
    {
        $this->resetValidation(['cashInAmount', 'cashInReason', 'cashInReference']);
        $this->cashInAmount = '';
        $this->cashInReason = '';
        $this->cashInReference = '';
    }

    public function resetCashOutForm(): void
    {
        $this->resetValidation(['cashOutAmount', 'cashOutReason', 'cashOutReference']);
        $this->cashOutAmount = '';
        $this->cashOutReason = '';
        $this->cashOutReference = '';
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function setPeriod(string $period): void
    {
        $now = now();

        switch ($period) {
            case 'today':
                $this->entryDateFrom = $now->format('Y-m-d');
                $this->entryDateTo = $now->format('Y-m-d');
                break;
            case 'week':
                $this->entryDateFrom = $now->copy()->startOfWeek()->format('Y-m-d');
                $this->entryDateTo = $now->copy()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $this->entryDateFrom = $now->copy()->startOfMonth()->format('Y-m-d');
                $this->entryDateTo = $now->copy()->endOfMonth()->format('Y-m-d');
                break;
        }

        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        if (!in_array($tab, ['register', 'history', 'sessions'], true)) {
            return;
        }

        $this->activeTab = $tab;

        if (in_array($tab, ['history', 'sessions'], true)) {
            $this->resetPage();
        }
    }

    public function exportJournalExcel(): StreamedResponse
    {
        $this->authorizeExport();

        $rows = $this->reportService()->entriesQuery(
            $this->entryDateFrom ?: null,
            $this->entryDateTo ?: null,
            $this->search,
            null,
            $this->sourceFilter
        )->orderBy('entry_date')->orderBy('id')->get();

        return CaisseExcelExporter::download(
            'journal-caisse-' . now()->format('Ymd_His') . '.xls',
            ['Date', 'Type', 'Motif', 'Référence', 'Entrée', 'Sortie', 'Solde'],
            CaisseExcelExporter::journalRows($rows),
            'Journal de caisse'
        );
    }

    public function exportJournalPdf()
    {
        $this->authorizeExport();

        $rows = $this->reportService()->entriesQuery(
            $this->entryDateFrom ?: null,
            $this->entryDateTo ?: null,
            $this->search,
            null,
            $this->sourceFilter
        )->orderBy('entry_date')->orderBy('id')->get();

        return $this->streamPdf('journal-caisse-' . now()->format('Ymd_His') . '.pdf', 'inovcom-caisse::pdf.journal', [
            'entries' => $rows,
            'settings' => $this->documentSettings(),
            'generatedAt' => now(),
            'dateFrom' => $this->entryDateFrom,
            'dateTo' => $this->entryDateTo,
            'search' => trim($this->search),
            'title' => 'Journal de caisse',
        ]);
    }

    public function exportSessionExcel(int $sessionId): StreamedResponse
    {
        $this->authorizeExport();
        $report = $this->reportService()->buildSessionReport(CaisseSession::query()->findOrFail($sessionId));

        return CaisseExcelExporter::download(
            'etat-caisse-' . $report['session']->session_number . '.xls',
            ['Date', 'Type', 'Motif', 'Référence', 'Entrée', 'Sortie', 'Solde'],
            CaisseExcelExporter::journalRows($report['entries']),
            'État de caisse — ' . $report['session']->session_number
        );
    }

    public function exportSessionPdf(int $sessionId)
    {
        $this->authorizeExport();
        $report = $this->reportService()->buildSessionReport(CaisseSession::query()->findOrFail($sessionId));

        return $this->streamPdf(
            'etat-caisse-' . $report['session']->session_number . '.pdf',
            'inovcom-caisse::pdf.session-state',
            [
                'session' => $report['session'],
                'summary' => $report['summary'],
                'entries' => $report['entries'],
                'variance' => $report['variance'],
                'settings' => $this->documentSettings(),
                'generatedAt' => now(),
            ]
        );
    }

    public function exportActiveSessionPdf()
    {
        $session = app(CaisseService::class)->activeSession();
        if (!$session) {
            session()->flash('error', 'Aucune caisse ouverte à exporter.');
            return;
        }

        return $this->exportSessionPdf($session->id);
    }

    public function exportActiveSessionExcel(): StreamedResponse
    {
        $session = app(CaisseService::class)->activeSession();
        if (!$session) {
            abort(422, 'Aucune caisse ouverte.');
        }

        return $this->exportSessionExcel($session->id);
    }

    public function openSession(): void
    {
        if (!$this->can('caisse.open')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $data = $this->validate([
            'openingAmount' => 'required|numeric|min:0',
            'openingNote' => 'nullable|string|max:255',
        ]);

        try {
            app(CaisseService::class)->openSession(
                (float) $data['openingAmount'],
                $data['openingNote'] ?: null
            );

            $this->openingAmount = '0';
            $this->openingNote = '';
            $this->closeCountedAmount = (string) app(CaisseService::class)->currentBalance();
            session()->flash('success', 'Caisse ouverte. Vous pouvez enregistrer les mouvements manuellement.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function addCashIn(): void
    {
        if (!$this->can('caisse.cash_in')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $data = $this->validate([
            'cashInAmount' => 'required|numeric|min:0.01',
            'cashInReason' => 'required|string|max:255',
            'cashInReference' => 'required|string|max:100',
        ]);

        try {
            app(CaisseService::class)->cashIn(
                (float) $data['cashInAmount'],
                $data['cashInReason'],
                null,
                trim($data['cashInReference'])
            );
            $this->resetCashInForm();
            $this->closeCountedAmount = (string) app(CaisseService::class)->currentBalance();
            session()->flash('success', 'Entrée de caisse enregistrée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function addCashOut(): void
    {
        if (!$this->can('caisse.cash_out')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $data = $this->validate([
            'cashOutAmount' => 'required|numeric|min:0.01',
            'cashOutReason' => 'required|string|max:255',
            'cashOutReference' => 'required|string|max:100',
        ]);

        try {
            app(CaisseService::class)->cashOut(
                (float) $data['cashOutAmount'],
                $data['cashOutReason'],
                null,
                trim($data['cashOutReference'])
            );
            $this->resetCashOutForm();
            $this->closeCountedAmount = (string) app(CaisseService::class)->currentBalance();
            session()->flash('success', 'Sortie de caisse enregistrée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function closeSession(): void
    {
        if (!$this->can('caisse.close')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $data = $this->validate([
            'closeCountedAmount' => 'required|numeric|min:0',
            'closeNote' => 'nullable|string|max:255',
        ]);

        try {
            $service = app(CaisseService::class);
            $session = $service->closeSession(
                (float) $data['closeCountedAmount'],
                $data['closeNote'] ?: null
            );

            $expected = (float) ($session->closing_amount_expected ?? 0);
            $counted = (float) ($session->closing_amount_counted ?? 0);
            $variance = $counted - $expected;

            $message = 'Caisse clôturée.';
            if (abs($variance) >= 0.01) {
                $message .= ' Écart constaté : ' . fmt_money($variance) . ' FCFA.';
            }

            $this->closeCountedAmount = '';
            $this->closeNote = '';
            session()->flash('success', $message);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function reopenSession(int $sessionId): void
    {
        if (!$this->can('caisse.open')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $session = app(CaisseService::class)->reopenSession($sessionId);
            $this->closeCountedAmount = (string) app(CaisseService::class)->sessionBalance($session);
            session()->flash('success', 'Session rouverte. Les mouvements manuels sont à nouveau possibles.');
            $this->activeTab = 'register';
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $service = app(CaisseService::class);
        $schemaReady = $service->isReady();
        $activeSession = $service->activeSession();
        $balance = $service->currentBalance();
        $sessionOverdue = $service->isActiveSessionOverdue($activeSession);
        $sessionSummary = $activeSession ? $service->sessionSummary($activeSession) : null;

        $periodTotals = ['in' => 0.0, 'out' => 0.0];

        if ($schemaReady) {
            $entries = $this->reportService()->entriesQuery(
                $this->entryDateFrom ?: null,
                $this->entryDateTo ?: null,
                $this->search,
                null,
                $this->sourceFilter
            )
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->paginate($this->perPage);

            $totalsBase = $this->reportService()->entriesQuery(
                $this->entryDateFrom ?: null,
                $this->entryDateTo ?: null,
                $this->search,
                null,
                $this->sourceFilter
            );
            $periodTotals['in'] = (float) (clone $totalsBase)->where('direction', 'in')->sum('amount');
            $periodTotals['out'] = (float) (clone $totalsBase)->where('direction', 'out')->sum('amount');
        } else {
            $entries = new LengthAwarePaginator(
                items: collect([]),
                total: 0,
                perPage: $this->perPage,
                currentPage: 1,
                options: ['path' => request()->url(), 'query' => request()->query()]
            );
        }

        $sessions = $schemaReady
            ? CaisseSession::query()->with(['opener', 'closer'])->orderByDesc('opened_at')->limit(200)->get()
            : collect([]);

        $todayEntries = $schemaReady && $activeSession
            ? $service->sessionEntries($activeSession)
            : collect([]);

        return view('inovcom-caisse::livewire.caisse.index')
            ->layout('layouts.app', [
                'title' => 'Caisse',
                'subtitle' => $activeSession
                    ? 'Session ' . $activeSession->session_number . ' · Solde ' . fmt_money($balance) . ' FCFA'
                    : 'Caisse fermée — ouvrez une session pour commencer',
            ])
            ->with([
                'entries' => $entries,
                'periodTotals' => $periodTotals,
                'sourceOptions' => \InovCom\Caisse\Models\CaisseEntry::SOURCE_LABELS,
                'activeSession' => $activeSession,
                'balance' => $balance,
                'schemaReady' => $schemaReady,
                'sessionOverdue' => $sessionOverdue,
                'sessionSummary' => $sessionSummary,
                'sessions' => $sessions,
                'todayEntries' => $todayEntries,
                'canOpen' => $this->can('caisse.open'),
                'canCashIn' => $this->can('caisse.cash_in'),
                'canCashOut' => $this->can('caisse.cash_out'),
                'canClose' => $this->can('caisse.close'),
                'canView' => $this->can('caisse.view'),
            ]);
    }

    private function reportService(): CaisseReportService
    {
        return app(CaisseReportService::class);
    }

    private function authorizeExport(): void
    {
        if (!$this->can('caisse.view')) {
            abort(403, 'Permission refusée.');
        }

        if (!app(CaisseService::class)->isReady()) {
            abort(422, 'Module caisse non initialisé.');
        }
    }

    private function documentSettings(): array
    {
        $tenant = app(TenantManager::class)->tenant();

        return app(TenantBrandingService::class)->documentSettings($tenant);
    }

    private function streamPdf(string $filename, string $view, array $data)
    {
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }
}
