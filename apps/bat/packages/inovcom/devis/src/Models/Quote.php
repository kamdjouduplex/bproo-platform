<?php

namespace InovCom\Devis\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;
use InovCom\Kernel\Traits\WorkflowStateMachine;

class Quote extends TenantModel
{
    use Auditable, WorkflowStateMachine {
        stampTransitionTimestamp as protected baseStampTransitionTimestamp;
    }

    protected $table = 'quotes';

    protected $fillable = [
        'code', 'client_id', 'offer_id', 'title', 'status',
        'valid_until', 'notes', 'internal_notes', 'terms',
        // Financial
        'total_ht', 'tax_rate', 'discount_percent', 'discount_amount',
        'net_ht', 'tax_amount', 'total_ttc', 'margin_percent', 'currency',
        // Workflow timestamps
        'sent_at', 'accepted_at', 'refused_at', 'refuse_reason',
        'expired_at', 'last_reminder_at',
        // Versioning
        'version', 'parent_id',
    ];

    protected $casts = [
        'valid_until'      => 'date',
        'total_ht'         => 'decimal:2',
        'tax_rate'         => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount'  => 'decimal:2',
        'net_ht'           => 'decimal:2',
        'tax_amount'       => 'decimal:2',
        'total_ttc'        => 'decimal:2',
        'margin_percent'   => 'decimal:2',
        'sent_at'          => 'datetime',
        'accepted_at'      => 'datetime',
        'refused_at'       => 'datetime',
        'expired_at'       => 'datetime',
        'last_reminder_at' => 'datetime',
    ];

    // ── WorkflowStateMachine ──────────────────────────────────────────
    public function allowedTransitions(): array
    {
        return [
            'draft'    => ['sent'],
            'sent'     => ['accepted', 'refused', 'expired'],
            'accepted' => [],
            'refused'  => ['draft'],
            'expired'  => ['draft', 'refused'],
        ];
    }

    protected function stampTransitionTimestamp(string $status): void
    {
        $this->baseStampTransitionTimestamp($status);

        if ($status === 'expired' && $this->hasColumn('expired_at')) {
            $this->expired_at = now();
        }
    }

    // ── Edit lock ─────────────────────────────────────────────────────
    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isLocked(): bool
    {
        return !$this->isEditable();
    }

    public function isExpiredByDate(): bool
    {
        return $this->valid_until
            && $this->valid_until->isPast()
            && in_array($this->status, ['sent', 'draft'], true);
    }

    public function daysUntilExpiry(): ?int
    {
        if (!$this->valid_until) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->valid_until, false);
    }

    // ── Scopes ────────────────────────────────────────────────────────
    public function scopeOrdered($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // ── Relationships ─────────────────────────────────────────────────
    public function client()
    {
        return $this->belongsTo(\InovCom\Clients\Models\Client::class, 'client_id');
    }

    public function offer()
    {
        return $this->belongsTo(\InovCom\Offres\Models\Offer::class, 'offer_id');
    }

    public function lines()
    {
        return $this->hasMany(QuoteLine::class, 'quote_id')->orderBy('position');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function revisions()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('version');
    }

    public function familyRootId(): int
    {
        return (int) ($this->parent_id ?? $this->id);
    }

    public function familyRoot(): self
    {
        if ($this->parent_id) {
            return static::on($this->getConnectionName())->find($this->parent_id) ?? $this;
        }

        return $this;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, self> */
    public function versionFamily()
    {
        $rootId = $this->familyRootId();

        return static::on($this->getConnectionName())
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)->orWhere('parent_id', $rootId);
            })
            ->orderBy('version')
            ->get();
    }

    public function isLatestVersion(): bool
    {
        $rootId = $this->familyRootId();
        $maxVersion = (int) static::on($this->getConnectionName())
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)->orWhere('parent_id', $rootId);
            })
            ->max('version');

        return (int) $this->version === $maxVersion;
    }

    public function scopeLatestVersionOnly($query)
    {
        $table = $query->getModel()->getTable();

        return $query->whereRaw(
            "{$table}.version = (
                SELECT MAX(q2.version)
                FROM {$table} q2
                WHERE COALESCE(q2.parent_id, q2.id) = COALESCE({$table}.parent_id, {$table}.id)
            )"
        );
    }

    public function project()
    {
        return $this->hasOne(\InovCom\Projets\Models\Project::class, 'quote_id');
    }

    public function invoices()
    {
        return $this->hasMany(\InovCom\Facturation\Models\Invoice::class, 'quote_id');
    }

    // ── Business logic ────────────────────────────────────────────────
    public function recalculateTotals(): void
    {
        $lines = $this->lines()->get();

        $totalHt = (float) $lines
            ->filter(fn ($line) => ($line->line_type ?? '') !== 'section')
            ->sum('amount');

        $discount = round($totalHt * ((float) $this->discount_percent / 100), 2);
        $netHt = round($totalHt - $discount, 2);
        $tax = round($netHt * ((float) $this->tax_rate / 100), 2);
        $totalTtc = round($netHt + $tax, 2);

        $totalCost = (float) $lines
            ->filter(fn ($line) => ($line->line_type ?? '') !== 'section')
            ->sum('cost');

        $margin = $totalHt > 0
            ? round(($totalHt - $totalCost) / $totalHt * 100, 2)
            : null;

        $this->total_ht        = $totalHt;
        $this->discount_amount = $discount;
        $this->net_ht          = $netHt;
        $this->tax_amount      = $tax;
        $this->total_ttc       = $totalTtc;
        $this->margin_percent  = $margin;
        $this->saveQuietly();
    }

    public function recalculateTotal(): void
    {
        $this->recalculateTotals();
    }
}
