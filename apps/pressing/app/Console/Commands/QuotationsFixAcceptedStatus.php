<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuotationsFixAcceptedStatus extends Command
{
    protected $signature = 'quotations:fix-accepted-status {tenantCode}';

    protected $description = 'Migrate quotation status validated → accepted (constraint PostgreSQL).';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (!$tenant) {
            $this->error('Tenant introuvable.');
            return self::FAILURE;
        }

        $manager->setTenant($tenant);

        if (!Schema::connection('tenant')->hasTable('quotations')) {
            $this->warn('Table quotations absente.');
            return self::SUCCESS;
        }

        $conn = DB::connection('tenant');

        if ($conn->getDriverName() === 'pgsql') {
            $conn->statement('ALTER TABLE quotations DROP CONSTRAINT IF EXISTS quotations_status_check');
        }

        $updated = $conn->table('quotations')->where('status', 'validated')->update(['status' => 'accepted']);

        if ($conn->getDriverName() === 'pgsql') {
            $conn->statement(
                "ALTER TABLE quotations ADD CONSTRAINT quotations_status_check CHECK (status::text = ANY (ARRAY['draft','sent','accepted','suspended','rejected']::text[]))"
            );
        }

        $this->info("Tenant {$tenant->code} : {$updated} devis mis à jour.");

        return self::SUCCESS;
    }
}
