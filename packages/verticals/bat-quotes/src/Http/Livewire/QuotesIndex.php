<?php

namespace InovCom\Devis\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Devis\Models\Quote;
use InovCom\Devis\Services\QuoteDuplicationService;
use Livewire\Component;
use Livewire\WithPagination;

class QuotesIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public function mount(): void
    {
        $this->tenantAuthorize('devis.view');
    }

    public string $search = '';
    public string $statusFilter = '';
    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function duplicate(int $id): void
    {
        $this->tenantAuthorize('devis.create');
        $source = Quote::on('tenant')->findOrFail($id);
        $newQuote = app(QuoteDuplicationService::class)->copyAsNew($source);

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        notify()->success(__('Copie créée : :code', ['code' => $newQuote->code]));

        $this->redirect(
            route('tenant.devis.edit', ['tenant' => $tenantCode, 'quote' => $newQuote->id]),
            navigate: true
        );
    }

    public function render()
    {
        $quotes = Quote::on('tenant')
            ->with(['client', 'offer'])
            ->latestVersionOnly()
            ->selectRaw('quotes.*, (
                SELECT COUNT(*)
                FROM quotes qf
                WHERE COALESCE(qf.parent_id, qf.id) = COALESCE(quotes.parent_id, quotes.id)
            ) as family_size')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('title', 'like', '%' . $this->search . '%')
                        ->orWhere('code', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->ordered()
            ->paginate($this->perPage);

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        return view('inovcom-devis::livewire.quotes.index', [
            'quotes' => $quotes,
            'tenantCode' => $tenantCode,
            'canImport' => $this->tenantCan('devis.import'),
            'canCreate' => $this->tenantCan('devis.create'),
        ])->layout('layouts.app', [
            'title' => __('Devis'),
            'subtitle' => __('Élaboration et soumission des devis. Historique et statuts.'),
        ]);
    }
}
