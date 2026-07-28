<?php

namespace InovCom\Attendance\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\Attendance\Services\AttendanceService;

class AttendanceTeamSheetPrintController
{
    public function __invoke(Request $request): View
    {
        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);
        $service = app(AttendanceService::class);

        $from = Carbon::parse($request->input('date_from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->input('date_to', now()->toDateString()));

        $teamReport = $service->teamPresenceReport($from, $to);

        return view('inovcom-attendance::print.team-sheet', array_merge([
            'settings' => $settings,
            'teamReport' => $teamReport,
            'periodLabel' => $from->format('d/m/Y') . ' — ' . $to->format('d/m/Y'),
        ], PrintDocument::context(
            $request,
            'fiches-presence-equipe',
            $from->format('Ymd') . '-' . $to->format('Ymd'),
            'tenant.attendance.sheet'
        )));
    }
}
