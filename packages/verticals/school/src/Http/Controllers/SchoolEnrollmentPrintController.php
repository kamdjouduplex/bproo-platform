<?php

namespace School\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use School\Http\Controllers\Concerns\AuthorizesSchoolHttp;
use School\Models\SchoolEnrollment;

class SchoolEnrollmentPrintController
{
    use AuthorizesSchoolHttp;

    public function __invoke(Request $request, int $enrollment): View
    {
        $this->authorizeSchoolPermission('school_enrollments.view');

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $model = SchoolEnrollment::query()
            ->with(['student', 'academicYear', 'schoolClass'])
            ->findOrFail($enrollment);

        $student = $model->student;
        $tenantCode = $request->query('tenant') ?? session('tenant_code');

        return view('school::print.enrollment-slip', array_merge([
            'enrollment' => $model,
            'student' => $student,
            'photoUrl' => $student?->photoUrl($tenantCode),
            'settings' => $settings,
            'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo School'),
        ], PrintDocument::context(
            $request,
            'fiche-inscription',
            $student?->student_code ?? ('I'.$model->id),
            'tenant.school.enrollments.show',
            ['id' => $model->id]
        )));
    }
}
