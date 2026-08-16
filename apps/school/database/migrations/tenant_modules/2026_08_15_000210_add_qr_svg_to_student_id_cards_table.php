<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('student_id_cards', function (Blueprint $table) {
            if (! Schema::connection('tenant')->hasColumn('student_id_cards', 'qr_svg')) {
                $table->mediumText('qr_svg')->nullable()->after('qr_token');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('student_id_cards', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('student_id_cards', 'qr_svg')) {
                $table->dropColumn('qr_svg');
            }
        });
    }
};
