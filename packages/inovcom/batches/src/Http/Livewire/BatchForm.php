<?php

namespace InovCom\Batches\Http\Livewire;

use InovCom\Batches\BatchesModule;
use InovCom\Batches\Models\Batch;
use InovCom\Kernel\Contracts\BatchesApi;
use Livewire\Component;

class BatchForm extends Component
{
    public ?int $batchId = null;

    public ?int $item_id = null;

    public string $batch_number = '';

    public string $expiry_date = '';

    public string $quantity = '';

    public string $item_label = '';

    public function mount(?Batch $batch = null): void
    {
        BatchesModule::syncPermissions();

        if (! $batch) {
            return;
        }

        if (! $this->canUpdateExpiry()) {
            session()->flash('error', 'Vous n’avez pas le droit de modifier les lots.');
            $this->redirect(route('tenant.batches.index', ['tenant' => $this->tenantCode()]), navigate: true);

            return;
        }

        $batch->loadMissing('item');
        $this->batchId = $batch->id;
        $this->item_id = (int) $batch->item_id;
        $this->batch_number = (string) $batch->batch_number;
        $this->expiry_date = $batch->expiry_date->format('Y-m-d');
        $this->quantity = (string) $batch->quantity;
        $this->item_label = $batch->item
            ? item_display($batch->item->sku, $batch->item->name)
            : '—';
    }

    public function save(): void
    {
        $api = app(BatchesApi::class);
        if (! $api->isAvailable()) {
            session()->flash('error', 'Le module Lots n\'est pas disponible.');

            return;
        }

        if ($this->batchId) {
            $this->saveExpiryCorrection($api);

            return;
        }

        $this->saveNewBatch($api);
    }

    private function saveNewBatch(BatchesApi $api): void
    {
        $data = $this->validate([
            'item_id' => 'required|exists:tenant.items,id',
            'batch_number' => 'required|string|max:100',
            'expiry_date' => 'required|date|after_or_equal:today',
            'quantity' => 'required|numeric|min:0.001',
        ], [
            'item_id.required' => 'Choisissez un article.',
            'batch_number.required' => 'Le numéro de lot est obligatoire.',
            'expiry_date.required' => 'La date de péremption est obligatoire.',
            'expiry_date.after_or_equal' => 'La date de péremption doit être aujourd\'hui ou plus tard.',
            'quantity.required' => 'La quantité est obligatoire.',
            'quantity.min' => 'La quantité doit être supérieure à zéro.',
        ]);

        $api->recordReceipt(
            (int) $data['item_id'],
            $data['batch_number'],
            \Carbon\Carbon::parse($data['expiry_date']),
            (float) $data['quantity'],
            'manual',
            0
        );

        session()->flash('success', 'Lot enregistré.');
        $this->redirect(route('tenant.batches.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    private function saveExpiryCorrection(BatchesApi $api): void
    {
        if (! $this->canUpdateExpiry()) {
            session()->flash('error', 'Vous n’avez pas le droit de modifier la date de péremption.');

            return;
        }

        $data = $this->validate([
            'expiry_date' => 'required|date',
        ], [
            'expiry_date.required' => 'La date de péremption est obligatoire.',
            'expiry_date.date' => 'La date de péremption est invalide.',
        ]);

        try {
            $api->updateExpiryDate((int) $this->batchId, \Carbon\Carbon::parse($data['expiry_date']));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', 'Date de péremption mise à jour.');
        $this->redirect(route('tenant.batches.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    public function canUpdateExpiry(): bool
    {
        $user = auth('tenant')->user();
        if (! $user) {
            return false;
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }
        if (method_exists($user, 'hasPermission')) {
            return $user->hasPermission('batches.update');
        }

        return false;
    }

    public function render()
    {
        $items = [];
        if (! $this->batchId) {
            $items = \InovCom\Items\Models\Item::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku']);
        }

        return view('inovcom-batches::livewire.batches.form')
            ->layout('layouts.app', [
                'title' => $this->batchId ? 'Modifier la péremption' : 'Nouveau lot',
                'subtitle' => 'Pharmacie',
            ])
            ->with([
                'items' => $items,
                'isEdit' => (bool) $this->batchId,
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
