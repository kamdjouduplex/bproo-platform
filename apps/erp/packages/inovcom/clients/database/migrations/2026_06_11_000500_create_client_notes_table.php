<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('client_notes')) {
            return;
        }

        Schema::connection('tenant')->create('client_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->text('body');
            // note, call, meeting, reminder, system
            $table->string('type', 20)->default('note');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamps();

            $table->index('client_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('client_notes');
    }
};
