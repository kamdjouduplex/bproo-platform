<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformProspect extends Model
{
    public const STAGE_LEAD = 'lead';
    public const STAGE_QUALIFIED = 'qualified';
    public const STAGE_PROPOSAL = 'proposal';
    public const STAGE_NEGOTIATION = 'negotiation';
    public const STAGE_WON = 'won';
    public const STAGE_LOST = 'lost';

    protected $fillable = [
        'company_name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'country',
        'city',
        'source',
        'stage',
        'product_interest',
        'expected_value',
        'currency',
        'probability',
        'next_follow_up_at',
        'notes',
        'owner_user_id',
        'converted_tenant_id',
        'converted_at',
    ];

    protected $casts = [
        'expected_value' => 'decimal:2',
        'next_follow_up_at' => 'date',
        'converted_at' => 'datetime',
    ];

    public static function stages(): array
    {
        return [
            self::STAGE_LEAD => 'Nouveau',
            self::STAGE_QUALIFIED => 'Qualifié',
            self::STAGE_PROPOSAL => 'Proposition',
            self::STAGE_NEGOTIATION => 'Négociation',
            self::STAGE_WON => 'Gagné',
            self::STAGE_LOST => 'Perdu',
        ];
    }

    /** Stages on the opportunities kanban (includes won pending conversion). */
    public static function opportunityStages(): array
    {
        return [
            self::STAGE_QUALIFIED => 'Qualifié',
            self::STAGE_PROPOSAL => 'Proposition',
            self::STAGE_NEGOTIATION => 'Négociation',
            self::STAGE_WON => 'À convertir',
        ];
    }

    public static function nextOpportunityStage(string $stage): ?string
    {
        return match ($stage) {
            self::STAGE_QUALIFIED => self::STAGE_PROPOSAL,
            self::STAGE_PROPOSAL => self::STAGE_NEGOTIATION,
            self::STAGE_NEGOTIATION => self::STAGE_WON,
            default => null,
        };
    }

    public static function defaultProbabilityForStage(string $stage): int
    {
        return match ($stage) {
            self::STAGE_LEAD => 10,
            self::STAGE_QUALIFIED => 30,
            self::STAGE_PROPOSAL => 55,
            self::STAGE_NEGOTIATION => 75,
            self::STAGE_WON => 100,
            self::STAGE_LOST => 0,
            default => 20,
        };
    }

    public function isOpportunity(): bool
    {
        return array_key_exists($this->stage, self::opportunityStages())
            && $this->converted_tenant_id === null;
    }

    public function weightedValue(): float
    {
        $prob = $this->probability ?? self::defaultProbabilityForStage($this->stage);

        return ((float) ($this->expected_value ?? 0)) * ($prob / 100);
    }

    public static function sources(): array
    {
        return [
            'manual' => 'Manuel',
            'referral' => 'Parrainage',
            'web' => 'Web',
            'partner' => 'Partenaire',
            'other' => 'Autre',
        ];
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PlatformProspectActivity::class)->orderByDesc('created_at');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Control Center operators available as salespeople (commerciaux).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function salespeople()
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function assignOwner(?int $userId, ?int $actorId = null): bool
    {
        $userId = $userId ?: null;
        $previous = $this->owner_user_id ? (int) $this->owner_user_id : null;

        if ($previous === $userId) {
            return false;
        }

        $this->update(['owner_user_id' => $userId]);

        $names = self::salespeople()->keyBy('id');
        $from = $previous ? ($names->get($previous)?->name ?? '#'.$previous) : 'Non affecté';
        $to = $userId ? ($names->get($userId)?->name ?? '#'.$userId) : 'Non affecté';

        PlatformProspectActivity::create([
            'platform_prospect_id' => $this->id,
            'user_id' => $actorId ?? auth()->id(),
            'type' => 'assign',
            'subject' => 'Commercial',
            'body' => "{$from} → {$to}",
        ]);

        return true;
    }

    public function convertedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'converted_tenant_id');
    }

    public function isConverted(): bool
    {
        return $this->converted_tenant_id !== null;
    }

    public function stageLabel(): string
    {
        return self::stages()[$this->stage] ?? $this->stage;
    }

    public function productLabel(): string
    {
        $types = config('tenant_types.types', []);

        return $types[$this->product_interest]['label'] ?? ($this->product_interest ?: '—');
    }
}
