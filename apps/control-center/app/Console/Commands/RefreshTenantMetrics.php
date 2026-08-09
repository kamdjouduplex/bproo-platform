<?php

namespace App\Console\Commands;

use App\Services\CompanyIntelligenceService;
use Illuminate\Console\Command;

class RefreshTenantMetrics extends Command
{
    protected $signature = 'tenants:refresh-metrics {--limit=}';

    protected $description = 'Refresh cached tenant metrics (users count) and flag seat-limit exceedances.';

    public function handle(CompanyIntelligenceService $intel): int
    {
        $limit = $this->option('limit');
        $limit = $limit !== null && $limit !== '' ? (int) $limit : null;

        $result = $intel->refreshAll($limit);

        $this->info("Refreshed {$result['refreshed']} tenant(s).");
        if ($result['newly_exceeded'] !== []) {
            $this->warn('Newly exceeded seats: '.implode(', ', $result['newly_exceeded']));
        }

        return self::SUCCESS;
    }
}
