<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_code', 50);
            $table->string('title', 255);
            $table->string('category', 100)->nullable(); // contract | plan | permit | photo | report | other
            $table->text('description')->nullable();
            $table->string('filename', 255);             // original uploaded filename
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            $table->string('storage_path', 500);         // local path or S3 key
            $table->string('disk', 50)->default('local'); // local | s3
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('parent_id')->nullable(); // previous version
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('documents')->nullOnDelete();
            $table->index(['tenant_code', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
