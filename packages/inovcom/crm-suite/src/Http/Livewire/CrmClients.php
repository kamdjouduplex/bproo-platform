<?php

namespace InovCom\Crm\Http\Livewire;

use InovCom\Clients\Models\Client;
use InovCom\Crm\Concerns\AuthorizesCrmActions;
use InovCom\Crm\Models\Opportunity;
use InovCom\Crm\Support\CrmVisibility;
use InovCom\Prospects\Models\Prospect;
use Livewire\Component;
use Livewire\WithPagination;

class CrmClients extends Component
{
    use AuthorizesCrmActions;
    use WithPagination;

    public string $search = '';

    public int $perPage = 12;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->authorizeCrm('crm.view');
        $visibility = app(CrmVisibility::class);

        $query = Client::query()
            ->when(! $visibility->seesAll() && $visibility->currentUserId(), function ($q) use ($visibility) {
                $q->where('salesrep_id', $visibility->currentUserId());
            })
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.mb_strtolower(trim($this->search)).'%';
                $q->where(function ($q2) use ($term) {
                    $q2->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term]);
                });
            })
            ->orderBy('name');

        $clients = $query->paginate($this->perPage);
        $ids = $clients->getCollection()->pluck('id');

        $prospectsByClient = Prospect::query()
            ->with(['nextPlannedActivity', 'owner'])
            ->whereIn('converted_client_id', $ids)
            ->get()
            ->keyBy('converted_client_id');

        $oppsByClient = Opportunity::query()
            ->whereIn('client_id', $ids)
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('client_id');

        return view('inovcom-crm::livewire.clients', [
            'clients' => $clients,
            'prospectsByClient' => $prospectsByClient,
            'oppsByClient' => $oppsByClient,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'Clients',
            'subtitle' => 'Vue relationnelle — les devis et factures restent dans l’ERP',
        ]);
    }
}
