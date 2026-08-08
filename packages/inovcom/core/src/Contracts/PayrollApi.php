<?php

namespace InovCom\Kernel\Contracts;

interface PayrollApi
{
    public function getPeriodTotal(string $startDate, string $endDate): float;
}
