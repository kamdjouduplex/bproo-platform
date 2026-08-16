<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('school_students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();

            $table->string('payment_type')->default('bank'); // bank | onsite
            $table->string('currency_code')->default('XOF');
            $table->decimal('amount', 14, 2);

            $table->string('status')->default('received'); // received | verified | rejected...
            $table->timestamp('paid_at')->nullable();

            $table->text('reference')->nullable(); // bank reference / receipt ref
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_payments');
    }
};

