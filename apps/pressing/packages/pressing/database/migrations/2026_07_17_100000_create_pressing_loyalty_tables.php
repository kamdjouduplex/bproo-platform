<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('pressing_clients')
            && ! Schema::connection('tenant')->hasColumn('pressing_clients', 'loyalty_points')) {
            Schema::connection('tenant')->table('pressing_clients', function (Blueprint $table) {
                $table->integer('loyalty_points')->default(0)->after('is_active');
                $table->integer('loyalty_orders_count')->default(0)->after('loyalty_points');
            });
        }

        if (! Schema::connection('tenant')->hasTable('pressing_loyalty_entries')) {
            Schema::connection('tenant')->create('pressing_loyalty_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('type', 20); // earn | redeem | adjust
                $table->integer('points'); // signed: +earned / -consumed
                $table->integer('balance_after')->default(0);
                $table->string('reason', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['client_id', 'order_id']);
            });
        }

        if (! Schema::connection('tenant')->hasTable('pressing_loyalty_rewards')) {
            Schema::connection('tenant')->create('pressing_loyalty_rewards', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->index();
                $table->string('code', 40)->unique();
                $table->string('reward_type', 20); // value | percent
                $table->decimal('reward_value', 15, 2)->default(0);
                $table->decimal('reward_max', 15, 2)->nullable(); // cap for percent rewards
                $table->integer('points_spent')->default(0);
                $table->string('status', 20)->default('available'); // available | used | expired | cancelled
                $table->unsignedBigInteger('order_id')->nullable()->index(); // order it was redeemed on
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->unsignedBigInteger('issued_by')->nullable();
                $table->unsignedBigInteger('used_by')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['client_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('pressing_loyalty_rewards');
        Schema::connection('tenant')->dropIfExists('pressing_loyalty_entries');

        if (Schema::connection('tenant')->hasTable('pressing_clients')) {
            Schema::connection('tenant')->table('pressing_clients', function (Blueprint $table) {
                foreach (['loyalty_points', 'loyalty_orders_count'] as $col) {
                    if (Schema::connection('tenant')->hasColumn('pressing_clients', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
