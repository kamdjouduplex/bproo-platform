<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('school_rooms')) {
            $schema->create('school_rooms', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80);
                $table->unsignedSmallInteger('capacity')->nullable();
                $table->string('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique('name', 'school_rooms_name_unique');
            });
        }

        if ($schema->hasTable('school_courses') && ! $schema->hasColumn('school_courses', 'room_id')) {
            $schema->table('school_courses', function (Blueprint $table) {
                $table->foreignId('room_id')->nullable()->after('teacher_id')->constrained('school_rooms')->nullOnDelete();
            });
        }

        if ($schema->hasTable('school_timetable_slots') && ! $schema->hasColumn('school_timetable_slots', 'room_id')) {
            $schema->table('school_timetable_slots', function (Blueprint $table) {
                $table->foreignId('room_id')->nullable()->after('end_time')->constrained('school_rooms')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('school_timetable_slots') && $schema->hasColumn('school_timetable_slots', 'room_id')) {
            $schema->table('school_timetable_slots', function (Blueprint $table) {
                $table->dropConstrainedForeignId('room_id');
            });
        }

        if ($schema->hasTable('school_courses') && $schema->hasColumn('school_courses', 'room_id')) {
            $schema->table('school_courses', function (Blueprint $table) {
                $table->dropConstrainedForeignId('room_id');
            });
        }

        $schema->dropIfExists('school_rooms');
    }
};
