<?php

namespace InovCom\Tickets\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class Ticket extends TenantModel
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'ticket_number',
        'title',
        'description',
        'category',
        'status',
        'priority',
        'assigned_to',
        'created_by',
        'closed_by',
        'store_id',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function comments()
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isEditable(): bool
    {
        return !$this->isClosed();
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_OPEN => 'Ouvert',
            self::STATUS_IN_PROGRESS => 'En cours',
            self::STATUS_RESOLVED => 'Résolu',
            self::STATUS_CLOSED => 'Clôturé',
            default => $status,
        };
    }

    public static function priorityLabel(string $priority): string
    {
        return match ($priority) {
            self::PRIORITY_LOW => 'Basse',
            self::PRIORITY_NORMAL => 'Normale',
            self::PRIORITY_HIGH => 'Haute',
            self::PRIORITY_URGENT => 'Urgente',
            default => $priority,
        };
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => self::statusLabel(self::STATUS_OPEN),
            self::STATUS_IN_PROGRESS => self::statusLabel(self::STATUS_IN_PROGRESS),
            self::STATUS_RESOLVED => self::statusLabel(self::STATUS_RESOLVED),
            self::STATUS_CLOSED => self::statusLabel(self::STATUS_CLOSED),
        ];
    }

    /** @return array<string, string> */
    public static function priorityOptions(): array
    {
        return [
            self::PRIORITY_LOW => self::priorityLabel(self::PRIORITY_LOW),
            self::PRIORITY_NORMAL => self::priorityLabel(self::PRIORITY_NORMAL),
            self::PRIORITY_HIGH => self::priorityLabel(self::PRIORITY_HIGH),
            self::PRIORITY_URGENT => self::priorityLabel(self::PRIORITY_URGENT),
        ];
    }
}
