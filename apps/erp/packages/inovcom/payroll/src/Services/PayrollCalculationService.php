<?php

namespace InovCom\Payroll\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InovCom\Payroll\Models\Employee;
use InovCom\Payroll\Models\EmployeePayrollAdjustment;
use InovCom\Payroll\Models\PayrollLine;
use InovCom\Payroll\Models\PayrollLineItem;
use InovCom\Payroll\Models\PayrollRun;

class PayrollCalculationService
{
    /** Part salariale CNPS (approximation PME Cameroun). */
    public const CNPS_EMPLOYEE_RATE = 0.042;

    public function __construct(
        private EmployeeService $employees,
        private PayrollAdjustmentService $adjustments
    ) {
    }

    /**
     * @return array{
     *   base_salary: float,
     *   bonuses: float,
     *   deductions: float,
     *   net_salary: float,
     *   items: array<int, array{type: string, label: string, amount: float, metadata?: array}>
     * }
     */
    public function calculateForEmployee(Employee $employee, PayrollRun $run): array
    {
        $periodStart = Carbon::parse($run->period_start);
        $periodEnd = Carbon::parse($run->period_end);
        $baseSalary = $this->employees->salaryAtDate($employee, $periodEnd);

        $expectedWorkdays = $this->countWorkdays($periodStart, $periodEnd);
        $dailyRate = $expectedWorkdays > 0 ? $baseSalary / $expectedWorkdays : 0;

        $items = [];
        $sort = 0;

        $items[] = [
            'type' => PayrollLineItem::TYPE_BASE,
            'label' => 'Salaire de base',
            'amount' => round($baseSalary, 2),
            'metadata' => ['sort_order' => $sort++],
        ];

        $adjustmentRows = $this->adjustments->forEmployeePeriod($employee, $periodStart, $periodEnd);

        foreach ($adjustmentRows as $adj) {
            $lineItem = $this->adjustmentToLineItem($adj, $dailyRate, $sort);
            if ($lineItem) {
                $items[] = $lineItem;
                $sort = ($lineItem['metadata']['sort_order'] ?? $sort - 1) + 1;
            }
        }

        $grossBeforeTax = collect($items)->sum(fn ($i) => max(0, (float) $i['amount']));
        if ($grossBeforeTax > 0 && !empty($employee->cnps_number)) {
            $cnps = round($grossBeforeTax * self::CNPS_EMPLOYEE_RATE, 2);
            if ($cnps > 0) {
                $items[] = [
                    'type' => PayrollLineItem::TYPE_TAX,
                    'label' => 'CNPS salarié (' . fmt_num(self::CNPS_EMPLOYEE_RATE * 100, 1) . '%)',
                    'amount' => -$cnps,
                    'metadata' => ['rate' => self::CNPS_EMPLOYEE_RATE, 'sort_order' => $sort++],
                ];
            }
        }

        $bonuses = 0.0;
        $deductions = 0.0;
        foreach ($items as $item) {
            $amt = (float) $item['amount'];
            if ($amt > 0 && $item['type'] !== PayrollLineItem::TYPE_BASE) {
                $bonuses += $amt;
            } elseif ($amt < 0) {
                $deductions += abs($amt);
            }
        }

        $net = max(0, round(collect($items)->sum(fn ($i) => (float) $i['amount']), 2));

        return [
            'base_salary' => round($baseSalary, 2),
            'bonuses' => round($bonuses, 2),
            'deductions' => round($deductions, 2),
            'net_salary' => $net,
            'items' => $items,
        ];
    }

    /**
     * @return array{type: string, label: string, amount: float, metadata: array}|null
     */
    private function adjustmentToLineItem(EmployeePayrollAdjustment $adj, float $dailyRate, int $sort): ?array
    {
        return match ($adj->type) {
            EmployeePayrollAdjustment::TYPE_UNPAID_DAYS => $this->unpaidDaysLine($adj, $dailyRate, $sort),
            EmployeePayrollAdjustment::TYPE_BONUS => [
                'type' => PayrollLineItem::TYPE_BONUS,
                'label' => $adj->label,
                'amount' => round((float) $adj->amount, 2),
                'metadata' => ['adjustment_id' => $adj->id, 'sort_order' => $sort],
            ],
            EmployeePayrollAdjustment::TYPE_DEDUCTION => [
                'type' => PayrollLineItem::TYPE_DEDUCTION,
                'label' => $adj->label,
                'amount' => -round((float) $adj->amount, 2),
                'metadata' => ['adjustment_id' => $adj->id, 'sort_order' => $sort],
            ],
            default => null,
        };
    }

    /**
     * @return array{type: string, label: string, amount: float, metadata: array}|null
     */
    private function unpaidDaysLine(EmployeePayrollAdjustment $adj, float $dailyRate, int $sort): ?array
    {
        $days = (float) $adj->days;
        if ($days <= 0 || $dailyRate <= 0) {
            return null;
        }

        $amount = round($days * $dailyRate, 2);

        return [
            'type' => PayrollLineItem::TYPE_UNPAID_DAYS,
            'label' => $adj->label . ' (' . fmt_num($days, 1) . ' j)',
            'amount' => -$amount,
            'metadata' => ['adjustment_id' => $adj->id, 'days' => $days, 'sort_order' => $sort],
        ];
    }

    public function syncLine(PayrollLine $line, array $calculation): PayrollLine
    {
        $line->update([
            'base_salary' => $calculation['base_salary'],
            'bonuses' => $calculation['bonuses'],
            'deductions' => $calculation['deductions'],
            'net_salary' => $calculation['net_salary'],
        ]);

        if (Schema::connection('tenant')->hasTable('payroll_line_items')) {
            $line->items()->delete();
            foreach ($calculation['items'] as $idx => $item) {
                PayrollLineItem::create([
                    'payroll_line_id' => $line->id,
                    'type' => $item['type'],
                    'label' => $item['label'],
                    'amount' => $item['amount'],
                    'sort_order' => $item['metadata']['sort_order'] ?? $idx,
                    'metadata' => $item['metadata'] ?? null,
                ]);
            }
        }

        return $line->fresh(['items', 'employee']);
    }

    public function buildRunLines(PayrollRun $run): Collection
    {
        app(PayrollAdjustmentService::class)->attachToRun($run);

        $employees = Employee::query()
            ->where('is_active', true)
            ->where(function ($q) use ($run) {
                $q->whereNull('hire_date')
                    ->orWhereDate('hire_date', '<=', $run->period_end);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $lines = collect();

        foreach ($employees as $employee) {
            $calc = $this->calculateForEmployee($employee, $run);

            $line = PayrollLine::updateOrCreate(
                ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
                [
                    'base_salary' => $calc['base_salary'],
                    'bonuses' => $calc['bonuses'],
                    'deductions' => $calc['deductions'],
                    'net_salary' => $calc['net_salary'],
                ]
            );

            $this->syncLine($line, $calc);
            $lines->push($line);
        }

        PayrollLine::query()
            ->where('payroll_run_id', $run->id)
            ->whereNotIn('employee_id', $employees->pluck('id'))
            ->delete();

        return $lines;
    }

    private function countWorkdays(Carbon $from, Carbon $to): int
    {
        $days = 0;
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            if (!$cursor->isSunday()) {
                $days++;
            }
            $cursor->addDay();
        }

        return max(1, $days);
    }
}
