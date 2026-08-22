<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('school_student_documents')) {
            $schema->create('school_student_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('school_students')->cascadeOnDelete();
                $table->string('type', 80);
                $table->string('title', 160)->nullable();
                $table->string('file_path');
                $table->string('original_name')->nullable();
                $table->string('mime', 120)->nullable();
                $table->unsignedInteger('size_bytes')->nullable();
                $table->date('issued_on')->nullable();
                $table->string('notes')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->index(['student_id', 'type'], 'school_docs_student_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_student_documents');
    }
};
