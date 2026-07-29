<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('client_reminders')) {
            return;
        }

        Schema::connection('tenant')->create('client_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->unsignedSmallInteger('level')->default(1); // 1=rappel, 2=mise en demeure, 3=contentieux
            $table->string('channel', 20)->default('phone');   // phone, sms, email, whatsapp, visit
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 20)->default('pending');  // pending, sent, resolved, escalated
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('client_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('client_reminders');
    }
};
