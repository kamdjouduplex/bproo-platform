<?php

namespace InovCom\Invoicing\Models;

use Carbon\Carbon;
use InovCom\Clients\Models\Client;
use InovCom\Kernel\TenantModel;
use InovCom\Quotations\Models\Quotation;
use InovCom\Users\Models\User;

class Invoice extends TenantModel
{
    protected $fillable = [
        'invoice_number',
        'declaration_type',
        'client_id',
        'quotation_id',
        'invoice_date',
        'due_date',
        'status',
        'document_type',
        'source_invoice_id',
        'subtotal',
        'discount_amount',
        'discount_percent',
        'discount_mode',
        'tax_amount',
        'total',
        'amount_paid',
        'balance',
        'notes',
        'customer_reference',
        'quotation_reference',
        'delivery_note_number',
        'additional_info',
        'payment_mode',
        'show_markup_coefficient',
        'store_id',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'show_markup_coefficient' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public static function openForQuotation(int $quotationId): ?self
    {
        return self::query()
            ->where('quotation_id', $quotationId)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('id')
            ->first();
    }

    public function lines()
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('id');
    }

    public function taxLines()
    {
        return $this->hasMany(InvoiceTaxLine::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceInvoice()
    {
        return $this->belongsTo(Invoice::class, 'source_invoice_id');
    }

    public function derivedInvoices()
    {
        return $this->hasMany(Invoice::class, 'source_invoice_id');
    }

    public function isCancellationDocument(): bool
    {
        return ($this->document_type ?? 'standard') === 'cancellation';
    }

    public function isReplacementDocument(): bool
    {
        return ($this->document_type ?? 'standard') === 'replacement';
    }

    public function isSuperseded(): bool
    {
        return $this->status === 'superseded';
    }

    public function deliveryNotes()
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function confirmedDeliveries()
    {
        return $this->deliveryNotes()->where('status', DeliveryNote::STATUS_CONFIRMED);
    }

    public function draftDeliveries()
    {
        return $this->deliveryNotes()->where('status', DeliveryNote::STATUS_DRAFT);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    public function payments()
    {
        if (!\Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('invoice_payments')) {
            return $this->hasMany(\InovCom\InvoicePayments\Models\InvoicePayment::class)->whereRaw('0 = 1');
        }

        return $this->hasMany(\InovCom\InvoicePayments\Models\InvoicePayment::class);
    }

    public function schedules()
    {
        if (!\Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('invoice_schedules')) {
            return $this->hasMany(InvoiceSchedule::class)->whereRaw('0 = 1');
        }

        return $this->hasMany(InvoiceSchedule::class)
            ->orderBy('due_date')
            ->orderBy('installment_number');
    }

    public function hasInstallmentSchedule(): bool
    {
        if (!\Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('invoice_schedules')) {
            return false;
        }

        return $this->schedules()->exists();
    }

    public function canReceivePayment(): bool
    {
        if ($this->isCancellationDocument() || $this->isSuperseded()) {
            return false;
        }

        return in_array($this->status, ['issued', 'partial'], true) && (float) $this->balance > 0.01;
    }

    public function updatePaymentStatus(): void
    {
        if ($this->status === 'cancelled' || $this->status === 'draft') {
            return;
        }

        $this->balance = round((float) $this->total - (float) $this->amount_paid, 2);

        if ($this->balance <= 0.01) {
            $this->status = 'paid';
            if ($this->balance >= -0.01) {
                $this->balance = 0;
            }
        } elseif ((float) $this->amount_paid > 0) {
            $this->status = 'partial';
        } else {
            $this->status = 'issued';
        }

        $this->save();
    }

    public function hasClientCredit(): bool
    {
        return (float) $this->balance < -0.01;
    }

    public function clientCreditAmount(): float
    {
        return $this->hasClientCredit() ? abs((float) $this->balance) : 0.0;
    }

    public static function declarationLabel(string $type): string
    {
        return match ($type) {
            'declared' => 'Avec déclaration',
            'non_declared' => 'Sans déclaration',
            default => $type,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Brouillon',
            'issued' => 'Émise',
            'partial' => 'Partiellement payée',
            'paid' => 'Payée',
            'cancelled' => 'Annulée',
            'superseded' => 'Remplacée (retour)',
            default => $status,
        };
    }

    /**
     * Modes de paiement affichés sur la facture imprimée (valeur stockée = libellé).
     *
     * @return array<string, string>
     */
    public static function paymentModeOptions(): array
    {
        return [
            '' => 'Par défaut (configuration)',
            'Espèces' => 'Espèces',
            'Chèque' => 'Chèque',
            'Virement bancaire' => 'Virement bancaire',
            'Mobile Money (OM / MTN)' => 'Mobile Money (OM / MTN)',
            'Chèque / virement / espèces' => 'Chèque / virement / espèces',
            'À crédit' => 'À crédit',
        ];
    }

    public function paymentProgressPercent(): float
    {
        if ((float) $this->total <= 0) {
            return 0;
        }

        return min(100, max(0, ((float) $this->amount_paid / (float) $this->total) * 100));
    }

    /**
     * Facture impayée dont l'échéance (ou une tranche d'échéancier) est dépassée.
     */
    public function isOverdue(): bool
    {
        if ($this->isCancellationDocument() || $this->isSuperseded()) {
            return false;
        }

        if (!in_array($this->status, ['issued', 'partial'], true)) {
            return false;
        }

        if ((float) $this->balance <= 0.01) {
            return false;
        }

        if ($this->hasInstallmentSchedule()) {
            $today = Carbon::today()->toDateString();

            return $this->schedules()
                ->where('status', '!=', 'paid')
                ->whereDate('due_date', '<', $today)
                ->whereRaw('amount_due - amount_paid > 0.01')
                ->exists();
        }

        $due = $this->due_date ?? $this->invoice_date;
        if (!$due) {
            return false;
        }

        return Carbon::parse($due)->startOfDay()->lt(Carbon::today());
    }

    public function daysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        if ($this->hasInstallmentSchedule()) {
            $oldest = $this->schedules()
                ->where('status', '!=', 'paid')
                ->whereDate('due_date', '<', Carbon::today()->toDateString())
                ->whereRaw('amount_due - amount_paid > 0.01')
                ->orderBy('due_date')
                ->first();

            if ($oldest) {
                return max(0, (int) Carbon::parse($oldest->due_date)->startOfDay()->diffInDays(Carbon::today()));
            }
        }

        $due = Carbon::parse($this->due_date ?? $this->invoice_date)->startOfDay();

        return max(0, (int) $due->diffInDays(Carbon::today()));
    }
}
