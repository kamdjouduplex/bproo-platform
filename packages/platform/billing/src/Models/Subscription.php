<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'billing_mode',
        'seats_billed',
        'unit_price',
        'status',
        'current_period_start',
        'current_period_end',
        'activated_at',
        'suspended_at',
        'cancelled_at',
        'suspension_reason',
        'grace_ends_at',
    ];

    protected $casts = [
        'current_period_start' => 'date',
        'current_period_end' => 'date',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'grace_ends_at' => 'date',
        'seats_billed' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Actif',
            self::STATUS_SUSPENDED => 'Suspendu',
            self::STATUS_EXPIRED => 'Expiré',
            self::STATUS_CANCELLED => 'Annulé',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Payments applied to this subscription (tenant_payments where subscription_id = this).
     */
    public function payments()
    {
        return $this->hasMany(TenantPayment::class, 'subscription_id')->orderByDesc('paid_at');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Check if the current period has ended (past period_end date).
     */
    public function isPeriodOver(): bool
    {
        return $this->current_period_end->isPast();
    }

    /**
     * Whether the subscription is currently in a grace period (grace_ends_at is set and in the future).
     */
    public function inGrace(): bool
    {
        return $this->grace_ends_at && $this->grace_ends_at->isFuture();
    }

    /**
     * Grant a grace period of N days from today. After this date, the subscription can be auto-suspended if period has ended.
     */
    public function grantGrace(int $days = 15): void
    {
        $this->update([
            'grace_ends_at' => now()->addDays($days)->startOfDay(),
        ]);
    }

    /**
     * Clear the grace period (e.g. when suspending or when payment is recorded).
     */
    public function clearGrace(): void
    {
        $this->update(['grace_ends_at' => null]);
    }

    /**
     * Suspend this subscription and optionally deactivate the tenant.
     */
    public function suspend(?string $reason = null, bool $deactivateTenant = true): void
    {
        $this->update([
            'status' => self::STATUS_SUSPENDED,
            'suspended_at' => $this->suspended_at ?? now(),
            'suspension_reason' => $reason ?? $this->suspension_reason,
            'grace_ends_at' => null,
        ]);

        if ($deactivateTenant && $this->tenant) {
            $this->tenant->update(['is_active' => false]);
        }
    }

    /**
     * Activate (or reactivate) this subscription and optionally activate the tenant.
     */
    public function activate(bool $activateTenant = true): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'activated_at' => $this->activated_at ?? now(),
            'suspended_at' => null,
            'suspension_reason' => null,
            'grace_ends_at' => null,
        ]);

        if ($activateTenant && $this->tenant) {
            $this->tenant->update(['is_active' => true]);
        }
    }

    /**
     * Cancel this subscription permanently (tenant stops). Optionally deactivate the tenant.
     */
    public function cancel(?string $reason = null, bool $deactivateTenant = true): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => $this->cancelled_at ?? now(),
            'suspended_at' => null,
            'suspension_reason' => null,
            'grace_ends_at' => null,
        ]);

        if ($deactivateTenant && $this->tenant) {
            $this->tenant->update(['is_active' => false]);
        }
    }

    /**
     * Set period to a new window (e.g. when first activating or after manual payment).
     */
    public function setPeriod(Carbon $start, Carbon $end): void
    {
        $this->update([
            'current_period_start' => $start,
            'current_period_end' => $end,
        ]);
    }

    /**
     * Extend current period by the plan's period length; clear grace when payment is recorded.
     */
    public function extendPeriod(): void
    {
        $months = $this->plan->periodMonths();
        $newEnd = $this->current_period_end->copy()->addMonths($months);
        $newStart = $this->current_period_end->copy()->addDay();

        $this->update([
            'current_period_start' => $newStart,
            'current_period_end' => $newEnd,
            'grace_ends_at' => null,
        ]);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_SUSPENDED => 'warning',
            self::STATUS_EXPIRED => 'secondary',
            self::STATUS_CANCELLED => 'secondary',
            default => 'secondary',
        };
    }
}
