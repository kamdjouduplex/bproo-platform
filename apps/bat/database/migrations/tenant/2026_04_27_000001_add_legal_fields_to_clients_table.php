<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('clients', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('clients', 'legal_form')) {
                $table->string('legal_form', 50)->nullable()->after('type');
            }
            if (!Schema::connection('tenant')->hasColumn('clients', 'industry')) {
                $table->string('industry', 150)->nullable()->after('legal_form');
            }
            if (!Schema::connection('tenant')->hasColumn('clients', 'rccm')) {
                $table->string('rccm', 100)->nullable()->after('tax_id');
            }
            if (!Schema::connection('tenant')->hasColumn('clients', 'phone2')) {
                $table->string('phone2', 50)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('clients', function (Blueprint $table) {
            foreach (['legal_form', 'industry', 'rccm', 'phone2'] as $col) {
                if (Schema::connection('tenant')->hasColumn('clients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
