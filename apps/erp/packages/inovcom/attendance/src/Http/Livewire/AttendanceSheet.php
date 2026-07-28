<?php

namespace InovCom\Attendance\Http\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use InovCom\Attendance\Services\AttendanceService;
use Livewire\Component;

class AttendanceSheet extends Component
{
    public string $employeeFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount(): void
    {
        if (!$this->can('attendance.sheet')) {
            abort(403);
        }

        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');

        if (request()->filled('employee_id')) {
            $this->employeeFilter = (string) request()->query('employee_id');
        }

        $user = Auth::guard('tenant')->user();
        $employee = app(AttendanceService::class)->resolveEmployeeForUser($user);
        if ($employee && !$this->can('attendance.view_all')) {
            $this->employeeFilter = (string) $employee->id;
        }
    }

    public function printUrl(): string
    {
        if ($this->employeeFilter === 'all') {
            return route('tenant.attendance.sheet.print-team', array_filter([
                'tenant' => $this->tenantCode(),
                'date_from' => $this->dateFrom ?: null,
                'date_to' => $this->dateTo ?: null,
            ]));
        }

        return route('tenant.attendance.sheet.print', array_filter([
            'tenant' => $this->tenantCode(),
            'employee_id' => $this->employeeFilter ?: null,
            'date_from' => $this->dateFrom ?: null,
            'date_to' => $this->dateTo ?: null,
        ]));
    }

    public function render()
    {
        $service = app(AttendanceService::class);
        $report = null;
        $teamReport = null;

        if ($this->employeeFilter === 'all' && $this->can('attendance.view_all')) {
            $teamReport = $service->teamPresenceReport(
                Carbon::parse($this->dateFrom),
                Carbon::parse($this->dateTo)
            );
        } elseif ($this->employeeFilter !== '') {
            $report = $service->presenceReport(
                (int) $this->employeeFilter,
                null,
                Carbon::parse($this->dateFrom),
                Carbon::parse($this->dateTo)
            );
        }

        return view('inovcom-attendance::livewire.sheet')
            ->layout('layouts.app', [
                'title' => 'Fiche de présence',
                'subtitle' => 'Arrivées, départs et indicateurs',
            ])
            ->with([
                'employees' => $service->activeEmployees(),
                'report' => $report,
                'teamReport' => $teamReport,
                'tenantCode' => $this->tenantCode(),
                'canViewAll' => $this->can('attendance.view_all'),
            ]);
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
            return false;
        }
        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
