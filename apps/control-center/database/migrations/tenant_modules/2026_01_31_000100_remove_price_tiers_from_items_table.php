<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('items', function (Blueprint $table) {
            $table->dropColumn(['price_semi_wholesale', 'price_wholesale']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('items', function (Blueprint $table) {
            $table->decimal('price_semi_wholesale', 12, 2)->default(0)->after('price');
            $table->decimal('price_wholesale', 12, 2)->default(0)->after('price_semi_wholesale');
        });
    }
};
