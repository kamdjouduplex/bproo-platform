<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        $schema->table('clients', function (Blueprint $table) use ($schema) {
            // Conditions de paiement (référentiel partagé payment_terms, sans FK dure
            // car le module providers peut ne pas être installé sur le tenant).
            if (! $schema->hasColumn('clients', 'payment_term_id')) {
                $table->unsignedBigInteger('payment_term_id')->nullable()->after('segment_id');
            }
            if (! $schema->hasColumn('clients', 'payment_method')) {
                $table->string('payment_method', 30)->nullable()->after('payment_term_id');
            }

            // Affectation commerciale
            if (! $schema->hasColumn('clients', 'salesrep_id')) {
                $table->unsignedBigInteger('salesrep_id')->nullable()->after('payment_method');
            }

            // Audit (colonnes simples sans FK pour ne pas bloquer la suppression d'un user)
            if (! $schema->hasColumn('clients', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }
            if (! $schema->hasColumn('clients', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable();
            }

            // Soft delete : préserve l'historique (factures, ventes) lors d'une suppression.
            if (! $schema->hasColumn('clients', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        $schema->table('clients', function (Blueprint $table) use ($schema) {
            foreach (['payment_term_id', 'payment_method', 'salesrep_id', 'created_by', 'updated_by'] as $column) {
                if ($schema->hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
            if ($schema->hasColumn('clients', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
