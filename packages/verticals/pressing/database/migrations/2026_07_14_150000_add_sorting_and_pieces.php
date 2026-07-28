<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('pressing_orders')) {
            Schema::connection('tenant')->table('pressing_orders', function (Blueprint $table) {
                if (! Schema::connection('tenant')->hasColumn('pressing_orders', 'sorting_status')) {
                    $table->string('sorting_status', 20)->default('pending')->after('status');
                    $table->timestamp('sorting_completed_at')->nullable()->after('sorting_status');
                    $table->unsignedBigInteger('sorted_by')->nullable()->after('sorting_completed_at');
                    $table->index('sorting_status');
                }
            });

            $lavageOrder = \Pressing\Models\WorkflowStage::query()
                ->whereNull('agence_id')
                ->where('name', 'Lavage')
                ->value('sort_order');

            if ($lavageOrder) {
                \Illuminate\Support\Facades\DB::connection('tenant')
                    ->table('pressing_orders')
                    ->whereIn('current_stage_id', function ($q) use ($lavageOrder) {
                        $q->select('id')
                            ->from('workflow_stages')
                            ->whereNull('agence_id')
                            ->where('sort_order', '>=', $lavageOrder);
                    })
                    ->update(['sorting_status' => 'completed']);
            }
        }

        if (! Schema::connection('tenant')->hasTable('pressing_order_pieces')) {
            Schema::connection('tenant')->create('pressing_order_pieces', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('pressing_orders')->cascadeOnDelete();
                $table->foreignId('order_item_id')->constrained('pressing_order_items')->cascadeOnDelete();
                $table->unsignedSmallInteger('piece_index');
                $table->string('label')->nullable();
                $table->string('color')->nullable();
                $table->string('size')->nullable();
                $table->string('fabric')->nullable();
                $table->text('defects')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('sorted_at')->nullable();
                $table->unsignedBigInteger('sorted_by')->nullable();
                $table->timestamps();

                $table->unique(['order_item_id', 'piece_index']);
                $table->index(['order_id', 'piece_index']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('pressing_order_pieces');

        if (Schema::connection('tenant')->hasTable('pressing_orders')) {
            Schema::connection('tenant')->table('pressing_orders', function (Blueprint $table) {
                foreach (['sorting_status', 'sorting_completed_at', 'sorted_by'] as $col) {
                    if (Schema::connection('tenant')->hasColumn('pressing_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
