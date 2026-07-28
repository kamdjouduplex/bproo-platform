<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->date('attendance_date');
            $table->timestamp('punched_at');
            $table->string('source', 32)->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['attendance_date', 'user_id']);
            $table->index(['attendance_date', 'employee_id']);
            $table->index('punched_at');
        });

        if (Schema::connection('tenant')->hasTable('employees')) {
            Schema::connection('tenant')->table('attendance_punches', function (Blueprint $table) {
                $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('attendance_punches');
    }
};
