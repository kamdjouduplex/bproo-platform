<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('validated_at')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->date('ordered_at')->nullable();
            $table->date('received_at')->nullable();
            $table->decimal('total_ht', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
