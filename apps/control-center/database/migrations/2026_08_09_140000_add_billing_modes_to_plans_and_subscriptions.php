<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'billing_mode')) {
                $table->string('billing_mode', 20)->default('flat')->after('billing_interval');
            }
            if (! Schema::hasColumn('plans', 'price_per_user')) {
                $table->decimal('price_per_user', 12, 2)->nullable()->after('price');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'billing_mode')) {
                $table->string('billing_mode', 20)->nullable()->after('plan_id');
            }
            if (! Schema::hasColumn('subscriptions', 'seats_billed')) {
                $table->unsignedInteger('seats_billed')->nullable()->after('billing_mode');
            }
            if (! Schema::hasColumn('subscriptions', 'unit_price')) {
                $table->decimal('unit_price', 12, 2)->nullable()->after('seats_billed');
            }
        });

        Schema::table('tenant_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_payments', 'months_applied')) {
                $table->unsignedInteger('months_applied')->nullable()->after('amount');
            }
            if (! Schema::hasColumn('tenant_payments', 'seats_billed')) {
                $table->unsignedInteger('seats_billed')->nullable()->after('months_applied');
            }
            if (! Schema::hasColumn('tenant_payments', 'unit_price')) {
                $table->decimal('unit_price', 12, 2)->nullable()->after('seats_billed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            foreach (['unit_price', 'seats_billed', 'months_applied'] as $col) {
                if (Schema::hasColumn('tenant_payments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            foreach (['unit_price', 'seats_billed', 'billing_mode'] as $col) {
                if (Schema::hasColumn('subscriptions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('plans', function (Blueprint $table) {
            foreach (['price_per_user', 'billing_mode'] as $col) {
                if (Schema::hasColumn('plans', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
