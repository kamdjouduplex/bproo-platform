<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('invoices')
            && !Schema::connection('tenant')->hasColumn('invoices', 'discount_mode')) {
            Schema::connection('tenant')->table('invoices', function (Blueprint $table) {
                $table->string('discount_mode', 10)->default('percent')->after('discount_percent');
            });

            $tenant = Schema::connection('tenant');
            if ($tenant->hasColumn('invoices', 'discount_mode')) {
                \Illuminate\Support\Facades\DB::connection('tenant')
                    ->table('invoices')
                    ->where('discount_percent', '>', 0)
                    ->update(['discount_mode' => 'percent']);

                \Illuminate\Support\Facades\DB::connection('tenant')
                    ->table('invoices')
                    ->where('discount_percent', '<=', 0)
                    ->where('discount_amount', '>', 0)
                    ->update(['discount_mode' => 'amount']);
            }
        }

        if (Schema::connection('tenant')->hasTable('invoice_lines')
            && !Schema::connection('tenant')->hasColumn('invoice_lines', 'line_discount_mode')) {
            Schema::connection('tenant')->table('invoice_lines', function (Blueprint $table) {
                $table->string('line_discount_mode', 10)->default('amount')->after('line_discount');
                $table->decimal('line_discount_input', 12, 4)->nullable()->after('line_discount_mode');
            });

            if (Schema::connection('tenant')->hasTable('quotation_lines')
                && Schema::connection('tenant')->hasColumn('quotation_lines', 'line_discount_mode')) {
                $invoiceIds = \Illuminate\Support\Facades\DB::connection('tenant')
                    ->table('invoices')
                    ->whereNotNull('quotation_id')
                    ->pluck('quotation_id', 'id');

                foreach ($invoiceIds as $invoiceId => $quotationId) {
                    $quotationLines = \Illuminate\Support\Facades\DB::connection('tenant')
                        ->table('quotation_lines')
                        ->where('quotation_id', $quotationId)
                        ->orderBy('id')
                        ->get();
                    $invoiceLines = \Illuminate\Support\Facades\DB::connection('tenant')
                        ->table('invoice_lines')
                        ->where('invoice_id', $invoiceId)
                        ->orderBy('id')
                        ->get();

                    foreach ($invoiceLines as $index => $invoiceLine) {
                        $quotationLine = $quotationLines[$index] ?? null;
                        if (!$quotationLine) {
                            continue;
                        }

                        \Illuminate\Support\Facades\DB::connection('tenant')
                            ->table('invoice_lines')
                            ->where('id', $invoiceLine->id)
                            ->update([
                                'line_discount_mode' => $quotationLine->line_discount_mode ?? 'amount',
                                'line_discount_input' => $quotationLine->line_discount_input,
                            ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasColumn('invoices', 'discount_mode')) {
            Schema::connection('tenant')->table('invoices', function (Blueprint $table) {
                $table->dropColumn('discount_mode');
            });
        }

        if (Schema::connection('tenant')->hasColumn('invoice_lines', 'line_discount_input')) {
            Schema::connection('tenant')->table('invoice_lines', function (Blueprint $table) {
                $table->dropColumn(['line_discount_input', 'line_discount_mode']);
            });
        }
    }
};
