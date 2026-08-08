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

    public function getBatchesForItem(int $itemId, bool $fefo = true, bool $excludeExpired = true): Collection
    {
        if (! $this->isAvailable()) {
            return collect();
        }

        $query = Batch::where('item_id', $itemId)->where('quantity', '>', 0);
        if ($excludeExpired) {
            $query->whereDate('expiry_date', '>=', now()->toDateString());
        }
        if ($fefo) {
            $query->orderBy('expiry_date');
        }

        return $query->get();
    }

    public function sellableQuantity(int $itemId): float
    {
        return (float) $this->getBatchesForItem($itemId, false, true)->sum('quantity');
    }

    public function previewAllocation(int $itemId, float $quantity, ?int $preferredBatchId = null): array
    {
        if (! $this->isAvailable() || $quantity <= 0) {
            return [];
        }

        $remaining = $quantity;
        $preview = [];

        $batches = $this->orderedBatchesForConsume($itemId, $preferredBatchId);
        foreach ($batches as $batch) {
            if ($remaining <= 0.0001) {
                break;
            }
            $take = min((float) $batch->quantity, $remaining);
            if ($take <= 0) {
                continue;
            }
            $preview[] = [
                'batch_id' => (int) $batch->id,
                'batch_number' => (string) $batch->batch_number,
                'expiry_date' => $batch->expiry_date->format('Y-m-d'),
                'quantity' => round($take, 3),
            ];
            $remaining -= $take;
        }

        return $preview;
    }

    public function consumeFromBatches(
        int $itemId,
        float $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $preferredBatchId = null
    ): array {
        if (! $this->isAvailable() || $quantity <= 0) {
            return [];
        }

        return DB::connection('tenant')->transaction(function () use ($itemId, $quantity, $referenceType, $referenceId, $preferredBatchId) {
            $remaining = $quantity;
            $consumed = [];

            $batches = $this->orderedBatchesForConsume($itemId, $preferredBatchId);
            $takenByBatch = [];
            foreach ($batches as $batchRow) {
                if ($remaining <= 0) {
                    break;
                }
                $batch = Batch::query()->whereKey($batchRow->id)->lockForUpdate()->first();
                if (! $batch || (float) $batch->quantity <= 0) {
                    continue;
                }
                if ($batch->expiry_date->lt(now()->startOfDay())) {
                    continue;
                }
                if ((int) $batch->item_id !== $itemId) {
                    continue;
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

                $takenByBatch[] = [
                    'batch' => $batch,
                    'quantity' => $take,
                ];
                $consumed[$batch->id] = ($consumed[$batch->id] ?? 0) + $take;
                $remaining -= $take;

                if ($preferredBatchId !== null) {
                    break;
                }
            }

            if ($remaining > 0.0001) {
                $msg = $preferredBatchId !== null
                    ? 'Stock insuffisant sur le lot sélectionné.'
                    : 'Impossible de finaliser la vente : stock périmé ou insuffisant '
                        .'(disponible non périmé : '.number_format(max(0, $quantity - $remaining), 3, ',', ' ').').';
                throw new \RuntimeException($msg);
            }

            foreach ($takenByBatch as $row) {
                /** @var Batch $batch */
                $batch = $row['batch'];
                $take = (float) $row['quantity'];
                if (Schema::connection('tenant')->hasTable('stock_levels')) {
                    $level = $this->getStockLevelRow($itemId);
                    $stockBefore = $level ? (float) $level->quantity : 0.0;
                    $this->decreaseStockLevel($itemId, $take);
                    $stockAfter = max(0, $stockBefore - $take);
                } else {
                    $stockBefore = 0.0;
                    $stockAfter = 0.0;
                }
                if (Schema::connection('tenant')->hasTable('stock_movements')) {
                    $this->recordStockMovementOut(
                        $itemId,
                        $take,
                        $referenceType,
                        $referenceId,
                        $stockBefore,
                        $stockAfter,
                        $this->batchReasonLabel('out', $batch)
                    );
                }
            }

            return $consumed;
        });
    }

    /**
     * @return Collection<int, Batch>
     */
    private function orderedBatchesForConsume(int $itemId, ?int $preferredBatchId): Collection
    {
        if ($preferredBatchId !== null) {
            $preferred = Batch::query()
                ->where('id', $preferredBatchId)
                ->where('item_id', $itemId)
                ->where('quantity', '>', 0)
                ->whereDate('expiry_date', '>=', now()->toDateString())
                ->get();

            return $preferred;
        }

        return $this->getBatchesForItem($itemId, true, true);
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
            $level = $this->getStockLevelRow($itemId);
            $stockBefore = $level ? (float) $level->quantity : 0.0;
            $this->increaseStockLevel($itemId, $quantity);
            $stockAfter = $stockBefore + $quantity;
        } else {
            $stockBefore = 0.0;
            $stockAfter = 0.0;
        }
        if (Schema::connection('tenant')->hasTable('stock_movements')) {
            $this->recordStockMovementIn(
                $itemId,
                $quantity,
                $referenceType,
                $referenceId,
                $stockBefore,
                $stockAfter,
                $this->batchReasonLabel('in', $batch)
            );
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

        $itemId = (int) $batch->item_id;
        if (Schema::connection('tenant')->hasTable('stock_levels')) {
            $level = $this->getStockLevelRow($itemId);
            $stockBefore = $level ? (float) $level->quantity : 0.0;
            $this->increaseStockLevel($itemId, $quantity);
            $stockAfter = $stockBefore + $quantity;
        } else {
            $stockBefore = 0.0;
            $stockAfter = 0.0;
        }
        if (Schema::connection('tenant')->hasTable('stock_movements')) {
            $this->recordStockMovementIn(
                $itemId,
                $quantity,
                $referenceType ?? 'sale_return',
                $referenceId ?? 0,
                $stockBefore,
                $stockAfter,
                $this->batchReasonLabel('in', $batch)
            );
        }
    }

    public function writeOffExpiredBatch(int $batchId, ?string $notes = null): array
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('Module lots indisponible.');
        }

        return DB::connection('tenant')->transaction(function () use ($batchId, $notes) {
            $batch = Batch::query()->whereKey($batchId)->lockForUpdate()->first();
            if (! $batch) {
                throw new \InvalidArgumentException('Lot introuvable.');
            }

            if (! $batch->isExpired()) {
                throw new \InvalidArgumentException(
                    'Seuls les lots déjà périmés peuvent être sortis ainsi. Utilisez les pertes pour une destruction anticipée.'
                );
            }

            $qty = round((float) $batch->quantity, 3);
            if ($qty <= 0.0001) {
                throw new \InvalidArgumentException('Ce lot n’a plus de quantité à sortir.');
            }

            $itemId = (int) $batch->item_id;
            $batchNumber = (string) $batch->batch_number;

            $batch->quantity = 0;
            $batch->save();

            BatchMovement::create([
                'batch_id' => $batch->id,
                'quantity' => -$qty,
                'quantity_before' => $qty,
                'quantity_after' => 0,
                'reference_type' => 'expiry_write_off',
                'reference_id' => $batch->id,
            ]);

            $lossRecordId = $this->maybeCreateExpiryLossRecord($batch, $qty, $notes);

            if (Schema::connection('tenant')->hasTable('stock_levels')) {
                $row = $this->getStockLevelRow($itemId);
                $stockBefore = $row ? (float) $row->quantity : 0.0;
                $stockAfter = max(0, $stockBefore - $qty);
                $reserved = $row ? (float) $row->reserved_quantity : 0.0;
                $avail = max(0, $stockAfter - $reserved);

                if ($row) {
                    DB::connection('tenant')->table('stock_levels')->where('item_id', $itemId)->update([
                        'quantity' => $stockAfter,
                        'available_quantity' => $avail,
                        'updated_at' => now(),
                    ]);
                }

                if (Schema::connection('tenant')->hasTable('stock_movements')) {
                    $reason = 'Lot '.$batchNumber.' · exp. '.$batch->expiry_date->format('d/m/Y');
                    if ($notes) {
                        $reason .= ' — '.$notes;
                    }
                    DB::connection('tenant')->table('stock_movements')->insert([
                        'item_id' => $itemId,
                        'type' => 'out',
                        'reference_type' => 'expiry_write_off',
                        'reference_id' => $lossRecordId ?? $batch->id,
                        'quantity' => -$qty,
                        'quantity_before' => $stockBefore,
                        'quantity_after' => $stockAfter,
                        'reason' => $reason,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return [
                'batch_id' => (int) $batch->id,
                'item_id' => $itemId,
                'quantity' => $qty,
                'batch_number' => $batchNumber,
                'loss_record_id' => $lossRecordId,
            ];
        });
    }

    /**
     * Optional audit in Losses module (confirmed, without double stock removal).
     */
    private function maybeCreateExpiryLossRecord(Batch $batch, float $qty, ?string $notes): ?int
    {
        if (! Schema::connection('tenant')->hasTable('loss_records')
            || ! Schema::connection('tenant')->hasTable('loss_reasons')) {
            return null;
        }

        $reasonId = DB::connection('tenant')
            ->table('loss_reasons')
            ->where('code', 'expired')
            ->value('id');

        if (! $reasonId) {
            $reasonId = DB::connection('tenant')
                ->table('loss_reasons')
                ->where('name', 'like', '%expir%')
                ->value('id');
        }

        if (! $reasonId) {
            return null;
        }

        $userId = auth('tenant')->id();
        if (! $userId) {
            return null;
        }

        $year = now()->year;
        $lastRef = DB::connection('tenant')
            ->table('loss_records')
            ->where('reference', 'like', 'LOSS-'.$year.'-%')
            ->orderByDesc('id')
            ->value('reference');
        $next = $lastRef ? ((int) preg_replace('/[^0-9]/', '', substr((string) $lastRef, -6)) + 1) : 1;
        $reference = 'LOSS-'.$year.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);

        $itemCost = 0.0;
        if (Schema::connection('tenant')->hasTable('items')) {
            $itemCost = (float) (DB::connection('tenant')->table('items')->where('id', $batch->item_id)->value('cost') ?? 0);
        }

        $description = 'Sortie lot périmé '.$batch->batch_number
            .' (péremption '.$batch->expiry_date->format('d/m/Y').')';
        if ($notes) {
            $description .= ' — '.$notes;
        }

        $payload = [
            'reference' => $reference,
            'item_id' => $batch->item_id,
            'loss_reason_id' => $reasonId,
            'quantity' => $qty,
            'value' => $itemCost > 0 ? round($itemCost * $qty, 2) : 0,
            'loss_date' => now()->toDateString(),
            'description' => $description,
            'status' => 'confirmed',
            'created_by' => $userId,
            'confirmed_by' => $userId,
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::connection('tenant')->hasColumn('loss_records', 'store_id')
            && class_exists(\App\Services\StoreContextService::class)) {
            $payload['store_id'] = app(\App\Services\StoreContextService::class)->currentStoreId();
        }

        return (int) DB::connection('tenant')->table('loss_records')->insertGetId($payload);
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

    private function batchReasonLabel(string $direction, Batch $batch): string
    {
        $exp = $batch->expiry_date?->format('d/m/Y') ?? '—';

        return 'Lot '.$batch->batch_number.' · exp. '.$exp;
    }

    private function recordStockMovementOut(
        int $itemId,
        float $quantity,
        ?string $refType,
        ?int $refId,
        float $before,
        float $after,
        string $reason
    ): void {
        DB::connection('tenant')->table('stock_movements')->insert([
            'item_id' => $itemId,
            'type' => 'out',
            'reference_type' => $refType,
            'reference_id' => $refId,
            'quantity' => -$quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reason' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordStockMovementIn(
        int $itemId,
        float $quantity,
        string $refType,
        int $refId,
        float $before,
        float $after,
        string $reason
    ): void {
        DB::connection('tenant')->table('stock_movements')->insert([
            'item_id' => $itemId,
            'type' => 'in',
            'reference_type' => $refType,
            'reference_id' => $refId,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reason' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
