<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('tenant');
        if ($conn->hasTable('return_comments')) {
            return;
        }

        $conn->create('return_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamps();

            $table->index('return_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('return_comments');
    }
};
