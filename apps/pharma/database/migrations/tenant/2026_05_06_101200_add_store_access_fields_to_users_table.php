<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('users')) {
            return;
        }

        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('users', 'assigned_store_id')) {
                $table->unsignedBigInteger('assigned_store_id')->nullable()->after('is_active');
                $table->index('assigned_store_id', 'users_assigned_store_id_index');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('users')) {
            return;
        }

        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('users', 'assigned_store_id')) {
                $table->dropIndex('users_assigned_store_id_index');
                $table->dropColumn('assigned_store_id');
            }
        });
    }
};
