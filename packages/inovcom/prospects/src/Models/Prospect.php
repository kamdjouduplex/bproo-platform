<?php

namespace InovCom\Prospects\Models;

use InovCom\Clients\Models\Client;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class Prospect extends TenantModel
{
    /** @deprecated Conservé pour rétrocompatibilité — migrer vers STATUS_QUALIFIE */
    public const STATUS_NOUVEAU = 'nouveau';

    /** @deprecated Conservé pour rétrocompatibilité — migrer vers STATUS_QUALIFIE */
    public const STATUS_CONTACTE = 'contacte';

    public const STATUS_QUALIFIE = 'qualifie';

    public const STATUS_NEGOCIATION = 'negociation';

    public const STATUS_GAGNE = 'gagne';

    public const STATUS_CONVERTI = 'converti';

    public const STATUS_PERDU = 'perdu';

    public const SOURCE_SALON = 'salon';

    public const SOURCE_SITE = 'site';

    public const SOURCE_RECO = 'recommandation';

    public const SOURCE_CAMPAGNE = 'campagne';

    public const SOURCE_OTHER = 'other';

    protected $fillable = [
        'reference',
        'name',
        'type',
        'email',
        'phone',
        'address',
        'tax_id',
        'rccm',
        'niu',
        'source',
        'status',
        'cost',
        'expected_value',
        'owner_id',
        'notes',
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
        'converted_at' => 'datetime',
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

    /**
     * Étapes actives du pipeline opportunités.
     *
     * @return list<string>
     */
    public static function pipelineStatuses(): array
    {
        return [
            self::STATUS_QUALIFIE,
            self::STATUS_NEGOCIATION,
            self::STATUS_GAGNE,
        ];
    }

    /**
     * Infos minimales pour entrer dans le pipeline.
     *
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
        return in_array($this->status, [self::STATUS_NOUVEAU, self::STATUS_CONTACTE], true)
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
        return $this->status === self::STATUS_PERDU;
    }

    public function isEditable(): bool
    {
        return ! $this->isConverted();
    }

    public function canConvert(): bool
    {
        return $this->status === self::STATUS_GAGNE;
    }

    /**
     * Bloqueurs à lever avant conversion en client.
     *
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
            self::STATUS_QUALIFIE => 'Qualifié',
            self::STATUS_NEGOCIATION => 'Négociation',
            self::STATUS_GAGNE => 'Gagné',
            self::STATUS_CONVERTI => 'Converti',
            self::STATUS_PERDU => 'Perdu',
        ];
    }

    /** Courte aide métier pour le pipeline. */
    public static function statusHints(): array
    {
        return [
            self::STATUS_QUALIFIE => 'Prospect qualifié — besoin identifié, à faire avancer.',
            self::STATUS_NEGOCIATION => 'Discussion commerciale / offre en cours.',
            self::STATUS_GAGNE => 'Affaire gagnée — prêt à convertir en client.',
            self::STATUS_CONVERTI => 'Devenu client dans le système.',
            self::STATUS_PERDU => 'Opportunité abandonnée (motif à renseigner).',
            self::STATUS_NOUVEAU => 'Ancien statut — à migrer vers Qualifié.',
            self::STATUS_CONTACTE => 'Ancien statut — à migrer vers Qualifié.',
        ];
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_CONVERTI, self::STATUS_GAGNE => 'badge-success',
            self::STATUS_NEGOCIATION => 'badge-info',
            self::STATUS_QUALIFIE => 'badge-warning',
            self::STATUS_PERDU => 'badge-error',
            default => 'badge-neutral',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_NOUVEAU, self::STATUS_CONTACTE => self::statusOptions()[self::STATUS_QUALIFIE],
            default => self::statusOptions()[$status] ?? $status,
        };
    }

    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_SALON => 'Salon / foire',
            self::SOURCE_SITE => 'Site web',
            self::SOURCE_RECO => 'Recommandation',
            self::SOURCE_CAMPAGNE => 'Campagne',
            self::SOURCE_OTHER => 'Autre',
        ];
    }

    public static function sourceLabel(string $source): string
    {
        return self::sourceOptions()[$source] ?? $source;
    }

    public static function typeOptions(): array
    {
        return [
            'company' => 'Entreprise',
            'individual' => 'Particulier',
        ];
    }
}
