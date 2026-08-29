<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('treasury_commitments')) {
            return;
        }

        Schema::connection('tenant')->create('treasury_commitments', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('category', 80)->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('due_date');
            $table->string('frequency', 20)->default('once');
            $table->string('account_code', 50)->nullable();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->string('beneficiary', 180)->nullable();
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('planned');
            $table->string('priority', 20)->default('normal');
            $table->unsignedInteger('alert_days')->nullable();
            $table->json('paid_dates')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['due_date', 'status']);
            $table->index('frequency');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('treasury_commitments');
    }
};
