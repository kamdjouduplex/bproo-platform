<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('invoice_payments')) {
            return;
        }

        Schema::connection('tenant')->table('invoice_payments', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('invoice_payments', 'status')) {
                $table->string('status', 20)->default('active')->after('amount');
            }
            if (!Schema::connection('tenant')->hasColumn('invoice_payments', 'amount_paid_before')) {
                $table->decimal('amount_paid_before', 15, 2)->nullable()->after('status');
            }
            if (!Schema::connection('tenant')->hasColumn('invoice_payments', 'balance_after')) {
                $table->decimal('balance_after', 15, 2)->nullable()->after('amount_paid_before');
            }
            if (!Schema::connection('tenant')->hasColumn('invoice_payments', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('created_by');
            }
            if (!Schema::connection('tenant')->hasColumn('invoice_payments', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            }
            if (!Schema::connection('tenant')->hasColumn('invoice_payments', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            }
        });

        $this->backfillSnapshots();
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('invoice_payments')) {
            return;
        }

        Schema::connection('tenant')->table('invoice_payments', function (Blueprint $table) {
            foreach (['status', 'amount_paid_before', 'balance_after', 'cancelled_at', 'cancelled_by', 'cancellation_reason'] as $col) {
                if (Schema::connection('tenant')->hasColumn('invoice_payments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function backfillSnapshots(): void
    {
        if (!Schema::connection('tenant')->hasColumn('invoice_payments', 'balance_after')) {
            return;
        }

        $byInvoice = DB::connection('tenant')->table('invoice_payments')
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get()
            ->groupBy('invoice_id');

        foreach ($byInvoice as $invoiceId => $payments) {
            $invoice = DB::connection('tenant')->table('invoices')->where('id', $invoiceId)->first();
            if (!$invoice) {
                continue;
            }

            $running = 0.0;
            $total = (float) $invoice->total;

            foreach ($payments as $p) {
                $amount = (float) $p->amount;
                $before = $running;
                $running = round($running + $amount, 2);
                $balanceAfter = round($total - $running, 2);

                DB::connection('tenant')->table('invoice_payments')
                    ->where('id', $p->id)
                    ->update([
                        'status' => 'active',
                        'amount_paid_before' => $before,
                        'balance_after' => $balanceAfter,
                    ]);
            }
        }
    }
};
