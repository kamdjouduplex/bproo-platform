@php
    $catalog = [
        'CA' => [
            'label' => 'CA',
            'text' => 'Chiffre d\'affaires : montant total des ventes réalisées sur la période.',
        ],
        'VENTE_DIRECT' => [
            'label' => 'Vente Direct',
            'text' => 'Ventes enregistrées directement au comptoir (ex-POS).',
        ],
        'CA_FACTURE' => [
            'label' => 'CA Facture',
            'text' => 'Chiffre d\'affaires des factures émises aux clients (TTC).',
        ],
        'COGS' => [
            'label' => 'COGS',
            'text' => 'Cost of Goods Sold — coût des ventes : coût d\'achat des marchandises effectivement vendues.',
        ],
        'TTC' => [
            'label' => 'TTC',
            'text' => 'Toutes taxes comprises : montant incluant la TVA et les taxes applicables.',
        ],
        'HT' => [
            'label' => 'HT',
            'text' => 'Hors taxes : montant avant application de la TVA et des autres taxes.',
        ],
        'TVA' => [
            'label' => 'TVA',
            'text' => 'Taxe sur la valeur ajoutée collectée sur les factures de la période (séparée du CA HT).',
        ],
        'BENEFICE' => [
            'label' => 'Bénéfice brut',
            'text' => 'Estimation : CA Vente Direct − coût des ventes − pertes de stock (hors dépenses, achats et paie).',
        ],
        'PART_CA' => [
            'label' => 'Part CA',
            'text' => 'Pourcentage du chiffre d\'affaires total représenté par le client sur la période.',
        ],
    ];

    $items = [];
    $seen = [];
    foreach ($keys ?? [] as $key) {
        if (isset($catalog[$key]) && ! isset($seen[$key])) {
            $items[] = $catalog[$key];
            $seen[$key] = true;
        }
    }
@endphp

@if (count($items) > 0)
    <footer @class(['reporting-glossary', 'reporting-glossary--standalone' => !empty($standalone)]) aria-label="Sigles et définitions">
        <p class="reporting-glossary__title">Sigles et définitions</p>
        <dl class="reporting-glossary__list">
            @foreach ($items as $item)
                <div class="reporting-glossary__item">
                    <dt>{{ $item['label'] }}</dt>
                    <dd>{{ $item['text'] }}</dd>
                </div>
            @endforeach
        </dl>
        @if (!empty($note))
            <p class="reporting-glossary__note">{{ $note }}</p>
        @endif
    </footer>
@endif
