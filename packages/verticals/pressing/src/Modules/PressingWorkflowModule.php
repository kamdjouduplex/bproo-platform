<?php

namespace Pressing\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use Pressing\Models\WorkflowStage;

class PressingWorkflowModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'pressing_workflow.view', 'name' => 'Voir le workflow pressing', 'description' => 'Accès Kanban production'],
            ['key' => 'pressing_workflow.move', 'name' => 'Déplacer les commandes', 'description' => 'Changer l’étape de production'],
            ['key' => 'pressing_workflow.manage', 'name' => 'Configurer le workflow', 'description' => 'Créer et ordonner les étapes'],
        ];
    }

    public function install(object $tenant): void
    {
        foreach (self::defaultPermissions() as $p) {
            Permission::on('tenant')->firstOrCreate(
                ['key' => $p['key']],
                ['name' => $p['name'], 'description' => $p['description'] ?? null]
            );
        }

        $stages = [
            ['name' => 'Tri', 'color' => '#6366f1', 'sort_order' => 1],
            ['name' => 'Mise en Production', 'color' => '#0ea5e9', 'sort_order' => 10],
            ['name' => 'Lavage', 'color' => '#3b82f6', 'sort_order' => 20],
            ['name' => 'Séchage', 'color' => '#14b8a6', 'sort_order' => 30],
            ['name' => 'Repassage', 'color' => '#8b5cf6', 'sort_order' => 35],
            ['name' => 'Fin de production', 'color' => '#f59e0b', 'sort_order' => 40],
            ['name' => 'Prêt', 'color' => '#22c55e', 'sort_order' => 90],
            ['name' => 'Livré', 'color' => '#16a34a', 'sort_order' => 100, 'is_final' => true],
        ];

        foreach ($stages as $stage) {
            WorkflowStage::firstOrCreate(
                ['agence_id' => null, 'name' => $stage['name']],
                [
                    'color' => $stage['color'],
                    'sort_order' => $stage['sort_order'],
                    'is_active' => true,
                    'is_final' => $stage['is_final'] ?? false,
                ]
            );
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
