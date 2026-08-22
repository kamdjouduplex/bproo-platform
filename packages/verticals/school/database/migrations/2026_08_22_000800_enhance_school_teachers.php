<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('school_teachers', function (Blueprint $table) {
            $table->string('teacher_code', 40)->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('gender', 16)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('education_level', 80)->nullable();
            $table->string('diploma_kind', 40)->nullable();
            $table->string('diploma_label')->nullable();
            $table->string('studies_in_progress')->nullable();
            $table->string('teaching_section', 80)->nullable();
            $table->string('schedule_note')->nullable();
            $table->decimal('remuneration_amount', 12, 2)->nullable();
            $table->string('profile_status', 20)->default('draft');
            $table->timestamp('validated_at')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
        });

        $seq = 1;
        $year = (int) date('Y');
        $rows = DB::connection('tenant')->table('school_teachers')->orderBy('id')->get();
        foreach ($rows as $row) {
            $code = sprintf('ENS-%d-%04d', $year, $seq++);
            $full = trim((string) $row->full_name);
            $parts = preg_split('/\s+/', $full, 2) ?: [];
            $first = $parts[0] ?? $full;
            $last = $parts[1] ?? ($parts[0] ?? 'Enseignant');

            DB::connection('tenant')->table('school_teachers')->where('id', $row->id)->update([
                'teacher_code' => $code,
                'first_name' => $first !== '' ? $first : 'Enseignant',
                'last_name' => $last !== '' ? $last : $first,
                'profile_status' => 'draft',
            ]);
        }

        Schema::connection('tenant')->table('school_teachers', function (Blueprint $table) {
            $table->unique('teacher_code');
            $table->unique('user_id');
        });

        Schema::connection('tenant')->create('school_teacher_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('school_teachers')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('school_subjects')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['teacher_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_teacher_subject');

        Schema::connection('tenant')->table('school_teachers', function (Blueprint $table) {
            $table->dropUnique(['teacher_code']);
            $table->dropUnique(['user_id']);
            $table->dropColumn([
                'teacher_code',
                'first_name',
                'last_name',
                'gender',
                'photo_path',
                'education_level',
                'diploma_kind',
                'diploma_label',
                'studies_in_progress',
                'teaching_section',
                'schedule_note',
                'remuneration_amount',
                'profile_status',
                'validated_at',
                'validated_by',
                'user_id',
            ]);
        });
    }
};
