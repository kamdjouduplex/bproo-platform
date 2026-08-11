<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasColumn('users', 'is_active')) {
            Schema::connection('tenant')->table('users', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('password');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasColumn('users', 'is_active')) {
            Schema::connection('tenant')->table('users', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
