<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('delivery_notes', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('delivery_notes', 'quotation_id')) {
                $table->foreignId('quotation_id')->nullable()->after('invoice_id')->constrained('quotations')->nullOnDelete();
            }
            if (!Schema::connection('tenant')->hasColumn('delivery_notes', 'client_id')) {
                $table->foreignId('client_id')->nullable()->after('quotation_id')->constrained('clients')->nullOnDelete();
            }
        });

        // Le BL peut désormais naître d'un devis (sans facture) : invoice_id devient nullable.
        try {
            DB::connection('tenant')->statement('ALTER TABLE delivery_notes ALTER COLUMN invoice_id DROP NOT NULL');
        } catch (\Throwable $e) {
            // Déjà nullable ou SGBD ne supportant pas cette syntaxe : on ignore.
        }

        Schema::connection('tenant')->table('delivery_note_lines', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('delivery_note_lines', 'quotation_line_id')) {
                $table->unsignedBigInteger('quotation_line_id')->nullable()->after('invoice_line_id');
                $table->index('quotation_line_id');
            }
        });

        try {
            DB::connection('tenant')->statement('ALTER TABLE delivery_note_lines ALTER COLUMN invoice_line_id DROP NOT NULL');
        } catch (\Throwable $e) {
            // Idem.
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('delivery_note_lines', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('delivery_note_lines', 'quotation_line_id')) {
                $table->dropColumn('quotation_line_id');
            }
        });

        Schema::connection('tenant')->table('delivery_notes', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('delivery_notes', 'client_id')) {
                $table->dropConstrainedForeignId('client_id');
            }
            if (Schema::connection('tenant')->hasColumn('delivery_notes', 'quotation_id')) {
                $table->dropConstrainedForeignId('quotation_id');
            }
        });
    }
};
