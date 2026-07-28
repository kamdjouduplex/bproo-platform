<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('quotes', 'expired_at')) {
                $table->timestamp('expired_at')->nullable()->after('refused_at');
            }
            if (!Schema::hasColumn('quotes', 'last_reminder_at')) {
                $table->timestamp('last_reminder_at')->nullable()->after('expired_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (Schema::hasColumn('quotes', 'last_reminder_at')) {
                $table->dropColumn('last_reminder_at');
            }
            if (Schema::hasColumn('quotes', 'expired_at')) {
                $table->dropColumn('expired_at');
            }
        });
    }
};
