<?php

namespace InovCom\Treasury\Services;

use App\Services\TenantManager;

class TreasurySettings
{
    public const URGENT_DAYS = 'treasury_urgent_days';
    public const UPCOMING_DAYS = 'treasury_upcoming_days';
    public const ALERT_DAYS = 'treasury_alert_days';

    public function urgentDays(): int
    {
        return max(1, (int) $this->get(self::URGENT_DAYS, 10));
    }

    public function upcomingDays(): int
    {
        return max($this->urgentDays(), (int) $this->get(self::UPCOMING_DAYS, 30));
    }

    public function alertDays(): int
    {
        return max(1, (int) $this->get(self::ALERT_DAYS, 7));
    }

    public function setUrgentDays(int $days): void
    {
        $this->set(self::URGENT_DAYS, max(1, $days));
    }

    public function setUpcomingDays(int $days): void
    {
        $this->set(self::UPCOMING_DAYS, max(1, $days));
    }

    public function setAlertDays(int $days): void
    {
        $this->set(self::ALERT_DAYS, max(1, $days));
    }

    private function get(string $key, int $default): int
    {
        $tenant = app(TenantManager::class)->tenant();

        return $tenant ? (int) $tenant->getSetting($key, $default) : $default;
    }

    private function set(string $key, int $value): void
    {
        $tenant = app(TenantManager::class)->tenant();
        if ($tenant) {
            $tenant->setSetting($key, (string) $value);
        }
    }
}
