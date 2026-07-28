<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // EMP00001
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('position')->nullable();    // Poste / fonction
            $table->string('department')->nullable();  // Service / département
            $table->string('contract_type')->default('cdi'); // cdi | cdd | stage | freelance | alternance
            $table->date('hire_date');
            $table->date('end_date')->nullable();      // Used for CDD end
            $table->decimal('base_salary', 14, 2)->default(0);
            $table->string('iban')->nullable();
            $table->string('status')->default('active'); // active | on_leave | terminated
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('employees');
    }
};
