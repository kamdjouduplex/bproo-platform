<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('school_students')) {
            return;
        }

        Schema::connection('tenant')->table('school_students', function (Blueprint $table) {
            if (! Schema::connection('tenant')->hasColumn('school_students', 'nisu')) {
                $table->string('nisu', 80)->nullable()->after('student_code');
                $table->unique('nisu');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('tenant')->hasTable('school_students')) {
            return;
        }

        Schema::connection('tenant')->table('school_students', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('school_students', 'nisu')) {
                $table->dropUnique(['nisu']);
                $table->dropColumn('nisu');
            }
        });
    }
};
