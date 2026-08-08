<?php

namespace InovCom\Purchases\Http\Livewire;

use InovCom\Purchases\Models\PurchaseOrder;
use InovCom\Purchases\Services\PurchasesService;
use Livewire\Component;

class ReceiptForm extends Component
{
    public PurchaseOrder $purchase;
    public array $receivedQuantities = [];
    public array $batchNumbers = [];
    public array $expiryDates = [];
    public string $receipt_date = '';
    public ?string $notes = null;

    public function mount(PurchaseOrder $purchase): void
    {
        $this->purchase = $purchase->load('lines.item');
        $this->receipt_date = now()->format('Y-m-d');

        if (!$this->purchase->canReceive()) {
            session()->flash('error', 'Cette commande ne peut pas être réceptionnée.');
            $this->redirect(route('tenant.purchases.show', [
                $purchase->id,
                'tenant' => $this->tenantCode(),
            ]), navigate: true);
            return;
        }

        foreach ($this->purchase->lines as $line) {
            if ($line->remaining_quantity > 0) {
                $this->receivedQuantities[$line->id] = (string) $line->remaining_quantity;
                $this->batchNumbers[$line->id] = '';
                $this->expiryDates[$line->id] = '';
            }
        }
    }

    public function receive(): void
    {
        $data = $this->validate([
            'receipt_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'receivedQuantities.*' => 'nullable|numeric|min:0',
            'batchNumbers.*' => 'nullable|string|max:100',
            'expiryDates.*' => 'nullable|date',
        ]);

        // Filter out zero quantities
        $quantities = array_filter($this->receivedQuantities, function ($qty) {
            return (float) $qty > 0;
        });

        if (empty($quantities)) {
            session()->flash('error', 'Aucune quantité à réceptionner.');
            return;
        }

        $lotInfo = [];
        foreach ($quantities as $lineId => $qty) {
            $lotInfo[$lineId] = [
                'batch_number' => $this->batchNumbers[$lineId] ?? '',
                'expiry_date' => $this->expiryDates[$lineId] ?? '',
            ];
        }

        $purchasesService = app(PurchasesService::class);
        try {
            $receipt = $purchasesService->receiveGoods(
                $this->purchase->id,
                $quantities,
                $data['notes'],
                null,
                $data['receipt_date'],
                $lotInfo
            );
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        session()->flash('success', 'Marchandises réceptionnées : ' . $receipt->receipt_number);
        $this->redirect(route('tenant.purchases.show', [
            $this->purchase->id,
            'tenant' => $this->tenantCode(),
        ]), navigate: true);
    }

    public function render()
    {
        return view('inovcom-purchases::livewire.purchases.receipt', [
            'lineRequiresLot' => $this->lineRequiresLotMap(),
        ])
            ->layout('layouts.app', [
                'title' => 'Réception commande',
                'subtitle' => $this->purchase->order_number,
            ]);
    }

    /**
     * @return array<int, bool>
     */
    private function lineRequiresLotMap(): array
    {
        $batchesApi = app()->bound(\InovCom\Kernel\Contracts\BatchesApi::class)
            ? app(\InovCom\Kernel\Contracts\BatchesApi::class)
            : null;
        $batchesAvailable = $batchesApi && $batchesApi->isAvailable();
        $map = [];

        foreach ($this->purchase->lines as $line) {
            $item = $line->item;
            $batchTracked = $item && is_array($item->metadata ?? null) && ! empty($item->metadata['batch_tracked']);
            $map[$line->id] = $batchesAvailable && $batchTracked;
        }

        return $map;
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
