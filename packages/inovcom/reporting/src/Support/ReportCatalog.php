<?php

namespace InovCom\Reporting\Support;

class ReportCatalog
{
    /**
     * @return array<string, array{label: string, reports: array<string, array<string, mixed>>}>
     */
    public static function modules(): array
    {
        $productFilters = ['store', 'client', 'category', 'item'];

        $productHint = 'Factures émises et ventes caisse de la période. Bénéfice = CA − coût d’achat (fiche article ou coût saisi sur la ligne).';
        $productReports = [
            'top_produits' => [
                'label' => 'Top articles par CA',
                'filters' => $productFilters,
                'statuses' => [],
                'hint' => $productHint,
            ],
            'top_produits_marge' => [
                'label' => 'Top articles par bénéfice',
                'filters' => $productFilters,
                'statuses' => [],
                'hint' => $productHint,
            ],
            'top_produits_qty' => [
                'label' => 'Top articles par quantité',
                'filters' => $productFilters,
                'statuses' => [],
                'hint' => $productHint,
            ],
            'ca_categorie' => [
                'label' => 'CA par catégorie',
                'filters' => ['store', 'category'],
                'statuses' => [],
                'hint' => $productHint,
            ],
        ];

        return [
            'invoicing' => [
                'label' => 'Facturation',
                'reports' => [
                    'synthese' => [
                        'label' => 'Synthèse de la période',
                        'filters' => ['store'],
                        'statuses' => [],
                        'hint' => 'Nombre et montant pour chaque indicateur de la période (factures, encaissements, créances, stock, devis, dépenses).',
                    ],
                    'journal_factures' => [
                        'label' => 'Journal des factures',
                        'filters' => ['store', 'client', 'status', 'user', 'item', 'amount'],
                        'statuses' => [
                            '' => 'Tous',
                            'issued' => 'Impayée',
                            'partial' => 'Partielle',
                            'paid' => 'Payée',
                        ],
                    ],
                    'ca_ht_tva' => [
                        'label' => 'CA HT / TVA',
                        'filters' => ['store'],
                        'statuses' => [],
                    ],
                    'ca_client' => [
                        'label' => 'CA par client',
                        'filters' => ['store', 'client'],
                        'statuses' => [],
                    ],
                    'ca_commercial' => [
                        'label' => 'CA par commercial',
                        'filters' => ['store', 'user'],
                        'statuses' => [],
                    ],
                    'factures_par_statut' => [
                        'label' => 'Factures par statut',
                        'filters' => ['store'],
                        'statuses' => [],
                    ],
                    'creances' => [
                        'label' => 'Créances clients',
                        'filters' => ['store', 'client'],
                        'statuses' => [],
                        'hint' => 'Factures impayées ou partielles de la période, avec retard et solde TTC.',
                    ],
                    'creances_aging' => [
                        'label' => 'Créances par ancienneté',
                        'filters' => ['store', 'client'],
                        'statuses' => [],
                        'hint' => 'Répartition du solde ouvert selon l’échéance (à échoir, 1–30 j, 31–60 j, 61–90 j, +90 j).',
                    ],
                    ...$productReports,
                ],
            ],
            'sales' => [
                'label' => 'Ventes',
                'reports' => [
                    'journal_ventes' => [
                        'label' => 'Journal des ventes',
                        'filters' => ['store', 'client', 'user', 'item', 'amount'],
                        'statuses' => [],
                    ],
                    ...$productReports,
                ],
            ],
            'payments' => [
                'label' => 'Paiements',
                'reports' => [
                    'journal_encaissements' => [
                        'label' => 'Journal des encaissements',
                        'filters' => ['client', 'status'],
                        'statuses' => [
                            '' => 'Tous',
                            'active' => 'Actif',
                            'cancelled' => 'Annulé',
                        ],
                    ],
                    'encaissements_par_mode' => [
                        'label' => 'Encaissements par mode',
                        'filters' => ['client'],
                        'statuses' => [],
                    ],
                ],
            ],
            'quotations' => [
                'label' => 'Devis',
                'reports' => [
                    'devis_par_statut' => [
                        'label' => 'Devis par statut',
                        'filters' => ['store', 'client'],
                        'statuses' => [],
                    ],
                    'journal_devis' => [
                        'label' => 'Journal des devis',
                        'filters' => ['store', 'client', 'status', 'amount'],
                        'statuses' => [
                            '' => 'Tous',
                            'draft' => 'Brouillon',
                            'sent' => 'Envoyé',
                            'accepted' => 'Accepté',
                            'suspended' => 'Suspendu',
                            'rejected' => 'Rejeté',
                        ],
                    ],
                ],
            ],
            'purchases' => [
                'label' => 'Achats',
                'reports' => [
                    'journal_achats' => [
                        'label' => 'Journal des achats',
                        'filters' => ['status', 'amount'],
                        'statuses' => [
                            '' => 'Tous',
                            'draft' => 'Brouillon',
                            'sent' => 'Envoyée',
                            'received' => 'Reçue',
                            'cancelled' => 'Annulée',
                        ],
                    ],
                    'achats_par_fournisseur' => [
                        'label' => 'Achats par fournisseur',
                        'filters' => ['status'],
                        'statuses' => [
                            '' => 'Tous',
                            'draft' => 'Brouillon',
                            'sent' => 'Envoyée',
                            'received' => 'Reçue',
                            'cancelled' => 'Annulée',
                        ],
                    ],
                ],
            ],
            'expenses' => [
                'label' => 'Dépenses',
                'reports' => [
                    'journal_depenses' => [
                        'label' => 'Journal des dépenses',
                        'filters' => ['category', 'status', 'amount'],
                        'statuses' => [
                            '' => 'Tous',
                            'pending' => 'En attente',
                            'approved' => 'Validée',
                            'paid' => 'Payée',
                            'rejected' => 'Rejetée',
                        ],
                    ],
                    'depenses_par_categorie' => [
                        'label' => 'Dépenses par catégorie',
                        'filters' => ['status'],
                        'statuses' => [
                            '' => 'Tous',
                            'pending' => 'En attente',
                            'approved' => 'Validée',
                            'paid' => 'Payée',
                        ],
                    ],
                ],
            ],
            'stock' => [
                'label' => 'Stock',
                'reports' => [
                    'valeur_stock' => [
                        'label' => 'Valeur du stock',
                        'filters' => ['store', 'category', 'item'],
                        'statuses' => [],
                        'hint' => 'Instantané du stock actuel (la période de dates n’applique pas). Valeur d’achat = quantité × coût. Valeur de vente = quantité × prix de vente.',
                    ],
                    'stock_par_categorie' => [
                        'label' => 'Stock par catégorie',
                        'filters' => ['store'],
                        'statuses' => [],
                        'hint' => 'Instantané : nombre d’articles, quantités et valeurs d’achat / de vente par catégorie.',
                    ],
                    'stock_low' => [
                        'label' => 'Stock faible',
                        'filters' => ['store', 'category'],
                        'statuses' => [],
                        'hint' => 'Articles dont le disponible est inférieur ou égal au seuil de réappro, avec valeur d’achat.',
                    ],
                    'stock_out' => [
                        'label' => 'Ruptures de stock',
                        'filters' => ['store', 'category'],
                        'statuses' => [],
                        'hint' => 'Articles dont le disponible est à zéro, avec valeur théorique nulle.',
                    ],
                    'stock_dormant' => [
                        'label' => 'Stock sans mouvement',
                        'filters' => ['store', 'category'],
                        'statuses' => [],
                        'hint' => 'Articles encore en stock sans vente ni facture sur la période filtrée, avec quantité et valeur.',
                    ],
                ],
            ],
        ];
    }

    public static function report(string $module, string $report): ?array
    {
        return self::modules()[$module]['reports'][$report] ?? null;
    }

    public static function firstReport(string $module): string
    {
        $reports = self::modules()[$module]['reports'] ?? [];

        return (string) array_key_first($reports);
    }
}
