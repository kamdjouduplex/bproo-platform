<?php

namespace InovCom\InvoicePayments\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\Invoicing\Models\Invoice;

class InvoicePaymentsService
{
    public function recordPayment(
        int $invoiceId,
        float $amount,
        string $paymentDate,
        string $paymentMethod = 'cash',
        ?string $notes = null,
        ?string $externalRef = null,
        ?int $userId = null
    ): InvoicePayment {
        $amount = round($amount, 2);

        return DB::connection('tenant')->transaction(function () use (
            $invoiceId,
            $amount,
            $paymentDate,
            $paymentMethod,
            $notes,
            $externalRef,
            $userId
        ) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoiceId);

            $this->assertCanReceivePayment($invoice, $amount);

            $paidBefore = round((float) $invoice->amount_paid, 2);
            $total = round((float) $invoice->total, 2);
            $balanceAfter = round($total - $paidBefore - $amount, 2);

            $payment = InvoicePayment::create([
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
            ]);

            $this->syncInvoiceFromPayments($invoice);

            app(\InovCom\Invoicing\Services\InvoiceScheduleService::class)
                ->reallocateFromInvoicePaid($invoice->fresh());

            // Auto-capture caisse : seuls les encaissements espèces alimentent le tiroir.
            if ($paymentMethod === 'cash') {
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

            return $payment->fresh(['invoice', 'creator']);
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

            if ((float) $payment->amount <= 0) {
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

        $amountPaid = (float) InvoicePayment::query()
            ->where('invoice_id', $invoice->id)
            ->active()
            ->sum('amount');

        $invoice->amount_paid = round($amountPaid, 2);
        $invoice->balance = round((float) $invoice->total - $amountPaid, 2);
        $invoice->updatePaymentStatus();

        return $invoice;
    }

    public function getOutstandingTotal(): float
    {
        return (float) Invoice::whereIn('status', ['issued', 'partial'])->sum('balance');
    }

    private function assertCanReceivePayment(Invoice $invoice, float $amount): void
    {
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

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être strictement positif.');
        }

        if ($amount > (float) $invoice->balance + 0.01) {
            throw new \RuntimeException(
                'Le montant (' . fmt_money($amount) . ' FCFA) dépasse le solde restant ('
                . fmt_money($invoice->balance) . ' FCFA).'
            );
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
}
