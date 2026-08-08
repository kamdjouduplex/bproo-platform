<?php

namespace InovCom\Attendance\Http\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use InovCom\Attendance\AttendanceModule;
use InovCom\Attendance\Concerns\HandlesAttendancePunch;
use InovCom\Attendance\Models\AttendancePunch;
use InovCom\Attendance\Services\AttendanceService;
use InovCom\Attendance\Services\AttendanceSettingsService;
use Livewire\Component;
use Livewire\WithPagination;

class AttendanceIndex extends Component
{
    use HandlesAttendancePunch;
    use WithPagination;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $employeeFilter = '';

    public string $employeeSearch = '';

    public string $period = 'this_month';

    public function mount(): void
    {
        if (! $this->canAttendance('attendance.view') && ! $this->canAttendance('attendance.view_all')) {
            abort(403);
        }

        try {
            AttendanceModule::syncPermissions();
        } catch (\Throwable) {
            // Tenant DB may be incomplete during early setup.
        }

        $this->applyPeriod('this_month');
        $this->refreshAttendanceStatus();
    }

    public function setPeriod(string $period): void
    {
        $this->applyPeriod($period);
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->period = 'custom';
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->period = 'custom';
        $this->resetPage();
    }

    public function updatedEmployeeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedEmployeeSearch(): void
    {
        $this->resetPage();
    }

    protected function applyPeriod(string $period): void
    {
        $allowed = ['this_month', 'last_month', 'this_year', 'last_7_days'];
        if (! in_array($period, $allowed, true)) {
            $period = 'this_month';
        }
        $this->period = $period;
        $bounds = app(AttendanceService::class)->periodBounds($period);
        $this->dateFrom = $bounds['from'];
        $this->dateTo = $bounds['to'];
    }

    protected function afterAttendanceUpdated(bool $success, string $message): void
    {
        if ($success) {
            $this->resetPage();
        }
        if ($message !== '') {
            session()->flash($success ? 'success' : 'error', $message);
        }
    }

    protected function canViewAll(): bool
    {
        return $this->canAttendance('attendance.view_all');
    }

    protected function canManageSettings(): bool
    {
        return $this->canAttendance('attendance.settings');
    }

    public function render()
    {
        $user = Auth::guard('tenant')->user();
        $service = app(AttendanceService::class);
        $canViewAll = $this->canViewAll();

        $query = AttendancePunch::query()->with(['user', 'employee'])->orderByDesc('punched_at');

        if (! $canViewAll) {
            $query->where('user_id', $user?->id);
            $this->employeeFilter = '';
            $this->employeeSearch = '';
        } else {
            if ($this->employeeFilter !== '') {
                $query->where('employee_id', (int) $this->employeeFilter);
            } elseif (trim($this->employeeSearch) !== '') {
                $term = '%'.trim($this->employeeSearch).'%';
                $query->where(function ($q) use ($term) {
                    $q->whereHas('employee', function ($eq) use ($term) {
                        $eq->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('employee_number', 'like', $term)
                            ->orWhere('phone', 'like', $term);
                    })->orWhereHas('user', function ($uq) use ($term) {
                        $uq->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
                });
            }
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

        $reportEmployeeId = $employee?->id;
        $reportUserId = $user?->id;
        if ($canViewAll && $this->employeeFilter !== '') {
            $reportEmployeeId = (int) $this->employeeFilter;
            $reportUserId = null;
        }

        $report = ($this->canAttendance('attendance.view') || $canViewAll)
            ? $service->presenceReport(
                $reportEmployeeId,
                $reportUserId,
                Carbon::parse($this->dateFrom ?: now()->startOfMonth()->format('Y-m-d')),
                Carbon::parse($this->dateTo ?: now()->format('Y-m-d'))
            )
            : null;

        $tenantCode = $this->tenantCode();
        $exportUrl = null;
        if ($tenantCode && Route::has('tenant.attendance.history.print')) {
            $exportUrl = route('tenant.attendance.history.print', array_filter([
                'tenant' => $tenantCode,
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
                'employee_id' => $canViewAll && $this->employeeFilter !== '' ? $this->employeeFilter : null,
            ]));
        }

        $settingsSvc = app(AttendanceSettingsService::class);
        $network = $settingsSvc->assertOnCompanyNetwork();

        return view('inovcom-attendance::livewire.index')
            ->layout('layouts.app', [
                'title' => 'Présence',
                'subtitle' => $canViewAll
                    ? 'Suivi des pointages — équipe et export'
                    : 'Mon historique de présence',
            ])
            ->with([
                'punches' => $punches,
                'employees' => $canViewAll ? $service->activeEmployees() : collect(),
                'canViewAll' => $canViewAll,
                'canPunch' => $this->canAttendance('attendance.punch'),
                'canSheet' => $this->canAttendance('attendance.sheet') || $this->canAttendance('attendance.view'),
                'canSettings' => $this->canManageSettings(),
                'todayStatus' => $todayStatus,
                'myReport' => $report,
                'tenantCode' => $tenantCode,
                'clock' => now()->format('H:i'),
                'todayLabel' => now()->translatedFormat('l j F Y'),
                'connectedUserName' => $user?->name,
                'exportUrl' => $exportUrl,
                'wifiName' => $settingsSvc->wifiName() ?: 'Wi‑Fi entreprise',
                'networkOk' => $network['allowed'],
                'networkMessage' => $network['message'],
                'networkConfigured' => $settingsSvc->networkRestrictionConfigured(),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
