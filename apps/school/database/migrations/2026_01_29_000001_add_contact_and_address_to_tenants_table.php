<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('contact_key_first_name')->nullable()->after('is_active');
            $table->string('contact_key_last_name')->nullable()->after('contact_key_first_name');
            $table->string('contact_key_phone')->nullable()->after('contact_key_last_name');
            $table->string('country')->nullable()->after('contact_key_phone');
            $table->string('city')->nullable()->after('country');
            $table->string('contact_key_address')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'contact_key_first_name',
                'contact_key_last_name',
                'contact_key_phone',
                'country',
                'city',
                'contact_key_address',
            ]);
        });
    }
};
