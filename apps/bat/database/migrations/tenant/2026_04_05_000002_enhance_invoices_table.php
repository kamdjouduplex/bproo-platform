<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('invoices', function (Blueprint $table) {
            // Financial (some may already exist in the original create migration)
            if (!Schema::connection('tenant')->hasColumn('invoices', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('invoices', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('invoices', 'discount_amount')) {
                $table->decimal('discount_amount', 15, 2)->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('invoices', 'net_ht')) {
                $table->decimal('net_ht', 15, 2)->default(0);
            }
            // tax_amount and total_ttc already exist in the original create migration
            if (!Schema::connection('tenant')->hasColumn('invoices', 'currency')) {
                $table->string('currency', 3)->default('XOF');
            }

            // Payment tracking
            if (!Schema::connection('tenant')->hasColumn('invoices', 'payment_terms')) {
                $table->unsignedInteger('payment_terms')->default(30);
            }
            if (!Schema::connection('tenant')->hasColumn('invoices', 'payment_method')) {
                $table->string('payment_method', 50)->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('invoices', 'bank_details')) {
                $table->text('bank_details')->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('invoices', 'amount_paid')) {
                $table->decimal('amount_paid', 15, 2)->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('invoices', 'amount_due')) {
                $table->decimal('amount_due', 15, 2)->default(0);
            }

            // Invoice type
            if (!Schema::connection('tenant')->hasColumn('invoices', 'invoice_type')) {
                $table->string('invoice_type', 50)->default('invoice');
            }
            if (!Schema::connection('tenant')->hasColumn('invoices', 'credit_note_for')) {
                $table->unsignedBigInteger('credit_note_for')->nullable();
            }

            // Workflow timestamps
            if (!Schema::connection('tenant')->hasColumn('invoices', 'sent_at')) {
                $table->timestamp('sent_at')->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('invoices', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('invoices', 'overdue_at')) {
                $table->timestamp('overdue_at')->nullable();
            }

            // Dunning
            if (!Schema::connection('tenant')->hasColumn('invoices', 'reminder_count')) {
                $table->unsignedSmallInteger('reminder_count')->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('invoices', 'last_reminder_at')) {
                $table->timestamp('last_reminder_at')->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('invoices', 'internal_notes')) {
                $table->text('internal_notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('invoices', function (Blueprint $table) {
            foreach ([
                'tax_rate', 'discount_percent', 'discount_amount', 'net_ht', 'currency',
                'payment_terms', 'payment_method', 'bank_details', 'amount_paid', 'amount_due',
                'invoice_type', 'credit_note_for', 'sent_at', 'paid_at', 'overdue_at',
                'reminder_count', 'last_reminder_at', 'internal_notes',
            ] as $col) {
                if (Schema::connection('tenant')->hasColumn('invoices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
