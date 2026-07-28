<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('tenant');
        if ($conn->hasTable('return_attachments')) {
            return;
        }

        $conn->create('return_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->string('type', 30)->default('photo'); // photo|document
            $table->string('label', 255)->nullable();
            $table->string('path', 500);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index('return_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('return_attachments');
    }
};
