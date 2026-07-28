<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('version')->nullable()->after('description');
            $table->string('installed_version')->nullable()->after('version');
            $table->json('compatibility')->nullable()->after('installed_version');
            $table->string('package_name')->nullable()->after('compatibility');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['version', 'installed_version', 'compatibility', 'package_name']);
        });
    }
};
