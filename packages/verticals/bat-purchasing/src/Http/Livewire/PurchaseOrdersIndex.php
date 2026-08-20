<?php

namespace InovCom\Achats\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Achats\Models\PurchaseOrder;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseOrdersIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public string $search  = '';
    public int    $perPage = 10;
    public ?int   $projectFilter = null;

    public function mount(): void
    {
        $this->tenantAuthorize('achats.view');
        if (request()->filled('project')) {
            $this->projectFilter = (int) request()->query('project');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $this->tenantAuthorize('achats.delete');
        $po = PurchaseOrder::on('tenant')->findOrFail($id);
        $po->lines()->delete();
        $po->delete();
        notify()->success(__('Bon de commande supprimé.'));
    }

    public function render()
    {
        $purchaseOrders = PurchaseOrder::on('tenant')
            ->with(['supplier', 'project'])
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('code', 'like', '%' . $this->search . '%')
                        ->orWhereHas('supplier', fn ($q) => $q
                            ->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('code', 'like', '%' . $this->search . '%'));
                });
            })
            ->ordered()
            ->paginate($this->perPage);

        return view('inovcom-achats::livewire.purchase-orders.index')
            ->layout('layouts.app', [
                'title'    => __('Achats'),
                'subtitle' => __('Bons de commande'),
            ])
            ->with([
                'purchaseOrders' => $purchaseOrders,
                'projectFilter'  => $this->projectFilter,
            ]);
    }
}
