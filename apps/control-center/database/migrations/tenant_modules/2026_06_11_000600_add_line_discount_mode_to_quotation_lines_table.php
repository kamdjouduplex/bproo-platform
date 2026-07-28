<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('quotation_lines')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_lines', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotation_lines', 'line_discount_mode')) {
                $table->string('line_discount_mode', 10)->default('amount')->after('line_discount');
            }
            if (!Schema::connection('tenant')->hasColumn('quotation_lines', 'line_discount_input')) {
                $table->decimal('line_discount_input', 12, 4)->nullable()->after('line_discount_mode');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('quotation_lines')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_lines', function (Blueprint $table) {
            $columns = [];
            if (Schema::connection('tenant')->hasColumn('quotation_lines', 'line_discount_input')) {
                $columns[] = 'line_discount_input';
            }
            if (Schema::connection('tenant')->hasColumn('quotation_lines', 'line_discount_mode')) {
                $columns[] = 'line_discount_mode';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
