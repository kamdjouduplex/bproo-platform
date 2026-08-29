<?php

namespace InovCom\InvoicePayments\Models;

use InovCom\Kernel\TenantModel;

class FiscalWithholdingType extends TenantModel
{
    protected $table = 'fiscal_withholding_types';

    protected $fillable = [
        'code',
        'name',
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

    /**
     * @return list<array{code: string, name: string, default_rate: float, default_account: string|null, sort_order: int}>
     */
    public static function defaults(): array
    {
        return [
            ['code' => 'tva_retenue', 'name' => 'TVA retenue', 'default_rate' => 19.25, 'default_account' => '4456', 'sort_order' => 10],
            ['code' => 'is_retenu', 'name' => 'IS retenu', 'default_rate' => 0, 'default_account' => '4441', 'sort_order' => 20],
            ['code' => 'autre_taxe', 'name' => 'Autre taxe', 'default_rate' => 0, 'default_account' => null, 'sort_order' => 30],
            ['code' => 'autre_retenue', 'name' => 'Autre retenue fiscale', 'default_rate' => 0, 'default_account' => null, 'sort_order' => 40],
        ];
    }

    public static function syncDefaults(): void
    {
        foreach (self::defaults() as $row) {
            self::firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'default_rate' => $row['default_rate'],
                    'default_account' => $row['default_account'],
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }
}
