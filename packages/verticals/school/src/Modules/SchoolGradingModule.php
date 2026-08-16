<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Models\SchoolGradeScale;
use School\Models\SchoolGradingSystem;
use School\Support\SyncsSchoolModulePermissions;

class SchoolGradingModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_grading.view', 'name' => 'Voir la notation', 'description' => 'Consulter systèmes, barèmes et règles'],
            ['key' => 'school_grading.manage', 'name' => 'Gérer la notation', 'description' => 'Configurer coefficients, barèmes et règles de calcul'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_grading');
        $this->seedDefaultSystem();
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }

    private function seedDefaultSystem(): void
    {
        if (SchoolGradingSystem::query()->exists()) {
            return;
        }

        $system = SchoolGradingSystem::query()->create([
            'code' => 'STD-20',
            'name' => 'Barème /20 (standard)',
            'scale_base' => 20,
            'description' => 'Système par défaut Bproo School',
            'is_active' => true,
        ]);

        $bands = [
            ['label' => 'Excellent', 'min_percent' => 80, 'max_percent' => 100, 'is_pass' => true, 'sort_order' => 10],
            ['label' => 'Très bien', 'min_percent' => 70, 'max_percent' => 79.99, 'is_pass' => true, 'sort_order' => 20],
            ['label' => 'Bien', 'min_percent' => 60, 'max_percent' => 69.99, 'is_pass' => true, 'sort_order' => 30],
            ['label' => 'Assez bien', 'min_percent' => 50, 'max_percent' => 59.99, 'is_pass' => true, 'sort_order' => 40],
            ['label' => 'Passable', 'min_percent' => 40, 'max_percent' => 49.99, 'is_pass' => true, 'sort_order' => 50],
            ['label' => 'Insuffisant', 'min_percent' => 0, 'max_percent' => 39.99, 'is_pass' => false, 'sort_order' => 60],
        ];

        foreach ($bands as $band) {
            SchoolGradeScale::query()->create(array_merge($band, [
                'grading_system_id' => $system->id,
            ]));
        }
    }
}
