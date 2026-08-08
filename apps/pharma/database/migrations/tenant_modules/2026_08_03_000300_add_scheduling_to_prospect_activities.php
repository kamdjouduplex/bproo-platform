<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('prospect_activities')) {
            return;
        }

        Schema::connection('tenant')->table('prospect_activities', function (Blueprint $table) {
            if (! Schema::connection('tenant')->hasColumn('prospect_activities', 'state')) {
                $table->string('state', 20)->default('done')->after('type');
            }
            if (! Schema::connection('tenant')->hasColumn('prospect_activities', 'due_at')) {
                $table->timestamp('due_at')->nullable()->after('body');
            }
            if (! Schema::connection('tenant')->hasColumn('prospect_activities', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('due_at');
            }
            if (! Schema::connection('tenant')->hasColumn('prospect_activities', 'assignee_id')) {
                $table->unsignedBigInteger('assignee_id')->nullable()->after('user_id');
            }
            if (! Schema::connection('tenant')->hasColumn('prospect_activities', 'summary')) {
                $table->string('summary', 180)->nullable()->after('type');
            }
        });

        DB::connection('tenant')->table('prospect_activities')
            ->whereNull('completed_at')
            ->update([
                'state' => 'done',
                'completed_at' => DB::raw('created_at'),
            ]);

        Schema::connection('tenant')->table('prospect_activities', function (Blueprint $table) {
            try {
                $table->index(['state', 'due_at'], 'prospect_activities_state_due_idx');
            } catch (\Throwable) {
            }
            try {
                $table->index(['assignee_id', 'state'], 'prospect_activities_assignee_state_idx');
            } catch (\Throwable) {
            }
            try {
                $table->index(['prospect_id', 'state', 'due_at'], 'prospect_activities_prospect_state_due_idx');
            } catch (\Throwable) {
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('tenant')->hasTable('prospect_activities')) {
            return;
        }

        Schema::connection('tenant')->table('prospect_activities', function (Blueprint $table) {
            foreach (['prospect_activities_state_due_idx', 'prospect_activities_assignee_state_idx', 'prospect_activities_prospect_state_due_idx'] as $idx) {
                try {
                    $table->dropIndex($idx);
                } catch (\Throwable) {
                }
            }
            foreach (['state', 'due_at', 'completed_at', 'assignee_id', 'summary'] as $col) {
                if (Schema::connection('tenant')->hasColumn('prospect_activities', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
