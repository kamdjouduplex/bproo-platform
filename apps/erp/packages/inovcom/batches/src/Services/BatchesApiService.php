<?php

namespace InovCom\Batches\Services;

use InovCom\Batches\Models\Batch;
use InovCom\Batches\Models\BatchMovement;
use InovCom\Kernel\Contracts\BatchesApi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BatchesApiService implements BatchesApi
{
    public function isAvailable(): bool
    {
        return Schema::connection('tenant')->hasTable('batches');
    }

    public function getBatchesForItem(int $itemId, bool $fefo = true): Collection
    {
        if (!$this->isAvailable()) {
            return collect();
        }

        $query = Batch::where('item_id', $itemId)->where('quantity', '>', 0);
        if ($fefo) {
            $query->orderBy('expiry_date');
        }
        return $query->get();
    }

    public function consumeFromBatches(int $itemId, float $quantity, ?string $referenceType = null, ?int $referenceId = null): array
    {
        if (!$this->isAvailable() || $quantity <= 0) {
            return [];
        }

        $remaining = $quantity;
        $consumed = [];

        $batches = $this->getBatchesForItem($itemId, true);
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min((float) $batch->quantity, $remaining);
            if ($take <= 0) {
                continue;
            }

            $qtyBefore = (float) $batch->quantity;
            $qtyAfter = $qtyBefore - $take;
            $batch->quantity = $qtyAfter;
            $batch->save();

            BatchMovement::create([
                'batch_id' => $batch->id,
                'quantity' => -$take,
                'quantity_before' => $qtyBefore,
                'quantity_after' => $qtyAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            $consumed[$batch->id] = $take;
            $remaining -= $take;
        }

        if ($consumed && Schema::connection('tenant')->hasTable('stock_levels')) {
            $this->decreaseStockLevel($itemId, $quantity);
        }
        if ($consumed && Schema::connection('tenant')->hasTable('stock_movements')) {
            $this->recordStockMovementOut($itemId, $quantity, $referenceType, $referenceId);
        }

        return $consumed;
    }

    public function recordReceipt(int $itemId, string $batchNumber, \Carbon\CarbonInterface $expiryDate, float $quantity, string $referenceType, int $referenceId): object
    {
        $batch = Batch::where('item_id', $itemId)
            ->where('batch_number', $batchNumber)
            ->whereDate('expiry_date', $expiryDate)
            ->first();

        if ($batch) {
            $qtyBefore = (float) $batch->quantity;
            $qtyAfter = $qtyBefore + $quantity;
            $batch->quantity = $qtyAfter;
            $batch->save();
        } else {
            $batch = Batch::create([
                'item_id' => $itemId,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'quantity' => $quantity,
                'received_at' => now(),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
            $qtyBefore = 0;
            $qtyAfter = $quantity;
        }

        BatchMovement::create([
            'batch_id' => $batch->id,
            'quantity' => $quantity,
            'quantity_before' => $qtyBefore,
            'quantity_after' => $qtyAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);

        if (Schema::connection('tenant')->hasTable('stock_levels')) {
            $this->increaseStockLevel($itemId, $quantity);
        }
        if (Schema::connection('tenant')->hasTable('stock_movements')) {
            $this->recordStockMovementIn($itemId, $quantity, $referenceType, $referenceId);
        }

        return $batch;
    }

    public function restoreToBatch(int $batchId, float $quantity, ?string $referenceType = null, ?int $referenceId = null): void
    {
        if (!$this->isAvailable() || $quantity <= 0) {
            return;
        }

        $batch = Batch::find($batchId);
        if (!$batch) {
            return;
        }

        $qtyBefore = (float) $batch->quantity;
        $qtyAfter = $qtyBefore + $quantity;
        $batch->quantity = $qtyAfter;
        $batch->save();

        BatchMovement::create([
            'batch_id' => $batch->id,
            'quantity' => $quantity,
            'quantity_before' => $qtyBefore,
            'quantity_after' => $qtyAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);

        if (Schema::connection('tenant')->hasTable('stock_levels')) {
            $this->increaseStockLevel((int) $batch->item_id, $quantity);
        }
        if (Schema::connection('tenant')->hasTable('stock_movements')) {
            $this->recordStockMovementIn(
                (int) $batch->item_id,
                $quantity,
                $referenceType ?? 'sale_return',
                $referenceId ?? 0
            );
        }
    }

    private function getStockLevelRow(int $itemId): ?object
    {
        return DB::connection('tenant')
            ->table('stock_levels')
            ->where('item_id', $itemId)
            ->first();
    }

    private function decreaseStockLevel(int $itemId, float $quantity): void
    {
        $row = $this->getStockLevelRow($itemId);
        if (!$row) {
            return;
        }
        $before = (float) $row->quantity;
        $after = max(0, $before - $quantity);
        $avail = max(0, $after - (float) $row->reserved_quantity);
        DB::connection('tenant')->table('stock_levels')->where('item_id', $itemId)->update([
            'quantity' => $after,
            'available_quantity' => $avail,
            'updated_at' => now(),
        ]);
    }

    private function increaseStockLevel(int $itemId, float $quantity): void
    {
        $row = $this->getStockLevelRow($itemId);
        $before = $row ? (float) $row->quantity : 0;
        $after = $before + $quantity;
        $reserved = $row ? (float) $row->reserved_quantity : 0;
        $avail = max(0, $after - $reserved);
        if ($row) {
            DB::connection('tenant')->table('stock_levels')->where('item_id', $itemId)->update([
                'quantity' => $after,
                'available_quantity' => $avail,
                'updated_at' => now(),
            ]);
        } else {
            DB::connection('tenant')->table('stock_levels')->insert([
                'item_id' => $itemId,
                'quantity' => $after,
                'reserved_quantity' => 0,
                'available_quantity' => $avail,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function recordStockMovementOut(int $itemId, float $quantity, ?string $refType, ?int $refId): void
    {
        $row = DB::connection('tenant')->table('stock_levels')->where('item_id', $itemId)->first();
        $before = $row ? (float) $row->quantity : 0;
        $after = max(0, $before - $quantity);
        DB::connection('tenant')->table('stock_movements')->insert([
            'item_id' => $itemId,
            'type' => 'out',
            'reference_type' => $refType,
            'reference_id' => $refId,
            'quantity' => -$quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reason' => 'batch_consume',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordStockMovementIn(int $itemId, float $quantity, string $refType, int $refId): void
    {
        $row = DB::connection('tenant')->table('stock_levels')->where('item_id', $itemId)->first();
        $before = $row ? (float) $row->quantity : 0;
        $after = $before + $quantity;
        DB::connection('tenant')->table('stock_movements')->insert([
            'item_id' => $itemId,
            'type' => 'in',
            'reference_type' => $refType,
            'reference_id' => $refId,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reason' => 'batch_receipt',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
