<?php

namespace InovCom\Items;

use InovCom\Items\Models\Brand;
use InovCom\Items\Models\Category;
use InovCom\Items\Models\Unit;
use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

/**
 * Pharmacy catalogue module — same data layer as Articles (items), pharmacy-oriented labels.
 * Mutually exclusive with `items` via module_family `catalog`.
 */
class MedicamentsModule implements ModuleLifecycle
{
    public function install(object $tenant): void
    {
        (new ItemsModule)->install($tenant);

        $labels = [
            'items.view' => ['name' => 'Voir les médicaments', 'description' => 'Consulter le catalogue médicaments'],
            'items.create' => ['name' => 'Créer des médicaments', 'description' => 'Ajouter des médicaments'],
            'items.update' => ['name' => 'Modifier les médicaments', 'description' => 'Modifier des médicaments'],
            'items.delete' => ['name' => 'Supprimer des médicaments', 'description' => 'Supprimer des médicaments'],
            'items.configure_list' => ['name' => 'Configurer la liste médicaments', 'description' => 'Colonnes visibles sur la liste des médicaments'],
            'items.view_cost' => ['name' => 'Voir le coût d\'achat', 'description' => 'Afficher le prix d\'achat sur la fiche médicament'],
        ];

        foreach ($labels as $key => $meta) {
            Permission::on('tenant')->where('key', $key)->update([
                'name' => $meta['name'],
                'description' => $meta['description'],
            ]);
        }

        foreach ([
            ['name' => 'Antalgiques / Antipyrétiques', 'code' => 'antalgiques'],
            ['name' => 'Antibiotiques', 'code' => 'antibiotiques'],
            ['name' => 'Anti-inflammatoires', 'code' => 'anti_inflammatoires'],
            ['name' => 'Antipaludéens', 'code' => 'antipaludeens'],
            ['name' => 'Vitamines & compléments', 'code' => 'vitamines'],
            ['name' => 'Dermatologie', 'code' => 'dermatologie'],
            ['name' => 'Gastro-entérologie', 'code' => 'gastro'],
            ['name' => 'Cardiologie', 'code' => 'cardiologie'],
            ['name' => 'Parapharmacie', 'code' => 'parapharmacie'],
        ] as $cat) {
            Category::firstOrCreate(
                ['code' => $cat['code']],
                ['name' => $cat['name'], 'is_active' => true]
            );
        }

        foreach ([
            ['name' => 'Boîte', 'abbreviation' => 'boîte'],
            ['name' => 'Flacon', 'abbreviation' => 'fl'],
            ['name' => 'Comprimé', 'abbreviation' => 'cp'],
            ['name' => 'Tube', 'abbreviation' => 'tube'],
            ['name' => 'Ampoule', 'abbreviation' => 'amp'],
            ['name' => 'Sachet', 'abbreviation' => 'sach'],
        ] as $unit) {
            Unit::firstOrCreate(
                ['name' => $unit['name']],
                ['abbreviation' => $unit['abbreviation'], 'is_active' => true]
            );
        }

        foreach ([
            ['name' => 'Sanofi', 'code' => 'sanofi'],
            ['name' => 'GSK', 'code' => 'gsk'],
            ['name' => 'Pfizer', 'code' => 'pfizer'],
            ['name' => 'Novartis', 'code' => 'novartis'],
            ['name' => 'Bayer', 'code' => 'bayer'],
            ['name' => 'AstraZeneca', 'code' => 'astrazeneca'],
            ['name' => 'Johnson & Johnson', 'code' => 'jnj'],
            ['name' => 'Merck', 'code' => 'merck'],
            ['name' => 'Roche', 'code' => 'roche'],
            ['name' => 'Abbott', 'code' => 'abbott'],
            ['name' => 'Servier', 'code' => 'servier'],
            ['name' => 'Pierre Fabre', 'code' => 'pierre_fabre'],
            ['name' => 'Biogaran', 'code' => 'biogaran'],
            ['name' => 'Mylan / Viatris', 'code' => 'mylan'],
            ['name' => 'Teva', 'code' => 'teva'],
            ['name' => 'Cipha', 'code' => 'cipha'],
            ['name' => 'Exphar', 'code' => 'exphar'],
            ['name' => 'Afripharm', 'code' => 'afripharm'],
            ['name' => 'Denk Pharma', 'code' => 'denk'],
            ['name' => 'Emzor', 'code' => 'emzor'],
        ] as $brand) {
            Brand::firstOrCreate(
                ['code' => $brand['code']],
                ['name' => $brand['name'], 'is_active' => true]
            );
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep catalogue data; pivot disable is enough.
    }
}
