<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('project_tasks', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
            $table->string('period_type', 10)->default('week')->after('position');
            $table->date('period_date')->nullable()->after('period_type');
            $table->boolean('is_validated')->default(false)->after('status');
            $table->timestamp('validated_at')->nullable()->after('is_validated');
            $table->unsignedBigInteger('validated_by')->nullable()->after('validated_at');
            $table->text('notes')->nullable()->after('description');

            $table->index(['project_id', 'period_date']);
            $table->index('is_validated');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('project_tasks', function (Blueprint $table) {
            $table->dropColumn(['code', 'period_type', 'period_date', 'is_validated', 'validated_at', 'validated_by', 'notes']);
        });
    }
};
