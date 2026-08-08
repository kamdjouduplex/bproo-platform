<?php

namespace InovCom\Expenses\Services;

use App\Services\StoreContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Kernel\Contracts\ExpensesApi;

class ExpensesApiService implements ExpensesApi
{
    public function getPeriodTotal(string $startDate, string $endDate): float
    {
        if (! Schema::connection('tenant')->hasTable('expenses')) {
            return 0.0;
        }

        $query = DB::connection('tenant')
            ->table('expenses')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->whereIn('status', ['approved', 'paid']);

        $storeId = app(StoreContextService::class)->currentStoreId();
        if ($storeId && Schema::connection('tenant')->hasColumn('expenses', 'store_id')) {
            $query->where('expenses.store_id', $storeId);
        }

        return (float) $query->sum('amount');
    }

    public function getByCategory(string $startDate, string $endDate): array
    {
        if (! Schema::connection('tenant')->hasTable('expenses')
            || ! Schema::connection('tenant')->hasTable('expense_categories')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('expenses')
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->whereBetween('expenses.expense_date', [$startDate, $endDate])
            ->whereIn('expenses.status', ['approved', 'paid'])
            ->select('expense_categories.name as category_name')
            ->selectRaw('SUM(expenses.amount) as total')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($r) => [
            'category_name' => $r->category_name,
            'total' => (float) $r->total,
        ])->all();
    }
}
