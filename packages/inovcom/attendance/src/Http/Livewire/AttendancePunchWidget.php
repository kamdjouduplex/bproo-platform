<?php

namespace InovCom\Attendance\Http\Livewire;

use InovCom\Attendance\Concerns\HandlesAttendancePunch;
use Livewire\Component;

class AttendancePunchWidget extends Component
{
    use HandlesAttendancePunch;

    public function mount(): void
    {
        $this->refreshAttendanceStatus();
    }

    public function render()
    {
        $tenantCode = request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;

        return view('inovcom-attendance::livewire.punch-widget', [
            'enabled' => $this->canAttendance('attendance.punch'),
            'tenantCode' => $tenantCode,
            'clock' => now()->format('H:i'),
        ]);
    }
}
