<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('batch_number');
            $table->date('expiry_date');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->timestamp('received_at')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'expiry_date']);
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('batches');
    }
};
