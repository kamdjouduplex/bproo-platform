<?php

namespace InovCom\Kernel\Contracts;

interface LossesApi
{
    public function getPeriodTotal(string $startDate, string $endDate): float;

    /**
     * @return array<string, float>
     */
    public function getDailyTrend(string $startDate, string $endDate): array;

    /**
     * @return array<int, array{reason_name: string, total_value: float, total_qty: float}>
     */
    public function getByReason(string $startDate, string $endDate): array;
}
