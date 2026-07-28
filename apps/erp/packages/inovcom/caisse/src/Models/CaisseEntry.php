<?php

namespace InovCom\Caisse\Models;

use InovCom\Kernel\TenantModel;

class CaisseEntry extends TenantModel
{
    public const TYPE_LABELS = [
        'opening_balance' => 'Solde initial',
        'opening_float' => 'Fond de caisse (ouverture)',
        'opening_adjustment' => "Ajustement d'ouverture",
        'cash_in' => 'Entrée manuelle',
        'cash_out' => 'Sortie manuelle',
        // Auto-capture des mouvements du système (espèces).
        'sale_cash_in' => 'Encaissement vente',
        'sale_return_cash_out' => 'Remboursement vente',
        'invoice_payment_cash_in' => 'Encaissement facture',
        'invoice_payment_cash_in_reversal' => 'Annulation encaissement facture',
        'debt_payment_cash_in' => 'Encaissement dette',
        'expense_cash_out' => 'Paiement dépense',
        'avoir_refund_cash_out' => 'Remboursement avoir',
        // Anciens libellés historiques conservés pour l'affichage des données existantes.
        'invoice_return_cash_out' => 'Remboursement avoir (historique)',
    ];

    public const SOURCE_LABELS = [
        'manual' => 'Manuel',
        'session' => 'Session',
        'sale' => 'Vente',
        'sale_return' => 'Retour vente',
        'invoice' => 'Facture',
        'debt' => 'Dette',
        'expense' => 'Dépense',
        'avoir' => 'Avoir',
    ];

    protected $table = 'caisse_entries';

    protected $fillable = [
        'caisse_session_id',
        'entry_date',
        'entry_type',
        'source',
        'direction',
        'amount',
        'balance_after',
        'is_reversal',
        'reversed_entry_id',
        'reason',
        'reference_type',
        'reference_id',
        'reference_number',
        'performed_by',
        'metadata',
    ];

    protected $casts = [
        'entry_date' => 'datetime',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'is_reversal' => 'boolean',
        'metadata' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(CaisseSession::class, 'caisse_session_id');
    }

    public function performer()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'performed_by');
    }

    public function getTypeLabelAttribute(): string
    {
        if (isset(self::TYPE_LABELS[$this->entry_type])) {
            return self::TYPE_LABELS[$this->entry_type];
        }

        if (is_string($this->entry_type) && str_ends_with($this->entry_type, '_reversal')) {
            $base = substr($this->entry_type, 0, -strlen('_reversal'));

            return 'Annulation — ' . (self::TYPE_LABELS[$base] ?? ucfirst(str_replace('_', ' ', $base)));
        }

        return ucfirst(str_replace('_', ' ', (string) $this->entry_type));
    }

    public function getSourceLabelAttribute(): string
    {
        if ($this->source && isset(self::SOURCE_LABELS[$this->source])) {
            return self::SOURCE_LABELS[$this->source];
        }

        // Repli : déduire l'origine depuis le type pour les anciennes écritures.
        return match (true) {
            in_array($this->entry_type, ['cash_in', 'cash_out'], true) => 'Manuel',
            in_array($this->entry_type, ['opening_balance', 'opening_float', 'opening_adjustment'], true) => 'Session',
            str_starts_with((string) $this->entry_type, 'sale_return') => 'Retour vente',
            str_starts_with((string) $this->entry_type, 'sale') => 'Vente',
            str_starts_with((string) $this->entry_type, 'invoice') => 'Facture',
            str_starts_with((string) $this->entry_type, 'debt') => 'Dette',
            str_starts_with((string) $this->entry_type, 'expense') => 'Dépense',
            str_contains((string) $this->entry_type, 'avoir') => 'Avoir',
            default => '—',
        };
    }
}
