<?php

namespace InovCom\Rh\Http\Controllers;

use App\Services\PdfService;
use App\Services\TenantManager;
use InovCom\Rh\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PayslipPdfController extends Controller
{
    public function __invoke(Request $request, PdfService $pdf, Payslip $payslip): \Illuminate\Http\Response
    {
        $payslip->loadMissing(['employee']);

        $tenant      = app(TenantManager::class)->tenant();
        $companyName = $tenant?->getSetting('company_name', config('app.name', 'Entreprise'));

        $employee = $payslip->employee;
        $filename = 'Bulletin-Paie-' . str_replace(' ', '-', $employee->fullName())
                  . '-' . $payslip->period_year . '-' . str_pad($payslip->period_month, 2, '0', STR_PAD_LEFT)
                  . '.pdf';

        return $pdf->stream('inovcom-rh::pdf.payslip', [
            'payslip'     => $payslip,
            'employee'    => $employee,
            'companyName' => $companyName,
        ], $filename);
    }
}
