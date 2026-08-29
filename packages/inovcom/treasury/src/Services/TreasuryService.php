<?php

namespace InovCom\Treasury\Services;

use InovCom\Treasury\Models\TreasuryCommitment;

class TreasuryService
{
    public function create(array $data, ?int $userId = null): TreasuryCommitment
    {
        $data['created_by'] = $userId ?? auth('tenant')->id();
        $data['status'] = $data['status'] ?? TreasuryCommitment::STATUS_PLANNED;

        return TreasuryCommitment::create($data);
    }

    public function update(TreasuryCommitment $commitment, array $data): TreasuryCommitment
    {
        $commitment->fill($data)->save();

        return $commitment->fresh();
    }

    public function cancel(TreasuryCommitment $commitment): TreasuryCommitment
    {
        $commitment->status = TreasuryCommitment::STATUS_CANCELLED;
        $commitment->save();

        return $commitment;
    }

    public function markPaid(TreasuryCommitment $commitment, ?string $date = null): TreasuryCommitment
    {
        $commitment->markDatePaid($date ?: ($commitment->due_date?->toDateString() ?? now()->toDateString()));

        return $commitment->fresh();
    }
}
