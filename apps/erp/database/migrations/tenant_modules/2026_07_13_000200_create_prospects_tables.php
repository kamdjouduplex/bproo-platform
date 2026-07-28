<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('prospects')) {
            Schema::connection('tenant')->create('prospects', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->string('name');
                $table->string('type', 20)->default('company'); // individual|company
                $table->string('email')->nullable();
                $table->string('phone', 40)->nullable();
                $table->text('address')->nullable();
                $table->string('tax_id', 64)->nullable();
                $table->string('rccm', 64)->nullable();
                $table->string('niu', 64)->nullable();
                $table->string('source', 40)->default('other');
                $table->string('status', 20)->default('nouveau');
                $table->decimal('cost', 14, 2)->default(0);
                $table->decimal('expected_value', 14, 2)->nullable();
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->text('notes')->nullable();
                $table->string('lost_reason')->nullable();
                $table->unsignedBigInteger('converted_client_id')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->unsignedBigInteger('store_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('source');
                $table->index('owner_id');
                $table->index('converted_client_id');
                $table->index('created_at');
                $table->index(['name', 'phone']);
            });
        }

        if (! Schema::connection('tenant')->hasTable('prospect_activities')) {
            Schema::connection('tenant')->create('prospect_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('prospect_id')->constrained('prospects')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('type', 30)->default('note'); // note|call|meeting|email|status
                $table->text('body');
                $table->string('from_status', 20)->nullable();
                $table->string('to_status', 20)->nullable();
                $table->timestamps();

                $table->index(['prospect_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('prospect_activities');
        Schema::connection('tenant')->dropIfExists('prospects');
    }
};
