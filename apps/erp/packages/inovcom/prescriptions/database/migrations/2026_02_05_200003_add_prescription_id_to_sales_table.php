<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('sales')) {
            return;
        }
        Schema::connection('tenant')->table('sales', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('sales', 'prescription_id')) {
                $table->foreignId('prescription_id')->nullable()->after('id')->constrained('prescriptions')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('sales')) {
            return;
        }
        Schema::connection('tenant')->table('sales', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('sales', 'prescription_id')) {
                $table->dropForeign(['prescription_id']);
            }
        });
    }
};
