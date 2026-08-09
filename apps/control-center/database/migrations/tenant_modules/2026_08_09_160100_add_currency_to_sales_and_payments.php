<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('sales')) {
            Schema::connection('tenant')->table('sales', function (Blueprint $table) {
                if (! Schema::connection('tenant')->hasColumn('sales', 'currency_code')) {
                    $table->string('currency_code', 3)->nullable()->after('total');
                }
                if (! Schema::connection('tenant')->hasColumn('sales', 'exchange_rate_to_default')) {
                    $table->decimal('exchange_rate_to_default', 18, 6)->nullable()->after('currency_code');
                }
                if (! Schema::connection('tenant')->hasColumn('sales', 'total_in_default')) {
                    $table->decimal('total_in_default', 15, 2)->nullable()->after('exchange_rate_to_default');
                }
            });
        }

        if (Schema::connection('tenant')->hasTable('payments')) {
            Schema::connection('tenant')->table('payments', function (Blueprint $table) {
                if (! Schema::connection('tenant')->hasColumn('payments', 'currency_code')) {
                    $table->string('currency_code', 3)->nullable()->after('amount');
                }
                if (! Schema::connection('tenant')->hasColumn('payments', 'exchange_rate_to_default')) {
                    $table->decimal('exchange_rate_to_default', 18, 6)->nullable()->after('currency_code');
                }
                if (! Schema::connection('tenant')->hasColumn('payments', 'amount_in_default')) {
                    $table->decimal('amount_in_default', 15, 2)->nullable()->after('exchange_rate_to_default');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('sales')) {
            Schema::connection('tenant')->table('sales', function (Blueprint $table) {
                foreach (['total_in_default', 'exchange_rate_to_default', 'currency_code'] as $col) {
                    if (Schema::connection('tenant')->hasColumn('sales', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::connection('tenant')->hasTable('payments')) {
            Schema::connection('tenant')->table('payments', function (Blueprint $table) {
                foreach (['amount_in_default', 'exchange_rate_to_default', 'currency_code'] as $col) {
                    if (Schema::connection('tenant')->hasColumn('payments', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
