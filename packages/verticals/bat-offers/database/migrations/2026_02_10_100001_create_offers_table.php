<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('category')->default('project'); // project | maintenance
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable(); // user_id (commercial / chargé d'étude)
            $table->string('source')->nullable(); // call_for_tender | recommendation | existing_client
            $table->string('status')->default('draft'); // draft | submitted | accepted | refused | archived
            $table->date('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
