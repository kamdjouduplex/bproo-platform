<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');
        if (! $schema->hasTable('school_exams')) {
            return;
        }

        $schema->table('school_exams', function (Blueprint $table) {
            if (! Schema::connection('tenant')->hasColumn('school_exams', 'kind')) {
                $table->string('kind', 80)->nullable()->after('title');
            }
            if (! Schema::connection('tenant')->hasColumn('school_exams', 'period')) {
                $table->string('period', 80)->nullable()->after('kind');
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');
        if (! $schema->hasTable('school_exams')) {
            return;
        }

        $schema->table('school_exams', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('school_exams', 'period')) {
                $table->dropColumn('period');
            }
            if (Schema::connection('tenant')->hasColumn('school_exams', 'kind')) {
                $table->dropColumn('kind');
            }
        });
    }
};
