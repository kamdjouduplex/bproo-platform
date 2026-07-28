<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InovCom\Kernel\TenantModel;

class PressingClient extends TenantModel
{
    use SoftDeletes;

    protected $table = 'pressing_clients';

    protected $fillable = [
        'code',
        'agence_id',
        'last_name',
        'first_name',
        'whatsapp',
        'phone',
        'email',
        'address',
        'notes',
        'is_active',
        'loyalty_points',
        'loyalty_orders_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'loyalty_points' => 'integer',
        'loyalty_orders_count' => 'integer',
    ];

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class, 'agence_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PressingOrder::class, 'client_id');
    }

    public function loyaltyEntries(): HasMany
    {
        return $this->hasMany(PressingLoyaltyEntry::class, 'client_id')->latest('id');
    }

    public function loyaltyRewards(): HasMany
    {
        return $this->hasMany(PressingLoyaltyReward::class, 'client_id')->latest('id');
    }

    public function availableRewards(): HasMany
    {
        return $this->hasMany(PressingLoyaltyReward::class, 'client_id')
            ->where('status', PressingLoyaltyReward::STATUS_AVAILABLE);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function ordersCount(): int
    {
        return $this->orders()->count();
    }

    public function openOrdersCount(): int
    {
        return $this->orders()->whereIn('status', ['open', 'ready'])->count();
    }

    public function totalRevenue(): float
    {
        return (float) $this->orders()->sum('total');
    }

    public function lastVisitAt()
    {
        return $this->orders()->latest('received_at')->value('received_at');
    }
}
