<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('prospects')) {
            return;
        }

        DB::connection('tenant')->table('prospects')
            ->whereIn('status', ['nouveau', 'contacte'])
            ->update(['status' => 'qualifie']);

        // Default for future inserts handled at app layer; keep column flexible (string).
    }

    public function down(): void
    {
        // Irreversible data remap (nouveau/contacte → qualifie).
    }
};
