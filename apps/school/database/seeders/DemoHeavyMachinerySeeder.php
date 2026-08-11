<?php

namespace Database\Seeders;

use InovCom\Clients\Models\Client;
use InovCom\Items\Models\Brand;
use InovCom\Items\Models\Category;
use InovCom\Items\Models\Item;
use InovCom\Items\Models\ItemUnitPrice;
use InovCom\Items\Models\Unit;
use InovCom\Providers\Models\Provider;
use InovCom\Stock\Services\StockService;
use Illuminate\Database\Seeder;

class DemoHeavyMachinerySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedItems();
        $this->seedClients();
        $this->seedProviders();
    }

    private function seedItems(): void
    {
        $category = Category::firstOrCreate(
            ['code' => 'moteur'],
            [
                'name' => 'Pièces moteur',
                'description' => 'Filtres, joints et composants moteur diesel',
                'is_active' => true,
            ]
        );

        $categoryTransmission = Category::firstOrCreate(
            ['code' => 'transmission'],
            [
                'name' => 'Transmission & freinage',
                'description' => 'Embrayage, freins et organes de transmission',
                'is_active' => true,
            ]
        );

        $brands = [
            'cat' => Brand::firstOrCreate(
                ['code' => 'cat'],
                ['name' => 'CAT', 'description' => 'Caterpillar', 'is_active' => true]
            ),
            'cummins' => Brand::firstOrCreate(
                ['code' => 'cummins'],
                ['name' => 'Cummins', 'description' => 'Moteurs diesel poids lourd', 'is_active' => true]
            ),
            'oem' => Brand::firstOrCreate(
                ['code' => 'oem'],
                ['name' => 'OEM', 'description' => 'Pièces d\'origine et équivalent', 'is_active' => true]
            ),
        ];

        $unit = Unit::where('abbreviation', 'pc')->first()
            ?? Unit::firstOrCreate(
                ['name' => 'Piece'],
                ['abbreviation' => 'pc', 'is_active' => true]
            );

        $items = [
            [
                'sku' => 'FIL-HUI-CAT15',
                'name' => 'Filtre à huile moteur CAT C15',
                'description' => 'Filtre à huile pour moteur Caterpillar C15, engins de chantier.',
                'brand_id' => $brands['cat']->id,
                'category_id' => $category->id,
                'cost' => 45000,
                'price' => 65000,
                'stock' => 25,
            ],
            [
                'sku' => 'JOINT-CUL-6C',
                'name' => 'Joint de culasse moteur diesel 6 cylindres',
                'description' => 'Joint de culasse pour moteur diesel 6 cylindres, poids lourd et engin lourd.',
                'brand_id' => $brands['oem']->id,
                'category_id' => $category->id,
                'cost' => 120000,
                'price' => 175000,
                'stock' => 8,
            ],
            [
                'sku' => 'POMPE-INJ-PL',
                'name' => 'Pompe à injection diesel poids lourd',
                'description' => 'Pompe à injection haute pression pour camion et engin de manutention.',
                'brand_id' => $brands['oem']->id,
                'category_id' => $category->id,
                'cost' => 350000,
                'price' => 485000,
                'stock' => 4,
            ],
            [
                'sku' => 'COUR-DIST-EC',
                'name' => 'Courroie de distribution engin de chantier',
                'description' => 'Courroie de distribution renforcée pour moteur diesel engin lourd.',
                'brand_id' => $brands['oem']->id,
                'category_id' => $category->id,
                'cost' => 28000,
                'price' => 42000,
                'stock' => 15,
            ],
            [
                'sku' => 'FIL-AIR-QSX',
                'name' => 'Filtre à air moteur Cummins QSX',
                'description' => 'Filtre à air principal pour moteur Cummins QSX, engins et camions.',
                'brand_id' => $brands['cummins']->id,
                'category_id' => $category->id,
                'cost' => 55000,
                'price' => 78000,
                'stock' => 12,
            ],
            [
                'sku' => 'TURBO-DIESEL-6C',
                'name' => 'Turbo compresseur moteur diesel 6 cylindres',
                'description' => 'Turbo reconditionné pour moteur diesel 6 cylindres, poids lourd et engin.',
                'brand_id' => $brands['oem']->id,
                'category_id' => $category->id,
                'cost' => 420000,
                'price' => 580000,
                'stock' => 3,
            ],
            [
                'sku' => 'POMPE-EAU-ISX',
                'name' => 'Pompe à eau moteur Cummins ISX',
                'description' => 'Pompe à eau pour circuit de refroidissement moteur Cummins ISX.',
                'brand_id' => $brands['cummins']->id,
                'category_id' => $category->id,
                'cost' => 65000,
                'price' => 95000,
                'stock' => 10,
            ],
            [
                'sku' => 'FIL-GASOIL-PL',
                'name' => 'Filtre gasoil avec séparateur eau',
                'description' => 'Filtre à gasoil et séparateur d\'eau pour camion et engin diesel.',
                'brand_id' => $brands['oem']->id,
                'category_id' => $category->id,
                'cost' => 32000,
                'price' => 48000,
                'stock' => 20,
            ],
            [
                'sku' => 'RAD-DIESEL-EC',
                'name' => 'Radiateur moteur diesel engin de chantier',
                'description' => 'Radiateur aluminium pour moteur diesel engin lourd et bulldozer.',
                'brand_id' => $brands['cat']->id,
                'category_id' => $category->id,
                'cost' => 185000,
                'price' => 265000,
                'stock' => 5,
            ],
            [
                'sku' => 'DISQ-FR-430',
                'name' => 'Disque de frein poids lourd 430 mm',
                'description' => 'Disque de frein ventilé 430 mm pour essieu poids lourd.',
                'brand_id' => $brands['oem']->id,
                'category_id' => $categoryTransmission->id,
                'cost' => 75000,
                'price' => 110000,
                'stock' => 14,
            ],
            [
                'sku' => 'KIT-EMB-PL',
                'name' => 'Kit embrayage poids lourd complet',
                'description' => 'Kit embrayage disque + plateau + butée pour camion 16 tonnes.',
                'brand_id' => $brands['oem']->id,
                'category_id' => $categoryTransmission->id,
                'cost' => 210000,
                'price' => 295000,
                'stock' => 6,
            ],
        ];

        $stockService = app(StockService::class);

        foreach ($items as $row) {
            $wasRecentlyCreated = !Item::where('sku', $row['sku'])->exists();

            $item = Item::updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'category_id' => $row['category_id'],
                    'brand_id' => $row['brand_id'],
                    'unit_id' => $unit->id,
                    'cost' => $row['cost'],
                    'price' => $row['price'],
                    'is_active' => true,
                ]
            );

            ItemUnitPrice::updateOrCreate(
                [
                    'item_id' => $item->id,
                    'unit_id' => $unit->id,
                ],
                [
                    'conversion_factor' => 1,
                    'price' => $row['price'],
                    'cost' => $row['cost'],
                    'is_default' => true,
                ]
            );

            if ($wasRecentlyCreated && class_exists(StockService::class)) {
                $stockService->addStock(
                    $item->id,
                    (float) $row['stock'],
                    'in',
                    'seed',
                    null,
                    'Stock initial démo'
                );
            }
        }
    }

    private function seedClients(): void
    {
        $clients = [
            [
                'code' => 'CLI-DEMO01',
                'name' => 'Transports Kamfo Express',
                'type' => 'company',
                'email' => 'contact@kamfo-express.cm',
                'phone' => '+237 6 98 45 12 30',
                'address' => 'Zone industrielle, Douala',
                'tax_id' => 'M123456789',
                'rccm' => 'RC/DLA/2020/B/1842',
                'niu' => 'M1234567890123',
                'bp' => 'BP 4521 Douala',
                'credit_limit' => 2000000,
                'payment_method' => 'credit',
                'price_tier' => 'wholesale',
                'notes' => 'Flotte de camions poids lourd — pièces moteur et filtres.',
            ],
            [
                'code' => 'CLI-DEMO02',
                'name' => 'BTP Engins Lourds Congo',
                'type' => 'company',
                'email' => 'achats@btp-engins.cg',
                'phone' => '+242 05 55 22 18 90',
                'address' => 'Quartier Mpila, Pointe-Noire',
                'tax_id' => 'CG987654321',
                'rccm' => 'RC/PNR/2019/B/0567',
                'niu' => 'CG9876543210456',
                'bp' => 'BP 890 Pointe-Noire',
                'credit_limit' => 5000000,
                'payment_method' => 'bank_transfer',
                'price_tier' => 'wholesale',
                'notes' => 'Chantiers miniers et engins de terrassement.',
            ],
            [
                'code' => 'CLI-DEMO03',
                'name' => 'Garage Mécanique Diesel Plus',
                'type' => 'company',
                'email' => 'garage.diesel@yahoo.fr',
                'phone' => '+237 6 77 33 44 55',
                'address' => 'Rue de la Gare, Yaoundé',
                'tax_id' => 'M556677889',
                'rccm' => 'RC/YDE/2021/B/3290',
                'niu' => 'M5566778890123',
                'bp' => 'BP 1200 Yaoundé',
                'credit_limit' => 800000,
                'payment_method' => 'cash',
                'price_tier' => 'semi_wholesale',
                'notes' => 'Réparation moteurs diesel et pompes à injection.',
            ],
        ];

        foreach ($clients as $row) {
            Client::updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'address' => $row['address'],
                    'tax_id' => $row['tax_id'],
                    'rccm' => $row['rccm'],
                    'niu' => $row['niu'],
                    'bp' => $row['bp'],
                    'credit_limit' => $row['credit_limit'],
                    'discount_rate' => 0,
                    'price_tier' => $row['price_tier'],
                    'payment_method' => $row['payment_method'],
                    'is_active' => true,
                    'is_blocked' => false,
                    'notes' => $row['notes'],
                ]
            );
        }
    }

    private function seedProviders(): void
    {
        $providers = [
            [
                'code' => 'FOUR-DEMO01',
                'name' => 'Euro Diesel Parts France',
                'phone' => '+33 4 78 45 90 12',
                'email' => 'export@eurodiesel-parts.fr',
                'address' => '12 rue des Mécaniciens',
                'city' => 'Lyon',
                'country' => 'FR',
                'is_foreign' => true,
                'default_currency' => 'EUR',
                'tax_id' => 'FR82345678901',
                'payment_method' => 'bank_transfer',
                'notes' => 'Fournisseur européen — filtres, joints et pompes injection.',
            ],
            [
                'code' => 'FOUR-DEMO02',
                'name' => 'Africa Heavy Parts Douala',
                'phone' => '+237 6 99 11 22 33',
                'email' => 'ventes@africaheavy.cm',
                'address' => 'Bonabéri, avenue du Port',
                'city' => 'Douala',
                'country' => 'CM',
                'is_foreign' => false,
                'default_currency' => null,
                'tax_id' => 'M334455667',
                'payment_method' => 'mobile_money',
                'notes' => 'Stock local pièces engins lourds et poids lourd.',
            ],
            [
                'code' => 'FOUR-DEMO03',
                'name' => 'CAT Parts Distribution',
                'phone' => '+237 6 70 88 99 00',
                'email' => 'parts@cat-distrib.cm',
                'address' => 'Zone portuaire, Douala',
                'city' => 'Douala',
                'country' => 'CM',
                'is_foreign' => false,
                'default_currency' => null,
                'tax_id' => 'M778899001',
                'payment_method' => 'bank_transfer',
                'notes' => 'Distributeur officiel pièces Caterpillar.',
            ],
        ];

        foreach ($providers as $row) {
            Provider::updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'email' => $row['email'],
                    'address' => $row['address'],
                    'city' => $row['city'],
                    'country' => $row['country'],
                    'is_foreign' => $row['is_foreign'],
                    'default_currency' => $row['default_currency'],
                    'tax_id' => $row['tax_id'],
                    'payment_method' => $row['payment_method'],
                    'is_active' => true,
                    'notes' => $row['notes'],
                ]
            );
        }
    }
}
