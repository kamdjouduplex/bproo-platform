<?php

namespace InovCom\Logistique\Listeners;

use InovCom\Logistique\Events\DeliveryCompleted;
use InovCom\Stock\Services\StockService;
use Illuminate\Support\Facades\Log;

class DeductStockFromDelivery
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * Deduct stock for every item on the completed delivery.
     * Each item is removed from the delivery's source warehouse.
     * Runs synchronously — no queue needed for this use case.
     */
    public function handle(DeliveryCompleted $event): void
    {
        $delivery = $event->delivery->loadMissing('items');

        foreach ($delivery->items as $item) {
            if ((float) $item->quantity <= 0) {
                continue;
            }

            try {
                $this->stockService->removeStock(
                    productId:     (int) $item->product_id,
                    warehouseId:   (int) $delivery->source_warehouse_id,
                    quantity:      (float) $item->quantity,
                    referenceType: 'delivery',
                    referenceId:   $delivery->id,
                    notes:         "Livraison {$delivery->code}"
                );
            } catch (\Throwable $e) {
                Log::error("DeductStockFromDelivery: failed for delivery {$delivery->code}, product {$item->product_id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
