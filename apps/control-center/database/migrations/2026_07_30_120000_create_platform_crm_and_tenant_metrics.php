<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_prospects', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('source', 50)->default('manual'); // manual|referral|web|partner|other
            $table->string('stage', 40)->default('lead'); // lead|qualified|proposal|negotiation|won|lost
            $table->string('product_interest', 50)->nullable(); // erp|pressing|bat
            $table->decimal('expected_value', 14, 2)->nullable();
            $table->string('currency', 3)->default('XOF');
            $table->unsignedTinyInteger('probability')->nullable(); // 0-100
            $table->date('next_follow_up_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['stage', 'next_follow_up_at']);
            $table->index('product_interest');
        });

        Schema::create('platform_prospect_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_prospect_id')->constrained('platform_prospects')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40)->default('note'); // note|call|email|meeting|stage_change|convert
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();

            $table->index(['platform_prospect_id', 'created_at']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'metrics_cached_at')) {
                $table->timestamp('metrics_cached_at')->nullable()->after('provisioned_at');
            }
            if (! Schema::hasColumn('tenants', 'users_count')) {
                $table->unsignedInteger('users_count')->nullable()->after('metrics_cached_at');
            }
            if (! Schema::hasColumn('tenants', 'modules_enabled_count')) {
                $table->unsignedInteger('modules_enabled_count')->nullable()->after('users_count');
            }
            if (! Schema::hasColumn('tenants', 'last_tenant_activity_at')) {
                $table->timestamp('last_tenant_activity_at')->nullable()->after('modules_enabled_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (['last_tenant_activity_at', 'modules_enabled_count', 'users_count', 'metrics_cached_at'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::dropIfExists('platform_prospect_activities');
        Schema::dropIfExists('platform_prospects');
    }
};
