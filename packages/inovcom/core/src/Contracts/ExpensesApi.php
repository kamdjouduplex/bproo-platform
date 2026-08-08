<?php

namespace InovCom\Kernel\Contracts;

interface ExpensesApi
{
    public function getPeriodTotal(string $startDate, string $endDate): float;

    /**
     * @return array<int, array{category_name: string, total: float}>
     */
    public function getByCategory(string $startDate, string $endDate): array;
}
