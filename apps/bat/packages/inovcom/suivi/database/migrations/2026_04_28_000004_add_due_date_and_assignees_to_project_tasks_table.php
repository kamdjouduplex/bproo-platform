<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('project_tasks', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('period_date');
            $table->json('assignee_ids')->nullable()->after('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('project_tasks', function (Blueprint $table) {
            $table->dropColumn(['due_date', 'assignee_ids']);
        });
    }
};
