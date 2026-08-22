<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('school_students')) {
            Schema::connection('tenant')->table('school_students', function (Blueprint $table) {
                if (! Schema::connection('tenant')->hasColumn('school_students', 'birth_place')) {
                    $table->string('birth_place')->nullable()->after('birth_date');
                }
                if (! Schema::connection('tenant')->hasColumn('school_students', 'address')) {
                    $table->string('address')->nullable()->after('birth_place');
                }
                if (! Schema::connection('tenant')->hasColumn('school_students', 'parent_relationship')) {
                    $table->string('parent_relationship')->nullable()->after('parent_email');
                }
                if (! Schema::connection('tenant')->hasColumn('school_students', 'emergency_contact_name')) {
                    $table->string('emergency_contact_name')->nullable()->after('parent_relationship');
                }
                if (! Schema::connection('tenant')->hasColumn('school_students', 'emergency_contact_phone')) {
                    $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
                }
                if (! Schema::connection('tenant')->hasColumn('school_students', 'previous_school')) {
                    $table->string('previous_school')->nullable()->after('emergency_contact_phone');
                }
            });
        }

        if (! Schema::connection('tenant')->hasTable('school_settings')) {
            Schema::connection('tenant')->create('school_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::connection('tenant')->hasTable('school_attendance_records')) {
            Schema::connection('tenant')->create('school_attendance_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
                $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('school_students')->cascadeOnDelete();
                $table->date('attendance_date');
                $table->string('status', 40); // present | absent | late | excused
                $table->string('remark')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['student_id', 'class_id', 'attendance_date'],
                    'school_attendance_student_day_unique'
                );
                $table->index(['academic_year_id', 'class_id', 'attendance_date'], 'school_attendance_class_day_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_attendance_records');
        Schema::connection('tenant')->dropIfExists('school_settings');

        if (Schema::connection('tenant')->hasTable('school_students')) {
            Schema::connection('tenant')->table('school_students', function (Blueprint $table) {
                foreach ([
                    'birth_place', 'address', 'parent_relationship',
                    'emergency_contact_name', 'emergency_contact_phone', 'previous_school',
                ] as $column) {
                    if (Schema::connection('tenant')->hasColumn('school_students', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
