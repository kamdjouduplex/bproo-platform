@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code');
@endphp

<div class="page-body reception-create">
    @if (session('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <style>
        .reception-create { --rc-accent: #2563eb; --rc-muted: #64748b; }
        .reception-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .reception-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 16px;
            align-items: start;
        }
        @media (max-width: 1024px) {
            .reception-layout { grid-template-columns: 1fr; }
        }
        .reception-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 14px;
        }
        .reception-card__title {
            margin: 0 0 12px;
            font-size: .95rem;
            font-weight: 700;
            color: #0f172a;
        }
        .billing-modes {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        @media (max-width: 720px) {
            .billing-modes { grid-template-columns: 1fr; }
        }
        .billing-mode {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            text-align: left;
            background: #fff;
            cursor: pointer;
            transition: border-color .15s, background .15s, box-shadow .15s;
        }
        .billing-mode:hover { border-color: #94a3b8; background: #f8fafc; }
        .billing-mode--active {
            border-color: var(--rc-accent);
            background: #eff6ff;
            box-shadow: 0 0 0 1px rgba(37,99,235,.15);
        }
        .billing-mode__label { font-weight: 700; font-size: 13px; color: #0f172a; display: block; }
        .billing-mode__hint { font-size: 11px; color: #64748b; line-height: 1.35; margin-top: 4px; display: block; }
        .billing-mode__count { font-size: 10px; color: #0ea5e9; font-weight: 600; margin-top: 6px; display: block; }
        .line-mode-toggle {
            display: inline-flex;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 4px;
        }
        .line-mode-toggle button {
            border: 0;
            background: #fff;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
        }
        .line-mode-toggle button.active {
            background: #2563eb;
            color: #fff;
        }
        .line-mode-toggle button:disabled {
            opacity: .35;
            cursor: not-allowed;
        }
        .reception-hint-box {
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 12px;
            color: #475569;
            line-height: 1.45;
        }
        .reception-empty {
            text-align: center;
            padding: 28px 16px;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            color: #64748b;
            font-size: 13px;
        }
        .reception-flow {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }
        @media (max-width: 720px) {
            .reception-flow { grid-template-columns: 1fr; }
        }
        .reception-flow__step {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            background: #fff;
            font-size: 12px;
            color: #64748b;
            line-height: 1.45;
        }
        .reception-flow__step--active {
            border-color: #2563eb;
            background: #eff6ff;
            color: #1e40af;
        }
        .reception-flow__step--done {
            border-color: #86efac;
            background: #f0fdf4;
            color: #166534;
        }
        .reception-flow__step--done strong { color: #15803d; }
        .reception-flow__step strong {
            display: block;
            font-size: 13px;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .reception-flow__step--active strong { color: #1d4ed8; }
        .rc-search { font-size: 1.05rem !important; padding: 12px 14px !important; }
        .rc-result {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            text-align: left;
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: border-color .15s, background .15s, box-shadow .15s;
        }
        .rc-result:hover {
            border-color: #3fa796;
            background: #f0fdf9;
            box-shadow: 0 4px 12px rgba(63,167,150,.12);
        }
        .rc-result__main { display:flex; flex-direction:column; gap:2px; min-width:0; }
        .rc-result__meta { font-size:12px; color:#64748b; }
        .rc-result__cta { font-size:12px; font-weight:700; color:#0f766e; white-space:nowrap; }
        .rc-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 80;
            background: rgba(15, 23, 42, 0.48);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .rc-modal {
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            overflow: auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.2);
            display: flex;
            flex-direction: column;
        }
        .rc-modal__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            padding: 18px 20px 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .rc-modal__title { margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; }
        .rc-modal__hint { margin: 4px 0 0; font-size: 12px; color: #64748b; }
        .rc-modal__close {
            width: 32px; height: 32px; border-radius: 50%;
            border: 1px solid #e2e8f0; background: #fff; color: #64748b;
            font-size: 20px; line-height: 1; cursor: pointer;
        }
        .rc-modal__close:hover { background: #f1f5f9; color: #0f172a; }
        .rc-modal__body { padding: 16px 20px; }
        .rc-modal__foot {
            display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap;
            padding: 12px 20px 18px; border-top: 1px solid #f1f5f9;
        }
        .client-picker { position: relative; }
        .client-picker__list {
            position: absolute;
            left: 0; right: 0; top: calc(100% + 4px);
            z-index: 30;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 12px 28px rgba(15,23,42,.12);
            max-height: 220px;
            overflow-y: auto;
        }
        .client-picker__option {
            width: 100%;
            text-align: left;
            border: 0;
            background: transparent;
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }
        .client-picker__option:hover, .client-picker__option--selected { background: #f8fafc; }
        .items-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .items-table th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: var(--rc-muted);
            padding: 8px 6px;
            border-bottom: 1px solid #e2e8f0;
        }
        .items-table td { padding: 6px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .items-table .input { padding: 7px 8px; font-size: 13px; }
        .weight-hero {
            text-align: center;
            padding: 20px 16px;
            background: linear-gradient(180deg, #f0f9ff 0%, #fff 100%);
            border: 1px dashed #7dd3fc;
            border-radius: 14px;
        }
        .weight-hero__input {
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            max-width: 160px;
            margin: 0 auto;
            border: 0;
            border-bottom: 2px solid #0ea5e9;
            border-radius: 0;
            background: transparent;
        }
        .weight-hero__total {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            margin-top: 12px;
        }
        .reception-summary {
            position: sticky;
            top: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            background: #fff;
        }
        .reception-summary__row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 13px;
            padding: 5px 0;
            color: #475569;
        }
        .reception-summary__total {
            border-top: 1px solid #e2e8f0;
            margin-top: 10px;
            padding-top: 12px;
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
        }
        .reception-sticky-bar {
            display: none;
        }
        @media (max-width: 1024px) {
            .reception-sticky-bar {
                display: flex;
                position: fixed;
                left: 0; right: 0; bottom: 0;
                z-index: 50;
                padding: 12px 16px;
                background: #fff;
                border-top: 1px solid #e2e8f0;
                box-shadow: 0 -8px 24px rgba(15,23,42,.08);
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }
            .reception-create { padding-bottom: 72px; }
        }
        .item-details-toggle {
            font-size: 11px;
            color: var(--rc-accent);
            background: none;
            border: 0;
            cursor: pointer;
            padding: 0;
        }
        .item-details {
            padding: 8px 0 4px;
        }
        .reception-items-hint {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 12px;
            padding: 10px 12px;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 10px;
            line-height: 1.45;
        }
    </style>

    <div class="reception-top">
        <div>
            <h2 class="client-list-head__title" style="margin:0;">
                @if ($isEditing)
                    Modifier {{ $editingOrderNumber }}
                @else
                    Nouvelle réception
                @endif
            </h2>
            <p style="margin:4px 0 0;font-size:13px;color:#64748b;">
                @if ($lockedAgence) Agence <strong>{{ $lockedAgence->name }}</strong> · @endif
                @if ($isEditing)
                    Corrigez client, mode de facturation ou lignes
                @else
                    Délai {{ $defaultDelayHours }}h
                @endif
            </p>
        </div>
        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.pressing_orders.index', ['tenant' => $tenantCode]) }}">← Liste</a>
    </div>

    @if ($isEditing && $orderAlreadySorted)
        <div class="alert alert-warning" style="margin-bottom:14px;">
            Cette commande a déjà une <strong>constitution</strong>. Après modification des types / quantités facturés,
            vérifiez éventuellement le tri si le contenu a changé.
        </div>
    @endif

    @unless ($isEditing)
    <div class="reception-flow">
        <div class="reception-flow__step {{ $receptionStep === 'client' ? 'reception-flow__step--active' : 'reception-flow__step--done' }}">
            <strong>1. {{ __('Identifier le client') }}</strong>
            {{ __('Recherche dans la base — création rapide si nouveau.') }}
        </div>
        <div class="reception-flow__step {{ $receptionStep === 'order' ? 'reception-flow__step--active' : '' }}">
            <strong>2. {{ __('Créer la commande') }}</strong>
            {{ __('Articles, tarifs, avance éventuelle.') }}
        </div>
        <div class="reception-flow__step">
            <strong>3. {{ __('Constitution (Tri)') }}</strong>
            {{ __('Détail des pièces puis production.') }}
        </div>
    </div>
    @endunless

    @if (! $isEditing && $receptionStep === 'client')
        {{-- ========== STEP 1: CLIENT LOOKUP ========== --}}
        <div class="reception-card rc-client-step">
            <h3 class="reception-card__title">{{ __('Qui dépose aujourd’hui ?') }}</h3>
            <p style="margin:0 0 14px;font-size:13px;color:#64748b;">
                {{ __('Cherchez d’abord dans la base clients (toutes agences). Si le client n’existe pas, créez-le en quelques secondes.') }}
            </p>

            @if ($canPickAgence)
                <div class="field" style="margin-bottom:12px;max-width:320px;">
                    <label class="field-label">{{ __('Agence de dépôt') }} *</label>
                    <select class="input" wire:model.live="agence_id">
                        <option value="">{{ __('Choisir…') }}</option>
                        @foreach ($agences as $agence)
                            <option value="{{ $agence->id }}">{{ $agence->name }}</option>
                        @endforeach
                    </select>
                    @error('agence_id')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror
                </div>
            @else
                <input type="hidden" wire:model="agence_id">
                <div style="margin-bottom:12px;font-size:13px;color:#64748b;">
                    {{ __('Agence') }} : <strong>{{ $lockedAgence?->name }}</strong>
                </div>
            @endif

            <div class="field">
                <label class="field-label">{{ __('Rechercher un client') }}</label>
                <input class="input rc-search"
                       type="search"
                       wire:model.live.debounce.250ms="client_search"
                       placeholder="{{ __('Nom, prénom, WhatsApp ou code…') }}"
                       autocomplete="off"
                       autofocus>
            </div>

            @if (trim($client_search) === '')
                <div class="reception-empty" style="margin-top:16px;">
                    {{ __('Tapez au moins quelques lettres ou un numéro WhatsApp pour démarrer.') }}
                </div>
            @elseif ($clients->isNotEmpty())
                <div class="rc-results" style="margin-top:14px;">
                    <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:8px;">
                        {{ trans_choice(':count résultat|:count résultats', $clients->count(), ['count' => $clients->count()]) }}
                    </div>
                    @foreach ($clients as $client)
                        <button type="button" class="rc-result" wire:click="selectClient({{ $client->id }})">
                            <div class="rc-result__main">
                                <strong>{{ $client->full_name }}</strong>
                                <span class="rc-result__meta">
                                    {{ $client->whatsapp }}
                                    @if ($client->agence)
                                        · {{ $client->agence->name }}
                                    @endif
                                    · {{ $client->code }}
                                </span>
                            </div>
                            <span class="rc-result__cta">{{ __('Sélectionner') }} →</span>
                        </button>
                    @endforeach
                </div>
                @if ($canCreateClient)
                    <div style="margin-top:14px;padding-top:14px;border-top:1px dashed #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                        <span style="font-size:13px;color:#64748b;">{{ __('Ce n’est pas le bon client ?') }}</span>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="openQuickCreate">
                            {{ __('Créer un nouveau client') }}
                        </button>
                    </div>
                @endif
            @else
                <div class="rc-empty-found" style="margin-top:16px;">
                    <div class="reception-empty" style="border-color:#fbbf24;background:#fffbeb;">
                        <strong style="display:block;color:#92400e;margin-bottom:4px;">{{ __('Aucun client trouvé') }}</strong>
                        {{ __('Aucun résultat pour « :q ».', ['q' => $client_search]) }}
                    </div>
                    @if ($canCreateClient)
                        <button type="button" class="btn btn-primary" style="margin-top:12px;width:100%;" wire:click="openQuickCreate">
                            {{ __('Créer ce client et continuer') }}
                        </button>
                    @else
                        <p style="font-size:12px;color:#94a3b8;margin-top:10px;">
                            {{ __('Vous n’avez pas la permission de créer un client.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        @if ($showQuickCreate)
            <div class="rc-modal-backdrop" wire:click.self="cancelQuickCreate" wire:key="qc-modal">
                <div class="rc-modal" role="dialog" aria-modal="true" aria-labelledby="rc-modal-title">
                    <div class="rc-modal__head">
                        <div>
                            <h4 id="rc-modal-title" class="rc-modal__title">{{ __('Nouveau client') }}</h4>
                            <p class="rc-modal__hint">{{ __('Champs essentiels uniquement — vous pourrez compléter plus tard.') }}</p>
                        </div>
                        <button type="button" class="rc-modal__close" wire:click="cancelQuickCreate" aria-label="{{ __('Fermer') }}">×</button>
                    </div>
                    <div class="rc-modal__body">
                        <div class="form-grid">
                            <div class="field">
                                <label class="field-label">{{ __('Prénom') }} *</label>
                                <input class="input" type="text" wire:model="qc_first_name" autofocus>
                                @error('qc_first_name')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label class="field-label">{{ __('Nom') }} *</label>
                                <input class="input" type="text" wire:model="qc_last_name">
                                @error('qc_last_name')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label class="field-label">WhatsApp *</label>
                                <input class="input" type="text" wire:model="qc_whatsapp" placeholder="6XX XXX XXX">
                                @error('qc_whatsapp')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label class="field-label">{{ __('Téléphone') }}</label>
                                <input class="input" type="text" wire:model="qc_phone" placeholder="{{ __('optionnel') }}">
                            </div>
                            <div class="field">
                                <label class="field-label">Email</label>
                                <input class="input" type="email" wire:model="qc_email" placeholder="{{ __('optionnel') }}">
                                @error('qc_email')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label class="field-label">{{ __('Adresse') }}</label>
                                <input class="input" type="text" wire:model="qc_address" placeholder="{{ __('optionnel') }}">
                            </div>
                        </div>
                    </div>
                    <div class="rc-modal__foot">
                        <button type="button" class="btn btn-secondary" wire:click="cancelQuickCreate">{{ __('Annuler') }}</button>
                        <button type="button" class="btn btn-primary" wire:click="saveQuickClient" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveQuickClient">{{ __('Enregistrer et passer à la commande') }}</span>
                            <span wire:loading wire:target="saveQuickClient">{{ __('Enregistrement…') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @else
        {{-- ========== STEP 2 / EDIT: ORDER FORM ========== --}}
        @if (session('success'))
            <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
        @endif

    <form wire:submit.prevent="save">
        <div class="reception-layout">
            <div>
                {{-- Selected client banner --}}
                @if ($selectedClient && ! $isEditing)
                    <div class="reception-card" style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;border-color:#86efac;background:linear-gradient(180deg,#ecfdf5,#fff);">
                        <div>
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#15803d;">{{ __('Client sélectionné') }}</div>
                            <div style="font-weight:700;font-size:1rem;">{{ $selectedClient->full_name }}</div>
                            <div style="font-size:13px;color:#64748b;">
                                {{ $selectedClient->whatsapp }}
                                @if ($selectedClient->agence) · {{ $selectedClient->agence->name }} @endif
                                · {{ $selectedClient->code }}
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="backToClientSearch">{{ __('Changer de client') }}</button>
                    </div>

                    @if ($appliedRewardId)
                        <div style="margin-bottom:14px;padding:8px 10px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;font-size:13px;display:flex;justify-content:space-between;align-items:center;gap:8px;">
                            <span>🎁 {{ __('Récompense appliquée') }} : <strong>{{ $appliedRewardLabel }}</strong></span>
                            <button type="button" style="border:0;background:none;color:#dc2626;cursor:pointer;" wire:click="clearReward">{{ __('Retirer') }}</button>
                        </div>
                    @elseif ($availableRewards->isNotEmpty())
                        <div style="margin-bottom:14px;padding:8px 10px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;font-size:13px;">
                            <div style="margin-bottom:6px;color:#9a3412;">🎁 {{ __('Récompenses fidélité disponibles') }}</div>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                @foreach ($availableRewards as $reward)
                                    <button type="button" class="btn btn-secondary btn-sm"
                                            wire:click="applyReward({{ $reward->id }})">
                                        {{ $reward->code }} · {{ $reward->label() }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif

                {{-- Mode de facturation --}}
                <div class="reception-card">
                    <h3 class="reception-card__title">{{ __('Mode de facturation') }}</h3>
                    <div class="billing-modes" role="radiogroup">
                        @foreach ($billingModes as $modeKey => $modeLabel)
                            <button type="button"
                                class="billing-mode {{ $billing_mode === $modeKey ? 'billing-mode--active' : '' }}"
                                wire:click="setBillingMode('{{ $modeKey }}')">
                                <span class="billing-mode__label">{{ $modeLabel }}</span>
                                <span class="billing-mode__hint">{{ \Pressing\Support\PressingBilling::modeDescription($modeKey) }}</span>
                                @if ($modeKey === 'mixed')
                                    <span class="billing-mode__count">{{ $typeCounts['mixed'] }} type(s) disponibles</span>
                                @elseif ($modeKey === 'fixed')
                                    <span class="billing-mode__count">{{ $typeCounts['fixed'] }} type(s) prix fixe</span>
                                @elseif ($modeKey === 'weight_by_type')
                                    <span class="billing-mode__count">{{ $typeCounts['per_kg'] }} type(s) au kilo</span>
                                @else
                                    <span class="billing-mode__count">Tarif global {{ number_format((float) $weight_unit_price, 0, ',', ' ') }} F/kg</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                    <div class="reception-hint-box">
                        @if ($billing_mode === 'mixed')
                            Combinez librement les lignes : prix fixe et/ou kilo. Sur chaque ligne, choisissez
                            <strong>Pièce</strong> ou <strong>Kilo</strong> si le type a les deux tarifs ({{ $compatibleCount }} type(s)).
                        @elseif ($billing_mode === 'fixed')
                            Seuls les types avec un <strong>prix fixe</strong> renseigné apparaissent ({{ $compatibleCount }}).
                        @elseif ($billing_mode === 'weight_by_type')
                            Seuls les types avec un <strong>prix/kg</strong> renseigné apparaissent ({{ $compatibleCount }}).
                        @else
                            Pesée globale du lot au <strong>prix fixe au kilo (tout cou)</strong>. Vous pouvez lister les pièces pour le suivi.
                        @endif
                        @if ($compatibleCount === 0 && $billing_mode !== 'weight_global')
                            · <a href="{{ route('tenant.pressing_settings.prices', ['tenant' => $tenantCode]) }}">Configurer les tarifs</a>
                        @endif
                    </div>
                </div>

                {{-- Client (edit mode only — new orders already chose the client in step 1) --}}
                @if ($isEditing)
                <div class="reception-card">
                    <h3 class="reception-card__title">{{ __('Client & agence') }}</h3>
                    <div class="form-grid">
                        @if ($canPickAgence)
                            <div class="field">
                                <label class="field-label">{{ __('Agence') }} *</label>
                                <select class="input" wire:model.live="agence_id" required>
                                    <option value="">{{ __('Choisir…') }}</option>
                                    @foreach ($agences as $agence)
                                        <option value="{{ $agence->id }}">{{ $agence->name }}</option>
                                    @endforeach
                                </select>
                                @error('agence_id')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror
                            </div>
                        @else
                            <input type="hidden" wire:model="agence_id">
                            <div class="field">
                                <label class="field-label">{{ __('Agence') }}</label>
                                <div class="input" style="background:#f8fafc;">{{ $lockedAgence?->name }}</div>
                            </div>
                        @endif

                        <div class="field client-picker" style="grid-column:1/-1;">
                            <label class="field-label">{{ __('Client') }} *</label>
                            @if ($selectedClient)
                                <div style="padding:8px 10px;background:#ecfdf5;border:1px solid #86efac;border-radius:8px;font-size:13px;">
                                    ✓ <strong>{{ $selectedClient->full_name }}</strong> · {{ $selectedClient->whatsapp }}
                                </div>
                            @endif
                            @error('client_id')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror
                        </div>

                        <div class="field" style="grid-column:1/-1;">
                            <label class="field-label">{{ __('Notes') }}</label>
                            <textarea class="input" rows="2" wire:model="notes" placeholder="{{ __('Instructions particulières…') }}"></textarea>
                        </div>
                    </div>
                </div>
                @else
                <div class="reception-card">
                    <h3 class="reception-card__title">{{ __('Notes') }}</h3>
                    @if ($canPickAgence)
                        <div class="field" style="margin-bottom:10px;max-width:320px;">
                            <label class="field-label">{{ __('Agence de dépôt') }}</label>
                            <select class="input" wire:model.live="agence_id">
                                @foreach ($agences as $agence)
                                    <option value="{{ $agence->id }}">{{ $agence->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" wire:model="agence_id">
                    @endif
                    <textarea class="input" rows="2" wire:model="notes" placeholder="{{ __('Instructions particulières…') }}"></textarea>
                </div>
                @endif

                {{-- Articles selon mode --}}
                <div class="reception-card">
                    @if ($billing_mode === 'weight_global')
                        <h3 class="reception-card__title">Pesée — tout cou</h3>
                        <div class="weight-hero">
                            <label class="field-label" style="display:block;margin-bottom:8px;">Poids total (kg)</label>
                            <input class="input weight-hero__input" type="number" step="0.001" min="0.001"
                                wire:model.live="total_weight_kg" placeholder="0.000" autofocus>
                            @error('total_weight_kg')<div class="text-error" style="font-size:12px;margin-top:6px;">{{ $message }}</div>@enderror

                            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:16px;">
                                <div>
                                    <label class="field-label">Prix / kg</label>
                                    <input class="input" type="number" step="0.01" min="0" wire:model.live="weight_unit_price" style="max-width:140px;text-align:center;">
                                </div>
                            </div>
                            @error('weight_unit_price')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror

                            <div class="weight-hero__total">
                                {{ number_format($this->subtotal, 0, ',', ' ') }} <span style="font-size:14px;font-weight:600;color:#64748b;">FCFA</span>
                            </div>
                        </div>

                        <details style="margin-top:16px;">
                            <summary style="cursor:pointer;font-size:13px;font-weight:600;color:#475569;">Détail des pièces (optionnel, sans impact sur le prix)</summary>
                            <div style="margin-top:10px;">
                                @foreach ($items as $index => $item)
                                    <div style="display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                                        <select class="input" wire:model="items.{{ $index }}.article_type_id" style="flex:1;min-width:140px;">
                                            <option value="">Type…</option>
                                            @foreach ($allArticleTypes as $type)
                                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                                            @endforeach
                                        </select>
                                        <input class="input" type="number" min="1" wire:model="items.{{ $index }}.quantity" style="width:70px;" placeholder="Qté">
                                        @if (count($items) > 1)
                                            <button type="button" class="btn btn-secondary btn-sm" wire:click="removeItem({{ $index }})">×</button>
                                        @endif
                                    </div>
                                @endforeach
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="addItem">+ Pièce</button>
                            </div>
                        </details>

                    @elseif ($billing_mode === 'mixed')
                        @if ($compatibleCount === 0)
                            <div class="reception-empty">
                                Aucun type avec tarif (fixe ou kilo) configuré.<br>
                                <a class="btn btn-primary btn-sm" style="margin-top:12px;" href="{{ route('tenant.pressing_settings.prices', ['tenant' => $tenantCode]) }}">Configurer les tarifs</a>
                            </div>
                        @else
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                            <h3 class="reception-card__title" style="margin:0;">Articles — mixte</h3>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="addItem">+ Ligne</button>
                        </div>
                        <p class="reception-items-hint">
                            Ajoutez des lignes en <strong>prix fixe</strong> et d’autres <strong>au kilo</strong> sur la même commande.
                            Le détail (couleurs…) se fait ensuite à la <strong>Constitution</strong>.
                        </p>
                        <div class="table-wrap">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Type / mode</th>
                                        <th style="width:80px;">Qté / kg</th>
                                        <th style="width:100px;">Tarif</th>
                                        <th style="width:90px;text-align:right;">Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $index => $item)
                                        @php
                                            $selectedType = $articleTypes->firstWhere('id', (int) ($item['article_type_id'] ?? 0));
                                            $canFixed = $selectedType ? \Pressing\Support\PressingBilling::hasFixedPrice($selectedType, $agence_id) : true;
                                            $canKg = $selectedType ? \Pressing\Support\PressingBilling::hasPerKgPrice($selectedType, $agence_id) : true;
                                            $linePerKg = \Pressing\Support\PressingBilling::isLinePerKg('mixed', $item);
                                        @endphp
                                        <tr wire:key="item-mixed-{{ $index }}">
                                            <td>
                                                <select class="input" wire:model.live="items.{{ $index }}.article_type_id" required>
                                                    <option value="">—</option>
                                                    @foreach ($articleTypes as $type)
                                                        @php
                                                            $tf = \Pressing\Support\PressingBilling::hasFixedPrice($type, $agence_id);
                                                            $tk = \Pressing\Support\PressingBilling::hasPerKgPrice($type, $agence_id);
                                                            $hint = $tf && $tk ? 'fixe + kg' : ($tk ? 'kg' : 'fixe');
                                                        @endphp
                                                        <option value="{{ $type->id }}">{{ $type->name }} ({{ $hint }})</option>
                                                    @endforeach
                                                </select>
                                                <div class="line-mode-toggle" role="group" aria-label="Mode de la ligne">
                                                    <button type="button"
                                                        class="{{ ! $linePerKg ? 'active' : '' }}"
                                                        @disabled(! $canFixed)
                                                        wire:click="setLinePricingMode({{ $index }}, 'fixed')">
                                                        Pièce
                                                    </button>
                                                    <button type="button"
                                                        class="{{ $linePerKg ? 'active' : '' }}"
                                                        @disabled(! $canKg)
                                                        wire:click="setLinePricingMode({{ $index }}, 'per_kg')">
                                                        Kilo
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                @if ($linePerKg)
                                                    <input class="input" type="number" step="0.001" min="0.001"
                                                           wire:model.live="items.{{ $index }}.weight_kg" placeholder="kg">
                                                    @error('items.'.$index.'.weight_kg')<div class="text-error" style="font-size:11px;">{{ $message }}</div>@enderror
                                                @else
                                                    <input class="input" type="number" min="1"
                                                           wire:model.live="items.{{ $index }}.quantity">
                                                    @error('items.'.$index.'.quantity')<div class="text-error" style="font-size:11px;">{{ $message }}</div>@enderror
                                                @endif
                                            </td>
                                            <td>
                                                @if ($linePerKg)
                                                    <input class="input" type="number" step="0.01" min="0"
                                                           wire:model.live="items.{{ $index }}.price_per_kg" title="Prix/kg">
                                                @else
                                                    <input class="input" type="number" step="0.01" min="0"
                                                           wire:model.live="items.{{ $index }}.unit_price" title="Prix unitaire">
                                                @endif
                                            </td>
                                            <td style="text-align:right;font-weight:700;">
                                                {{ number_format(\Pressing\Support\PressingBilling::lineTotal('mixed', $item), 0, ',', ' ') }}
                                            </td>
                                            <td>
                                                @if (count($items) > 1)
                                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="removeItem({{ $index }})">×</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($this->totalWeight > 0)
                            <p style="font-size:12px;color:#64748b;margin:8px 0 0;">
                                Poids (lignes kilo) : <strong>{{ number_format($this->totalWeight, 3, ',', ' ') }} kg</strong>
                            </p>
                        @endif
                        @endif

                    @elseif ($billing_mode === 'weight_by_type')
                        @if ($compatibleCount === 0)
                            <div class="reception-empty">
                                Aucun type « au kilo » configuré.<br>
                                <a class="btn btn-primary btn-sm" style="margin-top:12px;" href="{{ route('tenant.pressing_settings.prices', ['tenant' => $tenantCode]) }}">Configurer les tarifs</a>
                            </div>
                        @else
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                            <h3 class="reception-card__title" style="margin:0;">Articles au kilo</h3>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="addItem">+ Ligne</button>
                        </div>
                        <div class="table-wrap">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th style="width:90px;">Poids kg</th>
                                        <th style="width:90px;">Prix/kg</th>
                                        <th style="width:90px;text-align:right;">Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $index => $item)
                                        <tr wire:key="item-{{ $index }}">
                                            <td>
                                                <select class="input" wire:model.live="items.{{ $index }}.article_type_id" required>
                                                    <option value="">—</option>
                                                    @foreach ($articleTypes as $type)
                                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td><input class="input" type="number" step="0.001" min="0.001" wire:model.live="items.{{ $index }}.weight_kg" placeholder="0"></td>
                                            <td><input class="input" type="number" step="0.01" min="0" wire:model.live="items.{{ $index }}.price_per_kg"></td>
                                            <td style="text-align:right;font-weight:700;">
                                                {{ number_format(\Pressing\Support\PressingBilling::lineTotal('weight_by_type', $item), 0, ',', ' ') }}
                                            </td>
                                            <td>
                                                @if (count($items) > 1)
                                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="removeItem({{ $index }})">×</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p style="font-size:12px;color:#64748b;margin:8px 0 0;">Poids total : <strong>{{ number_format($this->totalWeight, 3, ',', ' ') }} kg</strong></p>
                        @endif

                    @else
                        @if ($compatibleCount === 0)
                            <div class="reception-empty">
                                Aucun type en prix fixe configuré.<br>
                                <a class="btn btn-primary btn-sm" style="margin-top:12px;" href="{{ route('tenant.pressing_settings.prices', ['tenant' => $tenantCode]) }}">Configurer les tarifs</a>
                            </div>
                        @else
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                            <h3 class="reception-card__title" style="margin:0;">Articles — prix fixe</h3>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="addItem">+ Ligne</button>
                        </div>
                        <p class="reception-items-hint">
                            Saisie facturation uniquement. Le détail du lot (couleurs, descriptifs) se fait juste après à l’étape <strong>Constitution</strong>.
                        </p>
                        <div class="table-wrap">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th style="width:70px;">Qté</th>
                                        <th style="width:100px;">P.U.</th>
                                        <th style="width:90px;text-align:right;">Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $index => $item)
                                        <tr wire:key="item-{{ $index }}">
                                            <td>
                                                <select class="input" wire:model.live="items.{{ $index }}.article_type_id" required>
                                                    <option value="">—</option>
                                                    @foreach ($articleTypes as $type)
                                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td><input class="input" type="number" min="1" wire:model.live="items.{{ $index }}.quantity"></td>
                                            <td><input class="input" type="number" step="0.01" min="0" wire:model.live="items.{{ $index }}.unit_price"></td>
                                            <td style="text-align:right;font-weight:700;">
                                                {{ number_format(((int)($item['quantity']??0)) * ((float)($item['unit_price']??0)), 0, ',', ' ') }}
                                            </td>
                                            <td>
                                                @if (count($items) > 1)
                                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="removeItem({{ $index }})">×</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    @endif
                </div>
            </div>

            <aside class="reception-summary">
                <h3 style="margin:0 0 12px;font-size:1rem;">Récapitulatif</h3>
                <div style="font-size:12px;color:#64748b;margin-bottom:10px;">
                    {{ \Pressing\Support\PressingBilling::modeLabel($billing_mode) }}
                </div>

                <div class="reception-summary__row">
                    <span>Sous-total</span>
                    <strong>{{ number_format($this->subtotal, 0, ',', ' ') }}</strong>
                </div>
                @if (in_array($billing_mode, ['weight_global', 'weight_by_type', 'mixed'], true) && $this->totalWeight > 0)
                    <div class="reception-summary__row">
                        <span>Poids</span>
                        <strong>{{ number_format($this->totalWeight, 3, ',', ' ') }} kg</strong>
                    </div>
                @endif
                @if (in_array($billing_mode, ['fixed', 'mixed'], true))
                    <div class="reception-summary__row">
                        <span>Articles</span>
                        <strong>{{ $this->itemsCount }}</strong>
                    </div>
                @endif

                <div class="field" style="margin:10px 0;">
                    <label class="field-label">Remise</label>
                    <input class="input" type="number" step="0.01" min="0" wire:model.live="discount_amount">
                </div>
                @if ($taxEnabled)
                    <div class="reception-summary__row">
                        <span>TVA ({{ $taxRate }}%)</span>
                        <strong>{{ number_format($this->computedTax, 0, ',', ' ') }}</strong>
                    </div>
                @else
                    <div class="field" style="margin:8px 0;">
                        <label class="field-label">Taxes</label>
                        <input class="input" type="number" step="0.01" min="0" wire:model.live="tax_amount">
                    </div>
                @endif

                <div class="reception-summary__row reception-summary__total">
                    <span>Total</span>
                    <span>{{ number_format($this->grandTotal, 0, ',', ' ') }} FCFA</span>
                </div>

                <div class="field" style="margin-top:14px;">
                    <label class="field-label">Avance</label>
                    <input class="input" type="number" step="0.01" min="0" wire:model="advance_amount" placeholder="0">
                    @if ($isEditing)
                        <p style="margin:6px 0 0;font-size:11px;color:#64748b;line-height:1.4;">
                            Corrigez l’avance de réception ici.
                            @if ($other_payments_total > 0)
                                Autres paiements déjà enregistrés :
                                <strong>{{ number_format($other_payments_total, 0, ',', ' ') }} FCFA</strong>
                                (inchangés).
                            @endif
                        </p>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;margin-top:14px;padding:11px;" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">
                        {{ $isEditing ? 'Enregistrer les corrections' : 'Enregistrer la réception → Tri' }}
                    </span>
                    <span wire:loading wire:target="save">Enregistrement…</span>
                </button>
            </aside>
        </div>

        <div class="reception-sticky-bar">
            <div>
                <div style="font-size:11px;color:#64748b;">Total</div>
                <strong style="font-size:1.1rem;">{{ number_format($this->grandTotal, 0, ',', ' ') }} FCFA</strong>
            </div>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                {{ $isEditing ? 'Enregistrer' : 'Réception → Tri' }}
            </button>
        </div>
    </form>
    @endif
</div>
