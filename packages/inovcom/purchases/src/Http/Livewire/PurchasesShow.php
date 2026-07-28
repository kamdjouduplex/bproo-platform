<?php

namespace InovCom\Purchases\Http\Livewire;

use InovCom\Purchases\Concerns\AuthorizesPurchases;
use InovCom\Purchases\Models\PurchaseOrder;
use InovCom\Purchases\Services\PurchasePriceHistoryService;
use InovCom\Purchases\Services\PurchasesService;
use Livewire\Component;

class PurchasesShow extends Component
{
    use AuthorizesPurchases;

    public PurchaseOrder $purchase;

    /** @var array<int, string> */
    public array $cancelQuantities = [];

    public string $cancelReason = '';
    public bool $reverseStock = true;
    public bool $showCancelModal = false;

    public function mount(PurchaseOrder $purchase): void
    {
        $this->purchase = $purchase->load([
            'provider',
            'creator',
            'lines.item',
            'receipts.lines.purchaseLine',
            'receipts.receiver',
        ]);

        foreach ($this->purchase->lines as $line) {
            $this->cancelQuantities[$line->id] = '0';
        }

        if (request()->boolean('cancel')) {
            $this->showCancelModal = true;
        }
    }

    public function confirmOrder(): void
    {
        if (!$this->canPurchase('purchases.confirm')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            app(PurchasesService::class)->confirmOrder($this->purchase->id);
            $this->refreshPurchase();
            session()->flash('success', 'Achat confirmé.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openCancelModal(): void
    {
        $this->showCancelModal = true;
    }

    public function closeCancelModal(): void
    {
        $this->showCancelModal = false;
    }

    public function cancelPartial(): void
    {
        if (!$this->canPurchase('purchases.cancel')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $quantities = [];
        foreach ($this->cancelQuantities as $lineId => $qty) {
            if ((float) $qty > 0) {
                $quantities[(int) $lineId] = (float) $qty;
            }
        }

        if (empty($quantities)) {
            session()->flash('error', 'Indiquez au moins une quantité à annuler.');
            return;
        }

        try {
            app(PurchasesService::class)->cancelOrderLines(
                $this->purchase->id,
                $quantities,
                $this->cancelReason ?: null,
                $this->reverseStock
            );
            $this->showCancelModal = false;
            $this->refreshPurchase();
            session()->flash('success', 'Annulation enregistrée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelEntire(): void
    {
        if (!$this->canPurchase('purchases.cancel')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            app(PurchasesService::class)->cancelEntireOrder(
                $this->purchase->id,
                $this->cancelReason ?: null,
                $this->reverseStock
            );
            $this->showCancelModal = false;
            $this->refreshPurchase();
            session()->flash('success', 'Commande entièrement annulée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    private function refreshPurchase(): void
    {
        $this->purchase = PurchaseOrder::with([
            'provider',
            'creator',
            'lines.item',
            'receipts.lines.purchaseLine',
            'receipts.receiver',
        ])->findOrFail($this->purchase->id);
    }

    public function render()
    {
        $priceHistory = app(PurchasePriceHistoryService::class);

        return view('inovcom-purchases::livewire.purchases.show')
            ->layout('layouts.app', [
                'title' => 'Commande ' . $this->purchase->order_number,
                'subtitle' => PurchasesService::statusLabel($this->purchase->status),
            ])
            ->with([
                'statusLabel' => PurchasesService::statusLabel($this->purchase->status),
                'canConfirm' => $this->canPurchase('purchases.confirm')
                    && $this->purchase->status === PurchasesService::STATUS_DRAFT,
                'canCancel' => $this->canPurchase('purchases.cancel') && $this->purchase->canCancel(),
                'canReceive' => $this->canPurchase('purchases.receive') && $this->purchase->canReceive(),
                'canEdit' => $this->canPurchase('purchases.update') && $this->purchase->isEditable(),
                'canPrint' => $this->canPurchase('purchases.view'),
                'priceHistory' => $priceHistory,
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
