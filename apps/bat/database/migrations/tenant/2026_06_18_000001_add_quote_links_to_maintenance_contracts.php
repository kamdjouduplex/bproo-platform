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
            if (!Schema::connection('tenant')->hasColumn('maintenance_contracts', 'quote_id')) {
                $table->unsignedBigInteger('quote_id')->nullable()->after('client_id');
                $table->index('quote_id');
            }
            if (!Schema::connection('tenant')->hasColumn('maintenance_contracts', 'offer_id')) {
                $table->unsignedBigInteger('offer_id')->nullable()->after('quote_id');
                $table->index('offer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('maintenance_contracts', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('maintenance_contracts', 'offer_id')) {
                $table->dropIndex(['offer_id']);
                $table->dropColumn('offer_id');
            }
            if (Schema::connection('tenant')->hasColumn('maintenance_contracts', 'quote_id')) {
                $table->dropIndex(['quote_id']);
                $table->dropColumn('quote_id');
            }
        });
    }
};
