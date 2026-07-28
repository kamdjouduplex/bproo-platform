<?php

namespace InovCom\Payroll\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\Payroll\Models\PayrollLine;
use InovCom\Payroll\Models\PayrollRun;

class PayslipPrintController
{
    public function __invoke(Request $request, PayrollRun $payroll_run, int $line): View
    {
        $payrollLine = PayrollLine::query()
            ->with(['employee', 'items', 'payrollRun'])
            ->where('payroll_run_id', $payroll_run->id)
            ->where('id', $line)
            ->firstOrFail();

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        return view('inovcom-payroll::print.payslip', array_merge([
            'settings' => $settings,
            'run' => $payroll_run,
            'line' => $payrollLine,
            'employee' => $payrollLine->employee,
            'items' => $payrollLine->items,
            'periodLabel' => $payroll_run->period_start->format('d/m/Y') . ' — ' . $payroll_run->period_end->format('d/m/Y'),
        ], PrintDocument::context(
            $request,
            'bulletin-paie',
            $payrollLine->employee->employee_number ?? (string) $payrollLine->employee_id,
            'tenant.payroll.show',
            ['payroll_run' => $payroll_run->id]
        )));
    }
}
