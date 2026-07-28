<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type', 100);   // model table name e.g. 'quotes'
            $table->unsignedBigInteger('auditable_id');
            $table->string('event', 100);             // created | updated | deleted | status_changed
            $table->unsignedBigInteger('user_id')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_auditable');
            $table->index('user_id', 'idx_audit_user');
            $table->index('created_at', 'idx_audit_created');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('audit_logs');
    }
};
