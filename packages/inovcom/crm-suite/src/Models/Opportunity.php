<?php

namespace InovCom\Crm\Models;

use InovCom\Clients\Models\Client;
use InovCom\Kernel\TenantModel;
use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Models\ProspectActivity;
use InovCom\Users\Models\User;

class Opportunity extends TenantModel
{
    protected $table = 'crm_opportunities';

    public const STAGE_QUALIFICATION = 'qualification';

    public const STAGE_QUALIFIEE = 'qualifiee';

    public const STAGE_OPPORTUNITE = 'opportunite';

    public const STAGE_SUIVI = 'suivi';

    public const STAGE_INTENTION = 'intention';

    public const STAGE_GAGNE = 'gagne';

    public const STAGE_PERDU = 'perdu';

    protected $fillable = [
        'prospect_id',
        'client_id',
        'title',
        'product_interest',
        'amount',
        'probability',
        'stage',
        'owner_id',
        'expected_close_date',
        'lost_reason',
        'lost_comment',
        'starred',
        'transferred_at',
        'quotation_id',
        'won_at',
        'lost_at',
        'last_activity_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'probability' => 'integer',
        'starred' => 'boolean',
        'expected_close_date' => 'date',
        'transferred_at' => 'datetime',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function prospect()
    {
        return $this->belongsTo(Prospect::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities()
    {
        return $this->hasMany(ProspectActivity::class, 'opportunity_id')->orderByDesc('created_at');
    }

    public function nextPlannedActivity()
    {
        return $this->hasOne(ProspectActivity::class, 'opportunity_id')
            ->where('state', ProspectActivity::STATE_PLANNED)
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at');
    }

    public static function openStages(): array
    {
        return [
            self::STAGE_QUALIFICATION,
            self::STAGE_QUALIFIEE,
            self::STAGE_OPPORTUNITE,
            self::STAGE_SUIVI,
            self::STAGE_INTENTION,
        ];
    }

    public static function pipelineStages(): array
    {
        return self::openStages();
    }

    public static function stageOptions(): array
    {
        return [
            self::STAGE_QUALIFICATION => 'Qualification',
            self::STAGE_QUALIFIEE => 'Qualifiée',
            self::STAGE_OPPORTUNITE => 'Opportunité',
            self::STAGE_SUIVI => 'Suivi / Négociation',
            self::STAGE_INTENTION => 'Intention d\'achat',
            self::STAGE_GAGNE => 'Gagnée',
            self::STAGE_PERDU => 'Perdue',
        ];
    }

    public static function stageHints(): array
    {
        return [
            self::STAGE_QUALIFICATION => 'Comprendre le besoin et le projet.',
            self::STAGE_QUALIFIEE => 'Critères minimums validés.',
            self::STAGE_OPPORTUNITE => 'Affaire suivie avec montant et échéance.',
            self::STAGE_SUIVI => 'Échanges, démos, relances.',
            self::STAGE_INTENTION => 'Le prospect demande à avancer — transmettre à l’ERP.',
            self::STAGE_GAGNE => 'Affaire gagnée, client associé.',
            self::STAGE_PERDU => 'Affaire perdue — motif obligatoire.',
        ];
    }

    public static function stageTone(string $stage): string
    {
        return match ($stage) {
            self::STAGE_QUALIFICATION => 'blue',
            self::STAGE_QUALIFIEE => 'cyan',
            self::STAGE_OPPORTUNITE => 'violet',
            self::STAGE_SUIVI => 'orange',
            self::STAGE_INTENTION => 'green',
            self::STAGE_GAGNE => 'success',
            self::STAGE_PERDU => 'rose',
            default => 'slate',
        };
    }

    public static function lostReasonOptions(): array
    {
        return [
            'prix' => 'Prix trop élevé',
            'concurrent' => 'Concurrent',
            'budget' => 'Budget insuffisant',
            'annule' => 'Projet annulé',
            'timing' => 'Mauvais timing',
            'besoin' => 'Pas de besoin réel',
            'injoignable' => 'Client injoignable',
            'reporte' => 'Décision reportée',
            'inadapte' => 'Produit/service non adapté',
            'autre' => 'Autre',
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->stage, self::openStages(), true);
    }

    public function isWon(): bool
    {
        return $this->stage === self::STAGE_GAGNE;
    }

    public function isLost(): bool
    {
        return $this->stage === self::STAGE_PERDU;
    }

    public function isClosed(): bool
    {
        return $this->isWon() || $this->isLost();
    }

    public function canRequestQuote(): bool
    {
        return $this->stage === self::STAGE_INTENTION && $this->transferred_at === null;
    }

    public function hasBeenTransferred(): bool
    {
        return $this->transferred_at !== null;
    }

    public function probabilityBand(): string
    {
        $p = (int) $this->probability;
        if ($p <= 30) {
            return 'low';
        }
        if ($p <= 60) {
            return 'mid';
        }

        return 'high';
    }

    public static function defaultProbability(string $stage): int
    {
        return match ($stage) {
            self::STAGE_QUALIFICATION => 20,
            self::STAGE_QUALIFIEE => 35,
            self::STAGE_OPPORTUNITE => 50,
            self::STAGE_SUIVI => 65,
            self::STAGE_INTENTION => 80,
            self::STAGE_GAGNE => 100,
            self::STAGE_PERDU => 0,
            default => 20,
        };
    }

    /**
     * Infos minimums pour passer à « Opportunité » (étape 4).
     *
     * @return list<string>
     */
    public function stageGaps(string $targetStage): array
    {
        $gaps = [];
        $rank = array_search($targetStage, array_keys(self::stageOptions()), true);
        $oppRank = array_search(self::STAGE_OPPORTUNITE, array_keys(self::stageOptions()), true);

        if ($rank !== false && $oppRank !== false && $rank >= $oppRank && $targetStage !== self::STAGE_PERDU) {
            if (blank(trim((string) $this->title))) {
                $gaps[] = 'Intitulé de l’opportunité';
            }
            if ($this->amount === null || (float) $this->amount <= 0) {
                $gaps[] = 'Montant estimé';
            }
            if (! $this->owner_id) {
                $gaps[] = 'Commercial responsable';
            }
            if (! $this->expected_close_date) {
                $gaps[] = 'Date estimée de décision';
            }
        }

        if (in_array($targetStage, self::openStages(), true) && ! $this->nextPlannedActivity) {
            $gaps[] = 'Prochaine action';
        }

        if ($targetStage === self::STAGE_PERDU && blank($this->lost_reason)) {
            $gaps[] = 'Motif de perte';
        }

        if ($targetStage === self::STAGE_GAGNE && ! $this->owner_id) {
            $gaps[] = 'Commercial responsable';
        }

        return $gaps;
    }

    public function displayCompany(): string
    {
        $prospect = $this->relationLoaded('prospect') ? $this->prospect : $this->prospect()->first();
        if (! $prospect) {
            return $this->title;
        }

        return $prospect->companyDisplayName();
    }
}
