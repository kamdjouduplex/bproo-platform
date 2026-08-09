<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'price_per_user',
        'currency',
        'billing_interval',
        'billing_mode',
        'is_active',
        'is_demo',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_per_user' => 'decimal:2',
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const BILLING_INTERVAL_MONTHLY = 'monthly';
    public const BILLING_INTERVAL_YEARLY = 'yearly';

    public const MODE_FLAT = 'flat';
    public const MODE_PER_SEAT = 'per_seat';

    public static function billingIntervals(): array
    {
        return [
            self::BILLING_INTERVAL_MONTHLY => 'Mensuel',
            self::BILLING_INTERVAL_YEARLY => 'Annuel',
        ];
    }

    public static function billingModes(): array
    {
        return [
            self::MODE_FLAT => 'Forfait mensuel (entreprise)',
            self::MODE_PER_SEAT => 'Par utilisateur (siège)',
        ];
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeNotDemo($query)
    {
        return $query->where('is_demo', false);
    }

    public function isPerSeat(): bool
    {
        return ($this->billing_mode ?: self::MODE_FLAT) === self::MODE_PER_SEAT;
    }

    public function isFlat(): bool
    {
        return ! $this->isPerSeat();
    }

    /**
     * Licensed / billable seats for a tenant on a per-seat plan.
     * Prefer max_users (contract), else cached active users_count, minimum 1.
     */
    public function resolveSeats(Tenant $tenant): int
    {
        $licensed = (int) ($tenant->max_users ?? 0);
        $active = (int) ($tenant->users_count ?? 0);

        if ($licensed > 0) {
            return max($licensed, 1);
        }

        return max($active, 1);
    }

    /**
     * Monthly amount charged for this plan for a given tenant.
     * Flat: plan.price. Per-seat: price_per_user × seats.
     */
    public function monthlyRateFor(Tenant $tenant, ?int $seats = null): float
    {
        if ($this->isPerSeat()) {
            $perUser = (float) ($this->price_per_user ?? 0);
            $seats = $seats ?? $this->resolveSeats($tenant);

            return round($perUser * max(1, $seats), 2);
        }

        return round((float) $this->price, 2);
    }

    public function rateLabel(?Tenant $tenant = null): string
    {
        $currency = $this->currency ?: 'XOF';
        if ($this->isPerSeat()) {
            $label = fmt_money((float) ($this->price_per_user ?? 0)).' '.$currency.'/utilisateur';
            if ($tenant) {
                $seats = $this->resolveSeats($tenant);
                $label .= ' · '.$seats.' siège(s) = '.fmt_money($this->monthlyRateFor($tenant, $seats)).' '.$currency.'/mois';
            }

            return $label;
        }

        return fmt_money((float) $this->price).' '.$currency.'/'.($this->billing_interval === self::BILLING_INTERVAL_YEARLY ? 'an' : 'mois');
    }

    public function periodMonths(): int
    {
        return $this->billing_interval === self::BILLING_INTERVAL_YEARLY ? 12 : 1;
    }
}
