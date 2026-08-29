<?php

namespace InovCom\Treasury\Support;

use Carbon\CarbonInterface;

class TreasuryUrgency
{
    public const OVERDUE = 'overdue';
    public const URGENT = 'urgent';
    public const UPCOMING = 'upcoming';
    public const PLANNED = 'planned';
    public const PAID = 'paid';

    /**
     * @return array{key: string, label: string, color: string, days: int}
     */
    public static function classify(
        CarbonInterface $dueDate,
        CarbonInterface $today,
        int $urgentDays = 10,
        int $upcomingDays = 30,
        bool $paid = false
    ): array {
        if ($paid) {
            return [
                'key' => self::PAID,
                'label' => 'Payé',
                'color' => '#64748b',
                'days' => $today->diffInDays($dueDate, false),
            ];
        }

        $days = (int) $today->startOfDay()->diffInDays($dueDate->copy()->startOfDay(), false);

        if ($days < 0) {
            return [
                'key' => self::OVERDUE,
                'label' => 'En retard',
                'color' => '#b91c1c',
                'days' => $days,
            ];
        }

        if ($days < max(1, $urgentDays)) {
            return [
                'key' => self::URGENT,
                'label' => 'Urgent',
                'color' => '#dc2626',
                'days' => $days,
            ];
        }

        if ($days <= max($urgentDays, $upcomingDays)) {
            return [
                'key' => self::UPCOMING,
                'label' => 'À anticiper',
                'color' => '#d97706',
                'days' => $days,
            ];
        }

        return [
            'key' => self::PLANNED,
            'label' => 'Planifié',
            'color' => '#16a34a',
            'days' => $days,
        ];
    }
}
