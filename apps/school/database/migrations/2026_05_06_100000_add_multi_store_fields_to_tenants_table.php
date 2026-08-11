<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('multi_store_enabled')->default(false)->after('is_active');
            $table->timestamp('multi_store_enabled_at')->nullable()->after('multi_store_enabled');
            $table->string('multi_store_setup_status', 20)->default('disabled')->after('multi_store_enabled_at');
            $table->text('multi_store_setup_error')->nullable()->after('multi_store_setup_status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'multi_store_enabled',
                'multi_store_enabled_at',
                'multi_store_setup_status',
                'multi_store_setup_error',
            ]);
        });
    }
};
