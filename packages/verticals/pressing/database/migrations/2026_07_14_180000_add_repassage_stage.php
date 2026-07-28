<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('workflow_stages')) {
            return;
        }

        $conn = DB::connection('tenant');

        $existing = $conn->table('workflow_stages')
            ->whereNull('agence_id')
            ->where('name', 'Repassage')
            ->first();

        if ($existing) {
            $conn->table('workflow_stages')->where('id', $existing->id)->update([
                'color' => '#8b5cf6',
                'sort_order' => 35,
                'is_active' => true,
                'is_final' => false,
                'updated_at' => now(),
            ]);
        } else {
            $conn->table('workflow_stages')->insert([
                'agence_id' => null,
                'name' => 'Repassage',
                'color' => '#8b5cf6',
                'sort_order' => 35,
                'is_active' => true,
                'is_final' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::connection('tenant')->hasTable('workflow_stages')) {
            return;
        }

        DB::connection('tenant')->table('workflow_stages')
            ->whereNull('agence_id')
            ->where('name', 'Repassage')
            ->update(['is_active' => false, 'updated_at' => now()]);
    }
};
