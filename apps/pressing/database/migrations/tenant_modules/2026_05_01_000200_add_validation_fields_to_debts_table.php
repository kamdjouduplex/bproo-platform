<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('debts', function (Blueprint $table) {
            $table->boolean('is_validated')->default(true)->after('status');
            $table->foreignId('validated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validated_by');
            $table->index('is_validated');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('debts', function (Blueprint $table) {
            $table->dropIndex(['is_validated']);
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn('validated_at');
            $table->dropColumn('is_validated');
        });
    }
};
