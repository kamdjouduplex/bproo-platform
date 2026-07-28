<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('channel', 100)->nullable()->after('source');
            $table->string('channel_detail', 500)->nullable()->after('channel');
        });

        // Migrate legacy status values to the new workflow vocabulary.
        // submitted / refused  → draft      (pre-assignment states, revert to editable)
        // accepted  / archived → terminated  (post-completion states, map to terminal)
        DB::table('offers')->whereIn('status', ['submitted', 'refused'])->update(['status' => 'draft']);
        DB::table('offers')->whereIn('status', ['accepted', 'archived'])->update(['status' => 'terminated']);
    }

    public function down(): void
    {
        // Status values cannot be fully reversed (original values are lost on rollback).
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['channel', 'channel_detail']);
        });
    }
};
