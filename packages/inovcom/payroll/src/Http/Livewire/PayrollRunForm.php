<?php

namespace InovCom\Payroll\Http\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use InovCom\Payroll\Concerns\AuthorizesPayrollActions;
use InovCom\Payroll\Models\Employee;
use InovCom\Payroll\Models\PayrollRun;
use InovCom\Payroll\Services\PayrollService;
use Livewire\Component;

class PayrollRunForm extends Component
{
    use AuthorizesPayrollActions;

    public ?int $payrollRunId = null;
    public ?string $period_start = null;
    public ?string $period_end = null;
    public string $notes = '';
    /** @var array<int, array{employee_id: int, base_salary: string, bonuses: string, deductions: string, net_salary: string}> */
    public array $lines = [];

    public function mount(?PayrollRun $payroll_run = null): void
    {
        $this->authorizePayrollAction('payroll.view');

        $service = app(PayrollService::class);

        if ($payroll_run && $payroll_run->exists) {
            $this->payrollRunId = $payroll_run->id;
            $this->period_start = $payroll_run->period_start->format('Y-m-d');
            $this->period_end = $payroll_run->period_end->format('Y-m-d');
            $this->notes = $payroll_run->notes ?? '';
            $this->loadLinesFromRun($payroll_run);
        } else {
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
            $this->period_start = $start->format('Y-m-d');
            $this->period_end = $end->format('Y-m-d');
            $run = $service->createRun($this->period_start, $this->period_end);
            $this->payrollRunId = $run->id;
            $this->loadLinesFromRun($run);
        }
    }

    private function loadLinesFromRun(PayrollRun $run): void
    {
        $this->lines = [];
        foreach ($run->lines()->with('employee')->get() as $line) {
            $this->lines[$line->employee_id] = [
                'employee_id' => $line->employee_id,
                'base_salary' => (string) $line->base_salary,
                'bonuses' => (string) $line->bonuses,
                'deductions' => (string) $line->deductions,
                'net_salary' => (string) $line->net_salary,
            ];
        }
    }

    public function recalculate(): void
    {
        $this->authorizePayrollAction('payroll.update');
        if (!$this->payrollRunId) {
            return;
        }

        try {
            $run = app(PayrollService::class)->recalculate(PayrollRun::findOrFail($this->payrollRunId));
            $this->loadLinesFromRun($run);
            session()->flash('success', 'Paie recalculée (ajustements enregistrés sur les employés).');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function save(): void
    {
        $this->authorizePayrollAction($this->payrollRunId ? 'payroll.update' : 'payroll.create');

        $this->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $run = PayrollRun::findOrFail($this->payrollRunId);
            app(PayrollService::class)->saveDraft(
                $run,
                $this->period_start,
                $this->period_end,
                $this->notes
            );
            session()->flash('success', 'Fiche de paie enregistrée.');
            $this->redirect(route('tenant.payroll.show', [
                'payroll_run' => $run->id,
                'tenant' => $this->tenantCode(),
            ]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function process(): void
    {
        $this->authorizePayrollAction('payroll.process');
        if (!$this->payrollRunId) {
            return;
        }

        try {
            app(PayrollService::class)->process(
                PayrollRun::findOrFail($this->payrollRunId),
                Auth::guard('tenant')->id()
            );
            session()->flash('success', 'Fiche de paie traitée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function markAsPaid(): void
    {
        $this->authorizePayrollAction('payroll.process');
        if (!$this->payrollRunId) {
            return;
        }

        try {
            app(PayrollService::class)->markAsPaid(PayrollRun::findOrFail($this->payrollRunId));
            session()->flash('success', 'Fiche marquée comme payée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->authorizePayrollAction('payroll.process');
        if (!$this->payrollRunId) {
            return;
        }

        try {
            app(PayrollService::class)->cancel(PayrollRun::findOrFail($this->payrollRunId));
            session()->flash('success', 'Fiche de paie annulée.');
            $this->redirect(route('tenant.payroll.index', ['tenant' => $this->tenantCode()]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $run = $this->payrollRunId
            ? PayrollRun::with(['lines.employee', 'lines.items', 'processedBy'])->find($this->payrollRunId)
            : null;

        $employees = Employee::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get();
        $employeeNames = $employees->keyBy('id')->map(fn ($e) => $e->full_name);

        return view('inovcom-payroll::livewire.payroll-runs.form')
            ->layout('layouts.app', [
                'title' => $this->payrollRunId ? 'Fiche de paie ' . ($run?->reference ?? '') : 'Nouvelle fiche de paie',
                'subtitle' => 'Paie mensuelle',
            ])
            ->with([
                'run' => $run,
                'employees' => $employees,
                'employeeNames' => $employeeNames,
                'tenantCode' => $this->tenantCode(),
                'canProcess' => $this->can('payroll.process'),
                'canUpdate' => $this->can('payroll.update'),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
