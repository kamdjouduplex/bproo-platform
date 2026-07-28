<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reshape workflow stages to the clear production pipeline:
 * Tri (hors Kanban) → Mise en Production → Lavage → Séchage → Fin de production
 * Keep "Prêt" / "Livré" as soft status markers off the Kanban.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('workflow_stages')) {
            return;
        }

        $conn = DB::connection('tenant');

        $ensure = function (string $name, string $color, int $order, bool $final = false) use ($conn) {
            $existing = $conn->table('workflow_stages')
                ->whereNull('agence_id')
                ->where('name', $name)
                ->first();

            if ($existing) {
                $conn->table('workflow_stages')->where('id', $existing->id)->update([
                    'color' => $color,
                    'sort_order' => $order,
                    'is_active' => true,
                    'is_final' => $final,
                    'updated_at' => now(),
                ]);

                return (int) $existing->id;
            }

            return (int) $conn->table('workflow_stages')->insertGetId([
                'agence_id' => null,
                'name' => $name,
                'color' => $color,
                'sort_order' => $order,
                'is_active' => true,
                'is_final' => $final,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        $triId = $ensure('Tri', '#6366f1', 1);
        $miseId = $ensure('Mise en Production', '#0ea5e9', 10);
        $lavageId = $ensure('Lavage', '#3b82f6', 20);
        $sechageId = $ensure('Séchage', '#14b8a6', 30);
        $finId = $ensure('Fin de production', '#f59e0b', 40);
        $pretId = $ensure('Prêt', '#22c55e', 90);
        $livreId = $ensure('Livré', '#16a34a', 100, true);

        // Remap legacy stages → new pipeline
        $remap = [
            'Réception' => $triId,
            'Repassage' => $sechageId,
            'Contrôle qualité' => $finId,
            'Emballage' => $finId,
        ];

        foreach ($remap as $oldName => $newId) {
            $old = $conn->table('workflow_stages')->whereNull('agence_id')->where('name', $oldName)->first();
            if (! $old) {
                continue;
            }
            $conn->table('pressing_orders')
                ->where('current_stage_id', $old->id)
                ->update(['current_stage_id' => $newId]);
            $conn->table('order_stage_history')
                ->where('stage_id', $old->id)
                ->update(['stage_id' => $newId, 'stage_name' => $conn->table('workflow_stages')->where('id', $newId)->value('name')]);
            $conn->table('workflow_stages')->where('id', $old->id)->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
        }

        // Orders already past Tri with sorting completed but still on Tri → Mise en Production
        $conn->table('pressing_orders')
            ->where('current_stage_id', $triId)
            ->where('sorting_status', 'completed')
            ->whereIn('status', ['open', 'ready'])
            ->update(['current_stage_id' => $miseId]);

        // Ready orders → Prêt stage (off Kanban)
        $conn->table('pressing_orders')
            ->where('status', 'ready')
            ->where('current_stage_id', $finId)
            ->update(['current_stage_id' => $pretId]);

        // Delivered → Livré
        $conn->table('pressing_orders')
            ->where('status', 'delivered')
            ->update(['current_stage_id' => $livreId]);

        // Hide non-kanban old leftovers that aren't remapped
        $conn->table('workflow_stages')
            ->whereNull('agence_id')
            ->whereNotIn('name', [
                'Tri', 'Mise en Production', 'Lavage', 'Séchage', 'Fin de production', 'Prêt', 'Livré',
            ])
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Non-destructive: leave stages as-is
    }
};
