<?php

namespace InovCom\Losses\Services;

use App\Services\StoreContextService;
use InovCom\Losses\Models\LossRecord;
use InovCom\Stock\Services\StockService;
use Illuminate\Support\Collection;

class LossesService
{
    public function __construct(
        private StockService $stockService
    ) {
    }

    public function createLossRecord(array $data, ?int $userId = null): LossRecord
    {
        $data['reference'] = $this->generateReference();
        $data['created_by'] = $userId ?? auth('tenant')->id();
        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('loss_records', 'store_id')) {
            $data['store_id'] = app(StoreContextService::class)->currentStoreId();
        }

        $record = LossRecord::create($data);

        if (($record->item->cost ?? 0) > 0 && empty($data['value'])) {
            $record->value = $record->quantity * $record->item->cost;
            $record->save();
        }

        return $record;
    }

    public function confirmLoss(int $recordId, ?int $userId = null): LossRecord
    {
        $record = LossRecord::findOrFail($recordId);

        if (!$record->isDraft()) {
            throw new \Exception('Seule une perte en brouillon peut être confirmée.');
        }

        $this->stockService->removeStock(
            $record->item_id,
            (float) $record->quantity,
            'out',
            'Loss',
            $record->id,
            "Perte: {$record->reason->name}"
        );

        $record->status = 'confirmed';
        $record->confirmed_by = $userId ?? auth('tenant')->id();
        $record->confirmed_at = now();
        $record->save();

        return $record->fresh();
    }

    public function getTotalLosses(?string $startDate = null, ?string $endDate = null): float
    {
        $query = LossRecord::where('status', 'confirmed');

        if ($startDate) {
            $query->where('loss_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('loss_date', '<=', $endDate);
        }

        return (float) $query->sum('value');
    }

    public function getLossesByReason(?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = LossRecord::where('status', 'confirmed')
            ->with('reason')
            ->selectRaw('loss_reason_id, SUM(value) as total_value, SUM(quantity) as total_quantity')
            ->groupBy('loss_reason_id');

        if ($startDate) {
            $query->where('loss_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('loss_date', '<=', $endDate);
        }

        return $query->get();
    }

    private function generateReference(): string
    {
        $year = now()->year;
        $last = LossRecord::whereYear('created_at', $year)->orderBy('id', 'desc')->first();

        $next = $last ? ((int) preg_replace('/[^0-9]/', '', $last->reference)) + 1 : 1;

        return 'LOSS-' . $year . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
