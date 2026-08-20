<?php

namespace InovCom\Attendance\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\Attendance\AttendanceModule;
use InovCom\Attendance\Concerns\HandlesAttendancePunch;
use InovCom\Attendance\Services\AttendanceService;
use InovCom\Attendance\Services\AttendanceSettingsService;
use Livewire\Attributes\Url;
use Livewire\Component;

class AttendanceIndex extends Component
{
    use HandlesAttendancePunch;

    /** Mois affiché (Y-m). */
    #[Url(as: 'month', except: '')]
    public string $month = '';

    /** Recherche employé (admin). */
    #[Url(as: 'q', except: '')]
    public string $search = '';

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

        $month = (string) request()->query('month', now()->format('Y-m'));
        $this->month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : now()->format('Y-m');
        $this->refreshAttendanceStatus();
    }

    public function updatedSearch(): void
    {
        //
    }

    public function updatedMonth(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }
    }

    protected function afterAttendanceUpdated(bool $success, string $message): void
    {
        if ($message !== '') {
            session()->flash($success ? 'success' : 'error', $message);
        }
    }

    protected function canManageSettings(): bool
    {
        return $this->canAttendance('attendance.settings');
    }

    public function printUrl(?int $employeeId = null, ?int $userId = null): string
    {
        $service = app(AttendanceService::class);
        [$from, $to] = $service->monthBounds($this->month);

        return route('tenant.attendance.sheet.print', array_filter([
            'tenant' => $this->tenantCode(),
            'employee_id' => $employeeId,
            'user_id' => $employeeId ? null : $userId,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
        ]));
    }

    public function render()
    {
        $user = Auth::guard('tenant')->user();
        $service = app(AttendanceService::class);
        $canViewAll = $this->canAttendance('attendance.view_all');
        $canPunch = $this->canAttendance('attendance.punch');

        [$from, $to] = $service->monthBounds($this->month);
        $monthOptions = $service->monthOptions(18);
        $monthLabel = $monthOptions[$this->month] ?? $from->locale('fr')->translatedFormat('F Y');

        $teamSummary = null;
        $myReport = null;
        $myEmployee = $user ? $service->resolveEmployeeForUser($user) : null;

        if ($canViewAll) {
            $teamSummary = $service->teamPresenceSummaries($from, $to, $this->search);
        } else {
            $myReport = $service->presenceReport(
                $myEmployee?->id,
                $myEmployee ? null : $user?->id,
                $from,
                $to
            );
        }

        $todayStatus = $user ? $service->todayStatus($user) : null;

        $printUrl = null;
        if ($myEmployee) {
            $printUrl = $this->printUrl((int) $myEmployee->id);
        } elseif ($user && ! $canViewAll) {
            $printUrl = $this->printUrl(null, (int) $user->id);
        }

        $settingsSvc = app(AttendanceSettingsService::class);
        $network = $settingsSvc->assertOnCompanyNetwork();

        return view('inovcom-attendance::livewire.index')
            ->layout('layouts.app', [
                'title' => 'Présence',
                'subtitle' => $canViewAll
                    ? 'Suivi de présence de l’équipe'
                    : 'Mon historique d’arrivée et de départ',
            ])
            ->with([
                'canViewAll' => $canViewAll,
                'canPunch' => $canPunch,
                'canSettings' => $this->canManageSettings(),
                'teamSummary' => $teamSummary,
                'myReport' => $myReport,
                'myEmployee' => $myEmployee,
                'monthOptions' => $monthOptions,
                'monthLabel' => $monthLabel,
                'dateFrom' => $from,
                'dateTo' => $to,
                'todayStatus' => $todayStatus,
                'tenantCode' => $this->tenantCode(),
                'clock' => now()->format('H:i'),
                'todayLabel' => now()->translatedFormat('l j F Y'),
                'printUrl' => $printUrl,
                'connectedUserName' => $user?->name,
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
