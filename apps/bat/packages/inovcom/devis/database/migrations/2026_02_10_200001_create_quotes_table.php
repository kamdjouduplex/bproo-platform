<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('offer_id')->nullable();
            $table->string('title');
            $table->string('status')->default('draft'); // draft | sent | accepted | refused
            $table->date('valid_until')->nullable();
            $table->decimal('total_ht', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
