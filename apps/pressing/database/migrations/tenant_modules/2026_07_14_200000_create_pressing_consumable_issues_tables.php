<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('pressing_consumable_issues')) {
            Schema::connection('tenant')->create('pressing_consumable_issues', function (Blueprint $table) {
                $table->id();
                $table->string('number', 40)->unique();
                $table->string('type', 20)->default('atelier'); // atelier | livraison
                $table->foreignId('order_id')->nullable()->constrained('pressing_orders')->nullOnDelete();
                $table->unsignedBigInteger('delivery_id')->nullable()->index();
                $table->unsignedBigInteger('taken_by')->nullable()->index(); // employé qui prend / utilise
                $table->unsignedBigInteger('issued_by')->nullable()->index(); // qui enregistre
                $table->string('purpose', 40)->nullable(); // lavage, sechage, repassage, finition, livraison, autre
                $table->string('work_context', 120)->nullable(); // ex. lot du matin, commande CMD-...
                $table->unsignedInteger('pieces_processed')->nullable(); // rendement: nb pièces traitées
                $table->text('notes')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::connection('tenant')->hasTable('pressing_consumable_issue_lines')) {
            Schema::connection('tenant')->create('pressing_consumable_issue_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('issue_id')->constrained('pressing_consumable_issues')->cascadeOnDelete();
                $table->unsignedBigInteger('item_id')->index();
                $table->decimal('quantity', 12, 3);
                $table->string('unit_label', 20)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('pressing_consumable_issue_lines');
        Schema::connection('tenant')->dropIfExists('pressing_consumable_issues');
    }
};
