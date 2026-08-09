<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'max_users')) {
                $table->unsignedInteger('max_users')->nullable()->after('users_count');
            }
            if (! Schema::hasColumn('tenants', 'users_limit_exceeded_at')) {
                $table->timestamp('users_limit_exceeded_at')->nullable()->after('max_users');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (['users_limit_exceeded_at', 'max_users'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
