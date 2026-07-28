<div class="page-body pricing-settings">
    @include('pressing::livewire.settings.partials.nav')

    <style>
        .pricing-settings { --ps-accent: #2563eb; --ps-muted: #64748b; }
        .pricing-default-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }
        @media (max-width: 800px) { .pricing-default-grid { grid-template-columns: 1fr; } }
        .pricing-mode-card {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
            background: #fff;
            text-align: left;
            cursor: pointer;
            transition: border-color .15s, background .15s, box-shadow .15s;
        }
        .pricing-mode-card:hover { border-color: #94a3b8; background: #f8fafc; }
        .pricing-mode-card--active {
            border-color: var(--ps-accent);
            background: #eff6ff;
            box-shadow: 0 0 0 1px rgba(37,99,235,.12);
        }
        .pricing-mode-card__title { font-weight: 700; font-size: 14px; color: #0f172a; display: block; }
        .pricing-mode-card__desc { font-size: 12px; color: var(--ps-muted); margin-top: 4px; line-height: 1.4; }
        .pricing-global-box {
            margin-top: 14px;
            padding: 14px;
            border-radius: 12px;
            border: 1px dashed #7dd3fc;
            background: linear-gradient(180deg, #f0f9ff, #fff);
        }
        .pricing-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .pricing-type-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
            background: #fff;
        }
        .pricing-type-card--inactive { opacity: .55; }
        .pricing-type-card__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 12px;
        }
        .pricing-type-card__name { font-weight: 700; font-size: 14px; color: #0f172a; }
        .pricing-type-card__code { font-size: 11px; color: #94a3b8; }
        .pricing-dual {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        @media (max-width: 420px) { .pricing-dual { grid-template-columns: 1fr; } }
        .pricing-field label { display: block; font-size: 11px; font-weight: 600; color: var(--ps-muted); margin-bottom: 4px; }
        .pricing-stats {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 12px 0 4px;
        }
        .pricing-stat {
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            background: #fff;
            cursor: pointer;
            color: #475569;
        }
        .pricing-stat--active { border-color: var(--ps-accent); background: #eff6ff; color: #1d4ed8; }
        .pricing-bulk {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 12px;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
        }
        @media (max-width: 720px) { .pricing-bulk { grid-template-columns: 1fr; } }
        .pricing-badge-row { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 8px; }
        .pricing-mini-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #64748b;
        }
        .pricing-mini-badge--ok { background: #dcfce7; color: #15803d; }
        .pricing-mini-badge--miss { background: #fef3c7; color: #b45309; }
    </style>

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif

    <form wire:submit.prevent="save">
        <section class="card app-table-card" style="margin-bottom:16px;">
            <div class="client-list-head">
                <div>
                    <h2 class="client-list-head__title">Tarifs & facturation</h2>
                    <p style="margin:4px 0 0;font-size:13px;color:#64748b;">
                        Configurez les 3 façons de facturer : prix fixe, kilo par type, et prix fixe au kilo (tout cou).
                    </p>
                </div>
                <div class="client-list-head__actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <select class="input input-sm" wire:model.live="agenceFilter" style="max-width:220px;">
                        <option value="">Tarif global (toutes agences)</option>
                        @foreach ($agences as $agence)
                            <option value="{{ $agence->id }}">{{ $agence->name }}</option>
                        @endforeach
                    </select>
                    @if ($canManage)
                        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">Enregistrer tout</span>
                            <span wire:loading wire:target="save">Enregistrement…</span>
                        </button>
                    @endif
                </div>
            </div>

            <div style="padding:0 16px 16px;">
                @if ($agenceFilter)
                    <div class="badge badge-info" style="margin-bottom:12px;">Tarifs spécifiques agence — prioritaires sur le global</div>
                @endif

                <h3 style="margin:0 0 8px;font-size:.95rem;">Mode par défaut à la réception</h3>
                <p style="margin:0 0 10px;font-size:12px;color:#64748b;">Pré-sélectionné lors de la création d'une commande.</p>

                <div class="pricing-default-grid" role="radiogroup">
                    @foreach ($billingModes as $modeKey => $modeLabel)
                        <button type="button"
                            class="pricing-mode-card {{ $billingDefaultMode === $modeKey ? 'pricing-mode-card--active' : '' }}"
                            wire:click="setDefaultMode('{{ $modeKey }}')"
                            @disabled(! $canManage)>
                            <span class="pricing-mode-card__title">{{ $modeLabel }}</span>
                            <span class="pricing-mode-card__desc">{{ \Pressing\Support\PressingBilling::modeDescription($modeKey) }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="pricing-global-box">
                    <label class="field-label" style="margin:0 0 6px;">Prix fixe au kilo — tout cou (FCFA/kg) *</label>
                    <input class="input" type="number" step="0.01" min="0" wire:model="globalWeightPrice"
                        style="max-width:220px;" @disabled(! $canManage)>
                    @error('globalWeightPrice')<div class="text-error" style="font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                    <p style="margin:6px 0 0;font-size:12px;color:#64748b;">
                        Utilisé quand la réception est en mode <strong>Au kilo — tout cou</strong> : un seul poids pour tout le lot.
                    </p>
                </div>
            </div>
        </section>

        <section class="card app-table-card">
            <div style="padding:16px 16px 0;">
                <h3 style="margin:0;font-size:.95rem;">Tarif de chaque type d'article</h3>
                <p style="margin:4px 0 0;font-size:12px;color:#64748b;line-height:1.45;">
                    Pour chaque type, renseignez <strong>les deux</strong> si besoin :
                    <strong>prix fixe</strong> (réception à la pièce) et <strong>prix/kg</strong> (réception au kilo par type).
                </p>

                <div class="pricing-stats" role="group">
                    <button type="button" class="pricing-stat {{ $typeFilter === 'all' ? 'pricing-stat--active' : '' }}" wire:click="$set('typeFilter', 'all')">
                        Tous · {{ $counts['all'] }}
                    </button>
                    <button type="button" class="pricing-stat {{ $typeFilter === 'fixed' ? 'pricing-stat--active' : '' }}" wire:click="$set('typeFilter', 'fixed')">
                        Avec prix fixe · {{ $counts['fixed'] }}
                    </button>
                    <button type="button" class="pricing-stat {{ $typeFilter === 'per_kg' ? 'pricing-stat--active' : '' }}" wire:click="$set('typeFilter', 'per_kg')">
                        Avec prix/kg · {{ $counts['per_kg'] }}
                    </button>
                    <button type="button" class="pricing-stat {{ $typeFilter === 'both' ? 'pricing-stat--active' : '' }}" wire:click="$set('typeFilter', 'both')">
                        Les deux · {{ $counts['both'] }}
                    </button>
                    <button type="button" class="pricing-stat {{ $typeFilter === 'missing' ? 'pricing-stat--active' : '' }}" wire:click="$set('typeFilter', 'missing')">
                        Incomplet
                    </button>
                </div>

                @if ($canManage)
                    <div class="pricing-bulk">
                        <div>
                            <label class="field-label">Appliquer un prix fixe à tous</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input class="input input-sm" type="number" step="0.01" min="0" wire:model="bulkFixedPrice" placeholder="Ex. 1000">
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="applyBulkFixed">Appliquer</button>
                            </div>
                        </div>
                        <div>
                            <label class="field-label">Appliquer un prix/kg à tous</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input class="input input-sm" type="number" step="0.01" min="0" wire:model="bulkPerKgPrice" placeholder="Ex. 1500">
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="applyBulkPerKg">Appliquer</button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div style="padding:16px;">
                @if ($filteredPrices->isEmpty())
                    <p style="text-align:center;color:#64748b;padding:24px;">Aucun type dans ce filtre.
                        <a href="{{ route('tenant.pressing_settings.article_types', ['tenant' => request()->query('tenant') ?? session('tenant_code')]) }}">Créer des types</a>
                    </p>
                @else
                    <div class="pricing-type-grid">
                        @foreach ($filteredPrices as $row)
                            @php
                                $realIndex = collect($prices)->search(fn ($p) => $p['article_type_id'] === $row['article_type_id']);
                                $hasFixed = (float) ($row['amount'] ?? 0) > 0;
                                $hasKg = (float) ($row['price_per_kg'] ?? 0) > 0;
                            @endphp
                            <article class="pricing-type-card {{ ! ($row['is_active'] ?? true) ? 'pricing-type-card--inactive' : '' }}"
                                wire:key="price-type-{{ $row['article_type_id'] }}">
                                <div class="pricing-type-card__head">
                                    <div>
                                        <div class="pricing-type-card__name">{{ $row['name'] }}</div>
                                        @if ($row['code'])
                                            <div class="pricing-type-card__code">{{ $row['code'] }}</div>
                                        @endif
                                        <div class="pricing-badge-row">
                                            <span class="pricing-mini-badge {{ $hasFixed ? 'pricing-mini-badge--ok' : 'pricing-mini-badge--miss' }}">
                                                {{ $hasFixed ? '✓ Prix fixe' : 'Prix fixe manquant' }}
                                            </span>
                                            <span class="pricing-mini-badge {{ $hasKg ? 'pricing-mini-badge--ok' : 'pricing-mini-badge--miss' }}">
                                                {{ $hasKg ? '✓ Prix/kg' : 'Prix/kg manquant' }}
                                            </span>
                                        </div>
                                    </div>
                                    @if (! ($row['is_active'] ?? true))
                                        <span class="badge badge-neutral">Inactif</span>
                                    @endif
                                </div>

                                <div class="pricing-dual">
                                    <div class="pricing-field">
                                        <label>Prix fixe (FCFA / pièce)</label>
                                        <input class="input input-sm" type="number" step="0.01" min="0"
                                            wire:model.live="prices.{{ $realIndex }}.amount"
                                            @disabled(! $canManage)
                                            placeholder="0">
                                        @error('prices.'.$realIndex.'.amount')<div class="text-error" style="font-size:11px;margin-top:2px;">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="pricing-field">
                                        <label>Prix au kilo (FCFA / kg)</label>
                                        <input class="input input-sm" type="number" step="0.01" min="0"
                                            wire:model.live="prices.{{ $realIndex }}.price_per_kg"
                                            @disabled(! $canManage)
                                            placeholder="0">
                                        @error('prices.'.$realIndex.'.price_per_kg')<div class="text-error" style="font-size:11px;margin-top:2px;">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif

                @if ($canManage)
                    <div style="margin-top:16px;text-align:right;">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Enregistrer les tarifs</button>
                    </div>
                @endif
            </div>
        </section>
    </form>
</div>
