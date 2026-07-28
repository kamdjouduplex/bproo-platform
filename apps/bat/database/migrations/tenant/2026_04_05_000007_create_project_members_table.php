<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('project_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role', 100)->nullable();                // project_manager | technician | foreman | subcontractor
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('project_members');
    }
};
