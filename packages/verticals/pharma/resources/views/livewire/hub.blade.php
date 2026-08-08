<div class="page-body crm-page">
    <div class="crm-page__intro">
        <div>
            <h2 class="crm-page__title">Bproo Pharma</h2>
            <p class="crm-page__lead">
                Accès rapide aux modules pharmacie. Le POS, le stock à lots et les ordonnances partagent le socle Bproo.
            </p>
        </div>
    </div>

    <div class="crm-kpi-grid">
        @foreach ($links as $link)
            <a class="crm-kpi-card" href="{{ route($link['route'], ['tenant' => request()->query('tenant') ?? session('tenant_code')]) }}" style="text-decoration:none;color:inherit;">
                <div class="crm-kpi-card__label">{{ $link['label'] }}</div>
                <div class="crm-kpi-card__meta" style="margin-top:8px;">{{ $link['hint'] }}</div>
            </a>
        @endforeach
    </div>

    <section class="crm-panel" style="margin-top:16px;">
        <h3 class="crm-panel__title">Feuille de route intégrée</h3>
        <p class="crm-muted" style="margin:8px 0 0;">
            V1 : POS, stock, lots, achats, caisse, clients — déjà branchés.
            Prochaines étapes verticales : DCI / famille thérapeutique, blocage lots périmés, mutuelles, fidélité.
        </p>
    </section>
</div>
