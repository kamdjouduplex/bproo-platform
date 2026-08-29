<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('quotations')) {
            return;
        }
        if (Schema::connection('tenant')->hasColumn('quotations', 'fulfillment_status')) {
            return;
        }

        Schema::connection('tenant')->table('quotations', function (Blueprint $table) {
            $table->string('fulfillment_status', 20)->default('none')->after('status');
        });
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('quotations')
            && Schema::connection('tenant')->hasColumn('quotations', 'fulfillment_status')) {
            Schema::connection('tenant')->table('quotations', function (Blueprint $table) {
                $table->dropColumn('fulfillment_status');
            });
        }
    }
};
