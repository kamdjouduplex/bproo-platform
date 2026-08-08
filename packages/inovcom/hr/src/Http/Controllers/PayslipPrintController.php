<?php

namespace InovCom\Payroll\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\Payroll\Concerns\AuthorizesPayrollActions;
use InovCom\Payroll\Models\PayrollLine;
use InovCom\Payroll\Models\PayrollLineItem;
use InovCom\Payroll\Models\PayrollRun;
use InovCom\Payroll\Support\AmountInWords;
use InovCom\Payroll\Support\EmployeeRules;

class PayslipPrintController
{
    use AuthorizesPayrollActions;

    public function __invoke(Request $request, PayrollRun $payroll_run, int $line): View
    {
        abort_unless($this->can('payroll.view'), 403, 'Action non autorisée.');

        $payrollLine = PayrollLine::query()
            ->with(['employee', 'items', 'payrollRun'])
            ->where('payroll_run_id', $payroll_run->id)
            ->where('id', $line)
            ->firstOrFail();

        $this->authorizePayslipLine($payrollLine->employee_id);
        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);
        $currency = (string) ($settings['currency'] ?? $tenant?->getSetting('currency', 'XOF') ?? 'XOF');
        $currencyLabel = AmountInWords::currencyLabel($currency);

        $items = $payrollLine->items;
        if ($items->isEmpty()) {
            $items = collect($this->fallbackItems($payrollLine));
        }

        $earnings = $items->filter(fn ($item) => (float) $item->amount > 0)->values();
        $deductions = $items->filter(fn ($item) => (float) $item->amount < 0)->values();

        $gross = round((float) $earnings->sum(fn ($i) => (float) $i->amount), 2);
        $totalDeductions = round((float) $deductions->sum(fn ($i) => abs((float) $i->amount)), 2);
        $net = round((float) $payrollLine->net_salary, 2);

        $periodMonth = $payroll_run->period_start->locale('fr')->translatedFormat('F Y');
        $periodLabel = $payroll_run->period_start->format('d/m/Y').' — '.$payroll_run->period_end->format('d/m/Y');

        $employee = $payrollLine->employee;

        return view('inovcom-payroll::print.payslip', array_merge([
            'settings' => $settings,
            'run' => $payroll_run,
            'line' => $payrollLine,
            'employee' => $employee,
            'items' => $items,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'gross' => $gross,
            'totalDeductions' => $totalDeductions,
            'net' => $net,
            'currencyLabel' => $currencyLabel,
            'periodMonth' => ucfirst($periodMonth),
            'periodLabel' => $periodLabel,
            'contractLabel' => EmployeeRules::contractTypeLabel($employee?->contract_type),
            'netInWords' => AmountInWords::format($net, $currencyLabel),
            'rowCount' => max($earnings->count(), $deductions->count(), 1),
        ], PrintDocument::context(
            $request,
            'bulletin-paie',
            $employee->employee_number ?? (string) $payrollLine->employee_id,
            'tenant.payroll.show',
            ['payroll_run' => $payroll_run->id]
        )));
    }

    /**
     * @return list<object{label: string, type: string, type_label: string, amount: float}>
     */
    private function fallbackItems(PayrollLine $line): array
    {
        $rows = [
            (object) [
                'label' => 'Salaire de base',
                'type' => PayrollLineItem::TYPE_BASE,
                'type_label' => PayrollLineItem::TYPE_LABELS[PayrollLineItem::TYPE_BASE],
                'amount' => (float) $line->base_salary,
            ],
        ];

        if ((float) $line->bonuses > 0) {
            $rows[] = (object) [
                'label' => 'Primes',
                'type' => PayrollLineItem::TYPE_BONUS,
                'type_label' => PayrollLineItem::TYPE_LABELS[PayrollLineItem::TYPE_BONUS],
                'amount' => (float) $line->bonuses,
            ];
        }

        if ((float) $line->deductions > 0) {
            $rows[] = (object) [
                'label' => 'Retenues',
                'type' => PayrollLineItem::TYPE_DEDUCTION,
                'type_label' => PayrollLineItem::TYPE_LABELS[PayrollLineItem::TYPE_DEDUCTION],
                'amount' => -1 * (float) $line->deductions,
            ];
        }

        return $rows;
    }
}
