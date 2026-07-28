<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class PressingLoyaltyReward extends TenantModel
{
    protected $table = 'pressing_loyalty_rewards';

    public const TYPE_VALUE = 'value';

    public const TYPE_PERCENT = 'percent';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_USED = 'used';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'client_id',
        'code',
        'reward_type',
        'reward_value',
        'reward_max',
        'points_spent',
        'status',
        'order_id',
        'discount_amount',
        'issued_by',
        'used_by',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'reward_value' => 'decimal:2',
        'reward_max' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'points_spent' => 'integer',
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(PressingClient::class, 'client_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PressingOrder::class, 'order_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isAvailable(): bool
    {
        if ($this->status !== self::STATUS_AVAILABLE) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /** Discount this reward would apply to a given billable amount. */
    public function discountFor(float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        if ($this->reward_type === self::TYPE_PERCENT) {
            $discount = round($amount * ((float) $this->reward_value / 100), 2);
            if ($this->reward_max !== null) {
                $discount = min($discount, (float) $this->reward_max);
            }
        } else {
            $discount = (float) $this->reward_value;
        }

        return (float) min($discount, $amount);
    }

    public function label(): string
    {
        if ($this->reward_type === self::TYPE_PERCENT) {
            $label = rtrim(rtrim(number_format((float) $this->reward_value, 2, '.', ''), '0'), '.').'%';
            if ($this->reward_max !== null) {
                $label .= ' (max '.number_format((float) $this->reward_max, 0, ',', ' ').')';
            }

            return $label;
        }

        return number_format((float) $this->reward_value, 0, ',', ' ').' FCFA';
    }
}
