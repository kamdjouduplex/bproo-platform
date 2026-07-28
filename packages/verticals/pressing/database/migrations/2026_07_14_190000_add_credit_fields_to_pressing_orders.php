<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('pressing_orders')) {
            return;
        }

        Schema::connection('tenant')->table('pressing_orders', function (Blueprint $table) {
            if (! Schema::connection('tenant')->hasColumn('pressing_orders', 'credit_status')) {
                $table->string('credit_status', 20)->nullable()->after('balance');
                $table->decimal('credit_amount', 15, 2)->nullable()->after('credit_status');
                $table->text('credit_notes')->nullable()->after('credit_amount');
                $table->unsignedBigInteger('credit_requested_by')->nullable()->after('credit_notes');
                $table->timestamp('credit_requested_at')->nullable()->after('credit_requested_by');
                $table->unsignedBigInteger('credit_validated_by')->nullable()->after('credit_requested_at');
                $table->timestamp('credit_validated_at')->nullable()->after('credit_validated_by');
                $table->text('credit_rejection_reason')->nullable()->after('credit_validated_at');
                $table->index('credit_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('tenant')->hasTable('pressing_orders')) {
            return;
        }

        Schema::connection('tenant')->table('pressing_orders', function (Blueprint $table) {
            foreach ([
                'credit_status', 'credit_amount', 'credit_notes',
                'credit_requested_by', 'credit_requested_at',
                'credit_validated_by', 'credit_validated_at',
                'credit_rejection_reason',
            ] as $col) {
                if (Schema::connection('tenant')->hasColumn('pressing_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
