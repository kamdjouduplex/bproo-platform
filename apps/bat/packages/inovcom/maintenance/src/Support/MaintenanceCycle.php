<?php

namespace InovCom\Maintenance\Support;

use Carbon\Carbon;
use InovCom\Maintenance\Models\MaintenanceContract;

class MaintenanceCycle
{
    public const FREQ_MONTHLY      = 'monthly';
    public const FREQ_BIMONTHLY    = 'bimonthly';
    public const FREQ_QUARTERLY    = 'quarterly';
    public const FREQ_SEMI_ANNUAL  = 'semi_annual';
    public const FREQ_YEARLY       = 'yearly';

    public static function interventionFrequencies(): array
    {
        return [
            self::FREQ_MONTHLY     => __('Mensuel'),
            self::FREQ_BIMONTHLY   => __('Bimestriel'),
            self::FREQ_QUARTERLY   => __('Trimestriel'),
            self::FREQ_SEMI_ANNUAL => __('Semestriel'),
            self::FREQ_YEARLY      => __('Annuel'),
        ];
    }

    public static function billingCycles(): array
    {
        return [
            'monthly'   => __('Mensuel'),
            'quarterly' => __('Trimestriel'),
            'yearly'    => __('Annuel'),
        ];
    }

    public static function frequencyLabel(?string $frequency): string
    {
        return self::interventionFrequencies()[$frequency] ?? ($frequency ?: '—');
    }

    public static function periodStartForFrequency(string $frequency, ?Carbon $reference = null): Carbon
    {
        $ref = ($reference ?? now())->copy();

        return match ($frequency) {
            self::FREQ_BIMONTHLY => $ref->month % 2 === 0
                ? $ref->copy()->startOfMonth()->subMonth()->startOfDay()
                : $ref->copy()->startOfMonth()->startOfDay(),
            self::FREQ_QUARTERLY   => $ref->copy()->firstOfQuarter()->startOfDay(),
            self::FREQ_SEMI_ANNUAL  => $ref->month <= 6
                ? $ref->copy()->startOfYear()->startOfDay()
                : $ref->copy()->month(7)->startOfMonth()->startOfDay(),
            self::FREQ_YEARLY      => $ref->copy()->startOfYear()->startOfDay(),
            default                => $ref->copy()->startOfMonth()->startOfDay(),
        };
    }

    public static function addFrequencyInterval(Carbon $date, string $frequency): Carbon
    {
        return match ($frequency) {
            self::FREQ_BIMONTHLY   => $date->copy()->addMonths(2),
            self::FREQ_QUARTERLY   => $date->copy()->addMonths(3),
            self::FREQ_SEMI_ANNUAL => $date->copy()->addMonths(6),
            self::FREQ_YEARLY      => $date->copy()->addYear(),
            default                => $date->copy()->addMonth(),
        };
    }

    public static function computeNextInterventionDate(MaintenanceContract $contract, ?Carbon $from = null): ?Carbon
    {
        if (!$contract->start_date) {
            return null;
        }

        $from ??= $contract->last_intervention_at
            ? Carbon::parse($contract->last_intervention_at)
            : Carbon::parse($contract->start_date);

        $next = self::addFrequencyInterval($from->copy()->startOfDay(), $contract->intervention_frequency ?? self::FREQ_MONTHLY);

        if ($contract->end_date && $next->gt($contract->end_date)) {
            return null;
        }

        return $next;
    }

    public static function syncSchedule(MaintenanceContract $contract): void
    {
        if (!$contract->next_intervention_at && $contract->start_date) {
            $contract->next_intervention_at = $contract->start_date;
        }

        if ($contract->next_intervention_at && $contract->isDirty('intervention_frequency')) {
            $base = $contract->last_intervention_at ?? $contract->start_date;
            $contract->next_intervention_at = self::computeNextInterventionDate(
                $contract->setAttribute('last_intervention_at', $base)
            ) ?? $contract->next_intervention_at;
        }
    }
}
