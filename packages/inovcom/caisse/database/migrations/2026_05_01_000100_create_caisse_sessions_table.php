<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('caisse_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_number')->unique();
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->timestamp('opened_at');
            $table->decimal('opening_amount', 15, 2)->default(0);
            $table->string('status', 16)->default('open')->index(); // open|closed
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('closing_amount_expected', 15, 2)->nullable();
            $table->decimal('closing_amount_counted', 15, 2)->nullable();
            $table->string('close_note', 255)->nullable();
            $table->timestamps();

            $table->index(['opened_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('caisse_sessions');
    }
};
