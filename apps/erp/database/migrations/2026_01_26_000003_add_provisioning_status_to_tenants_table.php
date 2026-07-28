<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'provisioning_status')) {
                $table->string('provisioning_status')->default('pending')->after('is_active');
                $table->text('provisioning_error')->nullable()->after('provisioning_status');
                $table->timestamp('provisioned_at')->nullable()->after('provisioning_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'provisioning_status')) {
                $table->dropColumn(['provisioning_status', 'provisioning_error', 'provisioned_at']);
            }
        });
    }
};
