<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('caisse_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caisse_session_id')->nullable()->index();
            $table->timestamp('entry_date')->index();
            $table->string('entry_type', 64)->index();
            $table->string('direction', 8)->index(); // in|out
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reason', 255);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 100)->nullable()->index();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('caisse_session_id')
                ->references('id')
                ->on('caisse_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('caisse_entries');
    }
};
