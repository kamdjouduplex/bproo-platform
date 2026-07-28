<?php

namespace InovCom\Inventory\Http\Livewire;

use InovCom\Inventory\Models\StockCount;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all'; // all, draft, in_progress, completed, cancelled
    public int $perPage = 20;

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function delete(int $countId): void
    {
        $count = StockCount::findOrFail($countId);
        
        if (!$count->isDraft() && !$count->isCancelled()) {
            session()->flash('error', 'Seuls les inventaires en brouillon ou annulés peuvent être supprimés.');
            return;
        }

        $count->delete();
        $this->resetPage();
    }

    public function render()
    {
        $counts = StockCount::query()
            ->with(['starter', 'completer'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('reference', 'like', '%' . $this->search . '%')
                        ->orWhere('title', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        return view('inovcom-inventory::livewire.inventory.index')
            ->layout('layouts.app', [
                'title' => 'Inventaires',
                'subtitle' => 'Gestion des inventaires de stock',
            ])
            ->with(['counts' => $counts]);
    }
}
