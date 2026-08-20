<?php

namespace InovCom\Facturation\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Facturation\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;

class InvoicesIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public function mount(): void
    {
        $this->tenantAuthorize('facturation.view');
        if (request()->filled('project')) {
            $this->projectFilter = (int) request()->query('project');
        }
    }

    public string $search = '';
    public int $perPage = 10;
    public ?int $projectFilter = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->lines()->delete();
        $invoice->delete();
        session()->flash('message', __('Facture supprimée.'));
    }

    public function render()
    {
        $invoices = Invoice::query()
            ->with(['client', 'project', 'quote'])
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('code', 'like', '%' . $this->search . '%')
                        ->orWhere('title', 'like', '%' . $this->search . '%');
                });
            })
            ->ordered()
            ->paginate($this->perPage);

        return view('inovcom-facturation::livewire.invoices.index')
            ->layout('layouts.app', [
                'title' => __('Facturation'),
                'subtitle' => __('Factures, suivi des paiements.'),
            ])
            ->with([
                'invoices' => $invoices,
                'projectFilter' => $this->projectFilter,
            ]);
    }
}
