<?php

namespace InovCom\Prospects\Models;

use InovCom\Clients\Models\Client;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class Prospect extends TenantModel
{
    public const STATUS_NOUVEAU = 'nouveau';

    public const STATUS_A_QUALIFIER = 'a_qualifier';

    public const STATUS_QUALIFIE = 'qualifie';

    public const STATUS_NON_QUALIFIE = 'non_qualifie';

    public const STATUS_CONVERTI = 'converti';

    /** @deprecated Pipeline déplacé vers Opportunity — conservé pour conversion ERP */
    public const STATUS_GAGNE = 'gagne';

    /** @deprecated Conservé pour rétrocompatibilité */
    public const STATUS_CONTACTE = 'contacte';

    /** @deprecated Conservé pour rétrocompatibilité */
    public const STATUS_NEGOCIATION = 'negociation';

    /** @deprecated Conservé pour rétrocompatibilité — motif sur Opportunity */
    public const STATUS_PERDU = 'perdu';

    public const SOURCE_SITE = 'site';

    public const SOURCE_WHATSAPP = 'whatsapp';

    public const SOURCE_FACEBOOK = 'facebook';

    public const SOURCE_INSTAGRAM = 'instagram';

    public const SOURCE_TIKTOK = 'tiktok';

    public const SOURCE_YOUTUBE = 'youtube';

    public const SOURCE_RECO = 'recommandation';

    public const SOURCE_APPEL = 'appel';

    public const SOURCE_PROSPECTION = 'prospection';

    public const SOURCE_EVENT = 'evenement';

    public const SOURCE_IMPORT = 'import';

    public const SOURCE_OTHER = 'other';

    /** @deprecated alias */
    public const SOURCE_SALON = 'salon';

    /** @deprecated alias */
    public const SOURCE_CAMPAGNE = 'campagne';

    protected $fillable = [
        'reference',
        'name',
        'first_name',
        'last_name',
        'company_name',
        'job_title',
        'type',
        'email',
        'phone',
        'whatsapp',
        'address',
        'city',
        'sector',
        'tax_id',
        'rccm',
        'niu',
        'source',
        'status',
        'cost',
        'expected_value',
        'owner_id',
        'notes',
        'need',
        'product_interest',
        'problem',
        'expectations',
        'decision_maker_name',
        'need_score',
        'decision_score',
        'budget_score',
        'timeline_score',
        'interaction_score',
        'score',
        'estimated_budget',
        'decision_deadline',
        'last_contacted_at',
        'is_favorite',
        'lost_reason',
        'converted_client_id',
        'converted_at',
        'store_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'expected_value' => 'decimal:2',
        'estimated_budget' => 'decimal:2',
        'converted_at' => 'datetime',
        'decision_deadline' => 'date',
        'last_contacted_at' => 'datetime',
        'is_favorite' => 'boolean',
        'need_score' => 'integer',
        'decision_score' => 'integer',
        'budget_score' => 'integer',
        'timeline_score' => 'integer',
        'interaction_score' => 'integer',
        'score' => 'integer',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedClient()
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
    }

    public function activities()
    {
        return $this->hasMany(ProspectActivity::class)->orderByDesc('created_at');
    }

    public function plannedActivities()
    {
        return $this->hasMany(ProspectActivity::class)
            ->where('state', ProspectActivity::STATE_PLANNED)
            ->orderBy('due_at');
    }

    public function nextPlannedActivity()
    {
        return $this->hasOne(ProspectActivity::class)
            ->where('state', ProspectActivity::STATE_PLANNED)
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at');
    }

    public function lastCompletedActivity()
    {
        return $this->hasOne(ProspectActivity::class)->ofMany(['id' => 'max'], function ($query) {
            $query->where('state', ProspectActivity::STATE_DONE)
                ->where('type', '!=', ProspectActivity::TYPE_STATUS);
        });
    }

    public function opportunities()
    {
        return $this->hasMany(\InovCom\Crm\Models\Opportunity::class)->orderByDesc('updated_at');
    }

    public function openOpportunities()
    {
        return $this->hasMany(\InovCom\Crm\Models\Opportunity::class)
            ->whereIn('stage', ['qualification', 'qualifiee', 'opportunite', 'suivi', 'intention']);
    }

    public function primaryOpportunity()
    {
        return $this->hasOne(\InovCom\Crm\Models\Opportunity::class)
            ->orderByRaw("CASE WHEN stage IN ('qualification','qualifiee','opportunite','suivi','intention') THEN 0 WHEN stage = 'gagne' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at');
    }

    public function contactName(): string
    {
        $full = trim(implode(' ', array_filter([
            trim((string) ($this->first_name ?? '')),
            trim((string) ($this->last_name ?? '')),
        ])));

        if ($full !== '') {
            return $full;
        }

        if ($this->type === 'individual') {
            return (string) $this->name;
        }

        return (string) ($this->name ?: '—');
    }

    public function companyDisplayName(): string
    {
        if (filled($this->company_name)) {
            return (string) $this->company_name;
        }

        if ($this->type === 'company') {
            return (string) $this->name;
        }

        return '—';
    }

    public function initials(): string
    {
        $source = $this->contactName() !== '—'
            ? $this->contactName()
            : $this->companyDisplayName();
        $parts = preg_split('/\s+/', trim($source)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : 'P';
    }

    public function avatarColor(): string
    {
        $palette = ['#7c3aed', '#2563eb', '#0891b2', '#059669', '#d97706', '#db2777', '#4f46e5', '#0f766e'];

        return $palette[abs((int) $this->id) % count($palette)];
    }

    public function ownerShortName(): string
    {
        $name = trim((string) ($this->owner?->name ?? ''));
        if ($name === '') {
            return '—';
        }
        $parts = preg_split('/\s+/', $name) ?: [];
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1)).'. '.$parts[array_key_last($parts)];
        }

        return $name;
    }

    public function ownerInitials(): string
    {
        $name = trim((string) ($this->owner?->name ?? ''));
        if ($name === '') {
            return '?';
        }
        $parts = preg_split('/\s+/', $name) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : '?';
    }

    public function ownerAvatarColor(): string
    {
        $palette = ['#4f46e5', '#0f766e', '#c2410c', '#0369a1', '#7c3aed', '#be185d', '#15803d', '#1d4ed8'];

        return $palette[abs((int) ($this->owner_id ?? 0)) % count($palette)];
    }

    public function temperature(): string
    {
        $score = (int) $this->score;
        if ($score >= 60) {
            return 'chaud';
        }
        if ($score >= 30) {
            return 'tiede';
        }

        return 'froid';
    }

    public function temperatureLabel(): string
    {
        return match ($this->temperature()) {
            'chaud' => 'Chaud',
            'tiede' => 'Tiède',
            default => 'Froid',
        };
    }

    public function whatsappNumber(): ?string
    {
        $n = trim((string) ($this->whatsapp ?: $this->phone ?: ''));

        return $n !== '' ? $n : null;
    }

    /**
     * @return list<string>
     */
    public function initiationGaps(): array
    {
        $gaps = [];
        if (blank(trim((string) $this->name))) {
            $gaps[] = 'Nom / raison sociale';
        }
        if (blank(trim((string) ($this->phone ?? ''))) && blank(trim((string) ($this->email ?? '')))) {
            $gaps[] = 'Téléphone ou e-mail';
        }

        return $gaps;
    }

    public function isReadyToInitiate(): bool
    {
        return in_array($this->status, [self::STATUS_NOUVEAU, self::STATUS_A_QUALIFIER, self::STATUS_CONTACTE], true)
            && $this->initiationGaps() === [];
    }

    public function isConverted(): bool
    {
        return $this->status === self::STATUS_CONVERTI;
    }

    public function isWon(): bool
    {
        return $this->status === self::STATUS_GAGNE;
    }

    public function isLost(): bool
    {
        return in_array($this->status, [self::STATUS_PERDU, self::STATUS_NON_QUALIFIE], true);
    }

    public function isEditable(): bool
    {
        return ! $this->isConverted();
    }

    public function canConvert(): bool
    {
        return ! $this->isConverted() && $this->status !== self::STATUS_NON_QUALIFIE;
    }

    public function canBecomeOpportunity(): bool
    {
        return ! $this->isConverted()
            && $this->status !== self::STATUS_NON_QUALIFIE
            && $this->initiationGaps() === [];
    }

    /**
     * @return list<string>
     */
    public function conversionGaps(): array
    {
        $gaps = [];

        if (blank(trim((string) $this->name))) {
            $gaps[] = 'Le nom / la raison sociale est obligatoire.';
        }

        if (blank(trim((string) ($this->phone ?? ''))) && blank(trim((string) ($this->email ?? '')))) {
            $gaps[] = 'Indiquez au moins un téléphone ou un e-mail de contact.';
        }

        if ($this->type === 'company') {
            if (blank(trim((string) ($this->rccm ?? '')))) {
                $gaps[] = 'Le RCCM est obligatoire pour convertir une entreprise.';
            }
            if (blank(trim((string) ($this->niu ?? '')))) {
                $gaps[] = 'Le NIU est obligatoire pour convertir une entreprise.';
            }
        }

        return $gaps;
    }

    public function isReadyToConvert(): bool
    {
        return $this->canConvert() && $this->conversionGaps() === [];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NOUVEAU => 'Nouveau',
            self::STATUS_A_QUALIFIER => 'À qualifier',
            self::STATUS_QUALIFIE => 'Qualifié',
            self::STATUS_NON_QUALIFIE => 'Non qualifié',
            self::STATUS_CONVERTI => 'Converti',
        ];
    }

    public static function statusHints(): array
    {
        return [
            self::STATUS_NOUVEAU => 'Vient d’être enregistré.',
            self::STATUS_A_QUALIFIER => 'En cours de qualification.',
            self::STATUS_QUALIFIE => 'Critères minimums validés — peut devenir une opportunité.',
            self::STATUS_NON_QUALIFIE => 'Ne correspond pas aux critères.',
            self::STATUS_CONVERTI => 'Devenu client dans l’ERP.',
            self::STATUS_GAGNE => 'Affaire gagnée (historique).',
            self::STATUS_PERDU => 'Perdu (historique).',
        ];
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_CONVERTI, self::STATUS_GAGNE, self::STATUS_QUALIFIE => 'badge-success',
            self::STATUS_A_QUALIFIER => 'badge-warning',
            self::STATUS_NOUVEAU => 'badge-info',
            self::STATUS_NON_QUALIFIE, self::STATUS_PERDU => 'badge-error',
            default => 'badge-neutral',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_CONTACTE => self::statusOptions()[self::STATUS_A_QUALIFIER],
            self::STATUS_NEGOCIATION => 'Négociation',
            self::STATUS_GAGNE => 'Gagné',
            self::STATUS_PERDU => 'Perdu',
            default => self::statusOptions()[$status] ?? $status,
        };
    }

    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_SITE => 'Site web',
            self::SOURCE_WHATSAPP => 'WhatsApp',
            self::SOURCE_FACEBOOK => 'Facebook',
            self::SOURCE_INSTAGRAM => 'Instagram',
            self::SOURCE_TIKTOK => 'TikTok',
            self::SOURCE_YOUTUBE => 'YouTube',
            self::SOURCE_RECO => 'Recommandation',
            self::SOURCE_APPEL => 'Appel téléphonique',
            self::SOURCE_PROSPECTION => 'Prospection',
            self::SOURCE_EVENT => 'Événement',
            self::SOURCE_IMPORT => 'Import Excel',
            self::SOURCE_SALON => 'Salon / foire',
            self::SOURCE_CAMPAGNE => 'Campagne',
            self::SOURCE_OTHER => 'Autre',
        ];
    }

    public static function sourceLabel(string $source): string
    {
        return self::sourceOptions()[$source] ?? $source;
    }

    public static function sourceTone(string $source): string
    {
        return match ($source) {
            self::SOURCE_RECO, self::SOURCE_WHATSAPP => 'green',
            self::SOURCE_SITE, self::SOURCE_FACEBOOK => 'blue',
            self::SOURCE_EVENT, self::SOURCE_INSTAGRAM => 'violet',
            self::SOURCE_APPEL => 'rose',
            self::SOURCE_IMPORT, self::SOURCE_YOUTUBE => 'cyan',
            self::SOURCE_PROSPECTION, self::SOURCE_SALON, self::SOURCE_CAMPAGNE, self::SOURCE_TIKTOK => 'orange',
            default => 'slate',
        };
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_QUALIFIE, self::STATUS_CONVERTI, self::STATUS_GAGNE => 'green',
            self::STATUS_A_QUALIFIER, self::STATUS_CONTACTE, self::STATUS_NEGOCIATION => 'orange',
            self::STATUS_NON_QUALIFIE, self::STATUS_PERDU => 'rose',
            self::STATUS_NOUVEAU => 'blue',
            default => 'slate',
        };
    }

    public static function typeOptions(): array
    {
        return [
            'company' => 'Entreprise',
            'individual' => 'Particulier',
        ];
    }

    /** @deprecated Utiliser Opportunity::openStages() */
    public static function pipelineStatuses(): array
    {
        return [self::STATUS_QUALIFIE];
    }
}
