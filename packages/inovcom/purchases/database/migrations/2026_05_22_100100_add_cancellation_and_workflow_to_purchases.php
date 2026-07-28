<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('purchase_lines', function (Blueprint $table) {
            $table->decimal('cancelled_quantity', 10, 3)->default(0)->after('received_quantity');
        });

        Schema::connection('tenant')->table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('status');
            $table->timestamp('cancelled_at')->nullable()->after('confirmed_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['confirmed_at', 'cancelled_at', 'cancellation_reason']);
        });

        Schema::connection('tenant')->table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn('cancelled_quantity');
        });
    }
};
