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
            if (! Schema::connection('tenant')->hasColumn('users', 'phone')) {
                $table->string('phone', 32)->nullable()->after('email');
                $table->unique('phone');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('tenant')->hasTable('users')) {
            return;
        }

        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('users', 'phone')) {
                $table->dropUnique(['phone']);
                $table->dropColumn('phone');
            }
        });
    }
};
