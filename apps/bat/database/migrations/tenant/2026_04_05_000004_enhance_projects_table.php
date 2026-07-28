<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        // Make quote_id nullable — projects can exist without a linked quote.
        Schema::connection('tenant')->table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('quote_id')->nullable()->change();
        });

        Schema::connection('tenant')->table('projects', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('projects', 'budget')) {
                $table->decimal('budget', 15, 2)->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('projects', 'actual_cost')) {
                $table->decimal('actual_cost', 15, 2)->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('projects', 'progress_percent')) {
                $table->unsignedSmallInteger('progress_percent')->default(0);
            }
            if (!Schema::connection('tenant')->hasColumn('projects', 'priority')) {
                $table->string('priority', 20)->default('normal');
            }
            if (!Schema::connection('tenant')->hasColumn('projects', 'project_type')) {
                $table->string('project_type', 50)->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('projects', 'contract_number')) {
                $table->string('contract_number', 100)->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('projects', 'site_address')) {
                $table->text('site_address')->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('projects', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('projects', 'closed_at')) {
                $table->timestamp('closed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('projects', function (Blueprint $table) {
            foreach ([
                'budget', 'actual_cost', 'progress_percent', 'priority',
                'project_type', 'contract_number', 'site_address',
                'completed_at', 'closed_at',
            ] as $col) {
                if (Schema::connection('tenant')->hasColumn('projects', $col)) {
                    $table->dropColumn($col);
                }
            }
            $table->unsignedBigInteger('quote_id')->nullable(false)->change();
        });
    }
};
