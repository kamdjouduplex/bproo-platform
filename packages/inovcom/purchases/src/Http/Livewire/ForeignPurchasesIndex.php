<?php

namespace InovCom\Purchases\Http\Livewire;

use App\Services\ModuleRegistry;
use InovCom\Purchases\Concerns\AuthorizesForeignPurchases;
use InovCom\Purchases\Models\ForeignPurchaseOrder;
use InovCom\Purchases\Services\ForeignPurchasesService;
use Livewire\Component;
use Livewire\WithPagination;

class ForeignPurchasesIndex extends Component
{
    use AuthorizesForeignPurchases;
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
        $orders = ForeignPurchaseOrder::query()
            ->with(['provider', 'creator', 'lines'])
            ->when($this->search !== '', function ($query) {
                $term = '%'.mb_strtolower(trim($this->search)).'%';
                $query->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(order_number) LIKE ?', [$term])
                        ->orWhereHas('provider', function ($q2) use ($term) {
                            $q2->whereRaw('LOWER(name) LIKE ?', [$term]);
                        });
                });
            }, function ($query) {
                $query->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
                    ->when($this->dateFrom, fn ($q) => $q->whereDate('order_date', '>=', $this->dateFrom))
                    ->when($this->dateTo, fn ($q) => $q->whereDate('order_date', '<=', $this->dateTo));
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate($this->perPage);

        $tenant = request()->attributes->get('tenant');

        return view('inovcom-purchases::livewire.foreign.index')
            ->layout('layouts.app', [
                'title' => 'Achats étrangers',
                'subtitle' => 'Commandes en devises',
            ])
            ->with([
                'orders' => $orders,
                'canCreate' => $this->canForeignPurchase('foreign_purchases.create'),
                'canView' => $this->canForeignPurchase('foreign_purchases.view')
                    || $this->canModifyForeignPurchase(),
                'canReceive' => $this->canForeignPurchase('foreign_purchases.receive'),
                'canViewPurchases' => app(ModuleRegistry::class)->isEnabled('purchases', $tenant),
            ]);
    }
}
