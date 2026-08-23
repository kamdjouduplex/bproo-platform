<?php

namespace InovCom\Prospects\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class ProspectActivity extends TenantModel
{
    public const TYPE_NOTE = 'note';

    public const TYPE_CALL = 'call';

    public const TYPE_WHATSAPP = 'whatsapp';

    public const TYPE_MEETING = 'meeting';

    public const TYPE_EMAIL = 'email';

    public const TYPE_STATUS = 'status';

    public const TYPE_TASK = 'task';

    public const TYPE_DEMO = 'demo';

    public const TYPE_PRESENTATION = 'presentation';

    public const TYPE_REUNION = 'reunion';

    public const TYPE_FOLLOWUP = 'relance';

    public const TYPE_VISIT = 'visite';

    public const TYPE_OTHER = 'autre';

    public const STATE_PLANNED = 'planned';

    public const STATE_DONE = 'done';

    public const STATE_CANCELLED = 'cancelled';

    protected $fillable = [
        'prospect_id',
        'opportunity_id',
        'user_id',
        'assignee_id',
        'type',
        'summary',
        'state',
        'body',
        'result',
        'due_at',
        'completed_at',
        'from_status',
        'to_status',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function prospect()
    {
        return $this->belongsTo(Prospect::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(\InovCom\Crm\Models\Opportunity::class, 'opportunity_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function isPlanned(): bool
    {
        return $this->state === self::STATE_PLANNED;
    }

    public function isOverdue(): bool
    {
        return $this->isPlanned()
            && $this->due_at
            && $this->due_at->lt(now());
    }

    public function isDueToday(): bool
    {
        return $this->isPlanned()
            && $this->due_at
            && $this->due_at->isToday();
    }

    public function isUpcoming(): bool
    {
        return $this->isPlanned()
            && $this->due_at
            && $this->due_at->isFuture()
            && ! $this->due_at->isToday();
    }

    /** En retard | Aujourd'hui | À venir | Terminée | Annulée | Planifiée */
    public function calendarLabel(): string
    {
        if ($this->state === self::STATE_DONE) {
            return 'Terminée';
        }
        if ($this->state === self::STATE_CANCELLED) {
            return 'Annulée';
        }
        if ($this->isOverdue()) {
            return 'En retard';
        }
        if ($this->isDueToday()) {
            return 'Aujourd\'hui';
        }
        if ($this->isUpcoming()) {
            return 'À venir';
        }

        return 'Planifiée';
    }

    public function calendarTone(): string
    {
        return match ($this->calendarLabel()) {
            'En retard' => 'rose',
            'Aujourd\'hui' => 'orange',
            'À venir' => 'green',
            'Terminée' => 'blue',
            default => 'slate',
        };
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_NOTE => 'Note',
            self::TYPE_CALL => 'Appel',
            self::TYPE_WHATSAPP => 'WhatsApp',
            self::TYPE_MEETING => 'Rendez-vous',
            self::TYPE_REUNION => 'Réunion',
            self::TYPE_DEMO => 'Démonstration',
            self::TYPE_PRESENTATION => 'Présentation',
            self::TYPE_FOLLOWUP => 'Relance',
            self::TYPE_VISIT => 'Visite',
            self::TYPE_EMAIL => 'E-mail',
            self::TYPE_TASK => 'Tâche',
            self::TYPE_OTHER => 'Autre',
            self::TYPE_STATUS => 'Statut',
        ];
    }

    public static function actionableTypeOptions(): array
    {
        return [
            self::TYPE_CALL => 'Appel',
            self::TYPE_WHATSAPP => 'WhatsApp',
            self::TYPE_EMAIL => 'E-mail',
            self::TYPE_MEETING => 'Rendez-vous',
            self::TYPE_DEMO => 'Démonstration',
            self::TYPE_FOLLOWUP => 'Relance',
            self::TYPE_VISIT => 'Visite',
            self::TYPE_NOTE => 'Note',
            self::TYPE_TASK => 'Tâche',
            self::TYPE_OTHER => 'Autre',
        ];
    }

    /** Liste des types utilisables dans les règles de validation Livewire. */
    public static function actionableTypeKeys(): string
    {
        return implode(',', array_keys(self::actionableTypeOptions()));
    }

    public static function stateOptions(): array
    {
        return [
            self::STATE_PLANNED => 'Planifiée',
            self::STATE_DONE => 'Terminée',
            self::STATE_CANCELLED => 'Annulée',
        ];
    }

    public static function typeLabel(string $type): string
    {
        return self::typeOptions()[$type] ?? $type;
    }

    public static function stateLabel(string $state): string
    {
        return self::stateOptions()[$state] ?? $state;
    }

    public function displayTitle(): string
    {
        if (filled($this->summary)) {
            return (string) $this->summary;
        }

        return self::typeLabel((string) $this->type);
    }
}
