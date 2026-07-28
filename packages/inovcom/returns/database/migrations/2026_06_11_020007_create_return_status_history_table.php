<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('tenant');
        if ($conn->hasTable('return_status_history')) {
            return;
        }

        $conn->create('return_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->index('return_id');
            $table->index('performed_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('return_status_history');
    }
};
