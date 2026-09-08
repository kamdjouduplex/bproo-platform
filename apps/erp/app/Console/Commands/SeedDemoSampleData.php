<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Database\Seeders\DemoErpYoutubeSeeder;
use Database\Seeders\DemoHeavyMachinerySeeder;
use Illuminate\Console\Command;

class SeedDemoSampleData extends Command
{
    protected $signature = 'tenant:seed-sample-data
                            {tenantCode=bproo_demo : Code du tenant (démo uniquement)}
                            {--force : Autorise un autre code que demo / bproo_demo}
                            {--reset-docs : Recrée les devis / factures / encaissements démo}';

    protected $description = 'Peuple un tenant de démonstration (catalogue, clients, devis, factures, encaissements)';

    /** @var list<string> */
    private array $demoCodes = ['demo', 'bproo_demo'];

    public function handle(): int
    {
        $tenantCode = (string) $this->argument('tenantCode');
        $manager = app(TenantManager::class);
        $tenant = $manager->resolveByCode($tenantCode);

        if (! $tenant) {
            $this->error("Tenant introuvable : {$tenantCode}");

            return self::FAILURE;
        }

        if (! in_array($tenantCode, $this->demoCodes, true) && ! $this->option('force')) {
            $this->error("Refusé : « {$tenantCode} » n’est pas un tenant de démo.");
            $this->line('Codes autorisés : demo, bproo_demo. Passez --force seulement si vous êtes sûr.');

            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        $this->applyCompanyIdentity($tenant);

        $this->info("Peuplement de « {$tenant->name} » ({$tenantCode})…");

        (new DemoHeavyMachinerySeeder())->run();
        $this->info('✓ Catalogue, 6 clients, 3 fournisseurs');

        $docs = (new DemoErpYoutubeSeeder())->setCommand($this);
        if ($this->option('reset-docs')) {
            $docs->resetDocuments();
            $this->info('✓ Anciens documents DEMO-YTB / BC-DEMO retirés');
        }
        $docs->run();
        $this->info('✓ Devis, factures, encaissements (dont TVA/IS retenus), achats');
        $this->newLine();
        $this->line('Pour la vidéo :');
        $this->line('  • Liste factures : payées / partielles / échue / brouillon / à encaisser');
        $this->line('  • Encaissements « Déjà encaissées » : recap mois précédent (TVA retenue) vs mois en cours');
        $this->line('  • Devis accepté BC-DEMO-A-FACTURER : à convertir en live');
        $this->line('  • Facture DEMO-YTB-06 : à encaisser en live');
        $this->newLine();
        $this->info('Connexion : /app/login?tenant='.$tenantCode);

        return self::SUCCESS;
    }

    private function applyCompanyIdentity($tenant): void
    {
        $settings = [
            'shop_name' => 'Bproo Négoce',
            'shop_tagline' => 'Pièces engins lourds & poids lourds',
            'shop_address' => 'Zone industrielle de Bassa, Douala',
            'shop_phone' => '+237 6 99 00 11 22',
            'shop_email' => 'contact@bproo-negoce.cm',
            'shop_bp' => 'BP 12890 Douala',
            'shop_tax_id' => 'M010101010101A',
            'shop_rccm' => 'RC/DLA/2019/B/2048',
            'shop_cnps' => 'M010101010101A',
            'shop_website' => 'www.bproo.cm',
            'invoice_footer' => 'Merci pour votre confiance — Bproo Négoce',
            'shop_bank_details' => 'Afriland First Bank — 10005 00001 01234567890 42',
            'payment_modes_default' => 'espèces / virement / mobile money',
            'tax_rate' => 19.25,
            'locale' => 'fr',
            'timezone' => 'Africa/Douala',
            'invoice_prefix_declared' => 'FTH',
            'invoice_prefix_non_declared' => 'FTN',
            'print_show_header_company_info' => true,
        ];

        foreach ($settings as $key => $value) {
            $tenant->setSetting($key, $value);
        }

        if (blank($tenant->name) || in_array($tenant->name, ['Boutique Demo', 'demo', 'bproo_demo'], true)) {
            $tenant->update(['name' => 'Bproo Négoce']);
        }
    }
}
