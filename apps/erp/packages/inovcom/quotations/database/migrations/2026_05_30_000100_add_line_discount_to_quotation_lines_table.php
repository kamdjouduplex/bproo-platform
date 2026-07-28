<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('quotation_lines', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotation_lines', 'line_discount')) {
                $table->decimal('line_discount', 12, 2)->default(0)->after('unit_price');
            }
            if (!Schema::connection('tenant')->hasColumn('quotation_lines', 'unit_price_net')) {
                $table->decimal('unit_price_net', 12, 2)->nullable()->after('line_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('quotation_lines', function (Blueprint $table) {
            $table->dropColumn(['line_discount', 'unit_price_net']);
        });
    }
};
