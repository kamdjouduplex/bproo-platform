<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use InovCom\Purchases\Services\PurchaseDocumentNumberService;

class PurchasesFixDocumentNumbers extends Command
{
    protected $signature = 'purchases:fix-numbers {tenantCode} {--dry-run : Afficher sans modifier}';

    protected $description = 'Corrige les numéros de commandes (ACH) et réceptions (REC) au format ACH-ANNÉE-00001';

    public function handle(TenantManager $manager, PurchaseDocumentNumberService $numbers): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (!$tenant) {
            $this->error('Tenant introuvable.');

            return self::FAILURE;
        }

        $manager->setTenant($tenant);

        if ($this->option('dry-run')) {
            $this->warn('Mode dry-run : aucune modification.');
            $orders = \InovCom\Purchases\Models\PurchaseOrder::query()->orderBy('id')->pluck('order_number', 'id');
            foreach ($orders as $id => $num) {
                $flag = $numbers->isCorrupted((string) $num) ? ' [CORROMPU]' : '';
                $this->line("  #{$id} → {$num}{$flag}");
            }

            return self::SUCCESS;
        }

        $result = $numbers->renumberAllExisting();
        $this->info("Tenant {$tenant->code} : {$result['orders']} commande(s), {$result['receipts']} réception(s) renumérotée(s).");
        $this->line('Format : ACH-' . now()->year . '-00001 (séquence sur 5 chiffres par année).');

        return self::SUCCESS;
    }
}
