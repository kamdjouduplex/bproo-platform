<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('student_id_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('school_students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();

            $table->string('batch_code')->nullable(); // used for batch cards in PRD
            $table->string('qr_token')->nullable()->unique();
            $table->string('barcode_data')->nullable()->unique(); // textual barcode data (rendering later)

            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('student_id_cards');
    }
};

