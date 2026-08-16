<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('school_payments', function (Blueprint $table) {
            $table->string('payer_name')->nullable()->after('reference');
            $table->string('bank_name')->nullable()->after('payer_name');
            $table->string('channel_detail')->nullable()->after('bank_name'); // opérateur mobile, n° chèque…
            $table->string('proof_path')->nullable()->after('channel_detail');
            $table->string('proof_original_name')->nullable()->after('proof_path');
            $table->text('notes')->nullable()->after('proof_original_name');
            $table->timestamp('verified_at')->nullable()->after('notes');
            $table->string('verified_by_name')->nullable()->after('verified_at');
            $table->timestamp('rejected_at')->nullable()->after('verified_by_name');
            $table->string('rejected_reason')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('school_payments', function (Blueprint $table) {
            $table->dropColumn([
                'payer_name',
                'bank_name',
                'channel_detail',
                'proof_path',
                'proof_original_name',
                'notes',
                'verified_at',
                'verified_by_name',
                'rejected_at',
                'rejected_reason',
            ]);
        });
    }
};
