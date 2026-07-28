<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('clients', function (Blueprint $table) {
            $table->string('rccm', 100)->nullable()->after('tax_id');
            $table->string('niu', 100)->nullable()->after('rccm');
            $table->string('bp', 100)->nullable()->after('niu');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('clients', function (Blueprint $table) {
            $table->dropColumn(['rccm', 'niu', 'bp']);
        });
    }
};
