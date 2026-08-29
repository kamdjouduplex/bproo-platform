<?php

namespace InovCom\Debts\Http\Livewire;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use InovCom\Debts\Models\Debt;
use InovCom\Debts\Services\DebtsService;
use InovCom\Clients\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DebtsIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public ?int $clientFilter = null;

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 20;

    public string $validationFilter = 'all';

    public bool $showAdvancedFilters = false;

    public function mount(): void
    {
        $client = request()->query('client');
        if ($client !== null && $client !== '') {
            $this->clientFilter = (int) $client;
        }

        if (request()->query('validation') === 'pending') {
            $this->validationFilter = 'pending';
        }

        if ($this->clientFilter || $this->dateFrom !== '' || $this->dateTo !== '') {
            $this->showAdvancedFilters = true;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedClientFilter($value): void
    {
        $this->clientFilter = ($value === '' || $value === null) ? null : (int) $value;
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedValidationFilter(): void
    {
        $this->resetPage();
    }

    public function toggleAdvancedFilters(): void
    {
        $this->showAdvancedFilters = ! $this->showAdvancedFilters;
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->validationFilter = 'all';
        $this->resetPage();
    }

    public function setValidationPending(): void
    {
        $this->validationFilter = 'pending';
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    public function setPeriod(string $period): void
    {
        $now = now();
        switch ($period) {
            case 'day':
                $this->dateFrom = $now->format('Y-m-d');
                $this->dateTo = $now->format('Y-m-d');
                break;
            case 'week':
                $this->dateFrom = $now->copy()->startOfWeek()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $this->dateFrom = $now->copy()->startOfMonth()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfMonth()->format('Y-m-d');
                break;
            case 'year':
                $this->dateFrom = $now->copy()->startOfYear()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfYear()->format('Y-m-d');
                break;
            case 'clear':
                $this->dateFrom = '';
                $this->dateTo = '';
                break;
        }
        $this->showAdvancedFilters = true;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->clientFilter = null;
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->validationFilter = 'all';
        $this->showAdvancedFilters = false;
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function delete(int $debtId): void
    {
        if (! $this->can('debts.delete')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        $debt = Debt::findOrFail($debtId);

        if ((float) $debt->balance > 0) {
            session()->flash('error', 'Seules les dettes soldées peuvent être supprimées.');

            return;
        }

        $debt->delete();
        $this->resetPage();
    }

    public function validateDebt(int $debtId): void
    {
        if (! $this->can('debts.validate')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        if (! Debt::supportsValidationWorkflow()) {
            session()->flash('error', 'Validation indisponible: exécutez les migrations tenant du module Dettes.');

            return;
        }

        $debt = Debt::findOrFail($debtId);
        if ((bool) $debt->is_validated) {
            session()->flash('success', 'Cette dette est déjà validée.');

            return;
        }

        $debt->is_validated = true;
        $debt->validated_by = auth('tenant')->id();
        $debt->validated_at = now();
        $debt->save();

        session()->flash('success', 'Dette validée avec succès.');
    }

    public function exportExcel(): ?StreamedResponse
    {
        if (! $this->can('debts.view')) {
            session()->flash('error', 'Permission refusée.');

            return null;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $headers = [
            'Référence',
            'Client',
            'Code client',
            'Origine',
            'Ouverture',
            'Échéance',
            'Montant',
            'Solde',
            'Statut',
        ];
        $title = 'Dettes clients — '.$this->filterLabel();
        $filename = 'dettes-'.now()->format('Ymd_His').'.xls';
        $escape = static fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

        return response()->streamDownload(function () use ($headers, $title, $escape) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<h3>'.$escape($title).'</h3>';
            echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
            foreach ($headers as $header) {
                echo '<th>'.$escape($header).'</th>';
            }
            echo '</tr></thead><tbody>';

            $exported = 0;
            $this->baseQuery()
                ->with($this->eagerRelations())
                ->orderBy('id')
                ->chunkById(300, function ($chunk) use (&$exported, $escape) {
                    foreach ($chunk as $debt) {
                        if ($exported >= 5000) {
                            return false;
                        }

                        $row = $this->mapDebtRow($debt);
                        echo '<tr>';
                        echo '<td>'.$escape($row['reference']).'</td>';
                        echo '<td>'.$escape($row['client_name']).'</td>';
                        echo '<td>'.$escape($row['client_code']).'</td>';
                        echo '<td>'.$escape($row['origin']).'</td>';
                        echo '<td>'.$escape($row['opened_at']).'</td>';
                        echo '<td>'.$escape($row['due_date']).'</td>';
                        echo '<td>'.$escape(fmt_money($row['total_amount'])).'</td>';
                        echo '<td>'.$escape(fmt_money($row['balance'])).'</td>';
                        echo '<td>'.$escape($row['status_label']).'</td>';
                        echo '</tr>';
                        $exported++;
                    }

                    return $exported < 5000;
                }, 'debts.id', 'id');

            echo '</tbody></table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportPdf()
    {
        if (! $this->can('debts.view')) {
            session()->flash('error', 'Permission refusée.');

            return null;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        try {
            $debts = $this->baseQuery()
                ->with($this->eagerRelations())
                ->orderByDesc('opened_at')
                ->orderByDesc('created_at')
                ->limit(5000)
                ->get();

            $rows = $debts->map(fn (Debt $debt) => $this->mapDebtRow($debt))->all();

            $tenant = app(TenantManager::class)->tenant();
            $settings = app(TenantBrandingService::class)->documentSettings($tenant);
            $filename = 'dettes-' . now()->format('Ymd_His') . '.pdf';

            $pdf = Pdf::loadView('inovcom-debts::pdf.debts-list', [
                'rows' => $rows,
                'settings' => $settings,
                'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo Pharma'),
                'title' => 'Dettes clients',
                'filterLabel' => $this->filterLabel(),
                'totalBalance' => collect($rows)->sum('balance'),
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape');

            $dompdf = $pdf->getDomPDF();
            $dompdf->render();
            $canvas = $dompdf->getCanvas();
            $fontMetrics = $dompdf->getFontMetrics();
            $font = $fontMetrics->getFont('DejaVu Sans');
            if ($font) {
                $size = 8;
                $width = $fontMetrics->getTextWidth('00/00', $font, $size);
                $x = ($canvas->get_width() - $width) / 2;
                $y = $canvas->get_height() - 18;
                $canvas->page_text($x, $y, '{PAGE_NUM}/{PAGE_COUNT}', $font, $size, [0.06, 0.46, 0.43]);
            }

            $output = $dompdf->output();

            return response()->streamDownload(function () use ($output) {
                echo $output;
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'Export PDF impossible. Affinez les filtres puis réessayez.');

            return null;
        }
    }

    public function render()
    {
        $debts = $this->baseQuery()
            ->with($this->eagerRelations())
            ->orderByDesc('opened_at')
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        $clients = Client::where('is_active', true)->orderBy('name')->get();

        $service = app(DebtsService::class);
        $totalOutstanding = $service->getTotalOutstanding(
            $this->clientFilter ?: null
        );

        return view('inovcom-debts::livewire.debts.index')
            ->layout('layouts.app', [
                'title' => 'Dettes',
                'subtitle' => 'Gestion des dettes clients',
            ])
            ->with([
                'debts' => $debts,
                'clients' => $clients,
                'totalOutstanding' => $totalOutstanding,
                'validationWorkflowReady' => Debt::supportsValidationWorkflow(),
                'canValidate' => $this->can('debts.validate'),
                'canDelete' => $this->can('debts.delete'),
                'canReceivePayment' => $this->can('debts.receive_payment'),
                'canCreate' => $this->can('debts.create'),
                'canExport' => $this->can('debts.view'),
                'activeFiltersCount' => $this->activeFiltersCount(),
            ]);
    }

    private function activeFiltersCount(): int
    {
        $count = 0;
        if ($this->clientFilter) {
            $count++;
        }
        if ($this->dateFrom !== '' || $this->dateTo !== '') {
            $count++;
        }

        return $count;
    }

    private function baseQuery(): Builder
    {
        return Debt::query()
            ->when(
                Debt::supportsValidationWorkflow() && $this->validationFilter === 'pending',
                fn ($q) => $q->where('is_validated', false)->whereIn('status', ['open', 'partial'])
            )
            ->when($this->search !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(function ($q2) use ($term) {
                    $q2->where('reference', 'like', $term)
                        ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term));
                    if (Schema::connection('tenant')->hasTable('sales')) {
                        $q2->orWhereHas('sale', fn ($q3) => $q3->where('sale_number', 'like', $term));
                    }
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->clientFilter, fn ($q) => $q->where('client_id', $this->clientFilter))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('opened_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('opened_at', '<=', $this->dateTo));
    }

    /**
     * @return array{
     *     reference: string,
     *     client_name: string,
     *     client_code: string,
     *     opened_at: string,
     *     due_date: string,
     *     total_amount: float,
     *     balance: float,
     *     status: string,
     *     status_label: string
     * }
     */
    private function mapDebtRow(Debt $debt): array
    {
        $statusLabels = [
            'open' => 'Ouvert',
            'partial' => 'Partiel',
            'paid' => 'Soldé',
            'overdue' => 'En retard',
        ];

        $statusLabel = $statusLabels[$debt->status] ?? (string) $debt->status;
        if (Debt::supportsValidationWorkflow() && ! $debt->is_validated) {
            $statusLabel = 'En attente de validation';
        }

        return [
            'reference' => (string) $debt->reference,
            'client_name' => (string) ($debt->client?->name ?? '—'),
            'client_code' => (string) ($debt->client?->code ?? ''),
            'origin' => $debt->sale?->sale_number
                ? (string) $debt->sale->sale_number
                : ($debt->sale_id ? 'Vente #'.$debt->sale_id : 'Manuelle'),
            'opened_at' => $debt->opened_at?->format('d/m/Y') ?? '—',
            'due_date' => $debt->due_date?->format('d/m/Y') ?? '—',
            'total_amount' => (float) $debt->total_amount,
            'balance' => (float) $debt->balance,
            'status' => (string) $debt->status,
            'status_label' => $statusLabel,
        ];
    }

    /**
     * @return list<string>
     */
    private function eagerRelations(): array
    {
        $with = ['client', 'creator', 'validator'];
        if (Schema::connection('tenant')->hasTable('sales')) {
            $with[] = 'sale';
        }

        return $with;
    }

    private function filterLabel(): string
    {
        $parts = [];
        if ($this->search !== '') {
            $parts[] = 'Recherche : '.$this->search;
        }
        if ($this->statusFilter !== 'all') {
            $parts[] = 'Statut : '.$this->statusFilter;
        }
        if ($this->clientFilter) {
            $client = Client::find($this->clientFilter);
            $parts[] = 'Client : '.($client?->name ?? '#'.$this->clientFilter);
        }
        if ($this->dateFrom !== '' || $this->dateTo !== '') {
            $parts[] = 'Période : '.($this->dateFrom ?: '…').' → '.($this->dateTo ?: '…');
        }
        if ($this->validationFilter === 'pending') {
            $parts[] = 'Validation en attente';
        }

        return $parts === [] ? 'Tous les enregistrements' : implode(' · ', $parts);
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }
}
