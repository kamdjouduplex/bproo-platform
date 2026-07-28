<?php

namespace InovCom\Purchases\Http\Livewire;

use InovCom\Purchases\Concerns\AuthorizesForeignPurchases;
use InovCom\Purchases\Models\ForeignPurchaseOrder;
use InovCom\Purchases\Services\ForeignPurchasesService;
use InovCom\Purchases\Services\PurchasePriceHistoryService;
use Livewire\Component;

class ForeignPurchasesShow extends Component
{
    use AuthorizesForeignPurchases;

    public ForeignPurchaseOrder $order;

    public function mount(ForeignPurchaseOrder $foreignPurchase): void
    {
        $this->order = $foreignPurchase->load([
            'provider',
            'creator',
            'lines.item',
            'receipts.lines.foreignPurchaseLine',
            'receipts.receiver',
        ]);
    }

    public function confirmOrder(): void
    {
        if (!$this->canForeignPurchase('foreign_purchases.confirm')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            app(ForeignPurchasesService::class)->confirmOrder($this->order->id);
            $this->refreshOrder();
            session()->flash('success', 'Achat étranger confirmé.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    private function refreshOrder(): void
    {
        $this->order = ForeignPurchaseOrder::with([
            'provider',
            'creator',
            'lines.item',
            'receipts.lines.foreignPurchaseLine',
            'receipts.receiver',
        ])->findOrFail($this->order->id);
    }

    public function render()
    {
        return view('inovcom-purchases::livewire.foreign.show')
            ->layout('layouts.app', [
                'title' => 'Achat étranger ' . $this->order->order_number,
                'subtitle' => ForeignPurchasesService::statusLabel($this->order->status),
            ])
            ->with([
                'statusLabel' => ForeignPurchasesService::statusLabel($this->order->status),
                'canConfirm' => $this->canForeignPurchase('foreign_purchases.confirm')
                    && $this->order->status === ForeignPurchasesService::STATUS_DRAFT,
                'canReceive' => $this->canForeignPurchase('foreign_purchases.receive')
                    && $this->order->canReceive(),
                'canModify' => $this->canModifyForeignPurchase()
                    && $this->order->isEditable(),
                'canPrint' => $this->canForeignPurchase('foreign_purchases.view')
                    || $this->canModifyForeignPurchase(),
                'isDraft' => $this->order->status === ForeignPurchasesService::STATUS_DRAFT,
                'priceHistory' => app(PurchasePriceHistoryService::class),
            ]);
    }
}
