<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('approvals')) {
            return;
        }

        Schema::connection('tenant')->create('approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable'); // expense_id, loss_record_id, etc.
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('comments')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->integer('approval_level')->default(1); // For multi-level approvals
            $table->timestamps();
            
            // morphs() already creates index on (approvable_type, approvable_id)
            $table->index('status');
            $table->index('requested_by');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('approvals');
    }
};
