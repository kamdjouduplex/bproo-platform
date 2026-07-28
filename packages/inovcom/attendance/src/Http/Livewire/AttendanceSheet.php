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
        if (!$this->can('attendance.sheet') && !$this->can('attendance.view')) {
            abort(403);
        }

        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');

        $user = Auth::guard('tenant')->user();
        $isAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();
        $canViewAll = $isAdmin;

        if (request()->filled('employee_id') && $canViewAll) {
            $this->employeeFilter = (string) request()->query('employee_id');
        }

        $employee = app(AttendanceService::class)->resolveEmployeeForUser($user);
        if (! $canViewAll) {
            // Always lock to connected user
            $this->employeeFilter = $employee ? (string) $employee->id : '';
            if ($this->employeeFilter === '' && $user) {
                // Fallback: presence by user id via presenceReport(null, userId)
                $this->employeeFilter = 'user:'.$user->id;
            }
        } elseif ($employee && $this->employeeFilter === '') {
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

        $employeeId = str_starts_with($this->employeeFilter, 'user:')
            ? null
            : ($this->employeeFilter ?: null);

        return route('tenant.attendance.sheet.print', array_filter([
            'tenant' => $this->tenantCode(),
            'employee_id' => $employeeId,
            'date_from' => $this->dateFrom ?: null,
            'date_to' => $this->dateTo ?: null,
        ]));
    }

    public function render()
    {
        $service = app(AttendanceService::class);
        $user = Auth::guard('tenant')->user();
        $isAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();
        $canViewAll = $isAdmin;

        $report = null;
        $teamReport = null;

        if ($canViewAll && $this->employeeFilter === 'all') {
            $teamReport = $service->teamPresenceReport(
                Carbon::parse($this->dateFrom),
                Carbon::parse($this->dateTo)
            );
        } elseif (str_starts_with((string) $this->employeeFilter, 'user:')) {
            $uid = (int) substr($this->employeeFilter, 5);
            $report = $service->presenceReport(
                null,
                $uid,
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
                'subtitle' => $canViewAll ? 'Arrivées, départs et indicateurs' : 'Ma fiche de présence',
            ])
            ->with([
                'employees' => $canViewAll ? $service->activeEmployees() : collect(),
                'report' => $report,
                'teamReport' => $teamReport,
                'tenantCode' => $this->tenantCode(),
                'canViewAll' => $canViewAll,
            ]);
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
            return false;
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
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
