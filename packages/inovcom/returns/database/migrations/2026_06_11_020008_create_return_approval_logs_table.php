<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('tenant');
        if ($conn->hasTable('return_approval_logs')) {
            return;
        }

        $conn->create('return_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type', 60);   // return|credit_note|refund
            $table->unsignedBigInteger('approvable_id');
            $table->string('decision', 20);          // approved|rejected
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id']);
            $table->index('decided_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('return_approval_logs');
    }
};
