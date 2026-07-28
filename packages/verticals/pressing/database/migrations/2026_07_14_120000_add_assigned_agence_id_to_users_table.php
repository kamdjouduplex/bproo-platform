<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('users')) {
            return;
        }

        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            if (! Schema::connection('tenant')->hasColumn('users', 'assigned_agence_id')) {
                $table->unsignedBigInteger('assigned_agence_id')->nullable()->after('assigned_store_id');
                $table->index('assigned_agence_id', 'users_assigned_agence_id_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('tenant')->hasTable('users')) {
            return;
        }

        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('users', 'assigned_agence_id')) {
                $table->dropIndex('users_assigned_agence_id_index');
                $table->dropColumn('assigned_agence_id');
            }
        });
    }
};
