<?php

namespace InovCom\Batches\Http\Livewire;

use InovCom\Batches\Models\Batch;
use Livewire\Component;
use Livewire\WithPagination;

class BatchesIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filter = 'all'; // all, near_expiry, expired

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Batch::query()->with('item')
            ->when($this->search !== '', function ($q) {
                $q->whereHas('item', function ($q2) {
                    $q2->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('sku', 'like', '%' . $this->search . '%')
                        ->orWhere('barcode', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filter === 'near_expiry', function ($q) {
                $q->where('quantity', '>', 0)
                    ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(90)->toDateString()]);
            })
            ->when($this->filter === 'expired', function ($q) {
                $q->where('quantity', '>', 0)->where('expiry_date', '<', now()->toDateString());
            })
            ->orderBy('expiry_date')->orderBy('batch_number');

        $batches = $query->paginate(20);

        return view('inovcom-batches::livewire.batches.index')
            ->layout('layouts.app', [
                'title' => 'Lots / Péremption',
                'subtitle' => 'Pharmacie',
            ])
            ->with('batches', $batches);
    }
}
