<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use InovCom\Devis\Models\Quote;
use InovCom\Kernel\Exceptions\InvalidWorkflowTransitionException;

class CheckExpiredQuotes extends Command
{
    protected $signature = 'quotes:check-expired {tenantCode?}';
    protected $description = 'Mark sent quotes as expired when valid_until date has passed';

    public function handle(TenantManager $tenantManager): int
    {
        $tenantCode = $this->argument('tenantCode');
        $tenants = $tenantCode
            ? Tenant::where('code', $tenantCode)->get()
            : Tenant::where('status', 'active')->get();

        $total = 0;

        foreach ($tenants as $tenant) {
            $tenantManager->setTenant($tenant);

            $quotes = Quote::on('tenant')
                ->where('status', 'sent')
                ->whereNotNull('valid_until')
                ->whereDate('valid_until', '<', now()->toDateString())
                ->get();

            foreach ($quotes as $quote) {
                try {
                    if ($quote->canTransitionTo('expired')) {
                        $quote->transitionTo('expired', null, __('Expiration automatique (validité dépassée)'));
                        $total++;
                        $this->line("  [{$tenant->code}] {$quote->code} → expired");
                    }
                } catch (InvalidWorkflowTransitionException $e) {
                    $this->warn("  [{$tenant->code}] {$quote->code}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Done. {$total} quote(s) marked expired.");

        return self::SUCCESS;
    }
}
