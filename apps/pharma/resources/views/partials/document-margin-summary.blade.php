@props([
    'totalCost' => 0,
    'margin' => 0,
    'marginPercent' => null,
])
@php
    $marginPositive = (float) $margin >= 0;
@endphp
<aside class="document-margin-summary" aria-label="Indicateurs de marge (internes)">
    <div class="document-margin-summary__title">Marge sur vente</div>
    <p class="document-margin-summary__hint">Info interne — non visible sur l’impression client</p>
    <dl class="document-margin-summary__grid">
        <div>
            <dt>Coût total</dt>
            <dd>{{ fmt_money((float) $totalCost) }} <span class="document-margin-summary__cur">FCFA</span></dd>
        </div>
        <div>
            <dt>Marge totale</dt>
            <dd class="{{ $marginPositive ? 'is-positive' : 'is-negative' }}">
                {{ fmt_money((float) $margin) }} <span class="document-margin-summary__cur">FCFA</span>
            </dd>
        </div>
        <div>
            <dt>Marge %</dt>
            <dd class="{{ $marginPositive ? 'is-positive' : 'is-negative' }}">
                @if ($marginPercent === null)
                    —
                @else
                    {{ fmt_num((float) $marginPercent, 1) }} %
                @endif
            </dd>
        </div>
    </dl>
</aside>
