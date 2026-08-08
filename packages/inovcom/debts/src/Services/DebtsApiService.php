<?php

namespace InovCom\Debts\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Kernel\Contracts\DebtsApi;

class DebtsApiService implements DebtsApi
{
    public function getSummary(string $startDate, string $endDate): array
    {
        $empty = [
            'receivables_total' => 0.0,
            'collected_in_period' => 0.0,
            'overdue_count' => 0,
            'overdue_total' => 0.0,
        ];

        if (! Schema::connection('tenant')->hasTable('debts')) {
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
}
