<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->string('name', 255);
            $table->string('role', 100)->nullable();                // billing | technical | decision_maker | site_manager
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('client_contacts');
    }
};
