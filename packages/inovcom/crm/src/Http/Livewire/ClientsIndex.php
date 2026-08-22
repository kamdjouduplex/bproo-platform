<?php

namespace InovCom\Clients\Http\Livewire;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use InovCom\Clients\Concerns\AuthorizesClientActions;
use InovCom\Clients\Exports\ClientsExporter;
use InovCom\Clients\Models\Client;
use InovCom\Clients\Models\ClientCategory;
use InovCom\Clients\Models\Segment;
use InovCom\Clients\Models\Zone;
use InovCom\Clients\Services\ClientDebtInsightService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientsIndex extends Component
{
    use WithPagination;
    use AuthorizesClientActions;

    public string $search = '';

    public ?int $segmentFilter = null;

    public ?int $zoneFilter = null;

    public ?int $categoryFilter = null;

    public ?int $salesrepFilter = null;

    public string $statusFilter = 'all'; // all, active, inactive, blocked

    public int $perPage = 10;

    public bool $showAdvancedFilters = false;

    public function updated($name): void
    {
        if (in_array($name, [
            'search', 'segmentFilter', 'zoneFilter', 'categoryFilter',
            'salesrepFilter', 'statusFilter', 'perPage',
        ], true)) {
            $this->resetPage();
        }
    }

    public function toggleAdvancedFilters(): void
    {
        $this->showAdvancedFilters = ! $this->showAdvancedFilters;
    }

    public function applySearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->segmentFilter = null;
        $this->zoneFilter = null;
        $this->categoryFilter = null;
        $this->salesrepFilter = null;
        $this->statusFilter = 'all';
        $this->showAdvancedFilters = false;
        $this->resetPage();
    }

    public function delete(int $clientId): void
    {
        if (! $this->can('clients.delete')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        $client = Client::find($clientId);
        if (! $client) {
            return;
        }

        // Garde-fou : interdiction de supprimer un client avec un encours ou un historique.
        $outstanding = app(ClientDebtInsightService::class)->forClient($clientId)['outstanding'] ?? 0.0;
        if ($outstanding > 0.0) {
            session()->flash('error', 'Suppression impossible : ce client a un encours de '.fmt_money($outstanding).' '.currency_label().'.');

            return;
        }

        if (Schema::connection('tenant')->hasTable('sales')
            && DB::connection('tenant')->table('sales')->where('client_id', $clientId)->exists()) {
            session()->flash('error', 'Suppression impossible : ce client possède un historique de ventes. Désactivez-le plutôt.');

            return;
        }

        $client->delete(); // soft delete
        session()->flash('success', 'Client supprimé.');
        $this->resetPage();
    }

    private function baseQuery()
    {
        $likeOperator = DB::connection('tenant')->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return Client::query()
            ->with(['category', 'zone', 'segment'])
            ->when($this->search !== '', function ($query) use ($likeOperator) {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($likeOperator, $term) {
                    $q->where('name', $likeOperator, $term)
                        ->orWhere('code', $likeOperator, $term)
                        ->orWhere('email', $likeOperator, $term)
                        ->orWhere('phone', $likeOperator, $term)
                        ->orWhere('niu', $likeOperator, $term)
                        ->orWhere('rccm', $likeOperator, $term);
                });
            })
            ->when($this->segmentFilter, fn ($q) => $q->where('segment_id', $this->segmentFilter))
            ->when($this->zoneFilter, fn ($q) => $q->where('zone_id', $this->zoneFilter))
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->salesrepFilter, fn ($q) => $q->where('salesrep_id', $this->salesrepFilter))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->statusFilter === 'blocked', fn ($q) => $q->where('is_blocked', true))
            ->orderBy('name');
    }

    public function exportExcel(): ?StreamedResponse
    {
        if (! $this->canExport()) {
            session()->flash('error', 'Permission refusée.');

            return null;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $clients = $this->baseQuery()->limit(5000)->get();
        $debtSummaries = app(ClientDebtInsightService::class)
            ->forClientIds($clients->pluck('id')->map(fn ($id) => (int) $id)->all());

        return ClientsExporter::download(
            'clients_'.now()->format('Ymd_His').'.xls',
            ClientsExporter::headers(),
            ClientsExporter::rows($clients, $debtSummaries),
            'Liste des clients — '.$this->filterLabel()
        );
    }

    /** @deprecated Use exportExcel() */
    public function export(): ?StreamedResponse
    {
        return $this->exportExcel();
    }

    public function exportPdf()
    {
        if (! $this->canExport()) {
            session()->flash('error', 'Permission refusée.');

            return null;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        try {
            $clients = $this->baseQuery()->limit(5000)->get();
            $debtSummaries = app(ClientDebtInsightService::class)
                ->forClientIds($clients->pluck('id')->map(fn ($id) => (int) $id)->all());

            $rows = [];
            $totalOutstanding = 0.0;
            foreach ($clients as $client) {
                $outstanding = (float) ($debtSummaries[$client->id]['outstanding'] ?? 0);
                $totalOutstanding += $outstanding;
                $rows[] = [
                    'code' => (string) $client->code,
                    'name' => (string) $client->name,
                    'type' => $client->type === 'company' ? 'Entreprise' : 'Particulier',
                    'phone' => (string) ($client->phone ?: '—'),
                    'email' => (string) ($client->email ?: '—'),
                    'niu' => (string) ($client->niu ?: ''),
                    'category' => (string) ($client->category?->name ?: '—'),
                    'zone' => (string) ($client->zone?->name ?: '—'),
                    'price_tier' => (string) $client->priceTierLabel(),
                    'credit_limit' => fmt_money((float) $client->credit_limit),
                    'outstanding' => fmt_money($outstanding),
                    'status' => $client->is_blocked ? 'Bloqué' : ($client->is_active ? 'Actif' : 'Inactif'),
                ];
            }

            $tenant = app(TenantManager::class)->tenant();
            $settings = app(TenantBrandingService::class)->documentSettings($tenant);
            $filename = 'clients-'.now()->format('Ymd_His').'.pdf';

            $pdf = Pdf::loadView('inovcom-clients::pdf.clients-list', [
                'rows' => $rows,
                'settings' => $settings,
                'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo Pharma'),
                'title' => 'Clients',
                'filterLabel' => $this->filterLabel(),
                'totalOutstanding' => $totalOutstanding,
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
        $clients = $this->baseQuery()->paginate($this->perPage);

        $debtService = app(ClientDebtInsightService::class);

        $activeFiltersCount = 0;
        if ($this->segmentFilter) {
            $activeFiltersCount++;
        }
        if ($this->zoneFilter) {
            $activeFiltersCount++;
        }
        if ($this->categoryFilter) {
            $activeFiltersCount++;
        }
        if ($this->salesrepFilter) {
            $activeFiltersCount++;
        }
        if ($this->statusFilter !== 'all') {
            $activeFiltersCount++;
        }

        return view('inovcom-clients::livewire.clients.index')
            ->layout('layouts.app', [
                'title' => 'Clients',
                'subtitle' => 'Gestion des clients',
            ])
            ->with([
                'clients' => $clients,
                'segments' => Segment::orderBy('name')->get(),
                'zones' => Zone::orderBy('name')->get(),
                'categories' => ClientCategory::orderBy('name')->get(),
                'salesreps' => $this->salesreps(),
                'debtSummaries' => $debtService->forPaginator($clients),
                'debtsModule' => $debtService->moduleAvailable(),
                'canCreate' => $this->can('clients.create'),
                'canDelete' => $this->can('clients.delete'),
                'canExport' => $this->canExport(),
                'activeFiltersCount' => $activeFiltersCount,
            ]);
    }

    private function canExport(): bool
    {
        return $this->can('clients.export') || $this->can('clients.view');
    }

    private function filterLabel(): string
    {
        $parts = [];
        if ($this->search !== '') {
            $parts[] = 'Recherche : '.$this->search;
        }
        if ($this->statusFilter !== 'all') {
            $labels = ['active' => 'Actifs', 'inactive' => 'Inactifs', 'blocked' => 'Bloqués'];
            $parts[] = 'Statut : '.($labels[$this->statusFilter] ?? $this->statusFilter);
        }
        if ($this->segmentFilter) {
            $parts[] = 'Segment : '.(Segment::find($this->segmentFilter)?->name ?? '#'.$this->segmentFilter);
        }
        if ($this->categoryFilter) {
            $parts[] = 'Catégorie : '.(ClientCategory::find($this->categoryFilter)?->name ?? '#'.$this->categoryFilter);
        }
        if ($this->zoneFilter) {
            $parts[] = 'Zone : '.(Zone::find($this->zoneFilter)?->name ?? '#'.$this->zoneFilter);
        }
        if ($this->salesrepFilter) {
            $rep = $this->salesreps()->firstWhere('id', $this->salesrepFilter);
            $parts[] = 'Commercial : '.($rep->name ?? '#'.$this->salesrepFilter);
        }

        return $parts === [] ? 'Tous les clients' : implode(' · ', $parts);
    }

    private function salesreps()
    {
        if (! Schema::connection('tenant')->hasTable('users')) {
            return collect();
        }

        return DB::connection('tenant')->table('users')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
