<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('audit_logs')) {
            Schema::connection('tenant')->create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('auditable_type', 100);
                $table->unsignedBigInteger('auditable_id');
                $table->string('event', 100);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['auditable_type', 'auditable_id'], 'idx_school_audit_auditable');
                $table->index('user_id', 'idx_school_audit_user');
                $table->index('created_at', 'idx_school_audit_created');
            });
        }

        if (Schema::connection('tenant')->hasTable('users')
            && ! Schema::connection('tenant')->hasColumn('users', 'preferred_locale')) {
            Schema::connection('tenant')->table('users', function (Blueprint $table) {
                $table->string('preferred_locale', 10)->nullable()->after('email');
            });
        }

        Schema::connection('tenant')->create('school_student_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('school_students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('entry_type'); // debit | credit
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2)->default(0);
            $table->string('label');
            $table->string('source_type')->nullable(); // payment | fee | adjustment
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'academic_year_id'], 'school_ledger_student_year');
            $table->index(['source_type', 'source_id'], 'school_ledger_source');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_student_ledger_entries');

        if (Schema::connection('tenant')->hasTable('users')
            && Schema::connection('tenant')->hasColumn('users', 'preferred_locale')) {
            Schema::connection('tenant')->table('users', function (Blueprint $table) {
                $table->dropColumn('preferred_locale');
            });
        }
        // Keep audit_logs if other modules may use it
    }
};
