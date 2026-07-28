<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('tenant');
        if ($conn->hasTable('returns')) {
            return;
        }

        $conn->create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 32)->unique();        // RET-2026-000001
            $table->unsignedBigInteger('client_id');
            $table->string('source_type', 20)->default('invoice'); // invoice|sale
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_number', 40)->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('type', 20)->default('partial');
            $table->string('resolution_type', 20)->nullable();
            $table->date('return_date');
            $table->decimal('subtotal_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('reason_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->unsignedBigInteger('inspected_by')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->index('return_date');
            $table->index('status');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('returns');
    }
};
