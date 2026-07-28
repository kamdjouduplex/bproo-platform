<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppression complète de l'ancienne fonctionnalité « Retour article / Avoir »
 * (refonte à venir). On retire les tables dédiées et les colonnes de liaison,
 * tout en conservant les colonnes partagées des factures (document_type,
 * source_invoice_id) utilisées par d'autres fonctionnalités.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('tenant');

        if ($conn->hasTable('loss_records') && $conn->hasColumn('loss_records', 'invoice_return_id')) {
            $conn->table('loss_records', function (Blueprint $table) {
                $table->dropColumn('invoice_return_id');
            });
        }

        if ($conn->hasTable('invoices') && $conn->hasColumn('invoices', 'invoice_return_id')) {
            $conn->table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_return_id');
            });
        }

        $conn->dropIfExists('invoice_return_refunds');
        $conn->dropIfExists('invoice_return_lines');
        $conn->dropIfExists('invoice_returns');
    }

    public function down(): void
    {
        // Suppression définitive : aucune restauration automatique.
        // La fonctionnalité de retour sera redéveloppée séparément.
    }
};
