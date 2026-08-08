<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('employees')) {
            return;
        }

        Schema::connection('tenant')->table('employees', function (Blueprint $table) {
            if (! Schema::connection('tenant')->hasColumn('employees', 'punch_pin')) {
                $table->string('punch_pin', 255)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('tenant')->hasTable('employees')) {
            return;
        }

        Schema::connection('tenant')->table('employees', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('employees', 'punch_pin')) {
                $table->dropColumn('punch_pin');
            }
        });
    }
};
