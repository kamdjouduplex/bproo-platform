<?php

namespace InovCom\Losses\Http\Livewire;

use InovCom\Items\Models\Item;
use InovCom\Losses\Models\LossRecord;
use InovCom\Losses\Models\LossReason;
use InovCom\Losses\Services\LossesService;
use Livewire\Component;

class LossesForm extends Component
{
    public ?int $recordId = null;

    public ?int $item_id = null;
    public ?int $loss_reason_id = null;
    public string $quantity = '0';
    public string $value = '0';
    public string $loss_date = '';
    public ?string $description = null;

    public string $itemSearch = '';
    public array $searchResults = [];

    public function mount(?LossRecord $loss_record = null): void
    {
        if (!$loss_record) {
            $this->loss_date = now()->format('Y-m-d');
            return;
        }

        $this->recordId = $loss_record->id;
        $this->item_id = $loss_record->item_id;
        $this->loss_reason_id = $loss_record->loss_reason_id;
        $this->quantity = (string) $loss_record->quantity;
        $this->value = (string) $loss_record->value;
        $this->loss_date = $loss_record->loss_date->format('Y-m-d');
        $this->description = $loss_record->description;
    }

    public function updatedItemSearch(): void
    {
        if (strlen(trim($this->itemSearch)) < 1) {
            $this->searchResults = [];
            return;
        }

        $term = trim($this->itemSearch);

        $items = Item::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($term) . '%'])
                ->orWhereRaw('LOWER(sku) LIKE ?', ['%' . strtolower($term) . '%'])
                ->orWhereRaw('LOWER(barcode) LIKE ?', ['%' . strtolower($term) . '%']))
            ->orderBy('name')
            ->limit(10)
            ->get();

        $this->searchResults = $items->map(fn ($i) => [
            'id' => $i->id,
            'name' => $i->name,
            'sku' => $i->sku,
            'barcode' => $i->barcode,
            'cost' => (string) ($i->cost ?? 0),
        ])->toArray();
    }

    public function selectItem(array $item): void
    {
        $this->item_id = $item['id'];
        $this->itemSearch = $item['name'];
        $this->searchResults = [];

        if ((float) $this->value <= 0 && (float) ($item['cost'] ?? 0) > 0 && (float) $this->quantity > 0) {
            $this->value = (string) ((float) $this->quantity * (float) $item['cost']);
        }
    }

    public function selectItemById(int $id): void
    {
        $item = Item::find($id);
        if (!$item) {
            return;
        }
        $this->selectItem([
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku ?? '',
            'barcode' => $item->barcode ?? '',
            'cost' => (string) ($item->cost ?? 0),
        ]);
    }

    public function updatedQuantity(): void
    {
        if ($this->item_id && (float) $this->quantity > 0) {
            $item = Item::find($this->item_id);
            if ($item && $item->cost) {
                $this->value = (string) ((float) $this->quantity * (float) $item->cost);
            }
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'item_id' => 'required|exists:tenant.items,id',
            'loss_reason_id' => 'required|exists:tenant.loss_reasons,id',
            'quantity' => 'required|numeric|min:0.001',
            'value' => 'nullable|numeric|min:0',
            'loss_date' => 'required|date',
            'description' => 'nullable|string|max:1000',
        ], [], [
            'item_id' => 'article',
            'loss_reason_id' => 'raison',
            'quantity' => 'quantité',
            'value' => 'valeur',
            'loss_date' => 'date',
            'description' => 'description',
        ]);

        $data['value'] = (float) ($data['value'] ?? 0);

        $service = app(LossesService::class);

        if ($this->recordId) {
            $record = LossRecord::findOrFail($this->recordId);
            if (!$record->isDraft()) {
                session()->flash('error', 'Seules les pertes en brouillon peuvent être modifiées.');
                return;
            }
            $record->update($data);
        } else {
            $service->createLossRecord($data);
        }

        $this->redirect(route('tenant.losses.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    public function render()
    {
        $reasons = LossReason::where('is_active', true)->orderBy('name')->get();

        return view('inovcom-losses::livewire.losses.form')
            ->layout('layouts.app', [
                'title' => $this->recordId ? 'Modifier perte' : 'Nouvelle perte',
                'subtitle' => 'Gestion des pertes',
            ])
            ->with(['reasons' => $reasons]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
