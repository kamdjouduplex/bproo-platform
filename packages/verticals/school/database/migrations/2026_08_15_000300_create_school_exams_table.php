<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('school_subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('school_teachers')->nullOnDelete();
            $table->string('title');
            $table->date('exam_date')->nullable();
            $table->decimal('max_score', 8, 2)->default(20);
            $table->decimal('coefficient', 8, 2)->default(1);
            $table->string('status')->default('draft'); // draft | open | closed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'class_id', 'subject_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_exams');
    }
};
