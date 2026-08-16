<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
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

class SchoolPaymentsIndex extends Component
{
    use AuthorizesSchoolActions;
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use SearchesStudents;
    use WithFileUploads;
    use WithPagination;

    public string $search = '';
    public string $filterYearId = '';
    public string $filterType = '';
    public string $filterStatus = '';

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

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterYearId(): void { $this->resetPage(); }
    public function updatedFilterType(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function updatedPaymentType(): void
    {
        // Clear type-specific fields when switching method.
        if ($this->paymentType === SchoolPaymentCatalog::TYPE_ONSITE || $this->paymentType === SchoolPaymentCatalog::TYPE_CARD) {
            $this->proofFile = null;
        }
    }

    public function create(): void
    {
        if (! $this->authorizeSchool('school_payments.manage')) {
            return;
        }
        $this->openCreateForm();
        $this->clearStudent();
        $this->academicYearId = AcademicYear::query()->where('is_active', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id');
        $this->paymentType = SchoolPaymentCatalog::TYPE_ONSITE;
        $this->currencyCode = 'XOF';
        $this->amount = 0;
        $this->reference = '';
        $this->payerName = '';
        $this->bankName = '';
        $this->channelDetail = '';
        $this->notes = '';
        $this->proofFile = null;
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
        $type = $this->paymentType;
        $rules = [
            'academicYearId' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'studentId' => ['required', 'integer', Rule::exists(SchoolStudent::class, 'id')],
            'paymentType' => ['required', Rule::in(SchoolPaymentCatalog::keys())],
            'currencyCode' => ['required', 'string', 'max:10'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payerName' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reference' => [SchoolPaymentCatalog::requiresReference($type) ? 'required' : 'nullable', 'string', 'max:255'],
            'bankName' => [$type === SchoolPaymentCatalog::TYPE_BANK ? 'required' : 'nullable', 'string', 'max:255'],
            'channelDetail' => [$type === SchoolPaymentCatalog::TYPE_MOBILE ? 'required' : 'nullable', 'string', 'max:255'],
            'proofFile' => [SchoolPaymentCatalog::requiresProof($type) ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ];

        $this->validate($rules, [
            'reference.required' => 'Le n° de bordereau / référence est obligatoire.',
            'bankName.required' => 'Indiquez la banque du versement.',
            'channelDetail.required' => 'Précisez l’opérateur ou le n° de chèque.',
            'proofFile.required' => 'Joignez le reçu / bordereau de versement (PDF ou image).',
            'proofFile.mimes' => 'Formats acceptés : PDF, JPG, PNG.',
        ]);

        $immediate = SchoolPaymentCatalog::isImmediate($type);
        $status = $immediate ? 'verified' : 'pending';

        $proofPath = null;
        $proofName = null;
        if ($this->proofFile) {
            $proofPath = $this->proofFile->store('school/payment-proofs', 'public');
            $proofName = $this->proofFile->getClientOriginalName();
        }

        $payment = SchoolPayment::query()->create([
            'student_id' => $this->studentId,
            'academic_year_id' => $this->academicYearId,
            'payment_type' => $type,
            'currency_code' => $this->currencyCode,
            'amount' => $this->amount,
            'status' => $status,
            'paid_at' => now(),
            'reference' => filled($this->reference) ? trim($this->reference) : null,
            'payer_name' => filled($this->payerName) ? trim($this->payerName) : null,
            'bank_name' => filled($this->bankName) ? trim($this->bankName) : null,
            'channel_detail' => filled($this->channelDetail) ? trim($this->channelDetail) : null,
            'proof_path' => $proofPath,
            'proof_original_name' => $proofName,
            'notes' => filled($this->notes) ? trim($this->notes) : null,
            'verified_at' => $immediate ? now() : null,
            'verified_by_name' => $immediate ? (auth('tenant')->user()?->name) : null,
        ]);

        if ($status === 'verified') {
            $this->issueReceipt($payment);
            app(StudentLedgerService::class)->recordCreditFromPayment($payment);
            $payment->loadMissing('student');
            if ($payment->student) {
                app(SchoolNotificationDispatcher::class)->dispatch('payment', $payment->student, [
                    'amount' => number_format((float) $payment->amount, 0, ',', ' '),
                    'currency' => $payment->currency_code,
                    'reference' => $payment->reference ?? ('#'.$payment->id),
                ]);
            }
            notify()->success('Paiement validé — reçu généré.');
        } else {
            notify()->success('Paiement enregistré — en attente de validation (justificatif joint).');
        }

        $this->cancel();
    }

    private function issueReceipt(SchoolPayment $payment): void
    {
        if ($payment->receipts()->exists()) {
            return;
        }

        StudentReceipt::query()->create([
            'payment_id' => $payment->id,
            'receipt_number' => sprintf('RCPT-%d-%s', $payment->id, now()->format('YmdHis')),
            'issued_at' => now(),
        ]);
    }

    public function render()
    {
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $term = trim($this->search);
        $methods = SchoolPaymentCatalog::methods();

        $payments = SchoolPayment::query()
            ->with(['student', 'academicYear', 'receipts'])
            ->when($this->filterYearId !== '', fn ($q) => $q->where('academic_year_id', (int) $this->filterYearId))
            ->when($this->filterType !== '', fn ($q) => $q->where('payment_type', $this->filterType))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(COALESCE(reference, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(bank_name, \'\')) LIKE ?', [$like])
                        ->orWhereHas('student', function ($sq) use ($like) {
                            $sq->whereRaw('LOWER(student_code) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like]);
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(12);

        return view('school::livewire.school.payments.index', [
            'years' => $years,
            'payments' => $payments,
            'methods' => $methods,
            'studentResults' => $this->studentSearchResults(),
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Paiements',
            'subtitle' => 'Espèces, banque, mobile money, chèque, carte — workflow de validation.',
        ]);
    }
}
