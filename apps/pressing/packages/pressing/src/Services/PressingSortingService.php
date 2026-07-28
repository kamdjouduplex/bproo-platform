<?php

namespace Pressing\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Pressing\Models\ArticleType;
use Pressing\Models\OrderStageHistory;
use Pressing\Models\PressingOrder;
use Pressing\Models\PressingOrderConstitutionLine;
use Pressing\Models\WorkflowStage;
use Pressing\Support\PressingConstitution;
use Pressing\Support\PressingWorkflow;

class PressingSortingService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public function seedFromReception(PressingOrder $order): void
    {
        if ($order->constitutionLines()->exists()) {
            return;
        }

        $order->loadMissing('items.articleType');
        $sort = 0;

        foreach ($order->items as $item) {
            PressingOrderConstitutionLine::create([
                'order_id' => $order->id,
                'article_type_id' => $item->article_type_id,
                'quantity' => max(1, (int) $item->quantity),
                'sort_order' => $sort++,
            ]);
        }

        if ($sort === 0) {
            $type = ArticleType::query()->where('is_active', true)->orderBy('sort_order')->first();
            if ($type) {
                PressingOrderConstitutionLine::create([
                    'order_id' => $order->id,
                    'article_type_id' => $type->id,
                    'quantity' => 1,
                    'sort_order' => 0,
                ]);
            }
        }
    }

    public function isLineValid(array $line): bool
    {
        return PressingConstitution::isLineValid($line);
    }

    public function allLinesValid(array $lines): bool
    {
        if ($lines === []) {
            return false;
        }

        foreach ($lines as $line) {
            if (! $this->isLineValid($line)) {
                return false;
            }
        }

        return true;
    }

    public function validLineCount(array $lines): int
    {
        return collect($lines)->filter(fn ($line) => $this->isLineValid($line))->count();
    }

    public function totalQuantityFromLines(array $lines): int
    {
        return PressingConstitution::totalQuantity($lines);
    }

    public function persistLines(PressingOrder $order, array $lines): Collection
    {
        $order->constitutionLines()->delete();

        $created = collect();
        $sort = 0;

        foreach ($lines as $line) {
            $typeId = (int) ($line['article_type_id'] ?? 0);
            if ($typeId <= 0) {
                continue;
            }

            $created->push(PressingOrderConstitutionLine::create([
                'order_id' => $order->id,
                'article_type_id' => $typeId,
                'color' => trim((string) ($line['color'] ?? '')) ?: null,
                'pattern' => trim((string) ($line['pattern'] ?? '')) ?: null,
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                'notes' => trim((string) ($line['notes'] ?? '')) ?: null,
                'sort_order' => $sort++,
            ]));
        }

        return $created;
    }

    public function linesToArray(PressingOrder $order): array
    {
        return $order->constitutionLines()
            ->with('articleType')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PressingOrderConstitutionLine $line) => [
                'id' => $line->id,
                'article_type_id' => $line->article_type_id,
                'type_name' => $line->articleType?->name ?? '',
                'color' => $line->color ?? '',
                'pattern' => $line->pattern ?? '',
                'quantity' => (int) $line->quantity,
                'notes' => $line->notes ?? '',
            ])
            ->values()
            ->all();
    }

    public function markInProgress(PressingOrder $order): void
    {
        if ($order->sorting_status === self::STATUS_COMPLETED) {
            return;
        }

        $order->update(['sorting_status' => self::STATUS_IN_PROGRESS]);
    }

    public function completeSorting(PressingOrder $order, array $lines, ?int $productionUserId = null): PressingOrder
    {
        if (! $this->allLinesValid($lines)) {
            throw new \InvalidArgumentException(
                'Chaque ligne doit avoir un type, une quantité et au moins une couleur ou un descriptif (jean, rayée, wax…).'
            );
        }

        $this->persistLines($order, $lines);

        $userId = Auth::guard('tenant')->id();
        $now = now();

        $productionStage = PressingWorkflow::productionEntryStage();
        if (! $productionStage) {
            throw new \RuntimeException('Étape « Mise en Production » introuvable.');
        }

        $assigneeId = $productionUserId ?: $order->assigned_user_id ?: $userId;

        $order->update([
            'sorting_status' => self::STATUS_COMPLETED,
            'sorting_completed_at' => $now,
            'sorted_by' => $userId,
            'current_stage_id' => $productionStage->id,
            'status' => 'open',
            'assigned_user_id' => $assigneeId,
        ]);

        OrderStageHistory::create([
            'order_id' => $order->id,
            'stage_id' => $productionStage->id,
            'stage_name' => $productionStage->name,
            'user_id' => $userId,
            'moved_at' => $now,
            'note' => $productionUserId
                ? 'Constitution validée — assignée à la production'
                : 'Constitution validée — mise en production',
        ]);

        return $order->fresh(['client', 'agence', 'constitutionLines.articleType', 'currentStage', 'assignee']);
    }

    public function canEnterProduction(PressingOrder $order): bool
    {
        return $order->sorting_status === self::STATUS_COMPLETED;
    }

    public function validateMoveToStage(PressingOrder $order, WorkflowStage $target): ?string
    {
        if (! PressingWorkflow::isProductionStage($target)) {
            return 'Seules les étapes de production (Mise en Production → Fin de production) sont gérées depuis le Kanban.';
        }

        if (! $this->canEnterProduction($order)) {
            return 'Constituez d’abord la commande (tri) avant la mise en production.';
        }

        return null;
    }
}
