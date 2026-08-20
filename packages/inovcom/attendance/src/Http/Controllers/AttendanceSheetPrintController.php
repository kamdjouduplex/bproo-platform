<?php

namespace InovCom\Attendance\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\Attendance\Services\AttendanceService;

class AttendanceSheetPrintController
{
    public function __invoke(Request $request): View
    {
        $employeeId = $request->filled('employee_id') ? (int) $request->employee_id : null;
        $userId = $request->filled('user_id') ? (int) $request->user_id : null;

        if (! $employeeId && ! $userId) {
            abort(422, 'Employé ou utilisateur requis.');
        }

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);
        $service = app(AttendanceService::class);

        $from = Carbon::parse($request->input('date_from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->input('date_to', now()->toDateString()));

        $report = $service->presenceReport($employeeId, $userId, $from, $to);
        $displayName = $service->displayName($report['employee'], $report['user']);
        $month = $from->format('Y-m');

        if ($employeeId) {
            $returnRoute = 'tenant.attendance.show';
            $returnParams = ['employeeId' => $employeeId, 'month' => $month];
            $docNumber = (string) $employeeId;
        } else {
            $returnRoute = 'tenant.attendance.show-user';
            $returnParams = ['userId' => $userId, 'month' => $month];
            $docNumber = 'u'.$userId;
        }

        return view('inovcom-attendance::print.sheet', array_merge([
            'settings' => $settings,
            'report' => $report,
            'displayName' => $displayName,
            'periodLabel' => $from->format('d/m/Y').' — '.$to->format('d/m/Y'),
        ], PrintDocument::context(
            $request,
            'fiche-presence',
            $docNumber,
            $returnRoute,
            $returnParams
        )));
    }
}
