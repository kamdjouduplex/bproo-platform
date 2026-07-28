<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('loss_records', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('loss_reason_id')->constrained('loss_reasons')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('value', 12, 2)->default(0);
            $table->date('loss_date');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'confirmed'])->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index('reference');
            $table->index('item_id');
            $table->index('loss_reason_id');
            $table->index('loss_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('loss_records');
    }
};
