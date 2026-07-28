<?php

namespace InovCom\Purchases\Http\Livewire;

use InovCom\Purchases\Concerns\AuthorizesForeignPurchases;
use InovCom\Purchases\Models\ForeignPurchaseOrder;
use InovCom\Purchases\Services\ForeignPurchasesService;
use Livewire\Component;

class ForeignReceiptForm extends Component
{
    use AuthorizesForeignPurchases;

    public ForeignPurchaseOrder $order;
    public array $receivedQuantities = [];
    public string $receipt_date = '';
    public ?string $notes = null;

    public function mount(ForeignPurchaseOrder $foreignPurchase): void
    {
        $this->order = $foreignPurchase->load('lines.item');
        $this->receipt_date = now()->format('Y-m-d');

        if (!$this->canForeignPurchase('foreign_purchases.receive')) {
            session()->flash('error', 'Permission refusée.');
            $this->redirect(route('tenant.foreign_purchases.show', [
                $foreignPurchase->id,
                'tenant' => $this->tenantCode(),
            ]), navigate: true);

            return;
        }

        if (!$this->order->canReceive()) {
            session()->flash('error', 'Cette commande ne peut pas être réceptionnée.');
            $this->redirect(route('tenant.foreign_purchases.show', [
                $foreignPurchase->id,
                'tenant' => $this->tenantCode(),
            ]), navigate: true);

            return;
        }

        foreach ($this->order->lines as $line) {
            if ($line->remaining_quantity > 0) {
                $this->receivedQuantities[$line->id] = (string) $line->remaining_quantity;
            }
        }
    }

    public function receive(): void
    {
        if (!$this->canForeignPurchase('foreign_purchases.receive')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $data = $this->validate([
            'receipt_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'receivedQuantities.*' => 'nullable|numeric|min:0',
        ]);

        $quantities = array_filter($this->receivedQuantities, fn ($qty) => (float) $qty > 0);

        if (empty($quantities)) {
            session()->flash('error', 'Aucune quantité à réceptionner.');
            return;
        }

        try {
            $receipt = app(ForeignPurchasesService::class)->receiveGoods(
                $this->order->id,
                $quantities,
                $data['notes'],
                null,
                $data['receipt_date']
            );
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        session()->flash('success', 'Marchandises réceptionnées : ' . $receipt->receipt_number);
        $this->redirect(route('tenant.foreign_purchases.show', [
            $this->order->id,
            'tenant' => $this->tenantCode(),
        ]), navigate: true);
    }

    public function render()
    {
        return view('inovcom-purchases::livewire.foreign.receipt')
            ->layout('layouts.app', [
                'title' => 'Réception achat étranger',
                'subtitle' => $this->order->order_number,
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
