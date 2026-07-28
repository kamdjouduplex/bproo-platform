<?php

namespace InovCom\Purchases\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InovCom\Purchases\Models\PurchaseLine;
use InovCom\Purchases\Models\PurchaseOrder;
use InovCom\Purchases\Models\ReceiptLine;
use InovCom\Purchases\Models\ReceiptNote;
use InovCom\Stock\Services\StockService;

class PurchasesService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    /** @deprecated Use STATUS_PARTIAL — kept for legacy rows */
    public const STATUS_SENT_LEGACY = 'sent';

    public function __construct(
        private StockService $stockService,
        private PurchasePriceHistoryService $priceHistory,
        private PurchaseDocumentNumberService $documentNumbers
    ) {}

    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        return PurchaseOrder::create($data);
    }

    public function addLineToOrder(int $orderId, array $lineData): PurchaseLine
    {
        $lineData['purchase_order_id'] = $orderId;
        $line = PurchaseLine::create($lineData);
        $this->updateOrderTotals($orderId);

        return $line;
    }

    public function updateOrderTotals(int $orderId): void
    {
        $order = PurchaseOrder::findOrFail($orderId);
        $order->subtotal = $order->lines()->sum('line_total');
        $order->total = $order->subtotal;
        $order->save();
    }

    public function confirmOrder(int $orderId): PurchaseOrder
    {
        $order = PurchaseOrder::with('lines')->findOrFail($orderId);
        $this->assertCanModifyOrder($order);

        if ($order->lines->isEmpty()) {
            throw new \RuntimeException('Impossible de confirmer une commande sans lignes.');
        }

        $order->status = self::STATUS_CONFIRMED;
        $order->confirmed_at = now();
        $order->save();

        return $order->fresh(['lines', 'provider']);
    }

    /**
     * @param  array<int, float>  $cancelQuantities  purchase_line_id => quantity to cancel
     */
    public function cancelOrderLines(
        int $orderId,
        array $cancelQuantities,
        ?string $reason = null,
        bool $reverseReceivedStock = true
    ): PurchaseOrder {
        $order = PurchaseOrder::with('lines')->findOrFail($orderId);

        if ($order->status === self::STATUS_CANCELLED) {
            throw new \RuntimeException('Cette commande est déjà annulée.');
        }

        if (empty($cancelQuantities)) {
            throw new \RuntimeException('Indiquez les quantités à annuler.');
        }

        DB::connection('tenant')->transaction(function () use ($order, $cancelQuantities, $reason, $reverseReceivedStock) {
            foreach ($cancelQuantities as $lineId => $qtyToCancel) {
                $qtyToCancel = (float) $qtyToCancel;
                if ($qtyToCancel <= 0) {
                    continue;
                }

                $line = $order->lines->firstWhere('id', (int) $lineId);
                if (!$line) {
                    continue;
                }

                $this->cancelLineQuantity($order, $line, $qtyToCancel, $reverseReceivedStock);
            }

            $this->refreshOrderStatus($order, $reason);
        });

        return $order->fresh(['lines', 'provider', 'receipts']);
    }

    public function cancelEntireOrder(int $orderId, ?string $reason = null, bool $reverseReceivedStock = true): PurchaseOrder
    {
        $order = PurchaseOrder::with('lines')->findOrFail($orderId);

        $quantities = [];
        foreach ($order->lines as $line) {
            $remaining = max(0, (float) $line->quantity - (float) $line->cancelled_quantity);
            if ($remaining > 0) {
                $quantities[$line->id] = $remaining;
            }
        }

        return $this->cancelOrderLines($orderId, $quantities, $reason, $reverseReceivedStock);
    }

    public function receiveGoods(
        int $orderId,
        array $receivedQuantities,
        ?string $notes = null,
        ?int $userId = null,
        ?string $receiptDate = null
    ): ReceiptNote {
        $order = PurchaseOrder::with('lines')->findOrFail($orderId);

        $this->ensureOrderReadyForReceipt($order);

        $receiptNumber = $this->documentNumbers->nextReceiptNumber(
            (int) ($receiptDate ? date('Y', strtotime($receiptDate)) : now()->format('Y'))
        );

        $receipt = ReceiptNote::create([
            'receipt_number' => $receiptNumber,
            'receipt_date' => $receiptDate ?? now()->toDateString(),
            'purchase_order_id' => $orderId,
            'status' => 'partial',
            'notes' => $notes,
            'received_by' => $userId ?? auth('tenant')->id(),
        ]);

        foreach ($receivedQuantities as $lineId => $quantity) {
            if ((float) $quantity <= 0) {
                continue;
            }

            $purchaseLine = PurchaseLine::findOrFail($lineId);
            if ((int) $purchaseLine->purchase_order_id !== (int) $orderId) {
                continue;
            }

            $maxQuantity = $purchaseLine->remaining_quantity;
            $quantityToReceive = min((float) $quantity, $maxQuantity);

            if ($quantityToReceive <= 0) {
                continue;
            }

            ReceiptLine::create([
                'receipt_note_id' => $receipt->id,
                'purchase_line_id' => $lineId,
                'quantity_received' => $quantityToReceive,
            ]);

            $purchaseLine->received_quantity = (float) $purchaseLine->received_quantity + $quantityToReceive;
            $purchaseLine->save();

            $this->stockService->addStock(
                $purchaseLine->item_id,
                $quantityToReceive,
                'in',
                'Purchase',
                $receipt->id,
                "Réception commande {$order->order_number}"
            );

            $this->priceHistory->record(
                (int) $purchaseLine->item_id,
                (float) $purchaseLine->unit_price,
                $order->id,
                $purchaseLine->id,
                $order->provider_id,
                $quantityToReceive
            );
        }

        $order->refresh();
        $this->refreshOrderStatus($order);

        if ($order->isFullyReceived()) {
            $receipt->status = 'complete';
            $receipt->save();
        }

        return $receipt;
    }

    public function canEditOrder(PurchaseOrder $order): bool
    {
        if ($order->status !== self::STATUS_DRAFT) {
            return false;
        }

        return !$order->receipts()->exists();
    }

    public function assertCanModifyOrder(PurchaseOrder $order): void
    {
        if ($order->status === self::STATUS_CANCELLED) {
            throw new \RuntimeException('Commande annulée : modification impossible.');
        }

        if ($order->status !== self::STATUS_DRAFT && $order->receipts()->exists()) {
            throw new \RuntimeException('Des réceptions existent : modification limitée.');
        }
    }

    public function refreshOrderStatus(PurchaseOrder $order, ?string $cancellationReason = null): void
    {
        $order->load('lines');

        if ($order->isFullyCancelled()) {
            $order->status = self::STATUS_CANCELLED;
            $order->cancelled_at = $order->cancelled_at ?? now();
            if ($cancellationReason) {
                $order->cancellation_reason = $cancellationReason;
            }
            $order->save();

            return;
        }

        if ($order->status === self::STATUS_CANCELLED) {
            return;
        }

        if ($order->isFullyReceived()) {
            $order->status = self::STATUS_RECEIVED;
            $order->save();

            return;
        }

        $hasReceipt = (float) $order->lines->sum('received_quantity') > 0;
        if ($hasReceipt) {
            $order->status = self::STATUS_PARTIAL;
            $order->save();

            return;
        }

        if ($order->confirmed_at) {
            $order->status = self::STATUS_CONFIRMED;
            $order->save();
        }
    }

    private function cancelLineQuantity(
        PurchaseOrder $order,
        PurchaseLine $line,
        float $qtyToCancel,
        bool $reverseReceivedStock
    ): void {
        $maxCancellable = (float) $line->quantity
            - (float) $line->cancelled_quantity;

        if ($qtyToCancel > $maxCancellable + 0.0001) {
            throw new \RuntimeException(
                "Quantité à annuler trop élevée pour « {$line->item_name} » (max {$maxCancellable})."
            );
        }

        $remainingToReceive = max(0, (float) $line->quantity - (float) $line->received_quantity - (float) $line->cancelled_quantity);
        $cancelFromOpen = min($qtyToCancel, $remainingToReceive);
        $cancelFromReceived = $qtyToCancel - $cancelFromOpen;

        if ($cancelFromReceived > 0 && !$reverseReceivedStock) {
            throw new \RuntimeException(
                "« {$line->item_name} » : une partie est déjà réceptionnée. Cochez « retirer du stock » pour annuler la quantité reçue."
            );
        }

        if ($cancelFromReceived > 0 && $reverseReceivedStock) {
            $toReverse = min($cancelFromReceived, (float) $line->received_quantity);
            if ($toReverse > 0) {
                $this->stockService->removeStock(
                    $line->item_id,
                    $toReverse,
                    'out',
                    'PurchaseCancel',
                    $order->id,
                    "Annulation commande {$order->order_number}"
                );
                $line->received_quantity = max(0, (float) $line->received_quantity - $toReverse);
            }
        }

        $line->cancelled_quantity = (float) $line->cancelled_quantity + $qtyToCancel;
        $line->save();
    }

    /**
     * Passe une commande brouillon en confirmée avant réception (sans double enregistrement prix).
     */
    private function ensureOrderReadyForReceipt(PurchaseOrder $order): void
    {
        if ($order->status === self::STATUS_CANCELLED || $order->isFullyCancelled()) {
            throw new \RuntimeException('Commande annulée : réception impossible.');
        }

        if ($order->lines->isEmpty()) {
            throw new \RuntimeException('Commande sans lignes : réception impossible.');
        }

        if ($order->status === self::STATUS_DRAFT) {
            $order->status = self::STATUS_CONFIRMED;
            $order->confirmed_at = now();
            $order->save();

            return;
        }

        if (!in_array($order->status, [self::STATUS_CONFIRMED, self::STATUS_PARTIAL, self::STATUS_SENT_LEGACY], true)) {
            throw new \RuntimeException('Cette commande ne peut pas être réceptionnée.');
        }
    }

    public function getOrdersByProvider(int $providerId): Collection
    {
        return PurchaseOrder::where('provider_id', $providerId)
            ->orderByDesc('order_date')
            ->get();
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'Brouillon',
            self::STATUS_CONFIRMED => 'Confirmée',
            self::STATUS_PARTIAL, self::STATUS_SENT_LEGACY => 'Réception partielle',
            self::STATUS_RECEIVED => 'Réceptionnée',
            self::STATUS_CANCELLED => 'Annulée',
            default => $status,
        };
    }
}
