<?php

namespace InovCom\Clients\Http\Livewire;

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
            session()->flash('error', 'Suppression impossible : ce client a un encours de ' . fmt_money($outstanding) . ' FCFA.');
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
            ->with(['category', 'zone'])
            ->when($this->search !== '', function ($query) use ($likeOperator) {
                $term = '%' . $this->search . '%';
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

    public function export()
    {
        if (! $this->can('clients.view')) {
            session()->flash('error', 'Permission refusée.');
            return null;
        }

        $clients = $this->baseQuery()->get();
        $debtSummaries = app(ClientDebtInsightService::class)
            ->forClientIds($clients->pluck('id')->map(fn ($id) => (int) $id)->all());

        return ClientsExporter::download(
            'clients_' . now()->format('Ymd_His') . '.xls',
            ClientsExporter::headers(),
            ClientsExporter::rows($clients, $debtSummaries),
            'Liste des clients — ' . now()->format('d/m/Y H:i')
        );
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
                'activeFiltersCount' => $activeFiltersCount,
            ]);
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
