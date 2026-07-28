<?php

namespace InovCom\Prospects\Http\Livewire;

use InovCom\Prospects\Concerns\AuthorizesProspectActions;
use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Services\ProspectsService;
use InovCom\Users\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class ProspectsIndex extends Component
{
    use AuthorizesProspectActions;
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $sourceFilter = 'all';

    public string $ownerFilter = 'all';

    public int $perPage = 20;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSourceFilter(): void
    {
        $this->resetPage();
    }

    public function updatedOwnerFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->sourceFilter = 'all';
        $this->ownerFilter = 'all';
        $this->resetPage();
    }

    public function delete(int $prospectId): void
    {
        $this->authorizeProspectAction('prospects.delete');

        try {
            $prospect = Prospect::findOrFail($prospectId);
            app(ProspectsService::class)->delete($prospect);
            session()->flash('success', 'Prospect supprimé.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $this->authorizeProspectAction('prospects.view');

        $ownerId = $this->ownerFilter !== 'all' ? (int) $this->ownerFilter : null;

        $query = Prospect::query()
            ->with(['owner', 'convertedClient'])
            ->when($this->search !== '', function ($q) {
                $term = '%' . mb_strtolower(trim($this->search)) . '%';
                $q->where(function ($q2) use ($term) {
                    $q2->whereRaw('LOWER(reference) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$term]);
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->sourceFilter !== 'all', fn ($q) => $q->where('source', $this->sourceFilter))
            ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $stats = app(ProspectsService::class)->summarize(
            $this->statusFilter === 'all' ? null : $this->statusFilter,
            $this->sourceFilter === 'all' ? null : $this->sourceFilter,
            $ownerId
        );

        // KPI cards ignore status filter for overview clarity
        $kpi = app(ProspectsService::class)->summarize(
            null,
            $this->sourceFilter === 'all' ? null : $this->sourceFilter,
            $ownerId
        );

        return view('inovcom-prospects::livewire.prospects.index')
            ->layout('layouts.app', [
                'title' => 'Prospects',
                'subtitle' => 'Prospection commerciale et conversion en clients',
            ])
            ->with([
                'prospects' => $query->paginate($this->perPage),
                'stats' => $kpi,
                'conversionBySource' => $stats['by_source'] ?? [],
                'owners' => User::query()->orderBy('name')->get(['id', 'name']),
                'tenantCode' => $this->tenantCode(),
                'canCreate' => $this->canProspect('prospects.create'),
                'canDelete' => $this->canProspect('prospects.delete'),
            ]);
    }
}
