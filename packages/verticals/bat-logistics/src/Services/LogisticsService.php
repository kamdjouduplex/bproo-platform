<?php

namespace InovCom\Logistique\Services;

use InovCom\Logistique\Events\DeliveryCompleted;
use InovCom\Logistique\Models\Delivery;
use InovCom\Logistique\Models\DeliveryItem;
use Illuminate\Support\Facades\DB;

class LogisticsService
{
    /**
     * Create a new delivery in pending state with its items.
     *
     * @param array $data          Delivery fields (driver_id, vehicle_id, source_warehouse_id, …)
     * @param array $items         Array of ['product_id' => int, 'quantity' => float]
     */
    public function createDelivery(array $data, array $items): Delivery
    {
        return DB::connection('tenant')->transaction(function () use ($data, $items) {
            $data['code']   = Delivery::generateCode();
            $data['status'] = 'pending';

            $delivery = Delivery::on('tenant')->create($data);

            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 0);
                if ((int) ($item['product_id'] ?? 0) < 1 || $qty <= 0) {
                    continue;
                }
                DeliveryItem::on('tenant')->create([
                    'delivery_id' => $delivery->id,
                    'product_id'  => $item['product_id'],
                    'quantity'    => $qty,
                ]);
            }

            return $delivery->load('items');
        });
    }

    /**
     * Assign (or reassign) a vehicle and driver to a pending delivery.
     */
    public function assignVehicle(int $deliveryId, int $vehicleId, int $driverId): Delivery
    {
        $delivery = Delivery::on('tenant')->findOrFail($deliveryId);

        if ($delivery->status !== 'pending') {
            throw new \RuntimeException('Seules les livraisons en attente peuvent être réaffectées.');
        }

        $delivery->update(['vehicle_id' => $vehicleId, 'driver_id' => $driverId]);

        return $delivery->fresh();
    }

    /**
     * Transition a pending delivery to in_progress.
     */
    public function markAsInProgress(int $deliveryId): Delivery
    {
        $delivery = Delivery::on('tenant')->findOrFail($deliveryId);

        if ($delivery->status !== 'pending') {
            throw new \RuntimeException("La livraison {$delivery->code} n'est pas en attente.");
        }

        $delivery->update(['status' => 'in_progress']);

        return $delivery->fresh();
    }

    /**
     * Complete a delivery and fire DeliveryCompleted → DeductStockFromDelivery.
     * Stock deduction is handled exclusively by the listener — NOT here.
     */
    public function markAsCompleted(int $deliveryId, ?int $userId = null): Delivery
    {
        $delivery = Delivery::on('tenant')->with('items')->findOrFail($deliveryId);

        if (!in_array($delivery->status, ['pending', 'in_progress'], true)) {
            throw new \RuntimeException("La livraison {$delivery->code} ne peut pas être complétée depuis son statut actuel.");
        }

        $delivery->update([
            'status'       => 'completed',
            'completed_at' => now(),
            'completed_by' => $userId,
        ]);

        event(new DeliveryCompleted($delivery));

        return $delivery->fresh();
    }

    /**
     * Cancel a pending delivery.
     */
    public function cancel(int $deliveryId): Delivery
    {
        $delivery = Delivery::on('tenant')->findOrFail($deliveryId);

        if (!in_array($delivery->status, ['pending'], true)) {
            throw new \RuntimeException("Seules les livraisons en attente peuvent être annulées.");
        }

        $delivery->update(['status' => 'cancelled']);

        return $delivery->fresh();
    }
}
