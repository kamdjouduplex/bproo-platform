<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('debt_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained('debts')->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('amount_due', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('debt_id');
            $table->index('due_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('debt_schedules');
    }
};
