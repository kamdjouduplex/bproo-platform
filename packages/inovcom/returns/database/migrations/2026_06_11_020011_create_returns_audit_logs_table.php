<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('tenant');
        if ($conn->hasTable('returns_audit_logs')) {
            return;
        }

        $conn->create('returns_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 60);   // return|credit_note|refund|customer_credit
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 40);        // created|updated|status_changed|deleted
            $table->jsonb('changes')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('performed_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('returns_audit_logs');
    }
};
