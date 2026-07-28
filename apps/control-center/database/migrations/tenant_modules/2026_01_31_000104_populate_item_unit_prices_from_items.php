<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing items: each gets one row in item_unit_prices (base unit)
        $defaultUnitId = DB::connection('tenant')->table('units')->value('id');
        $items = DB::connection('tenant')->table('items')->get();
        foreach ($items as $item) {
            $unitId = $item->unit_id ?? $defaultUnitId;
            if (!$unitId) {
                continue;
            }
            $exists = DB::connection('tenant')->table('item_unit_prices')
                ->where('item_id', $item->id)
                ->exists();
            if (!$exists) {
                DB::connection('tenant')->table('item_unit_prices')->insert([
                    'item_id' => $item->id,
                    'unit_id' => $unitId,
                    'conversion_factor' => 1,
                    'price' => $item->price,
                    'cost' => $item->cost,
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No rollback - data migration
    }
};
