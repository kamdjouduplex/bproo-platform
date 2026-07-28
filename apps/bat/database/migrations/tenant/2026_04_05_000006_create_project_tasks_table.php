<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('phase_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 50)->default('todo');          // todo | in_progress | done | blocked
            $table->string('priority', 20)->default('normal');
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('actual_hours', 8, 2)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('phase_id')->references('id')->on('project_phases')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('project_tasks');
    }
};
