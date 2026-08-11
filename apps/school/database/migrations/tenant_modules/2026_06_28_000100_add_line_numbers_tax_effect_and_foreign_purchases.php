<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['quotation_lines', 'invoice_lines'] as $table) {
            if (Schema::connection('tenant')->hasTable($table)
                && !Schema::connection('tenant')->hasColumn($table, 'line_number')) {
                Schema::connection('tenant')->table($table, function (Blueprint $table) {
                    $table->unsignedInteger('line_number')->nullable()->after('id');
                });
            }
        }

        foreach (['quotation_tax_lines', 'invoice_tax_lines'] as $table) {
            if (Schema::connection('tenant')->hasTable($table)
                && !Schema::connection('tenant')->hasColumn($table, 'tax_effect')) {
                Schema::connection('tenant')->table($table, function (Blueprint $table) {
                    $table->string('tax_effect', 10)->default('add')->after('tax_amount');
                });
            }
        }

        if (Schema::connection('tenant')->hasTable('invoice_tax_lines')
            && !Schema::connection('tenant')->hasColumn('invoice_tax_lines', 'tax_mode')) {
            Schema::connection('tenant')->table('invoice_tax_lines', function (Blueprint $table) {
                $table->string('tax_mode', 10)->default('amount')->after('tax_name');
            });
        }

        if (Schema::connection('tenant')->hasTable('providers')) {
            Schema::connection('tenant')->table('providers', function (Blueprint $table) {
                if (!Schema::connection('tenant')->hasColumn('providers', 'is_foreign')) {
                    $table->boolean('is_foreign')->default(false)->after('country');
                }
                if (!Schema::connection('tenant')->hasColumn('providers', 'default_currency')) {
                    $table->string('default_currency', 10)->nullable()->after('is_foreign');
                }
            });
        }

        if (!Schema::connection('tenant')->hasTable('foreign_purchase_orders')) {
            Schema::connection('tenant')->create('foreign_purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number')->unique();
                $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
                $table->date('order_date');
                $table->date('expected_date')->nullable();
                $table->string('currency_code', 10);
                $table->decimal('exchange_rate', 14, 6)->default(1);
                $table->decimal('subtotal_foreign', 15, 2)->default(0);
                $table->decimal('subtotal_local', 15, 2)->default(0);
                $table->string('status', 20)->default('draft');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('store_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['status', 'order_date']);
            });
        }

        if (!Schema::connection('tenant')->hasTable('foreign_purchase_lines')) {
            Schema::connection('tenant')->create('foreign_purchase_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('foreign_purchase_order_id')
                    ->constrained('foreign_purchase_orders')
                    ->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('items');
                $table->string('item_name');
                $table->decimal('quantity', 10, 3)->default(1);
                $table->decimal('unit_price_foreign', 15, 4)->default(0);
                $table->decimal('unit_price_local', 15, 2)->default(0);
                $table->decimal('line_total_foreign', 15, 2)->default(0);
                $table->decimal('line_total_local', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('foreign_purchase_lines');
        Schema::connection('tenant')->dropIfExists('foreign_purchase_orders');

        if (Schema::connection('tenant')->hasTable('providers')) {
            Schema::connection('tenant')->table('providers', function (Blueprint $table) {
                foreach (['default_currency', 'is_foreign'] as $col) {
                    if (Schema::connection('tenant')->hasColumn('providers', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        foreach (['invoice_tax_lines', 'quotation_tax_lines'] as $table) {
            if (Schema::connection('tenant')->hasTable($table)
                && Schema::connection('tenant')->hasColumn($table, 'tax_effect')) {
                Schema::connection('tenant')->table($table, function (Blueprint $table) {
                    $table->dropColumn('tax_effect');
                });
            }
        }

        if (Schema::connection('tenant')->hasTable('invoice_tax_lines')
            && Schema::connection('tenant')->hasColumn('invoice_tax_lines', 'tax_mode')) {
            Schema::connection('tenant')->table('invoice_tax_lines', function (Blueprint $table) {
                $table->dropColumn('tax_mode');
            });
        }

        foreach (['quotation_lines', 'invoice_lines'] as $table) {
            if (Schema::connection('tenant')->hasTable($table)
                && Schema::connection('tenant')->hasColumn($table, 'line_number')) {
                Schema::connection('tenant')->table($table, function (Blueprint $table) {
                    $table->dropColumn('line_number');
                });
            }
        }
    }
};
