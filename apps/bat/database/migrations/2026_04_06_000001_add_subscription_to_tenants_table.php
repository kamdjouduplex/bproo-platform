<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'plan')) {
                $table->string('plan', 20)->default('free')->after('is_active'); // free|starter|pro|enterprise
            }
            if (!Schema::hasColumn('tenants', 'plan_started_at')) {
                $table->timestamp('plan_started_at')->nullable()->after('plan');
            }
            if (!Schema::hasColumn('tenants', 'plan_expires_at')) {
                $table->timestamp('plan_expires_at')->nullable()->after('plan_started_at');
            }
            if (!Schema::hasColumn('tenants', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('plan_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (['plan', 'plan_started_at', 'plan_expires_at', 'trial_ends_at'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
