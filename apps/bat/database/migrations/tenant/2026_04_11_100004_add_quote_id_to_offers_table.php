<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->unsignedBigInteger('quote_id')->nullable()->after('close_note');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('quote_id');
        });
    }
};
