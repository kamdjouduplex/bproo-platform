<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('maintenance_contracts', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('maintenance_contracts', 'intervention_frequency')) {
                $table->string('intervention_frequency', 20)->default('monthly')->after('billing_cycle');
            }
            if (!Schema::connection('tenant')->hasColumn('maintenance_contracts', 'next_intervention_at')) {
                $table->date('next_intervention_at')->nullable()->after('intervention_frequency');
            }
            if (!Schema::connection('tenant')->hasColumn('maintenance_contracts', 'last_intervention_at')) {
                $table->date('last_intervention_at')->nullable()->after('next_intervention_at');
            }
            if (!Schema::connection('tenant')->hasColumn('maintenance_contracts', 'auto_generate_orders')) {
                $table->boolean('auto_generate_orders')->default(true)->after('last_intervention_at');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('maintenance_contracts', function (Blueprint $table) {
            foreach (['auto_generate_orders', 'last_intervention_at', 'next_intervention_at', 'intervention_frequency'] as $col) {
                if (Schema::connection('tenant')->hasColumn('maintenance_contracts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
