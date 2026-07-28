<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('type');                    // annual | sick | maternity | paternity | unpaid | other
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days_count');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('leaves');
    }
};
