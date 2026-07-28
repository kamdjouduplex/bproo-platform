<?php

namespace InovCom\Purchases\Http\Livewire;

use App\Services\ModuleRegistry;
use InovCom\Purchases\Concerns\AuthorizesPurchases;
use InovCom\Purchases\Models\PurchaseOrder;
use InovCom\Purchases\Services\PurchasesService;
use Livewire\Component;
use Livewire\WithPagination;

class PurchasesIndex extends Component
{
    use AuthorizesPurchases;
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $dateFrom = '';
    public string $dateTo = '';
    public int $perPage = 20;

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function applySearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $orders = PurchaseOrder::query()
            ->with(['provider', 'creator', 'lines'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('order_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('provider', function ($q2) {
                            $q2->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            }, function ($query) {
                $query->when($this->statusFilter !== 'all', function ($q) {
                    if ($this->statusFilter === 'partial') {
                        $q->whereIn('status', [
                            PurchasesService::STATUS_PARTIAL,
                            PurchasesService::STATUS_SENT_LEGACY,
                        ]);
                    } else {
                        $q->where('status', $this->statusFilter);
                    }
                })
                    ->when($this->dateFrom, fn ($q) => $q->whereDate('order_date', '>=', $this->dateFrom))
                    ->when($this->dateTo, fn ($q) => $q->whereDate('order_date', '<=', $this->dateTo));
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate($this->perPage);

        $tenant = request()->attributes->get('tenant');

        return view('inovcom-purchases::livewire.purchases.index')
            ->layout('layouts.app', [
                'title' => 'Achats',
                'subtitle' => 'Commandes d\'achat',
            ])
            ->with([
                'orders' => $orders,
                'canCreate' => $this->canPurchase('purchases.create'),
                'canView' => $this->canPurchase('purchases.view'),
                'canReceive' => $this->canPurchase('purchases.receive'),
                'canUpdate' => $this->canPurchase('purchases.update'),
                'canCancel' => $this->canPurchase('purchases.cancel'),
                'canForeignPurchases' => app(ModuleRegistry::class)->isEnabled('foreign_purchases', $tenant)
                    && \Illuminate\Support\Facades\Route::has('tenant.foreign_purchases.index'),
            ]);
    }
}
