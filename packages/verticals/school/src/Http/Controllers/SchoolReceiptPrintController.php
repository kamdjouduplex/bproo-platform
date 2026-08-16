<?php

namespace School\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use School\Http\Controllers\Concerns\AuthorizesSchoolHttp;
use School\Models\SchoolPayment;

class SchoolReceiptPrintController
{
    use AuthorizesSchoolHttp;

    public function __invoke(Request $request, int $payment): View
    {
        $this->authorizeSchoolPermission('school_payments.view');

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $paymentModel = SchoolPayment::query()
            ->with(['student', 'academicYear', 'receipts'])
            ->findOrFail($payment);

        $receipt = $paymentModel->receipts->sortByDesc('id')->first();

        return view('school::print.receipt', array_merge([
            'payment' => $paymentModel,
            'receipt' => $receipt,
            'settings' => $settings,
            'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo School'),
        ], PrintDocument::context(
            $request,
            'recu-scolarite',
            $receipt?->receipt_number ?? ('P'.$paymentModel->id),
            'tenant.school.payments.index'
        )));
    }
}
