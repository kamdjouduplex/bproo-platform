<?php

namespace InovCom\Kernel\Contracts;

interface DebtsApi
{
    /**
     * @return array{receivables_total: float, collected_in_period: float, overdue_count: int, overdue_total: float}
     */
    public function getSummary(string $startDate, string $endDate): array;
}
