<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SubscriptionResetTenantCommand extends Command
{
    protected $signature = 'subscription:reset-tenant
                            {tenant : Tenant code (e.g. DEMO)}
                            {--force : Skip confirmation}';

    protected $description = 'Reset a tenant\'s subscriptions, payments, balance and balance transactions so you can test from scratch';

    public function handle(): int
    {
        $code = $this->argument('tenant');
        $tenant = Tenant::where('code', $code)->first();

        if (!$tenant) {
            $this->error("Tenant not found: {$code}");
            return self::FAILURE;
        }

        $this->info("Tenant: {$tenant->name} ({$tenant->code})");

        if (!$this->option('force') && !$this->confirm('Delete all subscriptions, payments, balance transactions and set balance to 0?')) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($tenant) {
            $tenant->subscriptions()->delete();
            $tenant->payments()->delete();
            $tenant->balanceTransactions()->delete();
            $tenant->update([
                'balance' => 0,
                'is_active' => false,
            ]);
        });

        $this->info('Tenant subscription data reset. Balance = 0, is_active = false. You can test from scratch.');
        return self::SUCCESS;
    }
}
