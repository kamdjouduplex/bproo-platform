<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('employees')) {
            return;
        }

        Schema::connection('tenant')->table('employees', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('employees', 'contract_type')) {
                $table->string('contract_type', 32)->nullable()->after('department');
            }
            if (!Schema::connection('tenant')->hasColumn('employees', 'cnps_number')) {
                $table->string('cnps_number', 64)->nullable()->after('contract_type');
            }
            if (!Schema::connection('tenant')->hasColumn('employees', 'bank_name')) {
                $table->string('bank_name', 128)->nullable()->after('cnps_number');
            }
            if (!Schema::connection('tenant')->hasColumn('employees', 'bank_account')) {
                $table->string('bank_account', 64)->nullable()->after('bank_name');
            }
            if (!Schema::connection('tenant')->hasColumn('employees', 'gender')) {
                $table->string('gender', 16)->nullable()->after('bank_account');
            }
            if (!Schema::connection('tenant')->hasColumn('employees', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('gender');
            }
            if (!Schema::connection('tenant')->hasColumn('employees', 'address')) {
                $table->text('address')->nullable()->after('birth_date');
            }
            if (!Schema::connection('tenant')->hasColumn('employees', 'annual_leave_days')) {
                $table->unsignedSmallInteger('annual_leave_days')->default(18)->after('address');
            }
            if (!Schema::connection('tenant')->hasColumn('employees', 'notes')) {
                $table->text('notes')->nullable()->after('annual_leave_days');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('employees')) {
            return;
        }

        Schema::connection('tenant')->table('employees', function (Blueprint $table) {
            foreach (['contract_type', 'cnps_number', 'bank_name', 'bank_account', 'gender', 'birth_date', 'address', 'annual_leave_days', 'notes'] as $col) {
                if (Schema::connection('tenant')->hasColumn('employees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
