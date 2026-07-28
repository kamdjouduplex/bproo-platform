<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('quotes', function (Blueprint $table) {
            // Financials (guard each column — some may exist from the original create migration)
            if (!Schema::connection('tenant')->hasColumn('quotes', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('quotes', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('quotes', 'discount_amount')) {
                $table->decimal('discount_amount', 15, 2)->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('quotes', 'net_ht')) {
                $table->decimal('net_ht', 15, 2)->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('quotes', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('quotes', 'total_ttc')) {
                $table->decimal('total_ttc', 15, 2)->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('quotes', 'margin_percent')) {
                $table->decimal('margin_percent', 5, 2)->nullable();
            }

            // Currency & terms
            if (!Schema::connection('tenant')->hasColumn('quotes', 'currency')) {
                $table->string('currency', 3)->default('XOF');
            }
            if (!Schema::connection('tenant')->hasColumn('quotes', 'terms')) {
                $table->text('terms')->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('quotes', 'internal_notes')) {
                $table->text('internal_notes')->nullable();
            }

            // Workflow timestamps (sent_at may already exist in older create migrations)
            if (!Schema::connection('tenant')->hasColumn('quotes', 'sent_at')) {
                $table->timestamp('sent_at')->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('quotes', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('quotes', 'refused_at')) {
                $table->timestamp('refused_at')->nullable();
            }

            // Versioning / revision chain
            if (!Schema::connection('tenant')->hasColumn('quotes', 'version')) {
                $table->unsignedInteger('version')->default(1);
            }
            if (!Schema::connection('tenant')->hasColumn('quotes', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable();
            }
        });

        // Enhance quote_lines for item integration + margin tracking
        Schema::connection('tenant')->table('quote_lines', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quote_lines', 'cost')) {
                $table->decimal('cost', 15, 2)->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('quote_lines', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('quote_lines', 'line_type')) {
                $table->string('line_type', 50)->default('service');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('quote_lines', function (Blueprint $table) {
            $table->dropColumnIfExists('cost');
            $table->dropColumnIfExists('discount_percent');
            $table->dropColumnIfExists('line_type');
        });
        Schema::connection('tenant')->table('quotes', function (Blueprint $table) {
            foreach ([
                'tax_rate', 'discount_percent', 'discount_amount', 'net_ht',
                'tax_amount', 'total_ttc', 'margin_percent', 'currency',
                'terms', 'internal_notes', 'accepted_at', 'refused_at',
                'version', 'parent_id',
            ] as $col) {
                if (Schema::connection('tenant')->hasColumn('quotes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
