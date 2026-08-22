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
            'description' => 'Mentions sur 20 : Excellent 16–20, Très bien 14–16, Bien 12–14, Assez bien 10–12, Passable 8–10.',
            'is_active' => true,
        ]);

        $bands = [
            ['label' => 'Excellent', 'min_percent' => 16, 'max_percent' => 20, 'is_pass' => true, 'sort_order' => 10],
            ['label' => 'Très bien', 'min_percent' => 14, 'max_percent' => 15.99, 'is_pass' => true, 'sort_order' => 20],
            ['label' => 'Bien', 'min_percent' => 12, 'max_percent' => 13.99, 'is_pass' => true, 'sort_order' => 30],
            ['label' => 'Assez bien', 'min_percent' => 10, 'max_percent' => 11.99, 'is_pass' => true, 'sort_order' => 40],
            ['label' => 'Passable', 'min_percent' => 8, 'max_percent' => 9.99, 'is_pass' => true, 'sort_order' => 50],
            ['label' => 'Insuffisant', 'min_percent' => 0, 'max_percent' => 7.99, 'is_pass' => false, 'sort_order' => 60],
        ];

        foreach ($bands as $band) {
            SchoolGradeScale::query()->create(array_merge($band, [
                'grading_system_id' => $system->id,
            ]));
        }
    }
}
