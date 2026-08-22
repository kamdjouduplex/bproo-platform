<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('school_courses')) {
            $schema->create('school_courses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
                $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('school_subjects')->cascadeOnDelete();
                $table->foreignId('teacher_id')->constrained('school_teachers')->restrictOnDelete();
                $table->string('room', 80)->nullable();
                $table->string('color', 16)->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->unique(
                    ['academic_year_id', 'class_id', 'subject_id'],
                    'school_courses_year_class_subject_unique'
                );
                $table->index(['teacher_id', 'academic_year_id'], 'school_courses_teacher_year_idx');
            });
        }

        if (! $schema->hasTable('school_timetable_slots')) {
            $schema->create('school_timetable_slots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained('school_courses')->cascadeOnDelete();
                $table->unsignedTinyInteger('weekday'); // 1 = lundi … 6 = samedi
                $table->time('start_time');
                $table->time('end_time');
                $table->string('room', 80)->nullable();
                $table->timestamps();

                $table->index(['weekday', 'start_time'], 'school_tt_weekday_start_idx');
            });
        }

        if ($schema->hasTable('school_attendance_records')) {
            $schema->table('school_attendance_records', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('school_attendance_records', 'course_id')) {
                    $table->foreignId('course_id')->nullable()->after('class_id')->constrained('school_courses')->nullOnDelete();
                }
                if (! $schema->hasColumn('school_attendance_records', 'timetable_slot_id')) {
                    $table->foreignId('timetable_slot_id')->nullable()->after('course_id')->constrained('school_timetable_slots')->nullOnDelete();
                }
            });

            try {
                $schema->table('school_attendance_records', function (Blueprint $table) {
                    $table->dropUnique('school_attendance_student_day_unique');
                });
            } catch (\Throwable) {
            }

            try {
                $schema->table('school_attendance_records', function (Blueprint $table) {
                    $table->unique(
                        ['student_id', 'attendance_date', 'timetable_slot_id'],
                        'school_att_student_slot_day_unique'
                    );
                });
            } catch (\Throwable) {
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('school_attendance_records')) {
            try {
                $schema->table('school_attendance_records', function (Blueprint $table) {
                    $table->dropUnique('school_att_student_slot_day_unique');
                });
            } catch (\Throwable) {
            }

            $schema->table('school_attendance_records', function (Blueprint $table) use ($schema) {
                if ($schema->hasColumn('school_attendance_records', 'timetable_slot_id')) {
                    $table->dropConstrainedForeignId('timetable_slot_id');
                }
                if ($schema->hasColumn('school_attendance_records', 'course_id')) {
                    $table->dropConstrainedForeignId('course_id');
                }
            });
        }

        $schema->dropIfExists('school_timetable_slots');
        $schema->dropIfExists('school_courses');
    }
};
