<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('tenant');
        if ($conn->hasTable('return_reasons')) {
            return;
        }

        $conn->create('return_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label', 120);
            $table->string('category', 30)->default('other'); // defective|wrong_item|damaged|commercial|other
            $table->boolean('requires_inspection')->default(true);
            $table->boolean('restock_default')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
        });

        $now = now();
        \Illuminate\Support\Facades\DB::connection('tenant')->table('return_reasons')->insert([
            ['code' => 'wrong_item', 'label' => 'Erreur de produit', 'category' => 'wrong_item', 'requires_inspection' => true, 'restock_default' => true, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'defective', 'label' => 'Produit défectueux', 'category' => 'defective', 'requires_inspection' => true, 'restock_default' => false, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'damaged', 'label' => 'Produit endommagé', 'category' => 'damaged', 'requires_inspection' => true, 'restock_default' => false, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'not_conform', 'label' => 'Non conforme à la commande', 'category' => 'wrong_item', 'requires_inspection' => true, 'restock_default' => true, 'is_active' => true, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'commercial', 'label' => 'Geste commercial', 'category' => 'commercial', 'requires_inspection' => false, 'restock_default' => true, 'is_active' => true, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'other', 'label' => 'Autre motif', 'category' => 'other', 'requires_inspection' => true, 'restock_default' => true, 'is_active' => true, 'sort_order' => 99, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('return_reasons');
    }
};
