<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_exam_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('school_exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('school_students')->cascadeOnDelete();
            $table->decimal('score', 8, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('remarks')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_exam_marks');
    }
};
