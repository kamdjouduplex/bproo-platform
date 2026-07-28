<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('pressing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('pressing_orders')->cascadeOnDelete();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->string('method'); // cash, mobile_money, card, transfer
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'paid_at']);
            $table->index('agence_id');
        });

        Schema::connection('tenant')->create('order_stage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('pressing_orders')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('workflow_stages')->nullOnDelete();
            $table->string('stage_name')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('moved_at');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'moved_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('order_stage_history');
        Schema::connection('tenant')->dropIfExists('pressing_payments');
    }
};
