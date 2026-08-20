<?php

namespace InovCom\Projets\Support;

use InovCom\Projets\Models\Project;

class ProjectHubSnapshot
{
    /**
     * @param  array<int, array<string, mixed>>  $recentPurchaseOrders
     * @param  array<int, array<string, mixed>>  $recentInvoices
     * @param  array<int, array<string, mixed>>  $recentReports
     * @param  array<string, array{count: int, route: string|null, create: string|null}>  $links
     */
    public function __construct(
        public readonly Project $project,
        public readonly string $currency,
        public readonly float $budget,
        public readonly float $actualCost,
        public readonly float $billed,
        public readonly float $collected,
        public readonly float $amountDue,
        public readonly float $margin,
        public readonly ?float $marginPct,
        public readonly bool $overBudget,
        public readonly bool $late,
        public readonly int $openTaskCount,
        public readonly int $memberCount,
        public readonly ?array $latestReport,
        public readonly array $recentPurchaseOrders,
        public readonly array $recentInvoices,
        public readonly array $recentReports,
        public readonly array $links,
    ) {
    }

    public function money(float $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' ' . $this->currency;
    }
}
