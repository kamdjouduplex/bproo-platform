<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('quotes')) {
            return;
        }

        Schema::connection('tenant')->table('quotes', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotes', 'parent_id')) {
                return;
            }

            $table->dropUnique(['code']);
        });

        $revisions = DB::connection('tenant')->table('quotes')->whereNotNull('parent_id')->get(['id', 'parent_id', 'code']);
        foreach ($revisions as $revision) {
            $rootCode = DB::connection('tenant')->table('quotes')->where('id', $revision->parent_id)->value('code');
            if ($rootCode && $revision->code !== $rootCode) {
                DB::connection('tenant')->table('quotes')->where('id', $revision->id)->update(['code' => $rootCode]);
            }
        }

        Schema::connection('tenant')->table('quotes', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotes', 'parent_id')) {
                return;
            }

            $table->unique(['code', 'version'], 'quotes_code_version_unique');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('quotes')) {
            return;
        }

        Schema::connection('tenant')->table('quotes', function (Blueprint $table) {
            $table->dropUnique('quotes_code_version_unique');
            $table->dropIndex(['parent_id']);
            $table->unique('code');
        });
    }
};
