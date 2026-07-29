<?php

namespace InovCom\Inventory\Services;

use InovCom\Inventory\Models\Adjustment;
use InovCom\Inventory\Models\Reason;
use InovCom\Inventory\Models\StockCount;
use InovCom\Inventory\Models\StockCountLine;
use InovCom\Items\Models\Item;
use InovCom\Stock\Models\StockLevel;
use InovCom\Stock\Services\StockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Inventory service for managing stock counts and adjustments
 * Supports inventory counts even when store is operational
 */
class InventoryService
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Create a new stock count
     */
    public function createStockCount(array $data): StockCount
    {
        $data['reference'] = $this->generateReference();
        $count = StockCount::create($data);
        return $count;
    }

    /**
     * Start a stock count (initialize with current stock levels)
     */
    public function startStockCount(int $stockCountId, ?int $userId = null): StockCount
    {
        $count = StockCount::findOrFail($stockCountId);
        
        if (!$count->isDraft()) {
            throw new \Exception('Seul un inventaire en brouillon peut être démarré.');
        }

        // Get all active items with stock
        $items = Item::where('is_active', true)
            ->with('unit')
            ->get();

        // Create lines with expected quantities from stock levels
        foreach ($items as $item) {
            $stockLevel = $this->stockService->getStockLevel($item->id);
            
            StockCountLine::create([
                'stock_count_id' => $count->id,
                'item_id' => $item->id,
                'expected_quantity' => (float) $stockLevel->quantity,
            ]);
        }

        // Update count status
        $count->status = 'in_progress';
        $count->started_at = now();
        $count->started_by = $userId ?? auth('tenant')->id();
        $count->save();

        return $count->fresh();
    }

    /**
     * Add or update a count line
     */
    public function updateCountLine(
        int $stockCountId,
        int $itemId,
        float $countedQuantity,
        ?string $notes = null,
        ?int $userId = null
    ): StockCountLine {
        $count = StockCount::findOrFail($stockCountId);
        
        if (!$count->isInProgress()) {
            throw new \Exception('L\'inventaire doit être en cours pour être modifié.');
        }

        $line = StockCountLine::firstOrCreate(
            [
                'stock_count_id' => $stockCountId,
                'item_id' => $itemId,
            ],
            [
                'expected_quantity' => $this->stockService->getStockLevel($itemId)->quantity,
            ]
        );

        $line->counted_quantity = $countedQuantity;
        $line->notes = $notes;
        $line->counted_by = $userId ?? auth('tenant')->id();
        $line->counted_at = now();
        $line->calculateDifference();
        $line->save();

        return $line;
    }

    /**
     * Complete a stock count and create adjustments
     */
    public function completeStockCount(
        int $stockCountId,
        bool $applyAdjustments = true,
        ?int $userId = null
    ): StockCount {
        $count = StockCount::findOrFail($stockCountId);
        
        if (!$count->isInProgress()) {
            throw new \Exception('Seul un inventaire en cours peut être finalisé.');
        }

        // Mark all uncounted items as 0
        $count->lines()
            ->whereNull('counted_quantity')
            ->update([
                'counted_quantity' => 0,
                'counted_at' => now(),
            ]);

        // Recalculate all differences
        foreach ($count->lines as $line) {
            $line->calculateDifference();
            $line->save();
        }

        // Create adjustments if requested
        if ($applyAdjustments) {
            $adjustments = $this->createAdjustmentsFromCount($count, $userId);

            // Apply immediately so stock reflects inventory differences right away.
            foreach ($adjustments as $adjustment) {
                $this->applyAdjustment($adjustment->id, $userId);
            }
        }

        // Update count status
        $count->status = 'completed';
        $count->completed_at = now();
        $count->completed_by = $userId ?? auth('tenant')->id();
        $count->save();

        return $count->fresh();
    }

    /**
     * Create adjustments from stock count differences
     */
    public function createAdjustmentsFromCount(StockCount $count, ?int $userId = null): Collection
    {
        $adjustments = collect();

        foreach ($count->lines as $line) {
            if ($line->difference == 0) {
                continue; // No adjustment needed
            }

            // Determine reason based on difference
            $reason = $this->getDefaultReasonForDifference($line->difference);

            $adjustment = Adjustment::create([
                'reference' => $this->generateAdjustmentReference(),
                'stock_count_id' => $count->id,
                'item_id' => $line->item_id,
                'reason_id' => $reason?->id,
                'quantity' => $line->difference,
                'value' => abs($line->value_difference),
                'notes' => "Ajustement depuis inventaire {$count->reference}",
                'status' => 'pending',
                'created_by' => $userId ?? auth('tenant')->id(),
            ]);

            $adjustments->push($adjustment);
        }

        return $adjustments;
    }

    /**
     * Apply an adjustment (update stock)
     */
    public function applyAdjustment(int $adjustmentId, ?int $userId = null): Adjustment
    {
        $adjustment = Adjustment::findOrFail($adjustmentId);
        
        if (!$adjustment->isPending()) {
            throw new \Exception('Seul un ajustement en attente peut être appliqué.');
        }

        // Update stock using StockService
        if ($adjustment->quantity > 0) {
            $this->stockService->addStock(
                $adjustment->item_id,
                (float) $adjustment->quantity,
                'adjustment',
                'Adjustment',
                $adjustment->id,
                $adjustment->notes ?? "Ajustement {$adjustment->reference}"
            );
        } else {
            $this->stockService->removeStock(
                $adjustment->item_id,
                abs((float) $adjustment->quantity),
                'adjustment',
                'Adjustment',
                $adjustment->id,
                $adjustment->notes ?? "Ajustement {$adjustment->reference}"
            );
        }

        // Mark adjustment as applied
        $adjustment->status = 'applied';
        $adjustment->applied_by = $userId ?? auth('tenant')->id();
        $adjustment->applied_at = now();
        $adjustment->save();

        return $adjustment;
    }

    /**
     * Create a manual adjustment
     */
    public function createAdjustment(array $data, ?int $userId = null): Adjustment
    {
        $data['reference'] = $this->generateAdjustmentReference();
        $data['status'] = 'pending';
        $data['created_by'] = $userId ?? auth('tenant')->id();
        
        $adjustment = Adjustment::create($data);
        
        // Auto-apply if requested
        if ($data['auto_apply'] ?? false) {
            $this->applyAdjustment($adjustment->id, $userId);
        }
        
        return $adjustment;
    }

    /**
     * Get default reason for a difference
     */
    private function getDefaultReasonForDifference(float $difference): ?Reason
    {
        if ($difference < 0) {
            return Reason::where('type', 'loss')->where('is_active', true)->first();
        } elseif ($difference > 0) {
            return Reason::where('type', 'gain')->where('is_active', true)->first();
        }
        
        return Reason::where('type', 'adjustment')->where('is_active', true)->first();
    }

    /**
     * Generate stock count reference
     */
    private function generateReference(): string
    {
        $year = now()->year;
        $lastCount = StockCount::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastCount 
            ? ((int) preg_replace('/[^0-9]/', '', $lastCount->reference)) + 1 
            : 1;

        return 'INV-' . $year . '-' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate adjustment reference
     */
    private function generateAdjustmentReference(): string
    {
        $year = now()->year;
        $lastAdjustment = Adjustment::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastAdjustment 
            ? ((int) preg_replace('/[^0-9]/', '', $lastAdjustment->reference)) + 1 
            : 1;

        return 'ADJ-' . $year . '-' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get stock counts by status
     */
    public function getCountsByStatus(string $status): Collection
    {
        return StockCount::where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get pending adjustments
     */
    public function getPendingAdjustments(): Collection
    {
        return Adjustment::where('status', 'pending')
            ->with(['item', 'reason', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
