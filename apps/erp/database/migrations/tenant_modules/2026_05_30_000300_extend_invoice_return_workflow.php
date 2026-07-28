<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('invoices')) {
            Schema::connection('tenant')->table('invoices', function (Blueprint $table) {
                if (!Schema::connection('tenant')->hasColumn('invoices', 'document_type')) {
                    $table->string('document_type', 32)->default('standard')->after('status');
                }
                if (!Schema::connection('tenant')->hasColumn('invoices', 'source_invoice_id')) {
                    $table->unsignedBigInteger('source_invoice_id')->nullable()->after('document_type');
                    $table->index('source_invoice_id');
                }
                if (!Schema::connection('tenant')->hasColumn('invoices', 'invoice_return_id')) {
                    $table->unsignedBigInteger('invoice_return_id')->nullable()->after('source_invoice_id');
                    $table->index('invoice_return_id');
                }
            });
        }

        if (Schema::connection('tenant')->hasTable('invoice_returns')) {
            Schema::connection('tenant')->table('invoice_returns', function (Blueprint $table) {
                if (!Schema::connection('tenant')->hasColumn('invoice_returns', 'process_type')) {
                    $table->string('process_type', 32)->default('simple')->after('type');
                }
                if (!Schema::connection('tenant')->hasColumn('invoice_returns', 'workflow_step')) {
                    $table->string('workflow_step', 32)->default('setup')->after('process_type');
                }
                if (!Schema::connection('tenant')->hasColumn('invoice_returns', 'cancellation_invoice_id')) {
                    $table->unsignedBigInteger('cancellation_invoice_id')->nullable()->after('workflow_step');
                }
                if (!Schema::connection('tenant')->hasColumn('invoice_returns', 'replacement_invoice_id')) {
                    $table->unsignedBigInteger('replacement_invoice_id')->nullable()->after('cancellation_invoice_id');
                }
                if (!Schema::connection('tenant')->hasColumn('invoice_returns', 'cancellation_confirmed_at')) {
                    $table->timestamp('cancellation_confirmed_at')->nullable();
                }
                if (!Schema::connection('tenant')->hasColumn('invoice_returns', 'replacement_confirmed_at')) {
                    $table->timestamp('replacement_confirmed_at')->nullable();
                }
                if (!Schema::connection('tenant')->hasColumn('invoice_returns', 'loss_confirmed_at')) {
                    $table->timestamp('loss_confirmed_at')->nullable();
                }
            });
        }

        if (Schema::connection('tenant')->hasTable('invoice_return_lines')) {
            Schema::connection('tenant')->table('invoice_return_lines', function (Blueprint $table) {
                if (!Schema::connection('tenant')->hasColumn('invoice_return_lines', 'is_defective')) {
                    $table->boolean('is_defective')->default(false)->after('quantity');
                }
            });
        }

        if (Schema::connection('tenant')->hasTable('loss_records')) {
            Schema::connection('tenant')->table('loss_records', function (Blueprint $table) {
                if (!Schema::connection('tenant')->hasColumn('loss_records', 'invoice_return_id')) {
                    $table->unsignedBigInteger('invoice_return_id')->nullable()->after('description');
                    $table->index('invoice_return_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('loss_records')
            && Schema::connection('tenant')->hasColumn('loss_records', 'invoice_return_id')) {
            Schema::connection('tenant')->table('loss_records', function (Blueprint $table) {
                $table->dropIndex(['invoice_return_id']);
                $table->dropColumn('invoice_return_id');
            });
        }

        if (Schema::connection('tenant')->hasTable('invoice_return_lines')
            && Schema::connection('tenant')->hasColumn('invoice_return_lines', 'is_defective')) {
            Schema::connection('tenant')->table('invoice_return_lines', function (Blueprint $table) {
                $table->dropColumn('is_defective');
            });
        }

        if (Schema::connection('tenant')->hasTable('invoice_returns')) {
            Schema::connection('tenant')->table('invoice_returns', function (Blueprint $table) {
                foreach ([
                    'process_type', 'workflow_step', 'cancellation_invoice_id', 'replacement_invoice_id',
                    'cancellation_confirmed_at', 'replacement_confirmed_at', 'loss_confirmed_at',
                ] as $col) {
                    if (Schema::connection('tenant')->hasColumn('invoice_returns', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::connection('tenant')->hasTable('invoices')) {
            Schema::connection('tenant')->table('invoices', function (Blueprint $table) {
                foreach (['document_type', 'source_invoice_id', 'invoice_return_id'] as $col) {
                    if (Schema::connection('tenant')->hasColumn('invoices', $col)) {
                        if ($col !== 'document_type') {
                            $table->dropIndex([$col]);
                        }
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
