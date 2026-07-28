<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Database\Seeders\DemoPressingSeeder;
use Illuminate\Console\Command;

class SeedPressingDemoData extends Command
{
    protected $signature = 'pressing:seed-demo {tenantCode=pressing} {--fresh : Remove previous DEMO-* data before seeding}';

    protected $description = 'Populate the pressing tenant with rich demo data for the current month (orders, workflow, payments, deliveries, loyalty, consumables).';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        app()->instance('tenant', $tenant);

        // Bilingual-friendly shop identity (works for EN & FR demos)
        if (method_exists($tenant, 'setSetting')) {
            $tenant->setSetting('shop_name', 'Pressing Excellence');
            $tenant->setSetting('locale', 'en'); // default EN; switch in UI for FR demos
        }
        $tenant->update([
            'name' => 'Pressing Excellence',
            'multi_store_enabled' => false, // pressing uses multi-agences only
        ]);

        $this->info("Seeding demo data for « {$tenant->name} » ({$tenant->code})…");

        $seeder = new DemoPressingSeeder();
        $seeder->setCommand($this);
        $seeder->fresh = (bool) $this->option('fresh');
        $seeder->run();

        $this->newLine();
        $this->info('Demo ready. Open:');
        $this->line('  http://127.0.0.1:8000/app?tenant='.$tenant->code);
        $this->line('  Login: admin@pressing.com  (existing admin)');
        $this->line('  Switch language with FR/EN selector in the header.');

        return self::SUCCESS;
    }
}
