<?php

namespace InovCom\Attendance\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InovCom\Attendance\Models\AttendancePunch;
use InovCom\Attendance\Services\AttendanceService;

class AttendanceHistoryPrintController
{
    public function __invoke(Request $request): View
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            abort(403);
        }

        $canViewAll = (method_exists($user, 'isAdmin') && $user->isAdmin())
            || (method_exists($user, 'hasPermission') && $user->hasPermission('attendance.view_all'));

        if (
            ! $canViewAll
            && ! (method_exists($user, 'hasPermission') && $user->hasPermission('attendance.view'))
            && ! (method_exists($user, 'isAdmin') && $user->isAdmin())
        ) {
            abort(403);
        }

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);
        $service = app(AttendanceService::class);

        $from = Carbon::parse($request->input('date_from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->input('date_to', now()->toDateString()));
        $employeeId = $request->filled('employee_id') ? (int) $request->input('employee_id') : null;

        $query = AttendancePunch::query()
            ->with(['user', 'employee'])
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->orderBy('attendance_date')
            ->orderBy('punched_at');

        if (! $canViewAll) {
            $query->where('user_id', $user->id);
            $employeeId = null;
        } elseif ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $punches = $service->hasTable() ? $query->get() : collect();

        $scopeLabel = 'Ma présence';
        if ($canViewAll) {
            if ($employeeId) {
                $emp = $service->activeEmployees()->firstWhere('id', $employeeId);
                $scopeLabel = $emp?->full_name ?? ('Employé #'.$employeeId);
            } else {
                $scopeLabel = 'Tous les employés';
            }
        }

        return view('inovcom-attendance::print.history', array_merge([
            'settings' => $settings,
            'punches' => $punches,
            'service' => $service,
            'scopeLabel' => $scopeLabel,
            'periodLabel' => $from->format('d/m/Y').' — '.$to->format('d/m/Y'),
        ], PrintDocument::context(
            $request,
            'historique-presence',
            (string) ($employeeId ?? $user->id),
            'tenant.attendance.index'
        )));
    }
}
