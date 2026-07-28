<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique(); // Ex: VTE-2026-000001
            $table->date('sale_date');
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('price_tier')->default('retail'); // retail, semi_wholesale, wholesale
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->nullable(); // If discount is percentage
            $table->decimal('total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('sales');
    }
};
