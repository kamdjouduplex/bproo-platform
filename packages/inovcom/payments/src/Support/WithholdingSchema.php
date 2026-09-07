<?php

namespace InovCom\InvoicePayments\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InovCom\InvoicePayments\Models\FiscalWithholdingType;
use InovCom\InvoicePayments\Models\InvoicePayment;

class WithholdingSchema
{
    public static function ensure(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('fiscal_withholding_types')) {
            $schema->create('fiscal_withholding_types', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name');
                $table->string('kind', 20)->default(WithholdingKind::OTHER);
                $table->decimal('default_rate', 8, 4)->default(0);
                $table->string('default_account', 50)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        } elseif (!$schema->hasColumn('fiscal_withholding_types', 'kind')) {
            $schema->table('fiscal_withholding_types', function (Blueprint $table) {
                $table->string('kind', 20)->nullable()->after('name');
            });
        }

        if ($schema->hasTable('fiscal_withholding_types') && $schema->hasColumn('fiscal_withholding_types', 'kind')) {
            \Illuminate\Support\Facades\DB::connection('tenant')->table('fiscal_withholding_types')
                ->where(function ($q) {
                    $q->whereNull('kind')->orWhere('kind', '')->orWhere('kind', WithholdingKind::OTHER);
                })
                ->where(function ($q) {
                    $q->whereRaw('LOWER(code) LIKE ?', ['%tva%'])
                        ->orWhereRaw('LOWER(code) LIKE ?', ['%vat%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%tva%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%vat%']);
                })
                ->update(['kind' => WithholdingKind::VAT]);

            \Illuminate\Support\Facades\DB::connection('tenant')->table('fiscal_withholding_types')
                ->where(function ($q) {
                    $q->whereNull('kind')->orWhere('kind', '')->orWhere('kind', WithholdingKind::OTHER);
                })
                ->where(function ($q) {
                    $q->where('code', 'is_retenu')
                        ->orWhere('code', 'is')
                        ->orWhere('code', 'like', 'is_%')
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%impôt sur les sociétés%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%impot sur les societes%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%is retenu%']);
                })
                ->update(['kind' => WithholdingKind::IS]);
        }

        if ($schema->hasTable('invoice_payments')) {
            if (!$schema->hasColumn('invoice_payments', 'withholding_total')) {
                $schema->table('invoice_payments', function (Blueprint $table) {
                    $table->decimal('withholding_total', 15, 2)->default(0);
                });
            }
            if (!$schema->hasColumn('invoice_payments', 'settled_amount')) {
                $schema->table('invoice_payments', function (Blueprint $table) {
                    $table->decimal('settled_amount', 15, 2)->nullable();
                });
            }
        }

        if (!$schema->hasTable('invoice_payment_withholdings') && $schema->hasTable('invoice_payments')) {
            $schema->create('invoice_payment_withholdings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_payment_id')->constrained('invoice_payments')->cascadeOnDelete();
                $table->unsignedBigInteger('withholding_type_id')->nullable();
                $table->string('type_code', 50)->nullable();
                $table->string('type_name');
                $table->decimal('base_amount', 15, 2)->default(0);
                $table->decimal('rate', 8, 4)->default(0);
                $table->decimal('amount', 15, 2);
                $table->string('account_code', 50)->nullable();
                $table->string('comment', 500)->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('invoice_payment_attachments') && $schema->hasTable('invoice_payments')) {
            $schema->create('invoice_payment_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_payment_id')->constrained('invoice_payments')->cascadeOnDelete();
                $table->string('label')->nullable();
                $table->string('original_name')->nullable();
                $table->string('path');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
            });
        }

        InvoicePayment::rememberWithholdingsTable($schema->hasTable('invoice_payment_withholdings'));
        InvoicePayment::rememberAttachmentsTable($schema->hasTable('invoice_payment_attachments'));

        if ($schema->hasTable('fiscal_withholding_types')) {
            FiscalWithholdingType::syncDefaults();
        }
    }
}
