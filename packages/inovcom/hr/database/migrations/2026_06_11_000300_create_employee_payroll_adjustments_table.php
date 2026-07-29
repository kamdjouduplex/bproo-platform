<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('employee_payroll_adjustments')) {
            return;
        }

        Schema::connection('tenant')->create('employee_payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('type', 32);
            $table->decimal('days', 6, 2)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('label', 255);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'period_start', 'period_end']);
            $table->index(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('employee_payroll_adjustments');
    }
};
