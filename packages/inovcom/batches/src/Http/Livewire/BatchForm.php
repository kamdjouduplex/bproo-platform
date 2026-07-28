<?php

namespace InovCom\Batches\Http\Livewire;

use InovCom\Kernel\Contracts\BatchesApi;
use Livewire\Component;

class BatchForm extends Component
{
    public ?int $item_id = null;
    public string $batch_number = '';
    public string $expiry_date = '';
    public string $quantity = '';

    public function save(): void
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

        $api = app(BatchesApi::class);
        if (!$api->isAvailable()) {
            session()->flash('error', 'Le module Lots n\'est pas disponible.');
            return;
        }

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

    public function render()
    {
        $items = \InovCom\Items\Models\Item::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        return view('inovcom-batches::livewire.batches.form')
            ->layout('layouts.app', [
                'title' => 'Nouveau lot',
                'subtitle' => 'Pharmacie',
            ])
            ->with('items', $items);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
