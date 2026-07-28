<?php

namespace InovCom\Facturation\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;
use InovCom\Kernel\Traits\WorkflowStateMachine;

class Invoice extends TenantModel
{
    use Auditable, WorkflowStateMachine;

    protected $table = 'invoices';

    protected $fillable = [
        'code', 'client_id', 'project_id', 'quote_id', 'title', 'status',
        'issue_date', 'due_date', 'notes', 'internal_notes',
        // Financial
        'total_ht', 'tax_rate', 'discount_percent', 'discount_amount',
        'net_ht', 'tax_amount', 'total_ttc', 'currency',
        // Payment
        'payment_terms', 'payment_method', 'bank_details',
        'amount_paid', 'amount_due',
        // Type
        'invoice_type', 'credit_note_for',
        // Workflow
        'sent_at', 'paid_at', 'overdue_at',
        // Dunning
        'reminder_count', 'last_reminder_at',
    ];

    protected $casts = [
        'issue_date'       => 'date',
        'due_date'         => 'date',
        'total_ht'         => 'decimal:2',
        'tax_rate'         => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount'  => 'decimal:2',
        'net_ht'           => 'decimal:2',
        'tax_amount'       => 'decimal:2',
        'total_ttc'        => 'decimal:2',
        'amount_paid'      => 'decimal:2',
        'amount_due'       => 'decimal:2',
        'sent_at'          => 'datetime',
        'paid_at'          => 'datetime',
        'overdue_at'       => 'datetime',
        'last_reminder_at' => 'datetime',
    ];

    // ── WorkflowStateMachine ──────────────────────────────────────────
    public function allowedTransitions(): array
    {
        return [
            'draft'     => ['sent', 'cancelled'],
            'sent'      => ['paid', 'overdue', 'cancelled'],
            'overdue'   => ['paid', 'cancelled'],
            'paid'      => [],          // terminal
            'cancelled' => [],          // terminal
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────
    public function scopeOrdered($query)
    {
        return $query->orderBy('issue_date', 'desc')->orderBy('created_at', 'desc');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'sent')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString());
    }

    // ── Relationships ─────────────────────────────────────────────────
    public function client()
    {
        return $this->belongsTo(\InovCom\Clients\Models\Client::class, 'client_id');
    }

    public function project()
    {
        return $this->belongsTo(\InovCom\Projets\Models\Project::class, 'project_id');
    }

    public function quote()
    {
        return $this->belongsTo(\InovCom\Devis\Models\Quote::class, 'quote_id');
    }

    public function lines()
    {
        return $this->hasMany(InvoiceLine::class, 'invoice_id')->orderBy('position');
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class, 'invoice_id')->orderBy('payment_date');
    }

    // ── Business logic ────────────────────────────────────────────────

    /**
     * Recalculate totals from lines and persist.
     */
    public function recalculateTotals(): void
    {
        $lines = $this->lines()->get();
        $totalHt = (float) $lines->sum('amount');
        $discount = round($totalHt * ((float) $this->discount_percent / 100), 2);
        $netHt = round($totalHt - $discount, 2);
        $tax = round($netHt * ((float) $this->tax_rate / 100), 2);
        $totalTtc = round($netHt + $tax, 2);

        $this->total_ht        = $totalHt;
        $this->discount_amount = $discount;
        $this->net_ht          = $netHt;
        $this->tax_amount      = $tax;
        $this->total_ttc       = $totalTtc;
        $this->amount_due      = round($totalTtc - (float) $this->amount_paid, 2);
        $this->saveQuietly();
    }

    /**
     * Record a payment. Auto-transitions to 'paid' if fully settled.
     */
    public function recordPayment(float $amount, string $date, ?string $method, ?string $reference, ?int $userId): InvoicePayment
    {
        $payment = $this->payments()->create([
            'amount'         => $amount,
            'payment_date'   => $date,
            'payment_method' => $method,
            'reference'      => $reference,
            'recorded_by'    => $userId,
        ]);

        $this->amount_paid = round((float) $this->amount_paid + $amount, 2);
        $this->amount_due  = round((float) $this->total_ttc - (float) $this->amount_paid, 2);
        $this->saveQuietly();

        // Auto-transition to paid if fully settled.
        if ((float) $this->amount_due <= 0 && $this->status !== 'paid') {
            $this->transitionTo('paid', $userId);
        }

        return $payment;
    }

    /**
     * Compute and persist overdue_at based on issue_date + payment_terms.
     */
    public function computeOverdueAt(): void
    {
        if ($this->issue_date && $this->payment_terms) {
            $this->overdue_at = $this->issue_date->addDays($this->payment_terms);
            $this->saveQuietly();
        }
    }

    public function isOverdue(): bool
    {
        return $this->status === 'sent'
            && $this->due_date
            && $this->due_date->isPast();
    }
}
