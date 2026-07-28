<?php

namespace InovCom\Attendance\Http\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use InovCom\Attendance\Concerns\HandlesAttendancePunch;
use InovCom\Attendance\Models\AttendancePunch;
use InovCom\Attendance\Services\AttendanceService;
use Livewire\Component;
use Livewire\WithPagination;

class AttendanceIndex extends Component
{
    use HandlesAttendancePunch;
    use WithPagination;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $employeeFilter = '';

    public function mount(): void
    {
        if (! $this->canAttendance('attendance.view') && ! $this->canAttendance('attendance.view_all')) {
            abort(403);
        }

        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->refreshAttendanceStatus();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedEmployeeFilter(): void
    {
        $this->resetPage();
    }

    protected function afterAttendanceUpdated(bool $success, string $message): void
    {
        if ($success) {
            $this->resetPage();
        }
        // Alerts Livewire + flash session pour la même expérience que le reste de l’app
        if ($message !== '') {
            session()->flash($success ? 'success' : 'error', $message);
        }
    }

    public function render()
    {
        $user = Auth::guard('tenant')->user();
        $service = app(AttendanceService::class);
        $canViewAll = $this->canAttendance('attendance.view_all');

        $query = AttendancePunch::query()->with(['user', 'employee'])->orderByDesc('punched_at');

        if (! $canViewAll) {
            $query->where('user_id', $user->id);
        } elseif ($this->employeeFilter !== '') {
            $empId = (int) $this->employeeFilter;
            $query->where('employee_id', $empId);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('attendance_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $query->whereDate('attendance_date', '<=', $this->dateTo);
        }

        $punches = $service->hasTable() ? $query->paginate(25) : collect();

        $employee = $user ? $service->resolveEmployeeForUser($user) : null;
        $todayStatus = $user ? $service->todayStatus($user) : null;
        $report = $employee && $this->canAttendance('attendance.view')
            ? $service->presenceReport(
                $employee->id,
                null,
                Carbon::parse($this->dateFrom),
                Carbon::parse($this->dateTo)
            )
            : null;

        return view('inovcom-attendance::livewire.index')
            ->layout('layouts.app', [
                'title' => 'Présence',
                'subtitle' => 'Arrivée, départ et suivi des employés',
            ])
            ->with([
                'punches' => $punches,
                'employees' => $service->activeEmployees(),
                'canViewAll' => $canViewAll,
                'canPunch' => $this->canAttendance('attendance.punch'),
                'canSheet' => $this->canAttendance('attendance.sheet'),
                'todayStatus' => $todayStatus,
                'myReport' => $report,
                'tenantCode' => $this->tenantCode(),
                'clock' => now()->format('H:i'),
                'todayLabel' => now()->translatedFormat('l j F Y'),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
