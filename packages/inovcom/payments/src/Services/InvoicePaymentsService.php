<?php

namespace InovCom\InvoicePayments\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InovCom\InvoicePayments\Models\FiscalWithholdingType;
use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\InvoicePayments\Models\InvoicePaymentAttachment;
use InovCom\InvoicePayments\Models\InvoicePaymentWithholding;
use InovCom\InvoicePayments\Support\InvoiceFiscalBreakdown;
use InovCom\InvoicePayments\Support\WithholdingCalculator;
use InovCom\InvoicePayments\Support\WithholdingKind;
use InovCom\InvoicePayments\Support\WithholdingSchema;
use InovCom\Invoicing\Models\Invoice;

class InvoicePaymentsService
{
    /**
     * @param  list<array{
     *     type_id?: int|null,
     *     type_code?: string|null,
     *     type_name?: string|null,
     *     base_amount?: float|int|string|null,
     *     rate?: float|int|string|null,
     *     amount?: float|int|string|null,
     *     account_code?: string|null,
     *     comment?: string|null
     * }>  $withholdings
     */
    public function recordPayment(
        int $invoiceId,
        float $amount,
        string $paymentDate,
        string $paymentMethod = 'cash',
        ?string $notes = null,
        ?string $externalRef = null,
        ?int $userId = null,
        array $withholdings = []
    ): InvoicePayment {
        $amount = WithholdingCalculator::roundMoney(max(0, $amount));
        WithholdingSchema::ensure();
        $normalizedWithholdings = $this->normalizeWithholdings($withholdings);

        return DB::connection('tenant')->transaction(function () use (
            $invoiceId,
            $amount,
            $paymentDate,
            $paymentMethod,
            $notes,
            $externalRef,
            $userId,
            $normalizedWithholdings
        ) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoiceId);
            $normalizedWithholdings = $this->applyInvoiceFiscalRules($invoice, $normalizedWithholdings);
            if ($normalizedWithholdings !== []) {
                $amount = WithholdingCalculator::cashDue((float) $invoice->balance, $normalizedWithholdings);
            }

            $summary = WithholdingCalculator::summarize(
                (float) $invoice->total,
                (float) $invoice->amount_paid,
                $amount,
                $normalizedWithholdings
            );

            $this->assertCanReceivePayment($invoice, $summary['settled'], $summary['cash_received'], $summary['withholding_total']);

            $paidBefore = round((float) $invoice->amount_paid, 2);
            $balanceAfter = WithholdingCalculator::roundMoney($summary['remaining']);
            $hasWithholdingColumns = Schema::connection('tenant')->hasColumn('invoice_payments', 'settled_amount');

            $payload = [
                'reference' => $this->generateReceiptReference(),
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'status' => InvoicePayment::STATUS_ACTIVE,
                'amount_paid_before' => $paidBefore,
                'balance_after' => $balanceAfter,
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'external_reference' => $externalRef,
                'notes' => $notes,
                'created_by' => $userId ?? auth('tenant')->id(),
            ];

            if ($hasWithholdingColumns) {
                $payload['withholding_total'] = $summary['withholding_total'];
                $payload['settled_amount'] = $summary['settled'];
            }

            $payment = InvoicePayment::create($payload);

            $this->persistWithholdings($payment, $normalizedWithholdings);

            $this->syncInvoiceFromPayments($invoice);

            app(\InovCom\Invoicing\Services\InvoiceScheduleService::class)
                ->reallocateFromInvoicePaid($invoice->fresh());

            // Auto-capture caisse : seuls les encaissements espèces alimentent le tiroir.
            if ($paymentMethod === 'cash' && $amount > 0.01) {
                $invoiceLabel = $invoice->number ?? $invoice->invoice_number ?? ('#' . $invoice->id);
                \App\Support\CashLedger::recordIn(
                    \App\Support\CashLedger::INVOICE_PAYMENT_CASH_IN,
                    $amount,
                    'Encaissement facture ' . $invoiceLabel,
                    'invoice',
                    InvoicePayment::class,
                    (int) $payment->id,
                    $payment->reference,
                    ['invoice_id' => $invoice->id],
                    $userId
                );
            }

            return $payment->fresh(array_merge(
                ['invoice', 'creator'],
                InvoicePayment::optionalWithholdingsRelation(),
                InvoicePayment::optionalAttachmentsRelation()
            ));
        });
    }

    public function cancelPayment(int $paymentId, string $reason, ?int $userId = null): InvoicePayment
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Indiquez le motif d\'annulation.');
        }

        return DB::connection('tenant')->transaction(function () use ($paymentId, $reason, $userId) {
            $payment = InvoicePayment::lockForUpdate()->findOrFail($paymentId);

            if (!$payment->isActive()) {
                throw new \RuntimeException('Ce paiement est déjà annulé.');
            }

            if ($payment->settledAmount() <= 0) {
                throw new \RuntimeException('Seuls les encaissements positifs peuvent être annulés.');
            }

            $payment->status = InvoicePayment::STATUS_CANCELLED;
            $payment->cancelled_at = now();
            $payment->cancelled_by = $userId ?? auth('tenant')->id();
            $payment->cancellation_reason = $reason;
            $payment->save();

            $invoice = Invoice::lockForUpdate()->findOrFail($payment->invoice_id);
            $this->syncInvoiceFromPayments($invoice);

            app(\InovCom\Invoicing\Services\InvoiceScheduleService::class)
                ->reallocateFromInvoicePaid($invoice->fresh());

            // Contre-passe l'écriture de caisse si l'encaissement annulé était en espèces.
            \App\Support\CashLedger::reverse(
                \App\Support\CashLedger::INVOICE_PAYMENT_CASH_IN,
                InvoicePayment::class,
                (int) $payment->id,
                'Annulation encaissement ' . $payment->reference . ' — ' . $reason,
                $userId
            );

            return $payment->fresh(['invoice', 'creator', 'canceller']);
        });
    }

    public function syncInvoiceFromPayments(Invoice $invoice): Invoice
    {
        if (!Schema::connection('tenant')->hasTable('invoice_payments')) {
            return $invoice;
        }

        $payments = InvoicePayment::query()
            ->where('invoice_id', $invoice->id)
            ->active()
            ->get();

        $amountPaid = WithholdingCalculator::roundMoney(
            (float) $payments->sum(fn (InvoicePayment $payment) => $payment->settledAmount())
        );

        $invoice->amount_paid = $amountPaid;
        $invoice->balance = WithholdingCalculator::roundMoney((float) $invoice->total - $amountPaid);
        $invoice->updatePaymentStatus();

        return $invoice;
    }

    public function getOutstandingTotal(): float
    {
        return (float) Invoice::whereIn('status', ['issued', 'partial'])->sum('balance');
    }

    private function assertCanReceivePayment(
        Invoice $invoice,
        float $settledAmount,
        float $cashReceived = 0.0,
        float $withholdingTotal = 0.0
    ): void {
        if ($invoice->isCancellationDocument()) {
            throw new \RuntimeException('Une facture d\'annulation ne reçoit pas d\'encaissement.');
        }

        if ($invoice->isSuperseded()) {
            throw new \RuntimeException('Cette facture a été remplacée : encaissez sur la nouvelle facture.');
        }

        if (!$invoice->canReceivePayment()) {
            if ($invoice->status === 'paid') {
                throw new \RuntimeException('Cette facture est totalement soldée. Aucun nouvel encaissement n\'est possible.');
            }

            throw new \RuntimeException('Cette facture n\'accepte pas d\'encaissement.');
        }

        if ($settledAmount <= 0) {
            throw new \InvalidArgumentException('Le montant encaissé ou le total des retenues doit être strictement positif.');
        }

        if ($cashReceived < -0.01 || $withholdingTotal < -0.01) {
            throw new \InvalidArgumentException('Les montants d\'encaissement et de retenue ne peuvent pas être négatifs.');
        }

        if ($settledAmount > (float) $invoice->balance + 0.01) {
            throw new \RuntimeException(
                'Montant encaissé + retenues (' . fmt_money($settledAmount) . ' FCFA) dépasse le solde restant ('
                . fmt_money($invoice->balance) . ' FCFA).'
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $withholdings
     * @return list<array{
     *     type_id: int|null,
     *     type_code: string|null,
     *     type_name: string,
     *     base_amount: float,
     *     rate: float,
     *     amount: float,
     *     account_code: string|null,
     *     comment: string|null
     * }>
     */
    private function normalizeWithholdings(array $withholdings): array
    {
        $normalized = [];

        foreach ($withholdings as $row) {
            $amount = WithholdingCalculator::roundMoney((float) ($row['amount'] ?? 0));
            if ($amount <= 0) {
                continue;
            }

            $typeId = isset($row['type_id']) && $row['type_id'] ? (int) $row['type_id'] : null;
            $type = null;
            if ($typeId && Schema::connection('tenant')->hasTable('fiscal_withholding_types')) {
                $type = FiscalWithholdingType::query()->find($typeId);
            }

            $typeName = trim((string) ($row['type_name'] ?? $type?->name ?? ''));
            if ($typeName === '') {
                throw new \InvalidArgumentException('Chaque retenue doit avoir un type identifiable.');
            }

            $normalized[] = [
                'type_id' => $type?->id,
                'type_code' => $type?->code ?? (isset($row['type_code']) ? (string) $row['type_code'] : null),
                'type_name' => $typeName,
                'base_amount' => WithholdingCalculator::roundMoney((float) ($row['base_amount'] ?? 0)),
                'rate' => round((float) ($row['rate'] ?? 0), 4),
                'amount' => $amount,
                'account_code' => isset($row['account_code']) && trim((string) $row['account_code']) !== ''
                    ? trim((string) $row['account_code'])
                    : ($type?->default_account),
                'comment' => isset($row['comment']) && trim((string) $row['comment']) !== ''
                    ? trim((string) $row['comment'])
                    : null,
            ];
        }

        return $normalized;
    }

    /**
     * TVA / IS retenues = montants déjà établis sur la facture. Jamais un nouveau calcul.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function applyInvoiceFiscalRules(Invoice $invoice, array $rows): array
    {
        $fiscal = InvoiceFiscalBreakdown::fromInvoice($invoice);
        $alreadyVat = InvoiceFiscalBreakdown::alreadyWithheldVat($invoice);
        $alreadyIs = InvoiceFiscalBreakdown::alreadyWithheldIs($invoice);
        $corrected = [];

        foreach ($rows as $row) {
            $kind = WithholdingKind::resolve(
                $row['kind'] ?? null,
                $row['type_code'] ?? null,
                $row['type_name'] ?? null
            );

            if ($kind === WithholdingKind::VAT) {
                $error = $fiscal->withholdingError(WithholdingKind::VAT, $alreadyVat);
                if ($error) {
                    throw new \RuntimeException($error);
                }
                $amount = WithholdingCalculator::suggestInvoiceVatAmount(
                    $fiscal->vat,
                    $alreadyVat,
                    (float) $invoice->balance,
                    (float) $invoice->balance
                );
                if ($amount <= 0) {
                    throw new \RuntimeException('La TVA de cette facture a déjà été retenue.');
                }
                $alreadyVat += $amount;
                $row['base_amount'] = $fiscal->ht;
                $row['rate'] = $fiscal->vatRate;
                $row['amount'] = $amount;
            } elseif ($kind === WithholdingKind::IS) {
                $error = $fiscal->withholdingError(WithholdingKind::IS, $alreadyIs);
                if ($error) {
                    throw new \RuntimeException($error);
                }
                if ($fiscal->locksIsAmount()) {
                    $amount = WithholdingCalculator::suggestInvoiceVatAmount(
                        $fiscal->is,
                        $alreadyIs,
                        (float) $invoice->balance,
                        (float) $invoice->balance
                    );
                    if ($amount <= 0) {
                        throw new \RuntimeException('L’IS de cette facture a déjà été retenu.');
                    }
                    $alreadyIs += $amount;
                    $row['base_amount'] = $fiscal->ht;
                    $row['rate'] = $fiscal->isRate;
                    $row['amount'] = $amount;
                }
            }

            $corrected[] = $row;
        }

        return $corrected;
    }

    /**
     * @param  list<array<string, mixed>>  $withholdings
     */
    private function persistWithholdings(InvoicePayment $payment, array $withholdings): void
    {
        if ($withholdings === [] || !Schema::connection('tenant')->hasTable('invoice_payment_withholdings')) {
            return;
        }

        foreach ($withholdings as $row) {
            InvoicePaymentWithholding::create([
                'invoice_payment_id' => $payment->id,
                'withholding_type_id' => $row['type_id'],
                'type_code' => $row['type_code'],
                'type_name' => $row['type_name'],
                'base_amount' => $row['base_amount'],
                'rate' => $row['rate'],
                'amount' => $row['amount'],
                'account_code' => $row['account_code'],
                'comment' => $row['comment'],
            ]);
        }
    }

    private function generateReceiptReference(): string
    {
        $year = now()->year;
        $last = InvoicePayment::whereYear('created_at', $year)
            ->where('reference', 'like', 'REC-' . $year . '-%')
            ->orderByDesc('id')
            ->value('reference');
        $next = 1;

        if ($last && preg_match('/^REC-\d{4}-(\d{6})$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        // Compatibilité : si aucun REC, continuer après le dernier REG
        if ($next === 1) {
            $lastReg = InvoicePayment::whereYear('created_at', $year)
                ->where('reference', 'like', 'REG-' . $year . '-%')
                ->where('reference', 'not like', 'REG-RET-%')
                ->orderByDesc('id')
                ->value('reference');
            if ($lastReg && preg_match('/^REG-\d{4}-(\d{6})$/', (string) $lastReg, $m)) {
                $next = ((int) $m[1]) + 1;
            }
        }

        return 'REC-' . $year . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function generateReturnReference(): string
    {
        $year = now()->year;
        $last = InvoicePayment::whereYear('created_at', $year)
            ->where('reference', 'like', 'REG-RET-' . $year . '-%')
            ->orderByDesc('id')
            ->value('reference');
        $next = 1;

        if ($last && preg_match('/^REG-RET-\d{4}-(\d{6})$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return 'REG-RET-' . $year . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public function storeAttachment(
        InvoicePayment $payment,
        string $path,
        string $originalName,
        ?string $mimeType = null,
        ?int $sizeBytes = null,
        ?int $userId = null,
        ?string $label = null
    ): InvoicePaymentAttachment {
        WithholdingSchema::ensure();

        if (! Schema::connection('tenant')->hasTable('invoice_payment_attachments')) {
            throw new \RuntimeException('La table des justificatifs de retenue n\'est pas disponible.');
        }

        $attachment = InvoicePaymentAttachment::create([
            'invoice_payment_id' => $payment->id,
            'label' => $label ?: 'Attestation de retenue',
            'original_name' => $originalName,
            'path' => $path,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'uploaded_by' => $userId ?? auth('tenant')->id(),
        ]);

        InvoicePayment::rememberAttachmentsTable(true);

        return $attachment;
    }

    public function storeUploadedCertificate(InvoicePayment $payment, $file, ?int $userId = null): InvoicePaymentAttachment
    {
        $directory = 'invoice-payments/'.$payment->id;
        Storage::disk('public')->makeDirectory($directory);
        $extension = $file->getClientOriginalExtension() ?: 'pdf';
        $filename = Str::random(24).'.'.$extension;
        $path = $file->storeAs($directory, $filename, 'public');

        return $this->storeAttachment(
            $payment,
            $path,
            $file->getClientOriginalName(),
            $file->getMimeType(),
            $file->getSize(),
            $userId
        );
    }

    public function replaceUploadedCertificate(InvoicePaymentAttachment $attachment, $file, ?int $userId = null): InvoicePaymentAttachment
    {
        $payment = $attachment->payment;
        if (! $payment) {
            throw new \RuntimeException('Encaissement introuvable pour ce justificatif.');
        }

        $directory = 'invoice-payments/'.$payment->id;
        Storage::disk('public')->makeDirectory($directory);
        $extension = $file->getClientOriginalExtension() ?: 'pdf';
        $filename = Str::random(24).'.'.$extension;
        $path = $file->storeAs($directory, $filename, 'public');
        $oldPath = $attachment->path;

        $attachment->update([
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $userId ?? auth('tenant')->id(),
        ]);

        if ($oldPath && $oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return $attachment->fresh();
    }

    public function deleteAttachment(InvoicePaymentAttachment $attachment): void
    {
        if ($attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }
        $attachment->delete();
    }
}
