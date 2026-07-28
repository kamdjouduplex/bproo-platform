<?php

namespace InovCom\Offres\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;
use InovCom\Kernel\Traits\WorkflowStateMachine;

class Offer extends TenantModel
{
    use Auditable, WorkflowStateMachine;

    protected $table = 'offers';

    protected $fillable = [
        'code', 'title', 'category', 'client_id', 'assigned_to',
        'source', 'channel', 'channel_detail',
        'status', 'received_at', 'notes',
        'close_reason', 'close_note', 'quote_id',
    ];

    protected $casts = [
        'received_at' => 'date',
    ];

    // ── Status constants ──────────────────────────────────────────────
    const STATUS_DRAFT      = 'draft';
    const STATUS_IN_REVIEW  = 'in_review';
    const STATUS_TERMINATED = 'terminated';
    const STATUS_CLOSED     = 'closed';

    // ── Close reason types ────────────────────────────────────────────
    const CLOSE_REASONS = [
        'not_interesting' => 'Pas intéressante / hors périmètre',
        'budget'          => 'Budget insuffisant',
        'competition'     => 'Perdu face à la concurrence',
        'no_response'     => 'Sans suite / pas de réponse client',
        'technical'       => 'Contraintes techniques',
        'strategic'       => 'Décision stratégique interne',
        'other'           => 'Autre raison',
    ];

    // ── Channel labels ────────────────────────────────────────────────
    const CHANNELS = [
        'facebook'      => 'Facebook',
        'linkedin'      => 'LinkedIn',
        'tv'            => 'TV',
        'website'       => 'Site Web',
        'email'         => 'Email',
        'telephone'     => 'Téléphone',
        'referencement' => 'Référencement',
        'autre'         => 'Autre',
    ];

    // ── WorkflowStateMachine ──────────────────────────────────────────
    public function allowedTransitions(): array
    {
        return [
            self::STATUS_DRAFT      => [self::STATUS_IN_REVIEW],
            self::STATUS_IN_REVIEW  => [self::STATUS_TERMINATED, self::STATUS_CLOSED],
            self::STATUS_TERMINATED => [],
            self::STATUS_CLOSED     => [],
        ];
    }

    // ── Business guards ───────────────────────────────────────────────

    /** May move to In Review only when an assignee is set. */
    public function canMoveToInReview(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->assigned_to !== null;
    }

    /** May be closed only if not already terminal. */
    public function canClose(): bool
    {
        return !in_array($this->status, [self::STATUS_TERMINATED, self::STATUS_CLOSED], true);
    }

    /** A quote may be created only when In Review. */
    public function canCreateQuote(): bool
    {
        return $this->status === self::STATUS_IN_REVIEW;
    }

    // ── Scopes ────────────────────────────────────────────────────────
    public function scopeOrdered($query)
    {
        return $query->orderBy('received_at', 'desc')->orderBy('created_at', 'desc');
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
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

    public function assignedUser()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'assigned_to');
    }

    public function quote()
    {
        return $this->belongsTo(\InovCom\Devis\Models\Quote::class, 'quote_id');
    }
}
