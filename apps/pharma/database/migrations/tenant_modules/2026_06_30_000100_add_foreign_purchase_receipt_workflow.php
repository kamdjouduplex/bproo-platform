<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('foreign_purchase_lines')
            && !Schema::connection('tenant')->hasColumn('foreign_purchase_lines', 'received_quantity')) {
            Schema::connection('tenant')->table('foreign_purchase_lines', function (Blueprint $table) {
                $table->decimal('received_quantity', 10, 3)->default(0)->after('line_total_local');
                $table->decimal('cancelled_quantity', 10, 3)->default(0)->after('received_quantity');
            });
        }

        if (Schema::connection('tenant')->hasTable('foreign_purchase_orders')) {
            Schema::connection('tenant')->table('foreign_purchase_orders', function (Blueprint $table) {
                if (!Schema::connection('tenant')->hasColumn('foreign_purchase_orders', 'confirmed_at')) {
                    $table->timestamp('confirmed_at')->nullable()->after('status');
                }
            });
        }

        if (!Schema::connection('tenant')->hasTable('foreign_receipt_notes')) {
            Schema::connection('tenant')->create('foreign_receipt_notes', function (Blueprint $table) {
                $table->id();
                $table->string('receipt_number')->unique();
                $table->date('receipt_date');
                $table->foreignId('foreign_purchase_order_id')
                    ->constrained('foreign_purchase_orders')
                    ->cascadeOnDelete();
                $table->string('status')->default('partial');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('received_by')->nullable();
                $table->timestamps();

                $table->index(['foreign_purchase_order_id', 'receipt_date']);
            });
        }

        if (!Schema::connection('tenant')->hasTable('foreign_receipt_lines')) {
            Schema::connection('tenant')->create('foreign_receipt_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('foreign_receipt_note_id')
                    ->constrained('foreign_receipt_notes')
                    ->cascadeOnDelete();
                $table->foreignId('foreign_purchase_line_id')
                    ->constrained('foreign_purchase_lines')
                    ->cascadeOnDelete();
                $table->decimal('quantity_received', 10, 3);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('foreign_receipt_lines');
        Schema::connection('tenant')->dropIfExists('foreign_receipt_notes');

        if (Schema::connection('tenant')->hasTable('foreign_purchase_orders')
            && Schema::connection('tenant')->hasColumn('foreign_purchase_orders', 'confirmed_at')) {
            Schema::connection('tenant')->table('foreign_purchase_orders', function (Blueprint $table) {
                $table->dropColumn('confirmed_at');
            });
        }

        if (Schema::connection('tenant')->hasTable('foreign_purchase_lines')) {
            Schema::connection('tenant')->table('foreign_purchase_lines', function (Blueprint $table) {
                foreach (['cancelled_quantity', 'received_quantity'] as $col) {
                    if (Schema::connection('tenant')->hasColumn('foreign_purchase_lines', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
