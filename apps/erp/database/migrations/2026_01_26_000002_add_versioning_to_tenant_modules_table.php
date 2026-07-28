<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_modules', function (Blueprint $table) {
            // Check if columns exist before adding (for safety)
            if (!Schema::hasColumn('tenant_modules', 'installed_version')) {
                $table->string('installed_version')->nullable()->after('enabled');
            }
            if (!Schema::hasColumn('tenant_modules', 'installed_at')) {
                $table->timestamp('installed_at')->nullable()->after('installed_version');
            }
            // Note: updated_at already exists from timestamps() in original migration
        });
    }

    public function down(): void
    {
        Schema::table('tenant_modules', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_modules', 'installed_version')) {
                $table->dropColumn('installed_version');
            }
            if (Schema::hasColumn('tenant_modules', 'installed_at')) {
                $table->dropColumn('installed_at');
            }
        });
    }
};
