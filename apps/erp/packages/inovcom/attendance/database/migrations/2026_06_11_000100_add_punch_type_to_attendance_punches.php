<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('attendance_punches')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('attendance_punches', 'punch_type')) {
            return;
        }

        Schema::connection('tenant')->table('attendance_punches', function (Blueprint $table) {
            $table->string('punch_type', 8)->default('in')->after('punched_at');
            $table->index(['attendance_date', 'user_id', 'punch_type']);
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('attendance_punches')) {
            return;
        }

        if (!Schema::connection('tenant')->hasColumn('attendance_punches', 'punch_type')) {
            return;
        }

        Schema::connection('tenant')->table('attendance_punches', function (Blueprint $table) {
            $table->dropIndex(['attendance_date', 'user_id', 'punch_type']);
            $table->dropColumn('punch_type');
        });
    }
};
