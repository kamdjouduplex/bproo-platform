<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('payslips', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // PAY00001
            $table->unsignedBigInteger('employee_id');
            $table->unsignedTinyInteger('period_month'); // 1–12
            $table->unsignedSmallInteger('period_year');
            $table->decimal('base_salary', 14, 2);
            $table->json('additions')->nullable();     // [{label, amount}]
            $table->json('deductions')->nullable();    // [{label, amount}]
            $table->decimal('gross_salary', 14, 2)->default(0);
            $table->decimal('net_salary', 14, 2)->default(0);
            $table->string('status')->default('draft'); // draft | validated | paid
            $table->date('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payslips');
    }
};
