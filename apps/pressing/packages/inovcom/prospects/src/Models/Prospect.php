<?php

namespace InovCom\Prospects\Models;

use InovCom\Clients\Models\Client;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class Prospect extends TenantModel
{
    public const STATUS_NOUVEAU = 'nouveau';

    public const STATUS_CONTACTE = 'contacte';

    public const STATUS_QUALIFIE = 'qualifie';

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

    public function isConverted(): bool
    {
        return $this->status === self::STATUS_CONVERTI;
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
        return in_array($this->status, [self::STATUS_NOUVEAU, self::STATUS_CONTACTE, self::STATUS_QUALIFIE], true);
    }

    /**
     * Bloqueurs à lever avant conversion en client (aligné sur la fiche client entreprise).
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
            self::STATUS_NOUVEAU => 'Nouveau',
            self::STATUS_CONTACTE => 'Contacté',
            self::STATUS_QUALIFIE => 'Qualifié',
            self::STATUS_CONVERTI => 'Converti',
            self::STATUS_PERDU => 'Perdu',
        ];
    }

    /** Courte aide métier pour le pipeline. */
    public static function statusHints(): array
    {
        return [
            self::STATUS_NOUVEAU => 'Lead entrant, pas encore travaillé.',
            self::STATUS_CONTACTE => 'Premier contact établi (appel, visite, message).',
            self::STATUS_QUALIFIE => 'Besoin, budget et intention d’achat confirmés — prêt à convertir.',
            self::STATUS_CONVERTI => 'Devenu client dans le système.',
            self::STATUS_PERDU => 'Opportunité abandonnée (motif à renseigner).',
        ];
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_CONVERTI => 'badge-success',
            self::STATUS_QUALIFIE => 'badge-info',
            self::STATUS_CONTACTE => 'badge-warning',
            self::STATUS_PERDU => 'badge-error',
            default => 'badge-neutral',
        };
    }

    public static function statusLabel(string $status): string
    {
        return self::statusOptions()[$status] ?? $status;
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
