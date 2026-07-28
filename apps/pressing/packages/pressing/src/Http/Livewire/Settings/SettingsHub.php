<?php

namespace Pressing\Http\Livewire\Settings;

use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;

class SettingsHub extends Component
{
    use AuthorizesPressingActions;

    public function render()
    {
        $this->authorizePressingAction('pressing_settings.view');

        $tenantCode = request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;

        $sections = [
            [
                'title' => 'Types de vêtements',
                'description' => 'Catalogue paramétrable des articles (chemise, costume…).',
                'route' => 'tenant.pressing_settings.article_types',
            ],
            [
                'title' => 'Tarifs',
                'description' => 'Prix par type d’article et par agence.',
                'route' => 'tenant.pressing_settings.prices',
            ],
            [
                'title' => 'Étapes production',
                'description' => 'Kanban : Mise en Production → Lavage → Séchage → Repassage → Fin de production.',
                'route' => 'tenant.pressing_workflow.stages',
            ],
            [
                'title' => 'Délais',
                'description' => 'Délai de traitement par défaut des commandes.',
                'route' => 'tenant.pressing_settings.delays',
            ],
            [
                'title' => 'Taxes',
                'description' => 'Activation et taux de TVA applicables.',
                'route' => 'tenant.pressing_settings.taxes',
            ],
            [
                'title' => 'Messages',
                'description' => 'Modèles WhatsApp / SMS / email personnalisables.',
                'route' => 'tenant.pressing_settings.messages',
            ],
            [
                'title' => 'Notifications',
                'description' => 'Activer le moteur, canaux et clés API (WhatsApp, SMS, Email, In-App).',
                'route' => 'tenant.pressing_settings.notifications',
            ],
            [
                'title' => 'Types de paiement',
                'description' => 'Espèces, Mobile Money, carte, virement…',
                'route' => 'tenant.pressing_settings.payments',
            ],
            [
                'title' => 'Fidélité',
                'description' => 'Programme de points et récompenses (bons de réduction).',
                'route' => 'tenant.pressing_settings.loyalty',
            ],
            [
                'title' => 'Agences',
                'description' => 'Création et gestion des agences / points de dépôt.',
                'route' => 'tenant.agences.index',
            ],
            [
                'title' => 'Employés',
                'description' => 'Utilisateurs, rôles et accès au pressing.',
                'route' => 'tenant.users.index',
            ],
        ];

        return view('pressing::livewire.settings.hub', [
            'sections' => $sections,
            'tenantCode' => $tenantCode,
            'canManage' => $this->can('pressing_settings.manage'),
        ])->layout('layouts.app', [
            'title' => 'Paramétrage',
            'subtitle' => 'Configuration du pressing',
        ]);
    }
}
