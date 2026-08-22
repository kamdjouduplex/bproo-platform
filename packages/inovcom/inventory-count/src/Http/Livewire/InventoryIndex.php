<?php

namespace InovCom\Inventory\Http\Livewire;

use InovCom\Inventory\Models\StockCount;
use InovCom\Inventory\Services\InventoryPaperSheetService;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public int $perPage = 20;

    public bool $blindExport = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->blindExport = false;
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function delete(int $countId): void
    {
        $count = StockCount::findOrFail($countId);

        if (! $count->isDraft() && ! $count->isCancelled()) {
            session()->flash('error', 'Seuls les inventaires en brouillon ou annulés peuvent être supprimés.');

            return;
        }

        $count->delete();
        $this->resetPage();
    }

    public function exportPaperExcel()
    {
        $sheet = app(InventoryPaperSheetService::class);
        $showExpected = ! $this->blindExport;
        $rows = $sheet->buildRows(null, $showExpected, false);
        $title = 'Feuille de comptage — catalogue actif';

        return $sheet->streamExcel(
            $rows,
            $title,
            $showExpected,
            false,
            'feuille-inventaire-catalogue-'.now()->format('Ymd_His').'.xls'
        );
    }

    public function exportPaperPdf()
    {
        $sheet = app(InventoryPaperSheetService::class);
        $showExpected = ! $this->blindExport;
        $rows = $sheet->buildRows(null, $showExpected, false);

        $response = $sheet->streamPdf(
            $rows,
            'Feuille de comptage — catalogue actif',
            $showExpected,
            false,
            $showExpected ? 'Stock système affiché' : 'Comptage à l’aveugle',
            'feuille-inventaire-catalogue-'.now()->format('Ymd_His').'.pdf'
        );

        if (! $response) {
            session()->flash('error', 'Export PDF impossible. Réessayez.');

            return null;
        }

        return $response;
    }

    public function render()
    {
        $counts = StockCount::query()
            ->with(['starter', 'completer'])
            ->when($this->search !== '', function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($q) use ($term) {
                    $q->where('reference', 'like', $term)
                        ->orWhere('title', 'like', $term);
                });
            })
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
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
