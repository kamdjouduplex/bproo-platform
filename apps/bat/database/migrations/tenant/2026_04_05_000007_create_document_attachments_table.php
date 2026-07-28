<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->string('attachable_type', 100); // quote | project | maintenance_order | intervention | offer | invoice
            $table->unsignedBigInteger('attachable_id');
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->index(['attachable_type', 'attachable_id'], 'idx_doc_attachments');
            $table->unique(['document_id', 'attachable_type', 'attachable_id'], 'unique_doc_attachment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_attachments');
    }
};
