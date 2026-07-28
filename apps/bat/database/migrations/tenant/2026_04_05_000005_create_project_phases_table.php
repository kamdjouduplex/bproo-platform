<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('project_phases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('status', 50)->default('pending');       // pending | in_progress | completed
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->unsignedSmallInteger('progress_percent')->default(0);
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('project_phases');
    }
};
