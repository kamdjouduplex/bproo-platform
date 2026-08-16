<?php

namespace School\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use School\Http\Controllers\Concerns\AuthorizesSchoolHttp;
use School\Models\SchoolPayment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolPaymentProofController
{
    use AuthorizesSchoolHttp;

    public function __invoke(int $payment): StreamedResponse|\Illuminate\Http\Response
    {
        $this->authorizeSchoolPermission('school_payments.view');

        $record = SchoolPayment::query()->findOrFail($payment);

        if (! $record->proof_path || ! Storage::disk('public')->exists($record->proof_path)) {
            abort(404, 'Justificatif introuvable.');
        }

        $name = $record->proof_original_name ?: basename($record->proof_path);

        return Storage::disk('public')->response(
            $record->proof_path,
            $name,
            [
                'Content-Disposition' => 'inline; filename="'.$name.'"',
            ]
        );
    }
}
