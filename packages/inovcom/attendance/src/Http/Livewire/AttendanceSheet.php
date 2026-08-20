<?php

namespace InovCom\Attendance\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Ancienne page « fiche » — redirige vers le nouveau parcours (liste / détail).
 */
class AttendanceSheet extends Component
{
    public function mount(): void
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            abort(403);
        }

        $canSheet = $this->can('attendance.sheet');
        $canViewAll = $this->can('attendance.view_all');
        $canView = $this->can('attendance.view');

        if (! $canSheet && ! $canViewAll && ! $canView) {
            abort(403);
        }

        $tenant = request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;

        $month = now()->format('Y-m');
        if (request()->filled('date_from')) {
            try {
                $month = \Carbon\Carbon::parse(request()->query('date_from'))->format('Y-m');
            } catch (\Throwable) {
                //
            }
        }

        if (request()->filled('employee_id')) {
            $this->redirect(route('tenant.attendance.show', array_filter([
                'tenant' => $tenant,
                'employeeId' => (int) request()->query('employee_id'),
                'month' => $month,
            ])));

            return;
        }

        $this->redirect(route('tenant.attendance.index', array_filter([
            'tenant' => $tenant,
            'month' => $month,
        ])));
    }

    public function render()
    {
        return view('inovcom-attendance::livewire.sheet-redirect')
            ->layout('layouts.app', ['title' => 'Présence']);
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
}
