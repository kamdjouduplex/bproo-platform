<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Placeholder so the pharma vertical has a publishable migration tag.
 * Real pharma tables (DCI catalogue, mutuelles, …) land in later migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty — vertical bootstrap only.
        if (! Schema::connection('tenant')->hasTable('modules')) {
            return;
        }
    }

    public function down(): void
    {
        //
    }
};
