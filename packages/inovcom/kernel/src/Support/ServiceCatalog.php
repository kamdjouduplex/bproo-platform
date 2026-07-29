<?php

namespace InovCom\Kernel\Support;

/**
 * Unified taxonomy for Kreobat service delivery (BatiGest-style).
 *
 * Commercial (offers) → Quote → Service execution (project / maintenance / ad-hoc).
 */
class ServiceCatalog
{
    /** Offer categories (commercial intake). */
    public const OFFER_PROJECT     = 'project';
    public const OFFER_MAINTENANCE = 'maintenance';
    public const OFFER_SERVICE     = 'service';

    /** Project execution types (projects table). */
    public const EXEC_CONSTRUCTION = 'construction';
    public const EXEC_MAINTENANCE  = 'maintenance';
    public const EXEC_SERVICE      = 'service';
    public const EXEC_OTHER        = 'other';

    public static function offerCategories(): array
    {
        return [
            self::OFFER_PROJECT     => __('Chantier / Projet'),
            self::OFFER_MAINTENANCE => __('Maintenance & contrat'),
            self::OFFER_SERVICE     => __('Prestation ponctuelle'),
        ];
    }

    public static function executionTypes(): array
    {
        return [
            self::EXEC_CONSTRUCTION => __('Chantier & projet'),
            self::EXEC_MAINTENANCE  => __('Maintenance'),
            self::EXEC_SERVICE      => __('Prestation ponctuelle'),
            self::EXEC_OTHER        => __('Autre'),
        ];
    }

    public static function offerToExecutionType(?string $offerCategory): string
    {
        return match ($offerCategory) {
            self::OFFER_MAINTENANCE => self::EXEC_MAINTENANCE,
            self::OFFER_SERVICE     => self::EXEC_SERVICE,
            default                 => self::EXEC_CONSTRUCTION,
        };
    }

    public static function executionLabel(?string $type): string
    {
        return self::executionTypes()[$type] ?? ($type ?: '—');
    }

    public static function executionBadgeClass(string $type): string
    {
        return match ($type) {
            self::EXEC_CONSTRUCTION => 'bg-blue-100 text-blue-800',
            self::EXEC_MAINTENANCE  => 'bg-emerald-100 text-emerald-800',
            self::EXEC_SERVICE      => 'bg-violet-100 text-violet-800',
            default                 => 'bg-slate-100 text-slate-600',
        };
    }

    public static function offerCategoryLabel(?string $category): string
    {
        return self::offerCategories()[$category] ?? ($category ?: '—');
    }

    public static function offerCategoryBadgeClass(string $category): string
    {
        return match ($category) {
            self::OFFER_MAINTENANCE => 'bg-emerald-50 text-emerald-700',
            self::OFFER_SERVICE     => 'bg-violet-50 text-violet-700',
            default                 => 'bg-blue-50 text-blue-700',
        };
    }

    /**
     * UI copy for the post-acceptance cycle on quote detail.
     *
     * @return array{
     *     intro: string,
     *     step_title: string,
     *     auto_created: string,
     *     required: string,
     *     create_btn: string,
     *     open_btn: string,
     *     view_btn: string,
     *     invoicing_wait: string,
     *     module_missing: string,
     *     kind: 'contract'|'project'
     * }
     */
    public static function postAcceptanceCycle(?string $category): array
    {
        return match ($category) {
            self::OFFER_MAINTENANCE => [
                'intro'          => __('Après acceptation : contrat de maintenance d\'abord, puis facturation (totale ou acompte).'),
                'step_title'     => __('Contrat de maintenance'),
                'auto_created'   => __('Contrat créé automatiquement à l\'acceptation.'),
                'required'       => __('Le contrat doit être créé avant toute facturation.'),
                'create_btn'     => __('Créer le contrat'),
                'open_btn'       => __('Ouvrir le contrat'),
                'view_btn'       => __('Voir le contrat'),
                'invoicing_wait' => __('Disponible une fois le contrat créé.'),
                'module_missing' => __('Module maintenance non disponible.'),
                'kind'           => 'contract',
            ],
            self::OFFER_SERVICE => [
                'intro'          => __('Après acceptation : prestation ponctuelle d\'abord, puis facturation (totale ou acompte).'),
                'step_title'     => __('Prestation ponctuelle'),
                'auto_created'   => __('Prestation créée automatiquement à l\'acceptation.'),
                'required'       => __('La prestation doit être créée avant toute facturation.'),
                'create_btn'     => __('Créer la prestation'),
                'open_btn'       => __('Ouvrir la prestation'),
                'view_btn'       => __('Voir la prestation'),
                'invoicing_wait' => __('Disponible une fois la prestation créée.'),
                'module_missing' => __('Module projets non disponible.'),
                'kind'           => 'project',
            ],
            default => [
                'intro'          => __('Après acceptation : chantier / projet d\'abord, puis facturation (totale ou acompte).'),
                'step_title'     => __('Chantier / projet'),
                'auto_created'   => __('Projet créé automatiquement à l\'acceptation.'),
                'required'       => __('Le projet doit être créé avant toute facturation.'),
                'create_btn'     => __('Créer le projet'),
                'open_btn'       => __('Ouvrir le projet'),
                'view_btn'       => __('Voir le projet'),
                'invoicing_wait' => __('Disponible une fois le projet créé.'),
                'module_missing' => __('Module projets non disponible.'),
                'kind'           => 'project',
            ],
        };
    }

    public static function acceptSuccessWithExecution(?string $category, string $code): string
    {
        return match ($category) {
            self::OFFER_MAINTENANCE => __('Devis accepté. Contrat :code créé — vous pouvez maintenant facturer.', ['code' => $code]),
            self::OFFER_SERVICE     => __('Devis accepté. Prestation :code créée — vous pouvez maintenant facturer.', ['code' => $code]),
            default                 => __('Devis accepté. Projet :code créé — vous pouvez maintenant facturer.', ['code' => $code]),
        };
    }

    public static function acceptSuccessPendingExecution(?string $category): string
    {
        return match ($category) {
            self::OFFER_MAINTENANCE => __('Devis accepté. Créez le contrat de maintenance pour passer à la facturation.'),
            self::OFFER_SERVICE     => __('Devis accepté. Créez la prestation pour passer à la facturation.'),
            default                 => __('Devis accepté. Créez le projet pour passer à la facturation.'),
        };
    }

    public static function executionCreatedToast(?string $category, string $code): string
    {
        return match ($category) {
            self::OFFER_MAINTENANCE => __('Contrat :code créé.', ['code' => $code]),
            self::OFFER_SERVICE     => __('Prestation :code créée.', ['code' => $code]),
            default                 => __('Projet :code créé.', ['code' => $code]),
        };
    }

    public static function invoicingBlockedMessage(?string $category): string
    {
        return match ($category) {
            self::OFFER_MAINTENANCE => __('Créez d\'abord le contrat de maintenance lié à ce devis avant de facturer.'),
            self::OFFER_SERVICE     => __('Créez d\'abord la prestation liée à ce devis avant de facturer.'),
            default                 => __('Créez d\'abord le projet lié à ce devis avant de facturer.'),
        };
    }
}
