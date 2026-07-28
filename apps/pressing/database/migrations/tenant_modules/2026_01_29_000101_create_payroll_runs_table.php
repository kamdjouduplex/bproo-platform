<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'processed', 'paid'])->default('draft');
            $table->decimal('total_gross', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('reference');
            $table->index(['period_start', 'period_end']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payroll_runs');
    }
};
