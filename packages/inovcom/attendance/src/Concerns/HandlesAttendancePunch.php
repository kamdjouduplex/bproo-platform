<?php

namespace InovCom\Attendance\Concerns;

use Illuminate\Support\Facades\Auth;
use InovCom\Attendance\Services\AttendanceService;
use Livewire\Attributes\On;

trait HandlesAttendancePunch
{
    public bool $canPunchIn = true;

    public bool $canPunchOut = false;

    public bool $isPresent = false;

    public ?string $arrivalTime = null;

    public ?string $departureTime = null;

    public ?string $lastPunchTime = null;

    public ?string $lastPunchType = null;

    /** @var 'in'|'out'|null */
    public ?string $nextAction = 'in';

    public ?string $punchFlashMessage = null;

    public string $punchFlashType = 'success';

    public function punchIn(): void
    {
        $this->performPunch('in');
    }

    public function punchOut(): void
    {
        $this->performPunch('out');
    }

    protected function performPunch(string $type): void
    {
        $this->punchFlashMessage = null;

        if (! $this->canAttendance('attendance.punch')) {
            $this->dispatchAttendanceUpdated(false, 'Permission refusée pour le pointage.');

            return;
        }

        $user = Auth::guard('tenant')->user();
        if (! $user) {
            $this->dispatchAttendanceUpdated(false, 'Session expirée. Reconnectez-vous.');

            return;
        }

        $service = app(AttendanceService::class);
        $result = $type === 'out'
            ? $service->punchOut($user)
            : $service->punchIn($user);

        $this->dispatchAttendanceUpdated((bool) $result['success'], (string) $result['message']);
    }

    protected function dispatchAttendanceUpdated(bool $success, string $message): void
    {
        $this->dispatch('attendance-updated', success: $success, message: $message);
    }

    #[On('attendance-updated')]
    public function onAttendanceUpdated(bool $success = true, string $message = ''): void
    {
        $this->refreshAttendanceStatus();

        if ($message !== '') {
            $this->punchFlashMessage = $message;
            $this->punchFlashType = $success ? 'success' : 'error';
        }

        $this->afterAttendanceUpdated($success, $message);
    }

    /**
     * Hook pour la page liste (historique, etc.).
     */
    protected function afterAttendanceUpdated(bool $success, string $message): void
    {
        //
    }

    public function clearPunchFlash(): void
    {
        $this->punchFlashMessage = null;
    }

    public function refreshAttendanceStatus(): void
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            return;
        }

        $status = app(AttendanceService::class)->todayStatus($user);
        $this->canPunchIn = (bool) $status['can_punch_in'];
        $this->canPunchOut = (bool) $status['can_punch_out'];
        $this->isPresent = (bool) $status['is_present'];
        $this->arrivalTime = $status['arrival_time'];
        $this->departureTime = $status['departure_time'];
        $this->nextAction = $status['next_action'];
        $last = $status['last_punch'];
        $this->lastPunchTime = $last?->punched_at?->format('H:i');
        $this->lastPunchType = $last
            ? app(AttendanceService::class)->punchTypeLabel($last->punch_type ?? 'in')
            : null;
    }

    protected function canAttendance(string $permission): bool
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
}
