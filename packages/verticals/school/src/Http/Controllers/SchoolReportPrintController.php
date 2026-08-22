<?php

namespace School\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use School\Http\Controllers\Concerns\AuthorizesSchoolHttp;
use School\Support\SchoolReportBuilder;

class SchoolReportPrintController
{
    use AuthorizesSchoolHttp;

    public function __invoke(Request $request): View
    {
        $this->authorizeSchoolPermission('school_reports.view');

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $type = (string) $request->query('type', 'enrollments');
        if (! array_key_exists($type, SchoolReportBuilder::types())) {
            $type = 'enrollments';
        }

        $year = $request->query('year', '');
        $class = $request->query('class', '');
        $teacher = $request->query('teacher', '');
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        $report = (new SchoolReportBuilder(
            $type,
            filled($year) ? (int) $year : null,
            filled($class) ? (int) $class : null,
            $from !== '' ? Carbon::parse($from)->startOfDay() : null,
            $to !== '' ? Carbon::parse($to)->endOfDay() : null,
            filled($teacher) ? (int) $teacher : null,
        ))->build();

        return view('school::print.report', array_merge([
            'report' => $report,
            'settings' => $settings,
            'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo School'),
        ], PrintDocument::context(
            $request,
            'rapport-ecole',
            $type,
            'tenant.school.reports.index'
        )));
    }
}
