<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('tenant');
        if ($conn->hasTable('credit_notes')) {
            return;
        }

        $conn->create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_number', 32)->unique();   // AV-2026-000001
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('return_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('status', 20)->default('draft');
            $table->date('issue_date');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('used_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index('invoice_id');
            $table->index('return_id');
            $table->index('issue_date');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('credit_notes');
    }
};
