<?php

namespace InovCom\Sales\Http\Livewire;

use InovCom\Sales\Models\SaleReturn;
use Livewire\Component;
use Livewire\WithPagination;

class SaleReturnsIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = SaleReturn::with(['sale.client'])
            ->where('status', SaleReturn::STATUS_CONFIRMED)
            ->orderByDesc('return_date')
            ->orderByDesc('id');

        if (trim($this->search) !== '') {
            $term = '%' . strtolower(trim($this->search)) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(return_number) LIKE ?', [$term])
                    ->orWhereHas('sale', fn ($s) => $s->whereRaw('LOWER(sale_number) LIKE ?', [$term]));
            });
        }

        return view('inovcom-sales::livewire.sales.returns-index')
            ->layout('layouts.app', [
                'title' => 'Retours produits',
                'subtitle' => 'Historique des retours clients',
            ])
            ->with([
                'returns' => $query->paginate(20),
                'tenantCode' => request()->query('tenant')
                    ?? session()->get('tenant_code')
                    ?? optional(request()->attributes->get('tenant'))->code,
            ]);
    }
}
