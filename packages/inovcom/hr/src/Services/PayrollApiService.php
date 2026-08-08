<?php

namespace InovCom\Payroll\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Kernel\Contracts\PayrollApi;

class PayrollApiService implements PayrollApi
{
    public function getPeriodTotal(string $startDate, string $endDate): float
    {
        if (! Schema::connection('tenant')->hasTable('payroll_runs')) {
            return 0.0;
        }

        return (float) DB::connection('tenant')
            ->table('payroll_runs')
            ->whereIn('status', ['processed', 'paid'])
            ->where('period_start', '<=', $endDate)
            ->where('period_end', '>=', $startDate)
            ->sum('total_gross');
    }
}
