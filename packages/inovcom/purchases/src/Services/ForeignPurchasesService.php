<?php

namespace InovCom\Purchases\Services;

use InovCom\Purchases\Models\ForeignPurchaseLine;
use InovCom\Purchases\Models\ForeignPurchaseOrder;
use InovCom\Purchases\Models\ForeignReceiptLine;
use InovCom\Purchases\Models\ForeignReceiptNote;
use InovCom\Stock\Services\StockService;

class ForeignPurchasesService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_RECEIVED = 'received';

    public function __construct(
        private PurchaseDocumentNumberService $documentNumbers,
        private PurchasePriceHistoryService $priceHistory,
        private StockService $stockService
    ) {}

    public function createOrder(array $data, array $lines): ForeignPurchaseOrder
    {
        $order = ForeignPurchaseOrder::create($data);
        $this->syncLines($order, $lines);

        return $order->fresh(['lines', 'provider']);
    }

    public function updateOrder(ForeignPurchaseOrder $order, array $data, array $lines): ForeignPurchaseOrder
    {
        $this->assertCanModifyOrder($order);

        $order->fill($data);
        $order->save();
        $this->syncLines($order, $lines);

        return $order->fresh(['lines', 'provider']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function syncLines(ForeignPurchaseOrder $order, array $lines): void
    {
        $order->lines()->delete();

        $rate = (float) $order->exchange_rate;

        foreach ($lines as $lineData) {
            $quantity = (float) ($lineData['quantity'] ?? 1);
            $unitForeign = (float) ($lineData['unit_price_foreign'] ?? 0);
            $unitLocal = round($unitForeign * $rate, 2);

            ForeignPurchaseLine::create([
                'foreign_purchase_order_id' => $order->id,
                'item_id' => $lineData['item_id'],
                'item_name' => $lineData['item_name'],
                'quantity' => $quantity,
                'unit_price_foreign' => $unitForeign,
                'unit_price_local' => $unitLocal,
                'line_total_foreign' => round($quantity * $unitForeign, 2),
                'line_total_local' => round($quantity * $unitLocal, 2),
            ]);
        }

        $this->updateOrderTotals($order->id);
    }

    public function updateOrderTotals(int $orderId): void
    {
        $order = ForeignPurchaseOrder::findOrFail($orderId);
        $order->subtotal_foreign = $order->lines()->sum('line_total_foreign');
        $order->subtotal_local = $order->lines()->sum('line_total_local');
        $order->save();
    }

    public function confirmOrder(int $orderId): ForeignPurchaseOrder
    {
        $order = ForeignPurchaseOrder::with('lines')->findOrFail($orderId);
        $this->assertCanModifyOrder($order);

        if ($order->lines->isEmpty()) {
            throw new \RuntimeException('Impossible de confirmer une commande sans lignes.');
        }

        $order->status = self::STATUS_CONFIRMED;
        $order->confirmed_at = now();
        $order->save();

        return $order->fresh(['lines', 'provider']);
    }

    public function receiveGoods(
        int $orderId,
        array $receivedQuantities,
        ?string $notes = null,
        ?int $userId = null,
        ?string $receiptDate = null
    ): ForeignReceiptNote {
        $order = ForeignPurchaseOrder::with('lines')->findOrFail($orderId);

        $this->ensureOrderReadyForReceipt($order);

        $receiptNumber = $this->documentNumbers->nextForeignReceiptNumber(
            (int) ($receiptDate ? date('Y', strtotime($receiptDate)) : now()->format('Y'))
        );

        $receipt = ForeignReceiptNote::create([
            'receipt_number' => $receiptNumber,
            'receipt_date' => $receiptDate ?? now()->toDateString(),
            'foreign_purchase_order_id' => $orderId,
            'status' => 'partial',
            'notes' => $notes,
            'received_by' => $userId ?? auth('tenant')->id(),
        ]);

        foreach ($receivedQuantities as $lineId => $quantity) {
            if ((float) $quantity <= 0) {
                continue;
            }

            $purchaseLine = ForeignPurchaseLine::findOrFail($lineId);
            if ((int) $purchaseLine->foreign_purchase_order_id !== (int) $orderId) {
                continue;
            }

            $quantityToReceive = min((float) $quantity, $purchaseLine->remaining_quantity);
            if ($quantityToReceive <= 0) {
                continue;
            }

            ForeignReceiptLine::create([
                'foreign_receipt_note_id' => $receipt->id,
                'foreign_purchase_line_id' => $lineId,
                'quantity_received' => $quantityToReceive,
            ]);

            $purchaseLine->received_quantity = (float) $purchaseLine->received_quantity + $quantityToReceive;
            $purchaseLine->save();

            $this->stockService->addStock(
                $purchaseLine->item_id,
                $quantityToReceive,
                'in',
                'ForeignPurchase',
                $receipt->id,
                "Réception achat étranger {$order->order_number}"
            );

            $this->recordLinePrices($order, $purchaseLine, $quantityToReceive);
        }

        $order->refresh();
        $this->refreshOrderStatus($order);

        if ($order->isFullyReceived()) {
            $receipt->status = 'complete';
            $receipt->save();
        }

        return $receipt;
    }

    private function recordLinePrices(ForeignPurchaseOrder $order, ForeignPurchaseLine $line, float $quantity): void
    {
        $this->priceHistory->record(
            (int) $line->item_id,
            (float) $line->unit_price_local,
            null,
            null,
            $order->provider_id,
            $quantity
        );

        $this->priceHistory->recordForeign(
            (int) $line->item_id,
            (string) $order->currency_code,
            (float) $line->unit_price_foreign,
            (float) $line->unit_price_local,
            $order->id,
            (int) $line->id,
            $order->provider_id,
            $quantity
        );
    }

    private function ensureOrderReadyForReceipt(ForeignPurchaseOrder $order): void
    {
        if ($order->lines->isEmpty()) {
            throw new \RuntimeException('Commande sans lignes : réception impossible.');
        }

        if ($order->status === self::STATUS_DRAFT) {
            $order->status = self::STATUS_CONFIRMED;
            $order->confirmed_at = $order->confirmed_at ?? now();
            $order->save();
        }

        if (!in_array($order->status, [self::STATUS_CONFIRMED, self::STATUS_PARTIAL], true)) {
            throw new \RuntimeException('Cette commande ne peut pas être réceptionnée.');
        }
    }

    public function refreshOrderStatus(ForeignPurchaseOrder $order): void
    {
        $order->load('lines');

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

    public function canEditOrder(ForeignPurchaseOrder $order): bool
    {
        return $order->status === self::STATUS_DRAFT;
    }

    public function assertCanModifyOrder(ForeignPurchaseOrder $order): void
    {
        if (!$this->canEditOrder($order)) {
            throw new \RuntimeException('Cette commande ne peut plus être modifiée.');
        }
    }

    public function nextOrderNumber(?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        return $this->documentNumbers->nextForeignOrderNumber($year);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'Brouillon',
            self::STATUS_CONFIRMED => 'Confirmée',
            self::STATUS_PARTIAL => 'Réception partielle',
            self::STATUS_RECEIVED => 'Réceptionnée',
            default => $status,
        };
    }

    public static function convertToLocal(float $foreignAmount, float $exchangeRate): float
    {
        return round($foreignAmount * $exchangeRate, 2);
    }

    /** Taux indicatif 1 unité de devise → FCFA (zone UEMOA). */
    public static function defaultExchangeRate(string $currencyCode): float
    {
        return match (strtoupper($currencyCode)) {
            'EUR' => 655.957,
            'USD' => 600.0,
            'GBP' => 780.0,
            'XOF' => 1.0,
            'CNY' => 85.0,
            'NGN' => 0.39,
            'CAD' => 440.0,
            'INR' => 7.2,
            default => 655.957,
        };
    }
}
