<?php

namespace InovCom\InvoicePayments\Models;

use Illuminate\Support\Facades\Schema;
use InovCom\InvoicePayments\Support\WithholdingKind;
use InovCom\Kernel\TenantModel;

class FiscalWithholdingType extends TenantModel
{
    protected $table = 'fiscal_withholding_types';

    protected $fillable = [
        'code',
        'name',
        'kind',
        'default_rate',
        'default_account',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'default_rate' => 'decimal:4',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function withholdings()
    {
        return $this->hasMany(InvoicePaymentWithholding::class, 'withholding_type_id');
    }

    public function resolvedKind(): string
    {
        return WithholdingKind::resolve($this->kind ?? null, $this->code, $this->name);
    }

    /**
     * @return list<array{code: string, name: string, kind: string, default_rate: float, default_account: string|null, sort_order: int, description: string}>
     */
    public static function defaults(): array
    {
        return [
            [
                'code' => 'tva_retenue',
                'name' => 'TVA retenue',
                'kind' => WithholdingKind::VAT,
                'default_rate' => 19.25,
                'default_account' => '4456',
                'sort_order' => 10,
                'description' => 'Retient exactement la TVA déjà calculée sur la facture (pas un % du TTC).',
            ],
            [
                'code' => 'is_retenu',
                'name' => 'IS retenu',
                'kind' => WithholdingKind::IS,
                'default_rate' => 0,
                'default_account' => '4441',
                'sort_order' => 20,
                'description' => 'Retient l’IS déjà établi sur la facture (même montant).',
            ],
            [
                'code' => 'autre_taxe',
                'name' => 'Autre taxe',
                'kind' => WithholdingKind::OTHER,
                'default_rate' => 0,
                'default_account' => null,
                'sort_order' => 30,
                'description' => 'Retenue calculée sur une base (souvent le HT) × un taux.',
            ],
            [
                'code' => 'autre_retenue',
                'name' => 'Autre retenue fiscale',
                'kind' => WithholdingKind::OTHER,
                'default_rate' => 0,
                'default_account' => null,
                'sort_order' => 40,
                'description' => 'Retenue calculée sur une base × un taux.',
            ],
        ];
    }

    public static function syncDefaults(): void
    {
        $hasKind = Schema::connection('tenant')->hasColumn('fiscal_withholding_types', 'kind');

        foreach (self::defaults() as $row) {
            $attrs = [
                'name' => $row['name'],
                'default_rate' => $row['default_rate'],
                'default_account' => $row['default_account'],
                'description' => $row['description'],
                'is_active' => true,
                'sort_order' => $row['sort_order'],
            ];
            if ($hasKind) {
                $attrs['kind'] = $row['kind'];
            }

            $type = self::firstOrCreate(['code' => $row['code']], $attrs);

            if (! $hasKind) {
                continue;
            }

            $dirty = false;
            if ($type->code === 'tva_retenue' || $type->code === 'is_retenu') {
                if ($type->kind !== $row['kind']) {
                    $type->kind = $row['kind'];
                    $dirty = true;
                }
            } elseif (blank($type->kind)) {
                $type->kind = $row['kind'];
                $dirty = true;
            }
            if (blank($type->description) && ! blank($row['description'])) {
                $type->description = $row['description'];
                $dirty = true;
            }
            if ($dirty) {
                $type->save();
            }
        }
    }
}
