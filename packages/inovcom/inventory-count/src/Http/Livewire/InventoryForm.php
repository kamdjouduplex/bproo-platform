<?php

namespace InovCom\Inventory\Http\Livewire;

use InovCom\Inventory\Models\StockCount;
use InovCom\Inventory\Services\InventoryPaperSheetService;
use InovCom\Inventory\Services\InventoryService;
use InovCom\Items\Models\Item;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryForm extends Component
{
    use WithPagination;

    public ?int $countId = null;

    public string $title = '';

    public ?string $description = null;

    public bool $allow_operations = true;

    public array $lines = [];

    public string $itemSearch = '';

    public array $searchResults = [];

    public int $perPage = 20;

    /** Masquer le stock système sur la feuille papier (comptage à l'aveugle). */
    public bool $blindExport = false;

    public function mount(?StockCount $stock_count = null): void
    {
        if (! $stock_count) {
            return;
        }

        $this->countId = $stock_count->id;
        $this->title = $stock_count->title;
        $this->description = $stock_count->description;
        $this->allow_operations = $stock_count->allow_operations;

        if ($stock_count->isInProgress() || $stock_count->isCompleted()) {
            $this->loadLines();
        }
    }

    public function loadLines(): void
    {
        $count = StockCount::findOrFail($this->countId);
        $this->lines = $count->lines()
            ->with(['item.unit', 'counter'])
            ->orderBy('item_id')
            ->get()
            ->map(function ($line) {
                return [
                    'id' => $line->id,
                    'item_id' => $line->item_id,
                    'item_name' => $line->item->name,
                    'item_sku' => $line->item->sku,
                    'item_unit' => $line->item->unit?->abbreviation ?? 'pc',
                    'expected_quantity' => (string) $line->expected_quantity,
                    'counted_quantity' => $line->counted_quantity !== null ? (string) $line->counted_quantity : '',
                    'difference' => (string) $line->difference,
                    'value_difference' => (string) $line->value_difference,
                    'notes' => $line->notes ?? '',
                    'is_counted' => $line->isCounted(),
                ];
            })
            ->toArray();
    }

    public function updatedItemSearch(): void
    {
        if (strlen(trim($this->itemSearch)) < 1) {
            $this->searchResults = [];

            return;
        }

        $searchTerm = trim($this->itemSearch);

        $items = Item::query()
            ->where('is_active', true)
            ->where(function ($query) use ($searchTerm) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($searchTerm).'%'])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ['%'.strtolower($searchTerm).'%'])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', ['%'.strtolower($searchTerm).'%']);
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        $this->searchResults = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
            ];
        })->toArray();
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'allow_operations' => 'boolean',
        ]);

        $inventoryService = app(InventoryService::class);

        if ($this->countId) {
            $count = StockCount::findOrFail($this->countId);
            $count->update($data);
        } else {
            $count = $inventoryService->createStockCount($data);
            $this->countId = $count->id;
        }

        $this->redirect(route('tenant.inventory.edit', ['tenant' => $this->tenantCode(), 'stock_count' => $count->id]), navigate: true);
    }

    public function startCount(): void
    {
        if (! $this->countId) {
            session()->flash('error', 'Veuillez d\'abord sauvegarder l\'inventaire.');

            return;
        }

        try {
            app(InventoryService::class)->startStockCount($this->countId);
            $this->loadLines();
            session()->flash('success', 'Inventaire démarré avec succès.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function updateLine(int $lineIndex): void
    {
        if (! isset($this->lines[$lineIndex])) {
            return;
        }

        $line = $this->lines[$lineIndex];

        $data = $this->validate([
            "lines.{$lineIndex}.counted_quantity" => 'nullable|numeric|min:0',
            "lines.{$lineIndex}.notes" => 'nullable|string|max:500',
        ], [], [
            "lines.{$lineIndex}.counted_quantity" => 'quantité comptée',
            "lines.{$lineIndex}.notes" => 'notes',
        ]);

        try {
            app(InventoryService::class)->updateCountLine(
                $this->countId,
                $line['item_id'],
                (float) ($data['lines'][$lineIndex]['counted_quantity'] ?? 0),
                $data['lines'][$lineIndex]['notes'] ?? null
            );

            $this->loadLines();
            session()->flash('success', 'Ligne mise à jour avec succès.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function completeCount(bool $applyAdjustments = true): void
    {
        if (! $this->countId) {
            return;
        }

        try {
            app(InventoryService::class)->completeStockCount($this->countId, $applyAdjustments);
            session()->flash('success', 'Inventaire finalisé avec succès.');
            $this->redirect(route('tenant.inventory.index', ['tenant' => $this->tenantCode()]), navigate: true);
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function exportPaperExcel()
    {
        $sheet = app(InventoryPaperSheetService::class);
        [$count, $rows, $showExpected, $includeCounted, $title, $filename] = $this->prepareSheetPayload();

        return $sheet->streamExcel($rows, $title, $showExpected, $includeCounted, $filename.'.xls');
    }

    public function exportPaperPdf()
    {
        $sheet = app(InventoryPaperSheetService::class);
        [$count, $rows, $showExpected, $includeCounted, $title, $filename] = $this->prepareSheetPayload();

        $response = $sheet->streamPdf(
            $rows,
            $title,
            $showExpected,
            $includeCounted,
            $count?->reference,
            $filename.'.pdf'
        );

        if (! $response) {
            session()->flash('error', 'Export PDF impossible. Réessayez ou réduisez le catalogue.');

            return null;
        }

        return $response;
    }

    public function render()
    {
        $count = $this->countId ? StockCount::find($this->countId) : null;

        return view('inovcom-inventory::livewire.inventory.form')
            ->layout('layouts.app', [
                'title' => $this->countId ? 'Modifier inventaire' : 'Nouvel inventaire',
                'subtitle' => 'Gestion des inventaires',
            ])
            ->with([
                'count' => $count,
                'lines' => $this->lines,
            ]);
    }

    /**
     * @return array{0: ?StockCount, 1: list<array<string, mixed>>, 2: bool, 3: bool, 4: string, 5: string}
     */
    private function prepareSheetPayload(): array
    {
        $count = $this->countId ? StockCount::find($this->countId) : null;
        $includeCounted = $count?->isCompleted() ?? false;
        $showExpected = ! $this->blindExport;
        $rows = app(InventoryPaperSheetService::class)->buildRows($count, $showExpected, $includeCounted);

        $title = $count
            ? trim(($count->reference ? $count->reference.' — ' : '').($count->title ?: 'Inventaire'))
            : 'Feuille de comptage inventaire';

        $slug = $count?->reference ? strtolower(preg_replace('/[^a-zA-Z0-9\-]+/', '-', $count->reference) ?? 'inv') : 'catalogue';
        $filename = 'feuille-inventaire-'.$slug.'-'.now()->format('Ymd_His');

        return [$count, $rows, $showExpected, $includeCounted, $title, $filename];
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
