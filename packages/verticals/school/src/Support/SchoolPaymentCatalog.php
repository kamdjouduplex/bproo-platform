<?php

namespace School\Support;

/**
 * Catalogue des méthodes de paiement scolaires + règles de validation.
 */
class SchoolPaymentCatalog
{
    public const TYPE_ONSITE = 'onsite';
    public const TYPE_BANK = 'bank';
    public const TYPE_MOBILE = 'mobile_money';
    public const TYPE_CHEQUE = 'cheque';
    public const TYPE_CARD = 'card';

    /**
     * @return array<string, array{label:string, hint:string, immediate:bool, requires_proof:bool, requires_reference:bool}>
     */
    public static function methods(): array
    {
        return [
            self::TYPE_ONSITE => [
                'label' => 'Espèces à l’école',
                'hint' => 'Paiement au guichet — reçu immédiat.',
                'immediate' => true,
                'requires_proof' => false,
                'requires_reference' => false,
            ],
            self::TYPE_BANK => [
                'label' => 'Versement banque',
                'hint' => 'Bordereau / reçu bancaire obligatoire avant validation.',
                'immediate' => false,
                'requires_proof' => true,
                'requires_reference' => true,
            ],
            self::TYPE_MOBILE => [
                'label' => 'Mobile Money',
                'hint' => 'MTN / Orange / Moov — ID transaction requis.',
                'immediate' => false,
                'requires_proof' => true,
                'requires_reference' => true,
            ],
            self::TYPE_CHEQUE => [
                'label' => 'Chèque',
                'hint' => 'N° de chèque + scan/photo du chèque.',
                'immediate' => false,
                'requires_proof' => true,
                'requires_reference' => true,
            ],
            self::TYPE_CARD => [
                'label' => 'Carte bancaire',
                'hint' => 'Paiement carte à l’école — reçu immédiat.',
                'immediate' => true,
                'requires_proof' => false,
                'requires_reference' => false,
            ],
        ];
    }

    public static function label(string $type): string
    {
        return self::methods()[$type]['label'] ?? $type;
    }

    public static function keys(): array
    {
        return array_keys(self::methods());
    }

    public static function isImmediate(string $type): bool
    {
        return (bool) (self::methods()[$type]['immediate'] ?? false);
    }

    public static function requiresProof(string $type): bool
    {
        return (bool) (self::methods()[$type]['requires_proof'] ?? false);
    }

    public static function requiresReference(string $type): bool
    {
        return (bool) (self::methods()[$type]['requires_reference'] ?? false);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'verified' => 'Validé',
            'rejected' => 'Rejeté',
            'pending' => 'En attente de validation',
            default => $status,
        };
    }

    /**
     * @return array<int, array{key:string, label:string, done:bool, current:bool}>
     */
    public static function workflowSteps(\School\Models\SchoolPayment $payment): array
    {
        $hasProof = filled($payment->proof_path) || (self::requiresProof($payment->payment_type) === false && filled($payment->reference));
        $pending = $payment->status === 'pending';
        $verified = $payment->status === 'verified';
        $rejected = $payment->status === 'rejected';

        return [
            [
                'key' => 'registered',
                'label' => 'Enregistré',
                'done' => true,
                'current' => false,
            ],
            [
                'key' => 'proof',
                'label' => self::requiresProof($payment->payment_type) ? 'Justificatif joint' : 'Référence saisie',
                'done' => $hasProof || $verified,
                'current' => $pending && ! $hasProof && ! $rejected,
            ],
            [
                'key' => 'review',
                'label' => 'Contrôle école',
                'done' => $verified || $rejected,
                'current' => $pending && $hasProof && ! $rejected,
            ],
            [
                'key' => 'done',
                'label' => $rejected ? 'Rejeté' : 'Validé + reçu',
                'done' => $verified || $rejected,
                'current' => $verified || $rejected,
            ],
        ];
    }
}
