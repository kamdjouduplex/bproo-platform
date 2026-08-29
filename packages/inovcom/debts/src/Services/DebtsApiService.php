<?php

namespace InovCom\Debts\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Debts\Models\Debt;
use InovCom\Kernel\Contracts\DebtsApi;

class DebtsApiService implements DebtsApi
{
    public function __construct(private DebtsService $debts)
    {
    }

    public function isAvailable(): bool
    {
        return Schema::connection('tenant')->hasTable('debts')
            && Schema::connection('tenant')->hasColumn('debts', 'sale_id');
    }

    public function getSummary(string $startDate, string $endDate): array
    {
        $empty = [
            'receivables_total' => 0.0,
            'collected_in_period' => 0.0,
            'overdue_count' => 0,
            'overdue_total' => 0.0,
        ];

        if (! $this->isAvailable()) {
            return $empty;
        }

        $receivables = (float) DB::connection('tenant')
            ->table('debts')
            ->whereIn('status', ['open', 'partial', 'overdue'])
            ->sum('balance');

        $collected = 0.0;
        if (Schema::connection('tenant')->hasTable('debt_payments')) {
            $collected = (float) DB::connection('tenant')
                ->table('debt_payments')
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->sum('amount');
        }

        $overdueRows = DB::connection('tenant')
            ->table('debts')
            ->where(function ($q) use ($endDate) {
                $q->where('status', 'overdue')
                    ->orWhere(function ($q2) use ($endDate) {
                        $q2->whereIn('status', ['open', 'partial'])
                            ->whereNotNull('due_date')
                            ->where('due_date', '<', $endDate);
                    });
            })
            ->get(['balance']);

        return [
            'receivables_total' => $receivables,
            'collected_in_period' => $collected,
            'overdue_count' => $overdueRows->count(),
            'overdue_total' => (float) $overdueRows->sum('balance'),
        ];
    }

    public function recordCreditSale(
        int $clientId,
        float $amount,
        int $saleId,
        string $saleNumber,
        ?int $userId = null,
        ?string $openedAt = null
    ): ?array {
        if (! $this->isAvailable()) {
            return null;
        }

        $debt = $this->debts->createFromCreditSale(
            $clientId,
            $amount,
            $saleId,
            $saleNumber,
            $userId,
            $openedAt
        );

        return $debt ? $this->toArray($debt) : null;
    }

    public function applyCreditSaleReturn(int $saleId, float $amount): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        return $this->debts->applyCreditSaleReturn($saleId, $amount);
    }

    public function findBySaleId(int $saleId): ?array
    {
        if (! $this->isAvailable() || $saleId <= 0) {
            return null;
        }

        $debt = Debt::query()->where('sale_id', $saleId)->first();

        return $debt ? $this->toArray($debt) : null;
    }

    public function syncLinkedSales(array $saleIds): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $this->debts->syncLinkedSales($saleIds);
    }

    /**
     * @return array{id: int, reference: string, balance: float, status: string, total_amount: float}
     */
    private function toArray(Debt $debt): array
    {
        return [
            'id' => (int) $debt->id,
            'reference' => (string) $debt->reference,
            'balance' => (float) $debt->balance,
            'status' => (string) $debt->status,
            'total_amount' => (float) $debt->total_amount,
        ];
    }
}
