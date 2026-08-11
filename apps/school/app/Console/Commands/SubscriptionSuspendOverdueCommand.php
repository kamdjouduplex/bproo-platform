<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SubscriptionSuspendOverdueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:suspend-overdue
                            {--day=5 : Day of month to use as deadline (default: 5)}
                            {--dry-run : List what would be suspended without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Suspend active subscriptions whose period has ended (run on the 5th of each month)';

    public function handle(SubscriptionService $service): int
    {
        $day = (int) $this->option('day');
        $dryRun = $this->option('dry-run');

        $deadline = Carbon::create(now()->year, now()->month, $day, 0, 0, 0);
        if ($deadline->isFuture()) {
            $deadline->subMonth();
        }

        $this->info('Deadline: subscriptions with period_end before ' . $deadline->format('Y-m-d') . ' will be suspended (demo plans and active grace periods excluded).');

        $query = \App\Models\Subscription::query()
            ->with(['tenant', 'plan'])
            ->where('status', \App\Models\Subscription::STATUS_ACTIVE)
            ->where('current_period_end', '<', $deadline)
            ->whereHas('plan', fn ($q) => $q->where('is_demo', false))
            ->where(function ($q) use ($deadline) {
                $q->whereNull('grace_ends_at')
                    ->orWhere('grace_ends_at', '<', $deadline);
            });

        $count = $query->count();
        if ($count === 0) {
            $this->info('No subscriptions to suspend.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[DRY RUN] Would suspend {$count} subscription(s):");
            $query->with(['tenant', 'plan'])->get()->each(function ($sub) {
                $this->line("  - Tenant: {$sub->tenant->name} ({$sub->tenant->code}), Plan: {$sub->plan->name}, Period end: {$sub->current_period_end->format('Y-m-d')}");
            });
            return self::SUCCESS;
        }

        $suspended = $service->suspendOverdueSubscriptions($deadline);
        $this->info("Suspended {$suspended} subscription(s).");
        return self::SUCCESS;
    }
}
