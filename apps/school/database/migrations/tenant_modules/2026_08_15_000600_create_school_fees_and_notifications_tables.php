<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_fee_structures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 10)->default('XOF');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'class_id']);
        });

        Schema::connection('tenant')->create('school_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('school_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event');
            $table->string('channel'); // sms | email | system
            $table->string('status'); // sent | failed | skipped
            $table->foreignId('student_id')->nullable()->constrained('school_students')->nullOnDelete();
            $table->string('recipient')->nullable();
            $table->text('message')->nullable();
            $table->text('error')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_notification_logs');
        Schema::connection('tenant')->dropIfExists('school_notification_settings');
        Schema::connection('tenant')->dropIfExists('school_fee_structures');
    }
};
