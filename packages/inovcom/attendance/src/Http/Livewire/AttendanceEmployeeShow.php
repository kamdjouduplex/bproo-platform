<?php

namespace InovCom\Attendance\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\Attendance\Services\AttendanceService;
use InovCom\Users\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;

class AttendanceEmployeeShow extends Component
{
    public ?int $employeeId = null;

    public ?int $userId = null;

    #[Url(as: 'month', except: '')]
    public string $month = '';

    public function mount(?int $employeeId = null, ?int $userId = null): void
    {
        $user = Auth::guard('tenant')->user();
        $service = app(AttendanceService::class);
        $canViewAll = $this->can('attendance.view_all');

        if (! $canViewAll && ! $this->can('attendance.view')) {
            abort(403);
        }

        $employeeId = $employeeId ?? (request()->route('employeeId') ? (int) request()->route('employeeId') : null);
        $userId = $userId ?? (request()->route('userId') ? (int) request()->route('userId') : null);

        if (! $employeeId && ! $userId) {
            abort(404);
        }

        $myEmployee = $user ? $service->resolveEmployeeForUser($user) : null;

        if (! $canViewAll) {
            $allowed = false;
            if ($employeeId && $myEmployee && (int) $myEmployee->id === $employeeId) {
                $allowed = true;
            }
            if ($userId && $user && (int) $user->id === $userId) {
                $allowed = true;
            }
            if (! $allowed) {
                abort(403);
            }
        }

        if ($employeeId) {
            $employees = $service->activeEmployees();
            if (! $employees->contains(fn ($e) => (int) $e->id === $employeeId)) {
                abort(404, 'Employé introuvable.');
            }
            $this->employeeId = $employeeId;
            $this->userId = null;
        } else {
            $target = User::query()->where('is_active', true)->find($userId);
            if (! $target) {
                abort(404, 'Utilisateur introuvable.');
            }
            $linked = $service->resolveEmployeeForUser($target);
            if ($linked) {
                $this->redirect(route('tenant.attendance.show', array_filter([
                    'tenant' => $this->tenantCode(),
                    'employeeId' => $linked->id,
                    'month' => request()->query('month', now()->format('Y-m')),
                ])));

                return;
            }
            $this->userId = (int) $userId;
            $this->employeeId = null;
        }

        $this->month = (string) request()->query('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }
    }

    public function updatedMonth(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }
    }

    public function printUrl(): string
    {
        $service = app(AttendanceService::class);
        [$from, $to] = $service->monthBounds($this->month);

        return route('tenant.attendance.sheet.print', array_filter([
            'tenant' => $this->tenantCode(),
            'employee_id' => $this->employeeId,
            'user_id' => $this->employeeId ? null : $this->userId,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
        ]));
    }

    public function render()
    {
        $service = app(AttendanceService::class);
        [$from, $to] = $service->monthBounds($this->month);
        $monthOptions = $service->monthOptions(18);
        $monthLabel = $monthOptions[$this->month] ?? $from->locale('fr')->translatedFormat('F Y');

        $report = $service->presenceReport($this->employeeId, $this->userId, $from, $to);
        $displayName = $service->displayName($report['employee'], $report['user']);

        return view('inovcom-attendance::livewire.show')
            ->layout('layouts.app', [
                'title' => $displayName,
                'subtitle' => 'Historique de présence — '.$monthLabel,
            ])
            ->with([
                'report' => $report,
                'displayName' => $displayName,
                'monthOptions' => $monthOptions,
                'monthLabel' => $monthLabel,
                'dateFrom' => $from,
                'dateTo' => $to,
                'tenantCode' => $this->tenantCode(),
                'canViewAll' => $this->can('attendance.view_all'),
            ]);
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
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
