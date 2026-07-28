<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('items', function (Blueprint $table) {
            // Drop POS-specific price tiers and barcode
            if (Schema::connection('tenant')->hasColumn('items', 'price_semi_wholesale')) {
                $table->dropColumn('price_semi_wholesale');
            }
            if (Schema::connection('tenant')->hasColumn('items', 'price_wholesale')) {
                $table->dropColumn('price_wholesale');
            }
            if (Schema::connection('tenant')->hasColumn('items', 'barcode')) {
                $table->dropColumn('barcode');
            }
            if (Schema::connection('tenant')->hasColumn('items', 'brand_id')) {
                $table->dropForeign(['brand_id']);
                $table->dropColumn('brand_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('items', function (Blueprint $table) {
            $table->decimal('price_semi_wholesale', 12, 2)->default(0)->after('price');
            $table->decimal('price_wholesale', 12, 2)->default(0)->after('price_semi_wholesale');
            $table->string('barcode')->nullable()->unique()->after('sku');
            $table->unsignedBigInteger('brand_id')->nullable()->after('category_id');
        });
    }
};
