<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restructure subscription system: balance, balance transactions, tenant_payments.
     * Payment is the only way to set/change active period. Plan change refunds unused time to balance.
     * WARNING: This drops subscription_payments and subscriptions data, then recreates subscriptions.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('balance', 14, 2)->default(0)->after('provisioned_at');
            $table->string('balance_currency', 3)->default('XOF')->after('balance');
        });

        Schema::create('tenant_balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->decimal('amount', 14, 2); // positive = credit, negative = debit
            $table->string('type', 40); // payment_credit, subscription_application, plan_change_refund, admin_adjustment
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });

        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status', 30)->default('active');
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->date('grace_ends_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('current_period_end');
        });

        Schema::create('tenant_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('XOF');
            $table->date('paid_at');
            $table->string('method', 50)->default('cash');
            $table->string('reference', 255)->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'paid_at']);
            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tenant_balance_transactions');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['balance', 'balance_currency']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status', 30)->default('active');
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index('current_period_end');
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('XOF');
            $table->date('paid_at');
            $table->string('method', 50)->default('cash');
            $table->string('reference', 255)->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['subscription_id', 'paid_at']);
        });
    }
};
