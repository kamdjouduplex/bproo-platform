<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('quotes', 'refuse_reason')) {
                $table->text('refuse_reason')->nullable()->after('refused_at');
            }
        });

        Schema::table('quote_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('quote_lines', 'unit')) {
                $table->string('unit', 30)->nullable()->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (Schema::hasColumn('quotes', 'refuse_reason')) {
                $table->dropColumn('refuse_reason');
            }
        });

        Schema::table('quote_lines', function (Blueprint $table) {
            if (Schema::hasColumn('quote_lines', 'unit')) {
                $table->dropColumn('unit');
            }
        });
    }
};
