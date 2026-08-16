<?php

namespace School\Models;

use Illuminate\Support\Facades\Storage;
use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;
use School\Support\SchoolPaymentCatalog;

class SchoolPayment extends TenantModel
{
    use Auditable;

    protected $table = 'school_payments';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'payment_type',
        'currency_code',
        'amount',
        'status',
        'paid_at',
        'reference',
        'payer_name',
        'bank_name',
        'channel_detail',
        'proof_path',
        'proof_original_name',
        'notes',
        'verified_at',
        'verified_by_name',
        'rejected_at',
        'rejected_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(SchoolStudent::class, 'student_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function receipts()
    {
        return $this->hasMany(StudentReceipt::class, 'payment_id');
    }

    public function typeLabel(): string
    {
        return SchoolPaymentCatalog::label((string) $this->payment_type);
    }

    public function statusLabel(): string
    {
        return SchoolPaymentCatalog::statusLabel((string) $this->status);
    }

    public function hasProof(): bool
    {
        return filled($this->proof_path);
    }

    public function canVerify(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        if (SchoolPaymentCatalog::requiresProof((string) $this->payment_type) && ! $this->hasProof()) {
            return false;
        }

        if (SchoolPaymentCatalog::requiresReference((string) $this->payment_type) && ! filled($this->reference)) {
            return false;
        }

        return true;
    }

    public function proofUrl(?string $tenantCode = null): ?string
    {
        if (! $this->proof_path) {
            return null;
        }

        $tenantCode = $tenantCode
            ?? request()->query('tenant')
            ?? request()->attributes->get('tenant')?->code
            ?? session('tenant_code');

        if (! $tenantCode || ! \Illuminate\Support\Facades\Route::has('tenant.school.payments.proof')) {
            // Fallback (works only if public/storage → storage/app/public is linked)
            if (Storage::disk('public')->exists($this->proof_path)) {
                return Storage::disk('public')->url($this->proof_path);
            }

            return null;
        }

        return route('tenant.school.payments.proof', [
            'tenant' => $tenantCode,
            'payment' => $this->id,
        ]);
    }
}
