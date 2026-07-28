<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('debts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->decimal('total_amount', 12, 2);
            $table->decimal('balance', 12, 2);
            $table->date('due_date')->nullable();
            $table->date('opened_at');
            $table->enum('status', ['open', 'partial', 'paid', 'overdue'])->default('open');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('reference');
            $table->index('client_id');
            $table->index('due_date');
            $table->index('status');
            $table->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('debts');
    }
};
