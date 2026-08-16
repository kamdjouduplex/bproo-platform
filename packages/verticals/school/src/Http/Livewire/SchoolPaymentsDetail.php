<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Http\Livewire\Concerns\SearchesStudents;
use School\Models\AcademicYear;
use School\Models\SchoolPayment;
use School\Models\SchoolStudent;
use School\Models\StudentReceipt;
use School\Support\SchoolNotificationDispatcher;
use School\Support\SchoolPaymentCatalog;
use School\Support\StudentLedgerService;

class SchoolPaymentsDetail extends Component
{
    use AuthorizesSchoolActions;
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use SearchesStudents;
    use WithFileUploads;

    public int $paymentId;
    public string $mode = 'show';
    public ?int $academicYearId = null;
    public string $paymentType = 'onsite';
    public string $currencyCode = 'XOF';
    public float $amount = 0;
    public string $reference = '';
    public string $payerName = '';
    public string $bankName = '';
    public string $channelDetail = '';
    public string $notes = '';
    public $proofFile = null;
    public string $verifierName = '';
    public string $rejectReason = '';

    public function mount(int $id): void
    {
        $this->paymentId = $id;
        SchoolPayment::query()->findOrFail($id);
        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
        $this->verifierName = (string) (auth('tenant')->user()?->name ?? '');
    }

    public function edit(): void
    {
        $payment = $this->entity();
        if ($payment->status === 'verified') {
            notify()->error('Un paiement validé ne peut plus être modifié.');

            return;
        }
        $this->academicYearId = $payment->academic_year_id;
        $this->paymentType = $payment->payment_type;
        $this->currencyCode = $payment->currency_code;
        $this->amount = (float) $payment->amount;
        $this->reference = (string) ($payment->reference ?? '');
        $this->payerName = (string) ($payment->payer_name ?? '');
        $this->bankName = (string) ($payment->bank_name ?? '');
        $this->channelDetail = (string) ($payment->channel_detail ?? '');
        $this->notes = (string) ($payment->notes ?? '');
        $this->proofFile = null;
        $this->selectStudent((int) $payment->student_id);
        $this->openEditForm($payment->id);
    }

    public function uploadProof(): void
    {
        $payment = $this->entity();
        if ($payment->status === 'verified') {
            notify()->error('Paiement déjà validé.');

            return;
        }

        $this->validate([
            'proofFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'proofFile.required' => 'Sélectionnez le bordereau / reçu.',
        ]);

        $path = $this->proofFile->store('school/payment-proofs', 'public');
        $payment->update([
            'proof_path' => $path,
            'proof_original_name' => $this->proofFile->getClientOriginalName(),
            'status' => $payment->status === 'rejected' ? 'pending' : $payment->status,
            'rejected_at' => null,
            'rejected_reason' => null,
        ]);
        $this->proofFile = null;
        notify()->success('Justificatif enregistré. Vous pouvez valider le paiement.');
    }

    public function verify(): void
    {
        if (! $this->authorizeSchool('school_payments.verify')) {
            return;
        }

        $payment = $this->entity();
        if ($payment->status === 'verified') {
            notify()->info('Déjà validé.');

            return;
        }

        if (! $payment->canVerify()) {
            notify()->error('Impossible de valider : bordereau / référence manquant(s).');

            return;
        }

        $this->validate([
            'verifierName' => ['required', 'string', 'max:255'],
        ], [
            'verifierName.required' => 'Indiquez le nom du validateur.',
        ]);

        $payment->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by_name' => trim($this->verifierName),
            'rejected_at' => null,
            'rejected_reason' => null,
        ]);

        if (! $payment->receipts()->exists()) {
            StudentReceipt::query()->create([
                'payment_id' => $payment->id,
                'receipt_number' => sprintf('RCPT-%d-%s', $payment->id, now()->format('YmdHis')),
                'issued_at' => now(),
            ]);
        }

        $payment->loadMissing('student');
        if ($payment->student) {
            app(SchoolNotificationDispatcher::class)->dispatch('payment', $payment->student, [
                'amount' => number_format((float) $payment->amount, 0, ',', ' '),
                'currency' => $payment->currency_code,
                'reference' => $payment->reference ?? ('#'.$payment->id),
            ]);
        }

        app(StudentLedgerService::class)->recordCreditFromPayment($payment->fresh());

        notify()->success('Paiement validé — reçu école généré.');
    }

    public function reject(): void
    {
        if (! $this->authorizeSchool('school_payments.verify')) {
            return;
        }

        $payment = $this->entity();
        if ($payment->status === 'verified') {
            notify()->error('Un paiement validé ne peut pas être rejeté.');

            return;
        }

        $this->validate([
            'rejectReason' => ['required', 'string', 'max:500'],
        ], [
            'rejectReason.required' => 'Indiquez le motif du rejet.',
        ]);

        $payment->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_reason' => trim($this->rejectReason),
            'verified_at' => null,
            'verified_by_name' => null,
        ]);
        notify()->success('Paiement rejeté.');
    }

    protected function resetFormFields(): void
    {
        $this->academicYearId = null;
        $this->paymentType = SchoolPaymentCatalog::TYPE_ONSITE;
        $this->currencyCode = 'XOF';
        $this->amount = 0;
        $this->reference = '';
        $this->payerName = '';
        $this->bankName = '';
        $this->channelDetail = '';
        $this->notes = '';
        $this->proofFile = null;
        $this->clearStudent();
    }

    public function save(): void
    {
        if (! $this->authorizeSchool('school_payments.manage')) {
            return;
        }

        $payment = $this->entity();
        if ($payment->status === 'verified') {
            notify()->error('Paiement validé — modification interdite.');

            return;
        }

        $type = $this->paymentType;
        $this->validate([
            'academicYearId' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'studentId' => ['required', 'integer', Rule::exists(SchoolStudent::class, 'id')],
            'paymentType' => ['required', Rule::in(SchoolPaymentCatalog::keys())],
            'currencyCode' => ['required', 'string', 'max:10'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => [SchoolPaymentCatalog::requiresReference($type) ? 'required' : 'nullable', 'string', 'max:255'],
            'bankName' => [$type === SchoolPaymentCatalog::TYPE_BANK ? 'required' : 'nullable', 'string', 'max:255'],
            'channelDetail' => [$type === SchoolPaymentCatalog::TYPE_MOBILE ? 'required' : 'nullable', 'string', 'max:255'],
            'payerName' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'proofFile' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $data = [
            'student_id' => $this->studentId,
            'academic_year_id' => $this->academicYearId,
            'payment_type' => $type,
            'currency_code' => $this->currencyCode,
            'amount' => $this->amount,
            'reference' => filled($this->reference) ? trim($this->reference) : null,
            'payer_name' => filled($this->payerName) ? trim($this->payerName) : null,
            'bank_name' => filled($this->bankName) ? trim($this->bankName) : null,
            'channel_detail' => filled($this->channelDetail) ? trim($this->channelDetail) : null,
            'notes' => filled($this->notes) ? trim($this->notes) : null,
            'status' => SchoolPaymentCatalog::isImmediate($type) ? 'verified' : 'pending',
        ];

        if ($this->proofFile) {
            $data['proof_path'] = $this->proofFile->store('school/payment-proofs', 'public');
            $data['proof_original_name'] = $this->proofFile->getClientOriginalName();
        }

        $payment->update($data);
        notify()->success('Paiement mis à jour.');
        $this->cancel();
    }

    protected function entity(): SchoolPayment
    {
        return SchoolPayment::query()->with(['student', 'academicYear', 'receipts'])->findOrFail($this->paymentId);
    }

    public function render()
    {
        $payment = $this->entity();
        $isManage = $this->mode === 'manage';
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $methods = SchoolPaymentCatalog::methods();
        $steps = SchoolPaymentCatalog::workflowSteps($payment);

        return view('school::livewire.school.payments.detail', [
            'payment' => $payment,
            'isManage' => $isManage,
            'years' => $years,
            'methods' => $methods,
            'steps' => $steps,
            'receipt' => $payment->receipts->first(),
            'studentResults' => $this->studentSearchResults(),
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').'Paiement #'.$payment->id,
            'subtitle' => $payment->typeLabel().' · '.$payment->statusLabel(),
        ]);
    }
}
