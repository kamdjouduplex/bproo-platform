<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('tenant');
        if ($conn->hasTable('customer_credits')) {
            return;
        }

        // Ledger du portefeuille (wallet) client : le solde = somme signée des écritures.
        $conn->create('customer_credits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('direction', 8);            // credit (+) | debit (-)
            $table->decimal('amount', 15, 2);          // toujours positif
            $table->string('source_type', 30);         // credit_note|refund|sale_payment|adjustment
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('balance_after', 15, 2);   // cache de perf
            $table->string('reference', 40)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'id']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('customer_credits');
    }
};
