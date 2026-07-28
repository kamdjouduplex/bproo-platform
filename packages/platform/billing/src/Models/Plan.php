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
        'currency',
        'billing_interval',
        'is_active',
        'is_demo',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const BILLING_INTERVAL_MONTHLY = 'monthly';
    public const BILLING_INTERVAL_YEARLY = 'yearly';

    public static function billingIntervals(): array
    {
        return [
            self::BILLING_INTERVAL_MONTHLY => 'Mensuel',
            self::BILLING_INTERVAL_YEARLY => 'Annuel',
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

    /**
     * Scope: exclude demo plans (e.g. for auto-suspend).
     */
    public function scopeNotDemo($query)
    {
        return $query->where('is_demo', false);
    }

    /**
     * Period length in months for this plan.
     */
    public function periodMonths(): int
    {
        return $this->billing_interval === self::BILLING_INTERVAL_YEARLY ? 12 : 1;
    }
}
