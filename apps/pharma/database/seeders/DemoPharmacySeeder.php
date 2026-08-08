<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use InovCom\Batches\Models\Batch;
use InovCom\Caisse\Services\CaisseService;
use InovCom\Clients\Models\Client;
use InovCom\Items\Models\Brand;
use InovCom\Items\Models\Category;
use InovCom\Items\Models\Item;
use InovCom\Items\Models\ItemUnitPrice;
use InovCom\Items\Models\Unit;
use InovCom\Providers\Models\Provider;
use InovCom\Sales\Models\Payment;
use InovCom\Sales\Models\Sale;
use InovCom\Sales\Models\SaleLine;
use InovCom\Stock\Services\StockService;
use InovCom\Users\Models\User;

/**
 * Données de démo pharmacie pour vidéo / démo commerciale.
 *
 * Cas couverts :
 * - Produit 100 % périmé (vente bloquée) — star de la démo
 * - Produit mixte (lots périmés + valides) — vente partielle OK
 * - Produit bientôt périmé (alerte 30 j)
 * - Produit stock sain (vente normale FEFO)
 * - Produit sur ordonnance
 * - Produit sans suivi de lot (vente simple)
 * - Clients, fournisseur, caisse ouverte
 */
class DemoPharmacySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCatalogue();
        $this->seedClients();
        $this->seedProviders();
        $this->ensureOpenCashSession();
        $this->seedHistoricalSales();
    }

    private function seedCatalogue(): void
    {
        $analgesiques = Category::firstOrCreate(
            ['code' => 'analgesiques'],
            ['name' => 'Analgésiques', 'description' => 'Douleur et fièvre', 'is_active' => true]
        );
        $antibiotiques = Category::firstOrCreate(
            ['code' => 'antibiotiques'],
            ['name' => 'Antibiotiques', 'description' => 'Anti-infectieux', 'is_active' => true]
        );
        $vitamines = Category::firstOrCreate(
            ['code' => 'vitamines'],
            ['name' => 'Vitamines & compléments', 'description' => 'Vitamines et minéraux', 'is_active' => true]
        );
        $hygiene = Category::firstOrCreate(
            ['code' => 'hygiene'],
            ['name' => 'Hygiène & premiers soins', 'description' => 'Parapharmacie', 'is_active' => true]
        );

        $sanofi = Brand::firstOrCreate(
            ['code' => 'sanofi'],
            ['name' => 'Sanofi', 'description' => 'Laboratoire Sanofi', 'is_active' => true]
        );
        $ups = Brand::firstOrCreate(
            ['code' => 'ups'],
            ['name' => 'UPSA', 'description' => 'Laboratoire UPSA', 'is_active' => true]
        );
        $gsk = Brand::firstOrCreate(
            ['code' => 'gsk'],
            ['name' => 'GSK', 'description' => 'GlaxoSmithKline', 'is_active' => true]
        );
        $generique = Brand::firstOrCreate(
            ['code' => 'generique'],
            ['name' => 'Générique', 'description' => 'Médicaments génériques', 'is_active' => true]
        );

        $unit = Unit::where('abbreviation', 'cp')->first()
            ?? Unit::where('abbreviation', 'pc')->first()
            ?? Unit::firstOrCreate(
                ['name' => 'Comprimé'],
                ['abbreviation' => 'cp', 'is_active' => true]
            );

        $unitPiece = Unit::where('abbreviation', 'pc')->first()
            ?? Unit::firstOrCreate(
                ['name' => 'Pièce'],
                ['abbreviation' => 'pc', 'is_active' => true]
            );

        $today = now()->startOfDay();

        $products = [
            // ★ Cas vidéo : stock uniquement périmé → vente refusée
            [
                'sku' => 'MED-000001',
                'name' => 'Doliprane 500 mg',
                'barcode' => '3400930000001',
                'description' => 'Paracétamol 500 mg — démonstration blocage vente si lot périmé.',
                'category_id' => $analgesiques->id,
                'brand_id' => $sanofi->id,
                'unit_id' => $unit->id,
                'cost' => 750,
                'price' => 1500,
                'metadata' => [
                    'batch_tracked' => true,
                    'requires_prescription' => false,
                    'is_set' => false,
                    'dci' => 'Paracétamol',
                    'pharma_form' => 'Comprimé',
                    'dosage' => '500 mg',
                    'manufacturer' => 'Sanofi',
                    'storage_temp' => 'Température ambiante',
                    'therapeutic_family' => 'Antalgique / Antipyrétique',
                ],
                'batches' => [
                    ['batch_number' => 'LOT-DOL-EXP', 'expiry_date' => $today->copy()->subMonths(2)->toDateString(), 'quantity' => 60],
                    ['batch_number' => 'LOT-DOL-EXP2', 'expiry_date' => $today->copy()->subDays(15)->toDateString(), 'quantity' => 40],
                ],
                'demo_case' => 'expired_only',
            ],
            // Mixte : périmé + valide → on peut vendre la partie saine
            [
                'sku' => 'MED-AMOX-500',
                'name' => 'Amoxicilline 500 mg',
                'barcode' => '3400930000002',
                'description' => 'Antibiotique — lots mixtes (périmé + valide).',
                'category_id' => $antibiotiques->id,
                'brand_id' => $generique->id,
                'unit_id' => $unit->id,
                'cost' => 1200,
                'price' => 2500,
                'metadata' => [
                    'batch_tracked' => true,
                    'requires_prescription' => true,
                    'is_set' => false,
                    'dci' => 'Amoxicilline',
                    'pharma_form' => 'Gélule',
                    'dosage' => '500 mg',
                    'manufacturer' => 'Générique',
                    'storage_temp' => 'Température ambiante',
                    'therapeutic_family' => 'Antibiotique',
                ],
                'batches' => [
                    ['batch_number' => 'LOT-AMOX-OLD', 'expiry_date' => $today->copy()->subMonths(1)->toDateString(), 'quantity' => 20],
                    ['batch_number' => 'LOT-AMOX-OK', 'expiry_date' => $today->copy()->addYears(1)->toDateString(), 'quantity' => 80],
                ],
                'demo_case' => 'mixed',
            ],
            // Bientôt périmé (alerte 30 j) — encore vendable
            [
                'sku' => 'MED-VITC-1000',
                'name' => 'Vitamine C 1000 mg',
                'barcode' => '3400930000003',
                'description' => 'Complément — lot bientôt périmé (alerte 30 jours).',
                'category_id' => $vitamines->id,
                'brand_id' => $ups->id,
                'unit_id' => $unit->id,
                'cost' => 800,
                'price' => 1800,
                'metadata' => [
                    'batch_tracked' => true,
                    'requires_prescription' => false,
                    'is_set' => false,
                    'dci' => 'Acide ascorbique',
                    'pharma_form' => 'Comprimé effervescent',
                    'dosage' => '1000 mg',
                    'manufacturer' => 'UPSA',
                    'storage_temp' => 'Température ambiante',
                    'therapeutic_family' => 'Vitamine',
                ],
                'batches' => [
                    ['batch_number' => 'LOT-VITC-D30', 'expiry_date' => $today->copy()->addDays(20)->toDateString(), 'quantity' => 45],
                ],
                'demo_case' => 'near_expiry',
            ],
            // Stock sain — vente normale
            [
                'sku' => 'MED-EFF-500',
                'name' => 'Efferalgan 500 mg',
                'barcode' => '3400930000004',
                'description' => 'Paracétamol effervescent — stock sain, vente démo normale.',
                'category_id' => $analgesiques->id,
                'brand_id' => $ups->id,
                'unit_id' => $unit->id,
                'cost' => 900,
                'price' => 2000,
                'metadata' => [
                    'batch_tracked' => true,
                    'requires_prescription' => false,
                    'is_set' => false,
                    'dci' => 'Paracétamol',
                    'pharma_form' => 'Comprimé effervescent',
                    'dosage' => '500 mg',
                    'manufacturer' => 'UPSA',
                    'storage_temp' => 'Température ambiante',
                    'therapeutic_family' => 'Antalgique / Antipyrétique',
                ],
                'batches' => [
                    ['batch_number' => 'LOT-EFF-A', 'expiry_date' => $today->copy()->addMonths(8)->toDateString(), 'quantity' => 30],
                    ['batch_number' => 'LOT-EFF-B', 'expiry_date' => $today->copy()->addYears(2)->toDateString(), 'quantity' => 100],
                ],
                'demo_case' => 'healthy',
            ],
            // Sur ordonnance + stock OK
            [
                'sku' => 'MED-AUG-1G',
                'name' => 'Augmentin 1 g',
                'barcode' => '3400930000005',
                'description' => 'Antibiotique sur ordonnance — stock valide.',
                'category_id' => $antibiotiques->id,
                'brand_id' => $gsk->id,
                'unit_id' => $unit->id,
                'cost' => 3500,
                'price' => 6500,
                'metadata' => [
                    'batch_tracked' => true,
                    'requires_prescription' => true,
                    'is_set' => false,
                    'dci' => 'Amoxicilline + Acide clavulanique',
                    'pharma_form' => 'Comprimé',
                    'dosage' => '1 g',
                    'manufacturer' => 'GSK',
                    'storage_temp' => 'Température ambiante',
                    'therapeutic_family' => 'Antibiotique',
                ],
                'batches' => [
                    ['batch_number' => 'LOT-AUG-OK', 'expiry_date' => $today->copy()->addYears(1)->addMonths(3)->toDateString(), 'quantity' => 50],
                ],
                'demo_case' => 'rx',
            ],
            // Sans suivi de lot
            [
                'sku' => 'PARA-GEL-500',
                'name' => 'Gel hydroalcoolique 500 ml',
                'barcode' => '3400930000006',
                'description' => 'Parapharmacie — pas de suivi de lot.',
                'category_id' => $hygiene->id,
                'brand_id' => $generique->id,
                'unit_id' => $unitPiece->id,
                'cost' => 1500,
                'price' => 3000,
                'metadata' => [
                    'batch_tracked' => false,
                    'requires_prescription' => false,
                    'is_set' => false,
                    'pharma_form' => 'Gel',
                    'dosage' => '500 ml',
                    'manufacturer' => 'Divers',
                    'storage_temp' => 'Température ambiante',
                ],
                'batches' => [],
                'stock_only' => 75,
                'demo_case' => 'no_batch',
            ],
            // Stock bas (alerte réassort)
            [
                'sku' => 'MED-IBU-400',
                'name' => 'Ibuprofène 400 mg',
                'barcode' => '3400930000007',
                'description' => 'Anti-inflammatoire — stock bas pour démo réassort.',
                'category_id' => $analgesiques->id,
                'brand_id' => $generique->id,
                'unit_id' => $unit->id,
                'cost' => 600,
                'price' => 1200,
                'metadata' => [
                    'batch_tracked' => true,
                    'requires_prescription' => false,
                    'is_set' => false,
                    'dci' => 'Ibuprofène',
                    'pharma_form' => 'Comprimé',
                    'dosage' => '400 mg',
                    'manufacturer' => 'Générique',
                    'storage_temp' => 'Température ambiante',
                    'therapeutic_family' => 'AINS',
                ],
                'batches' => [
                    ['batch_number' => 'LOT-IBU-LOW', 'expiry_date' => $today->copy()->addMonths(14)->toDateString(), 'quantity' => 5],
                ],
                'demo_case' => 'low_stock',
            ],
        ];

        $stockService = app(StockService::class);

        foreach ($products as $row) {
            $item = Item::updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'name' => $row['name'],
                    'barcode' => $row['barcode'] ?? null,
                    'description' => $row['description'],
                    'category_id' => $row['category_id'],
                    'brand_id' => $row['brand_id'],
                    'unit_id' => $row['unit_id'],
                    'cost' => $row['cost'],
                    'price' => $row['price'],
                    'is_active' => true,
                    'metadata' => $row['metadata'],
                ]
            );

            ItemUnitPrice::updateOrCreate(
                [
                    'item_id' => $item->id,
                    'unit_id' => $row['unit_id'],
                ],
                [
                    'conversion_factor' => 1,
                    'price' => $row['price'],
                    'cost' => $row['cost'],
                    'is_default' => true,
                ]
            );

            $targetQty = 0.0;

            if (! empty($row['batches']) && Schema::connection('tenant')->hasTable('batches')) {
                // Remplace les lots démo de cet article pour un état prévisible.
                Batch::query()
                    ->where('item_id', $item->id)
                    ->where(function ($q) {
                        $q->where('reference_type', 'seed')
                            ->orWhere('batch_number', 'like', 'LOT-%');
                    })
                    ->delete();

                foreach ($row['batches'] as $batchRow) {
                    Batch::create([
                        'item_id' => $item->id,
                        'batch_number' => $batchRow['batch_number'],
                        'expiry_date' => $batchRow['expiry_date'],
                        'quantity' => $batchRow['quantity'],
                        'received_at' => now()->subDays(7),
                        'reference_type' => 'seed',
                        'reference_id' => 0,
                    ]);
                    $targetQty += (float) $batchRow['quantity'];
                }
            } else {
                $targetQty = (float) ($row['stock_only'] ?? 0);
            }

            $this->syncStockQuantity($stockService, $item->id, $targetQty);
        }
    }

    private function syncStockQuantity(StockService $stockService, int $itemId, float $targetQty): void
    {
        $level = $stockService->getStockLevel($itemId);
        $current = (float) $level->quantity;
        $delta = $targetQty - $current;

        if (abs($delta) < 0.0001) {
            return;
        }

        if ($delta > 0) {
            $stockService->addStock($itemId, $delta, 'in', 'seed', null, 'Alignement stock démo pharmacie');
        } else {
            // reduce via addStock with negative if supported — else set directly
            $level->quantity = $targetQty;
            $level->available_quantity = max(0, $targetQty - (float) $level->reserved_quantity);
            $level->save();
        }
    }

    private function seedClients(): void
    {
        $clients = [
            [
                'code' => 'CLI-PHARMA-01',
                'name' => 'Mme Aïcha Nguema',
                'type' => 'individual',
                'phone' => '+237 6 70 11 22 33',
                'email' => 'aicha.nguema@email.cm',
                'address' => 'Quartier Bastos, Yaoundé',
                'notes' => 'Cliente habituée — démo vente comptoir.',
            ],
            [
                'code' => 'CLI-PHARMA-02',
                'name' => 'M. Jean Mbarga',
                'type' => 'individual',
                'phone' => '+237 6 55 44 33 22',
                'email' => null,
                'address' => 'Douala, Akwa',
                'notes' => 'Client passage — démo rapide.',
            ],
            [
                'code' => 'CLI-CLINIQUE',
                'name' => 'Clinique Sainte-Marie',
                'type' => 'company',
                'phone' => '+237 2 22 10 20 30',
                'email' => 'pharmacie@clinique-stm.cm',
                'address' => 'Avenue Kennedy, Yaoundé',
                'tax_id' => 'M998877665',
                'credit_limit' => 500000,
                'payment_method' => 'credit',
                'notes' => 'Compte professionnel — ordonnances.',
            ],
        ];

        foreach ($clients as $row) {
            Client::updateOrCreate(
                ['code' => $row['code']],
                array_merge($row, [
                    'is_active' => true,
                    'is_blocked' => false,
                    'credit_limit' => $row['credit_limit'] ?? 0,
                    'current_balance' => 0,
                ])
            );
        }
    }

    private function seedProviders(): void
    {
        if (! class_exists(Provider::class)) {
            return;
        }

        Provider::updateOrCreate(
            ['code' => 'FRN-LABO-CM'],
            [
                'name' => 'Labo Distribution Cameroun',
                'email' => 'commandes@labo-dist.cm',
                'phone' => '+237 2 33 44 55 66',
                'address' => 'Zone industrielle Bassa, Douala',
                'is_active' => true,
                'notes' => 'Grossiste médicaments — démo achats / réceptions.',
            ]
        );
    }

    private function ensureOpenCashSession(): void
    {
        if (! class_exists(CaisseService::class)) {
            return;
        }

        try {
            $service = app(CaisseService::class);
            if (! $service->isReady()) {
                return;
            }
            if ($service->hasOpenSession()) {
                return;
            }

            $admin = User::query()->where('email', 'admin@pharma.com')->first()
                ?? User::query()->orderBy('id')->first();

            if ($admin) {
                Auth::guard('tenant')->login($admin);
            }

            $service->openSession(50000, 'Fond de caisse démo vidéo', $admin?->id);
        } catch (\Throwable) {
            // Module caisse peut être inactif / tables absentes
        }
    }

    /**
     * Ventes sur les 30 derniers jours (dont aujourd’hui) pour un tableau de bord impressionnant.
     * N’altère pas le stock actuel — historique purement comptable / démo.
     */
    private function seedHistoricalSales(): void
    {
        if (! Schema::connection('tenant')->hasTable('sales')
            || ! Schema::connection('tenant')->hasTable('sale_lines')) {
            return;
        }

        // Idempotent : retire les ventes démo précédentes
        $demoSaleIds = Sale::query()
            ->where('sale_number', 'like', 'VTE-DEMO-%')
            ->pluck('id');

        if ($demoSaleIds->isNotEmpty()) {
            if (Schema::connection('tenant')->hasTable('payments')) {
                Payment::query()->whereIn('sale_id', $demoSaleIds)->delete();
            }
            SaleLine::query()->whereIn('sale_id', $demoSaleIds)->delete();
            Sale::query()->whereIn('id', $demoSaleIds)->delete();
        }

        $sellableSkus = [
            'MED-EFF-500',
            'MED-VITC-1000',
            'PARA-GEL-500',
            'MED-IBU-400',
            'MED-AMOX-500',
            'MED-AUG-1G',
        ];

        $items = Item::query()
            ->whereIn('sku', $sellableSkus)
            ->where('is_active', true)
            ->get()
            ->keyBy('sku');

        if ($items->isEmpty()) {
            return;
        }

        $clients = Client::query()
            ->where('code', 'like', 'CLI-PHARMA%')
            ->orWhere('code', 'CLI-CLINIQUE')
            ->get();

        $admin = User::query()->where('email', 'admin@pharma.com')->first()
            ?? User::query()->orderBy('id')->first();

        $methods = ['cash', 'cash', 'cash', 'orange_money', 'mtn_money'];

        // Profil journalier (multiplicateur) — courbe réaliste sur 30 j
        $dayProfile = [
            // dim lun mar mer jeu ven sam (Carbon: 0=Sun … 6=Sat)
            0 => 0.55,
            1 => 0.95,
            2 => 1.10,
            3 => 1.20,
            4 => 1.15,
            5 => 1.35,
            6 => 0.85,
        ];

        $seq = 1;

        for ($d = 29; $d >= 0; $d--) {
            $day = now()->subDays($d)->startOfDay();
            $factor = $dayProfile[(int) $day->dayOfWeek] ?? 1.0;

            // Plus d’activité récente + un pic hier pour un trend positif vs aujourd’hui variable
            if ($d <= 7) {
                $factor *= 1.15;
            }
            if ($d === 1) {
                $factor *= 1.25; // hier un peu fort → trend du jour lisible
            }

            $saleCount = (int) max(2, round((4 + ($d % 5)) * $factor));
            if ($d === 0) {
                $saleCount = max(3, (int) round(5 * $factor)); // aujourd’hui bien rempli
            }

            for ($s = 0; $s < $saleCount; $s++) {
                $lineCount = random_int(1, 3);
                $lines = [];
                $subtotal = 0.0;

                for ($l = 0; $l < $lineCount; $l++) {
                    /** @var Item $item */
                    $item = $items->values()->random();
                    $qty = random_int(1, 4);
                    $unitPrice = (float) $item->price;
                    // Légère variation promo démo
                    if (random_int(1, 10) === 1) {
                        $unitPrice = round($unitPrice * 0.9, 0);
                    }
                    $lineTotal = round($qty * $unitPrice, 2);
                    $subtotal += $lineTotal;
                    $lines[] = [
                        'item' => $item,
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ];
                }

                $discount = ($subtotal > 10000 && random_int(1, 8) === 1)
                    ? round($subtotal * 0.05, 0)
                    : 0.0;
                $total = max(0, $subtotal - $discount);

                $saleNumber = sprintf('VTE-DEMO-%s-%03d', $day->format('Ymd'), $seq++);
                $client = $clients->isNotEmpty() && random_int(1, 3) !== 1
                    ? $clients->random()
                    : null;

                $saleHour = random_int(8, 18);
                $saleMinute = random_int(0, 59);
                $createdAt = $day->copy()->setTime($saleHour, $saleMinute);

                $sale = Sale::create([
                    'sale_number' => $saleNumber,
                    'sale_date' => $day->toDateString(),
                    'client_id' => $client?->id,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discount,
                    'discount_percent' => $discount > 0 ? 5 : null,
                    'total' => $total,
                    'notes' => 'seed-demo-dashboard',
                    'created_by' => $admin?->id,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                foreach ($lines as $line) {
                    /** @var Item $item */
                    $item = $line['item'];
                    SaleLine::create([
                        'sale_id' => $sale->id,
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'item_sku' => $item->sku,
                        'unit_id' => $item->unit_id,
                        'unit_name' => $item->unit?->abbreviation ?? $item->unit?->name,
                        'conversion_factor' => 1,
                        'quantity' => $line['qty'],
                        'unit_price' => $line['unit_price'],
                        'line_total' => $line['line_total'],
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }

                if (Schema::connection('tenant')->hasTable('payments')) {
                    Payment::create([
                        'sale_id' => $sale->id,
                        'method' => $methods[array_rand($methods)],
                        'amount' => $total,
                        'notes' => 'Paiement démo',
                        'received_by' => $admin?->id,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }
            }
        }
    }
}
