<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasColumn('invoices', 'discount_mode')) {
            return;
        }

        DB::connection('tenant')
            ->table('invoices')
            ->where('discount_percent', '>', 0)
            ->update(['discount_mode' => 'percent']);

        DB::connection('tenant')
            ->table('invoices')
            ->where('discount_percent', '<=', 0)
            ->where('discount_amount', '>', 0)
            ->update(['discount_mode' => 'amount']);

        if (!Schema::connection('tenant')->hasColumn('invoice_lines', 'line_discount_mode')
            || !Schema::connection('tenant')->hasColumn('quotation_lines', 'line_discount_mode')) {
            return;
        }

        $invoiceIds = DB::connection('tenant')
            ->table('invoices')
            ->whereNotNull('quotation_id')
            ->pluck('quotation_id', 'id');

        foreach ($invoiceIds as $invoiceId => $quotationId) {
            $quotationLines = DB::connection('tenant')
                ->table('quotation_lines')
                ->where('quotation_id', $quotationId)
                ->orderBy('id')
                ->get();
            $invoiceLines = DB::connection('tenant')
                ->table('invoice_lines')
                ->where('invoice_id', $invoiceId)
                ->orderBy('id')
                ->get();

            foreach ($invoiceLines as $index => $invoiceLine) {
                $quotationLine = $quotationLines[$index] ?? null;
                if (!$quotationLine) {
                    continue;
                }

                DB::connection('tenant')
                    ->table('invoice_lines')
                    ->where('id', $invoiceLine->id)
                    ->update([
                        'line_discount_mode' => $quotationLine->line_discount_mode ?? 'amount',
                        'line_discount_input' => $quotationLine->line_discount_input,
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Données de correction — pas de rollback.
    }
};
