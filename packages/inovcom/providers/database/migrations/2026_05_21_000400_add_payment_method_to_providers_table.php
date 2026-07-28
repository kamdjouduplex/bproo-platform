<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('providers', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'mobile_money', 'check', 'bank_transfer'])
                ->nullable()
                ->after('payment_term_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('providers', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
