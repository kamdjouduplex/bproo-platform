<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_reports', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->date('report_date');
            $table->string('weather', 20)->default('sunny');
            // sunny | cloudy | rainy | windy | other
            $table->unsignedSmallInteger('workers_count')->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->text('work_done')->nullable();
            $table->text('issues')->nullable();
            $table->text('next_steps')->nullable();
            $table->string('status', 20)->default('draft');
            // draft | submitted | validated
            $table->boolean('pv_signed')->default(false);
            $table->timestamp('pv_signed_at')->nullable();
            $table->string('pv_client_name', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('report_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_reports');
    }
};
