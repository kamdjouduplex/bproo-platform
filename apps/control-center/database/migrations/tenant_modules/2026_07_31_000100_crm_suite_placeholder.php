<?php

use Illuminate\Database\Migrations\Migration;

/**
 * CRM suite reuses prospects + clients schemas.
 * This no-op migration keeps a publishable tag for ModuleRegistry.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
