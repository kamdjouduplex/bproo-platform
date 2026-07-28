<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('client_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type', 50);                             // call | meeting | email | site_visit | note | quote_sent | invoice_sent
            $table->string('subject', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('activity_at');
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->index('activity_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('client_activities');
    }
};
