<?php

namespace InovCom\Returns\Http\Livewire;

use InovCom\Returns\Concerns\AuthorizesReturnActions;
use InovCom\Returns\Enums\ReturnStatus;
use InovCom\Returns\Models\ReturnRequest;
use InovCom\Returns\Services\ReturnService;
use Livewire\Component;
use Livewire\WithPagination;

class ReturnsIndex extends Component
{
    use WithPagination;
    use AuthorizesReturnActions;

    public string $search = '';
    public string $statusFilter = 'all';
    public ?int $clientFilter = null;
    public string $dateFrom = '';
    public string $dateTo = '';
    public int $perPage = 20;

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'clientFilter', 'dateFrom', 'dateTo']);
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    public function cancel(int $id): void
    {
        if (! $this->can('returns.cancel')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        try {
            $return = ReturnRequest::findOrFail($id);
            app(ReturnService::class)->cancel($return, 'Annulé depuis la liste', $this->tenantUserId());
            session()->flash('success', 'Retour annulé.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $returns = ReturnRequest::query()
            ->with(['client'])
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('return_number', 'like', '%' . $this->search . '%')
                        ->orWhere('source_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', '%' . $this->search . '%'));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->clientFilter, fn ($q) => $q->where('client_id', $this->clientFilter))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('return_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('return_date', '<=', $this->dateTo))
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->paginate($this->perPage);

        return view('inovcom-returns::livewire.returns.index')
            ->layout('layouts.app', [
                'title' => 'Retours & Avoirs',
                'subtitle' => 'Retours clients, avoirs et remboursements',
            ])
            ->with([
                'returns' => $returns,
                'clients' => $this->clientOptions(),
                'statuses' => ReturnStatus::options(),
                'canCreate' => $this->can('returns.create'),
                'canCancel' => $this->can('returns.cancel'),
            ]);
    }

    private function clientOptions()
    {
        if (! class_exists(\InovCom\Clients\Models\Client::class)) {
            return collect();
        }

        return \InovCom\Clients\Models\Client::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
