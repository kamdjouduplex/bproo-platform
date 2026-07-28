<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedBigInteger('quote_id');
            $table->unsignedBigInteger('client_id');
            $table->string('title');
            $table->string('status')->default('planned'); // planned | in_progress | on_hold | completed | closed
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable(); // chef de projet
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
