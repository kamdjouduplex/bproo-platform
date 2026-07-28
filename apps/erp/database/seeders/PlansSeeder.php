<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    /**
     * Seed default subscription plans: Standard 10 000 FCFA/month and Demo 0 FCFA.
     */
    public function run(): void
    {
        Plan::firstOrCreate(
            ['slug' => 'standard-10000-monthly'],
            [
                'name' => 'Standard — 10 000 FCFA/mois',
                'description' => 'Abonnement mensuel standard (10 000 FCFA/mois)',
                'price' => 10000,
                'currency' => 'XOF',
                'billing_interval' => Plan::BILLING_INTERVAL_MONTHLY,
                'is_active' => true,
                'is_demo' => false,
                'sort_order' => 0,
            ]
        );

        Plan::firstOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Démo (essai)',
                'description' => 'Plan démo pour tester le système avant de passer à l\'action. Aucune facturation. Les abonnements Démo ne sont jamais auto-suspendus.',
                'price' => 0,
                'currency' => 'XOF',
                'billing_interval' => Plan::BILLING_INTERVAL_MONTHLY,
                'is_active' => true,
                'is_demo' => true,
                'sort_order' => 1,
            ]
        );
    }
}
