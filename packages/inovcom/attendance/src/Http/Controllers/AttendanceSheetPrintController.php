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
        if (!$request->filled('employee_id')) {
            abort(422, 'Employé requis.');
        }

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);
        $service = app(AttendanceService::class);

        $from = Carbon::parse($request->input('date_from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->input('date_to', now()->toDateString()));

        $report = $service->presenceReport((int) $request->employee_id, null, $from, $to);
        $displayName = $service->displayName($report['employee'], $report['user']);

        return view('inovcom-attendance::print.sheet', array_merge([
            'settings' => $settings,
            'report' => $report,
            'displayName' => $displayName,
            'periodLabel' => $from->format('d/m/Y') . ' — ' . $to->format('d/m/Y'),
        ], PrintDocument::context(
            $request,
            'fiche-presence',
            (string) $request->employee_id,
            'tenant.attendance.sheet'
        )));
    }
}
