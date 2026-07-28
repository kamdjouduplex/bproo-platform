<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('route_name')->nullable()->after('description');
            $table->string('lifecycle_handler')->nullable()->after('route_name');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['route_name', 'lifecycle_handler']);
        });
    }
};
