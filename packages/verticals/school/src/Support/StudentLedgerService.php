<?php

namespace School\Support;

use Illuminate\Support\Facades\Schema;
use School\Models\SchoolFeeStructure;
use School\Models\SchoolPayment;
use School\Models\SchoolStudentLedgerEntry;

class StudentLedgerService
{
    public function balance(int $studentId, ?int $academicYearId = null): float
    {
        if (! $this->ready()) {
            return 0.0;
        }

        // Running balance is always cumulative for the student (year is metadata only).
        $last = SchoolStudentLedgerEntry::query()
            ->where('student_id', $studentId)
            ->orderByDesc('id')
            ->first();

        return $last ? (float) $last->balance_after : 0.0;
    }

    /**
     * Year-scoped tuition snapshot for one student.
     *
     * @return array{charged:float,paid:float,due:float,status:string}
     */
    public function tuitionSnapshot(int $studentId, int $academicYearId): array
    {
        if (! $this->ready()) {
            return ['charged' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'status' => 'unknown'];
        }

        $charged = (float) SchoolStudentLedgerEntry::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->where('entry_type', 'debit')
            ->where('source_type', 'fee')
            ->sum('amount');

        $paid = (float) SchoolStudentLedgerEntry::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->where('entry_type', 'credit')
            ->where('source_type', 'payment')
            ->sum('amount');

        // Annulations de paiement = débits liés à payment
        $reversed = (float) SchoolStudentLedgerEntry::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->where('entry_type', 'debit')
            ->where('source_type', 'payment')
            ->sum('amount');

        $paid = max(0, round($paid - $reversed, 2));
        $due = round($charged - $paid, 2);

        $status = 'unpaid';
        if ($charged <= 0 && $paid <= 0) {
            $status = 'none';
        } elseif ($due <= 0.009) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
        }

        return [
            'charged' => round($charged, 2),
            'paid' => round($paid, 2),
            'due' => max(0, $due),
            'status' => $status,
        ];
    }

    public function recordCreditFromPayment(SchoolPayment $payment): ?SchoolStudentLedgerEntry
    {
        if (! $this->ready() || $payment->status !== 'verified') {
            return null;
        }

        $exists = SchoolStudentLedgerEntry::query()
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->where('entry_type', 'credit')
            ->exists();
        if ($exists) {
            return null;
        }

        return $this->append(
            (int) $payment->student_id,
            $payment->academic_year_id ? (int) $payment->academic_year_id : null,
            'credit',
            (float) $payment->amount,
            'Paiement '.$payment->typeLabel().($payment->reference ? ' — '.$payment->reference : ''),
            'payment',
            (int) $payment->id
        );
    }

    public function reversePaymentCredit(SchoolPayment $payment): ?SchoolStudentLedgerEntry
    {
        if (! $this->ready()) {
            return null;
        }

        $exists = SchoolStudentLedgerEntry::query()
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->where('entry_type', 'debit')
            ->where('label', 'like', 'Annulation%')
            ->exists();
        if ($exists) {
            return null;
        }

        $hadCredit = SchoolStudentLedgerEntry::query()
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->where('entry_type', 'credit')
            ->exists();
        if (! $hadCredit) {
            return null;
        }

        return $this->append(
            (int) $payment->student_id,
            $payment->academic_year_id ? (int) $payment->academic_year_id : null,
            'debit',
            (float) $payment->amount,
            'Annulation paiement #'.$payment->id,
            'payment',
            (int) $payment->id
        );
    }

    /**
     * Charge fee structures for a class/year as debit (idempotent per fee structure).
     */
    public function chargeFeesForEnrollment(int $studentId, int $academicYearId, ?int $classId): int
    {
        if (! $this->ready()) {
            return 0;
        }

        $fees = SchoolFeeStructure::query()
            ->where('is_active', true)
            ->where(function ($q) use ($academicYearId) {
                $q->whereNull('academic_year_id')->orWhere('academic_year_id', $academicYearId);
            })
            ->when($classId, function ($q) use ($classId) {
                $q->where(function ($inner) use ($classId) {
                    $inner->whereNull('class_id')->orWhere('class_id', $classId);
                });
            }, fn ($q) => $q->whereNull('class_id'))
            ->get();

        $n = 0;
        foreach ($fees as $fee) {
            $exists = SchoolStudentLedgerEntry::query()
                ->where('student_id', $studentId)
                ->where('source_type', 'fee')
                ->where('source_id', $fee->id)
                ->where('academic_year_id', $academicYearId)
                ->exists();
            if ($exists) {
                continue;
            }

            $this->append(
                $studentId,
                $academicYearId,
                'debit',
                (float) $fee->amount,
                'Frais — '.$fee->name,
                'fee',
                (int) $fee->id
            );
            $n++;
        }

        return $n;
    }

    protected function append(
        int $studentId,
        ?int $academicYearId,
        string $entryType,
        float $amount,
        string $label,
        ?string $sourceType,
        ?int $sourceId
    ): SchoolStudentLedgerEntry {
        $current = $this->balance($studentId);
        $delta = $entryType === 'credit' ? $amount : -$amount;
        $after = round($current + $delta, 2);

        return SchoolStudentLedgerEntry::query()->create([
            'student_id' => $studentId,
            'academic_year_id' => $academicYearId,
            'entry_type' => $entryType,
            'amount' => $amount,
            'balance_after' => $after,
            'label' => $label,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'created_by_name' => auth('tenant')->user()?->name,
        ]);
    }

    protected function ready(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('school_student_ledger_entries');
        } catch (\Throwable) {
            return false;
        }
    }
}
