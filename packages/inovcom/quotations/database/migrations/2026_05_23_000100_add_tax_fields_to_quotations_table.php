<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('quotations', function (Blueprint $table) {
            $table->boolean('apply_tax')->default(false)->after('discount_percent');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('apply_tax');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('quotations', function (Blueprint $table) {
            $table->dropColumn(['apply_tax', 'tax_rate', 'tax_amount']);
        });
    }
};
