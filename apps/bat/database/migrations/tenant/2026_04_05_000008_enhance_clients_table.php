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
            if (!Schema::connection('tenant')->hasColumn('clients', 'segment')) {
                $table->string('segment', 50)->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('clients', 'assigned_to')) {
                $table->unsignedBigInteger('assigned_to')->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('clients', 'payment_terms')) {
                $table->unsignedInteger('payment_terms')->default(30);
            }
            if (!Schema::connection('tenant')->hasColumn('clients', 'currency')) {
                $table->string('currency', 3)->default('XOF');
            }
            if (!Schema::connection('tenant')->hasColumn('clients', 'website')) {
                $table->string('website', 255)->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('clients', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (!Schema::connection('tenant')->hasColumn('clients', 'last_contact_at')) {
                $table->timestamp('last_contact_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('clients', function (Blueprint $table) {
            foreach ([
                'segment', 'assigned_to', 'payment_terms',
                'currency', 'website', 'notes', 'last_contact_at',
            ] as $col) {
                if (Schema::connection('tenant')->hasColumn('clients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
