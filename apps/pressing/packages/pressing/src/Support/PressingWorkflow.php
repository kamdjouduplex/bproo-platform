<?php

namespace Pressing\Support;

use Illuminate\Support\Collection;
use Pressing\Models\WorkflowStage;

class PressingWorkflow
{
    /** Pre-production (hors Kanban) */
    public const STAGE_TRI = 'Tri';

    /** Kanban production */
    public const STAGE_MISE_EN_PRODUCTION = 'Mise en Production';

    public const STAGE_LAVAGE = 'Lavage';

    public const STAGE_SECHAGE = 'Séchage';

    public const STAGE_REPASSAGE = 'Repassage';

    public const STAGE_FIN_PRODUCTION = 'Fin de production';

    /** Legacy / hors Kanban */
    public const STAGE_PRET = 'Prêt';

    public const STAGE_LIVRE = 'Livré';

    public const STAGE_RECEPTION = 'Réception';

    /** Colonnes visibles du Kanban (ordre). */
    public static function kanbanStageNames(): array
    {
        return [
            self::STAGE_MISE_EN_PRODUCTION,
            self::STAGE_LAVAGE,
            self::STAGE_SECHAGE,
            self::STAGE_REPASSAGE,
            self::STAGE_FIN_PRODUCTION,
        ];
    }

    public static function productionEntryStage(): ?WorkflowStage
    {
        return self::stageByName(self::STAGE_MISE_EN_PRODUCTION);
    }

    public static function finProductionStage(): ?WorkflowStage
    {
        return self::stageByName(self::STAGE_FIN_PRODUCTION);
    }

    public static function stageByName(string $name): ?WorkflowStage
    {
        return WorkflowStage::query()
            ->whereNull('agence_id')
            ->where('name', $name)
            ->where('is_active', true)
            ->first();
    }

    /** Stages affichées sur le Kanban. */
    public static function kanbanStages(): Collection
    {
        $names = self::kanbanStageNames();

        return WorkflowStage::query()
            ->whereNull('agence_id')
            ->where('is_active', true)
            ->whereIn('name', $names)
            ->get()
            ->sortBy(fn (WorkflowStage $s) => array_search($s->name, $names, true))
            ->values();
    }

    public static function productionSortOrder(): int
    {
        return (int) (self::productionEntryStage()?->sort_order ?? 10);
    }

    public static function isProductionStage(WorkflowStage $stage): bool
    {
        return in_array($stage->name, self::kanbanStageNames(), true);
    }

    public static function isPreProductionStage(WorkflowStage $stage): bool
    {
        return ! self::isProductionStage($stage) && ! $stage->is_final;
    }

    public static function isTriStage(WorkflowStage $stage): bool
    {
        return $stage->name === self::STAGE_TRI;
    }

    public static function isFinProductionStage(WorkflowStage $stage): bool
    {
        return $stage->name === self::STAGE_FIN_PRODUCTION;
    }
}
