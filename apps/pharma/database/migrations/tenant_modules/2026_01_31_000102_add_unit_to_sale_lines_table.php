<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('sale_lines', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('item_sku')->constrained('units')->nullOnDelete();
            $table->string('unit_name')->nullable()->after('unit_id'); // Snapshot for display
            $table->decimal('conversion_factor', 12, 4)->default(1)->after('unit_name'); // For stock deduction
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('sale_lines', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'unit_name', 'conversion_factor']);
        });
    }
};
