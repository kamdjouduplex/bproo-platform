<?php

namespace InovCom\Sales\Http\Livewire;

use InovCom\Sales\Models\Sale;
use InovCom\Sales\Models\SuspendedSale;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class SalesIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public int $perPage = 20;

    public function mount(): void
    {
        $this->dateFrom = now()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function applySearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function setPeriod(string $period): void
    {
        $now = now();
        switch ($period) {
            case 'day':
                $this->dateFrom = $now->format('Y-m-d');
                $this->dateTo = $now->format('Y-m-d');
                break;
            case 'week':
                $this->dateFrom = $now->copy()->startOfWeek()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $this->dateFrom = $now->copy()->startOfMonth()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfMonth()->format('Y-m-d');
                break;
            case 'year':
                $this->dateFrom = $now->copy()->startOfYear()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfYear()->format('Y-m-d');
                break;
        }
        $this->resetPage();
    }

    public function deleteSuspended(int $id): void
    {
        if (!Schema::connection('tenant')->hasTable('suspended_sales')) {
            return;
        }
        $suspended = SuspendedSale::find($id);
        if ($suspended) {
            $suspended->delete();
            session()->flash('success', 'Vente suspendue supprimée.');
        }
    }

    public function render()
    {
        $suspendedSales = collect([]);
        if (Schema::connection('tenant')->hasTable('suspended_sales')) {
            $suspendedSales = SuspendedSale::with('user')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();
        }

        $sales = Sale::query()
            ->with(['client', 'creator', 'payments'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('sale_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('client', function ($q2) {
                            $q2->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            }, function ($query) {
                $query->when($this->dateFrom, fn ($q) => $q->whereDate('sale_date', '>=', $this->dateFrom))
                    ->when($this->dateTo, fn ($q) => $q->whereDate('sale_date', '<=', $this->dateTo));
            })
            ->orderBy('sale_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($this->perPage);

        return view('inovcom-sales::livewire.sales.index')
            ->layout('layouts.app', [
                'title' => 'Ventes',
                'subtitle' => 'Historique des ventes',
            ])
            ->with([
                'sales' => $sales,
                'suspendedSales' => $suspendedSales,
            ]);
    }
}
