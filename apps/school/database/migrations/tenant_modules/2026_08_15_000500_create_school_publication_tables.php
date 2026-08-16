<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_publication_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->boolean('require_fees_paid')->default(false);
            $table->decimal('min_fees_amount', 12, 2)->nullable();
            $table->boolean('require_validated_marks')->default(true);
            $table->boolean('require_director_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('school_result_publications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('publication_rule_id')->nullable()->constrained('school_publication_rules')->nullOnDelete();
            $table->string('status')->default('draft'); // draft | pending_approval | published | rejected
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'status']);
        });

        Schema::connection('tenant')->create('school_result_publication_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained('school_result_publications')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('school_students')->cascadeOnDelete();
            $table->decimal('average', 8, 2)->nullable();
            $table->string('grade_label')->nullable();
            $table->boolean('passed')->default(false);
            $table->boolean('promoted')->default(false);
            $table->boolean('eligible')->default(false);
            $table->boolean('is_published')->default(false);
            $table->json('checks')->nullable();
            $table->text('blocked_reasons')->nullable();
            $table->timestamps();

            $table->unique(['publication_id', 'student_id'], 'school_pub_line_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_result_publication_lines');
        Schema::connection('tenant')->dropIfExists('school_result_publications');
        Schema::connection('tenant')->dropIfExists('school_publication_rules');
    }
};
