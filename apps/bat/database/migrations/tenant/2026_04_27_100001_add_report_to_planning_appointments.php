<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('planning_appointments', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('planning_appointments', 'report')) {
                $table->text('report')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('planning_appointments', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('planning_appointments', 'report')) {
                $table->dropColumn('report');
            }
        });
    }
};
