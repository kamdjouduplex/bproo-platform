<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_grading_systems', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->decimal('scale_base', 8, 2)->default(20); // e.g. /20
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('school_grade_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_system_id')->constrained('school_grading_systems')->cascadeOnDelete();
            $table->string('label'); // A, B, Passable…
            $table->decimal('min_percent', 5, 2); // note min on scale_base (ex. 16 / 20)
            $table->decimal('max_percent', 5, 2); // note max on scale_base (ex. 20 / 20)
            $table->boolean('is_pass')->default(true);
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();

            $table->index(['grading_system_id', 'sort_order']);
        });

        Schema::connection('tenant')->create('school_subject_coefficients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('school_subjects')->cascadeOnDelete();
            $table->decimal('coefficient', 8, 2)->default(1);
            $table->timestamps();

            $table->unique(['academic_year_id', 'class_id', 'subject_id'], 'school_subj_coeff_unique');
        });

        Schema::connection('tenant')->create('school_grading_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('grading_system_id')->nullable()->constrained('school_grading_systems')->nullOnDelete();
            $table->decimal('pass_mark', 8, 2)->default(10); // on scale_base
            $table->decimal('promotion_average', 8, 2)->default(10);
            $table->unsignedInteger('max_failed_subjects')->nullable();
            $table->string('ranking_method')->default('weighted_average'); // weighted_average | simple_average
            $table->boolean('absent_counts_as_zero')->default(true);
            $table->boolean('require_validated_marks')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['academic_year_id', 'class_id'], 'school_grading_rules_year_class_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_grading_rules');
        Schema::connection('tenant')->dropIfExists('school_subject_coefficients');
        Schema::connection('tenant')->dropIfExists('school_grade_scales');
        Schema::connection('tenant')->dropIfExists('school_grading_systems');
    }
};
