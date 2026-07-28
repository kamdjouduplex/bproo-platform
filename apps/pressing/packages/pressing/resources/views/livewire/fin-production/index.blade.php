@php
    $list = $tab === 'pretes' ? $pretes : $aControler;
    // Dynamic stepper: 1=CQ list, 2=packaging form open, 3=ready/delivery
    if ($active && $active->status === 'ready') {
        $step = 3;
    } elseif ($active && $active->status === 'open') {
        $step = 2;
    } elseif ($tab === 'pretes') {
        $step = 3;
    } else {
        $step = 1;
    }
@endphp

<div class="page-body fin-prod">
    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <style>
        .fin-prod__layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 400px;
            gap: 16px;
            align-items: start;
        }
        @media (max-width: 960px) {
            .fin-prod__layout { grid-template-columns: 1fr; }
            .fin-prod__panel { order: -1; }
        }
        .fin-prod__tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .fin-prod__tab {
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            color: #475569;
        }
        .fin-prod__tab--active {
            border-color: #f59e0b;
            background: #fffbeb;
            color: #b45309;
        }
        .fin-prod__card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: border-color .15s, box-shadow .15s;
        }
        .fin-prod__card:hover { border-color: #94a3b8; }
        .fin-prod__card--active {
            border-color: #f59e0b;
            box-shadow: 0 0 0 1px rgba(245,158,11,.25);
        }
        .fin-prod__panel {
            position: sticky;
            top: 16px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
        }
        .fin-prod__const {
            list-style: none;
            margin: 10px 0 0;
            padding: 0;
        }
        .fin-prod__const li {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            background: #f8fafc;
        }
        .fin-prod__steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        .fin-prod__step {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
            font-size: 11px;
            color: #94a3b8;
            text-align: center;
            line-height: 1.35;
            background: #fff;
            position: relative;
            cursor: pointer;
            transition: border-color .15s, background .15s, box-shadow .15s;
            font-family: inherit;
            width: 100%;
            display: block;
        }
        .fin-prod__step:hover { border-color: #cbd5e1; }
        .fin-prod__step strong { display: block; color: #94a3b8; font-size: 12px; margin-bottom: 2px; }
        .fin-prod__step--done {
            border-color: #86efac;
            background: #f0fdf4;
            color: #166534;
        }
        .fin-prod__step--done strong { color: #15803d; }
        .fin-prod__step--on {
            border-color: #f59e0b;
            background: #fffbeb;
            color: #92400e;
            box-shadow: 0 0 0 1px rgba(245,158,11,.2);
        }
        .fin-prod__step--on strong { color: #b45309; }
        .fin-prod__step-num {
            display: inline-flex;
            width: 18px; height: 18px;
            align-items: center; justify-content: center;
            border-radius: 50%;
            font-size: 10px; font-weight: 800;
            margin-right: 4px;
            background: #e2e8f0; color: #64748b;
        }
        .fin-prod__step--on .fin-prod__step-num { background: #f59e0b; color: #fff; }
        .fin-prod__step--done .fin-prod__step-num { background: #22c55e; color: #fff; }
        .delivery-choice {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .delivery-choice button {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            background: #fff;
            cursor: pointer;
            text-align: left;
        }
        .delivery-choice button strong { display: block; font-size: 13px; margin-bottom: 4px; }
        .delivery-choice button span { font-size: 11px; color: #64748b; line-height: 1.35; }
        .delivery-choice button.active {
            border-color: #2563eb;
            background: #eff6ff;
        }
        .fin-prod__panel-section {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
        }
        .fin-prod__panel-section-title {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 8px;
        }
    </style>

    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <h2 class="client-list-head__title" style="margin:0;">Fin de production</h2>
            <p style="margin:4px 0 0;font-size:13px;color:#64748b;">Contrôle qualité → Emballage → Mise en livraison</p>
        </div>
        <div style="display:flex;gap:8px;">
            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.pressing_workflow.index', ['tenant' => $tenantCode]) }}">Kanban</a>
            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.pressing_deliveries.index', ['tenant' => $tenantCode]) }}">Livraisons</a>
        </div>
    </div>

    <div class="fin-prod__steps" aria-label="Progression">
        <button type="button" class="fin-prod__step {{ $step > 1 ? 'fin-prod__step--done' : ($step === 1 ? 'fin-prod__step--on' : '') }}"
                wire:click="switchTab('a_controler')">
            <strong><span class="fin-prod__step-num">{{ $step > 1 ? '✓' : '1' }}</span> Contrôle qualité</strong>
            {{ $step === 1 ? 'Sélectionnez une commande à vérifier' : ($step > 1 ? 'Lot vérifié' : 'En attente') }}
        </button>
        <div class="fin-prod__step {{ $step > 2 ? 'fin-prod__step--done' : ($step === 2 ? 'fin-prod__step--on' : '') }}">
            <strong><span class="fin-prod__step-num">{{ $step > 2 ? '✓' : '2' }}</span> Emballage</strong>
            {{ $step === 2 ? 'Confirmez emballage & mode de remise' : ($step > 2 ? 'Emballé' : 'Ouvrez une commande de l’onglet À contrôler') }}
        </div>
        <button type="button" class="fin-prod__step {{ $step === 3 ? 'fin-prod__step--on' : ($step > 3 ? 'fin-prod__step--done' : '') }}"
                wire:click="switchTab('pretes')">
            <strong><span class="fin-prod__step-num">{{ $step > 3 ? '✓' : '3' }}</span> Livraison</strong>
            {{ $step === 3 ? 'Prête — remettez via le menu Livraisons' : 'Après emballage, statut Prêt' }}
        </button>
    </div>

    <div class="fin-prod__layout">
        <div>
            <div class="fin-prod__tabs">
                <button type="button" class="fin-prod__tab {{ $tab === 'a_controler' ? 'fin-prod__tab--active' : '' }}"
                        wire:click="switchTab('a_controler')">
                    À contrôler · {{ $aControler->count() }}
                </button>
                <button type="button" class="fin-prod__tab {{ $tab === 'pretes' ? 'fin-prod__tab--active' : '' }}"
                        wire:click="switchTab('pretes')">
                    Emballées · {{ $pretes->count() }}
                </button>
            </div>

            <input class="input" type="search" wire:model.live.debounce.250ms="search"
                   placeholder="N° commande, client, WhatsApp…" style="margin-bottom:12px;">

            @forelse ($list as $order)
                <div class="fin-prod__card {{ $activeOrderId === $order->id ? 'fin-prod__card--active' : '' }}"
                     wire:key="fp-{{ $order->id }}"
                     wire:click="openOrder({{ $order->id }})">
                    <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                        <div>
                            <div style="font-weight:800;">{{ $order->number }}</div>
                            <div style="font-size:13px;color:#475569;">{{ $order->client?->full_name }}</div>
                        </div>
                        <span class="badge {{ $order->status === 'ready' ? 'badge-success' : 'badge-info' }}">
                            {{ $order->status === 'ready' ? 'Emballée' : 'À CQ' }}
                        </span>
                    </div>
                    @if ($order->constitutionSummary())
                        <div style="font-size:12px;color:#64748b;margin-top:8px;line-height:1.4;">
                            {{ \Illuminate\Support\Str::limit($order->constitutionSummary(), 90) }}
                        </div>
                    @endif
                    <div style="font-size:11px;margin-top:8px;color:#94a3b8;">
                        Total {{ number_format((float) $order->total, 0, ',', ' ') }}
                        · reste <strong style="color:{{ (float)$order->balance > 0 ? '#c2410c' : '#15803d' }};">{{ number_format((float) $order->balance, 0, ',', ' ') }}</strong>
                    </div>
                </div>
            @empty
                <div style="padding:28px;text-align:center;border:1px dashed #cbd5e1;border-radius:12px;color:#64748b;font-size:13px;">
                    @if ($tab === 'a_controler')
                        Aucune commande en fin de production.<br>
                        Déplacez une carte jusqu’à « Fin de production » sur le Kanban.
                    @else
                        Aucune commande emballée pour le moment.
                    @endif
                </div>
            @endforelse
        </div>

        <aside class="fin-prod__panel">
            @if ($active)
                <div style="display:flex;justify-content:space-between;gap:8px;align-items:start;">
                    <div>
                        <div style="font-weight:800;font-size:1.05rem;">{{ $active->number }}</div>
                        <div style="font-size:13px;color:#475569;">{{ $active->client?->full_name }} · {{ $active->client?->whatsapp }}</div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closePanel">×</button>
                </div>

                <div class="fin-prod__panel-section">
                    <div class="fin-prod__panel-section-title">1 · Contenu à contrôler</div>
                    <ul class="fin-prod__const">
                        @forelse ($active->constitutionLines as $line)
                            <li>{{ $line->label() }}</li>
                        @empty
                            <li style="font-weight:500;color:#94a3b8;">Constitution non renseignée</li>
                        @endforelse
                    </ul>
                </div>

                @if ($active->status === 'open' && $canProcess)
                    <div class="fin-prod__panel-section">
                        <div class="fin-prod__panel-section-title">2 · Emballage & remise</div>
                        <div class="field">
                            <label class="field-label">Notes contrôle qualité</label>
                            <textarea class="input" rows="2" wire:model="qc_notes" placeholder="Optionnel…"></textarea>
                        </div>

                        <div style="margin-top:12px;">
                            <label class="field-label">Mode de remise</label>
                            <div class="delivery-choice" style="margin-top:6px;">
                                <button type="button"
                                        class="{{ $delivery_type === 'agence' ? 'active' : '' }}"
                                        wire:click="$set('delivery_type', 'agence')">
                                    <strong>Retrait agence</strong>
                                    <span>Remise au comptoir par la réceptionniste.</span>
                                </button>
                                <button type="button"
                                        class="{{ $delivery_type === 'domicile' ? 'active' : '' }}"
                                        wire:click="$set('delivery_type', 'domicile')">
                                    <strong>Domicile</strong>
                                    <span>Acheminement chauffeur / livraison.</span>
                                </button>
                            </div>
                        </div>

                        @if ($delivery_type === 'domicile')
                            <div class="field" style="margin-top:12px;">
                                <label class="field-label">Adresse de livraison *</label>
                                <input class="input" wire:model="delivery_address" placeholder="Adresse complète…">
                                @error('delivery_address')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror
                            </div>
                        @endif

                        <div class="field" style="margin-top:10px;">
                            <label class="field-label">Notes livraison</label>
                            <input class="input" wire:model="delivery_notes" placeholder="Ex. appeler avant…">
                        </div>

                        <div style="display:flex;flex-direction:column;gap:8px;margin-top:16px;">
                            <button type="button" class="btn btn-primary" style="width:100%;"
                                    wire:click="validateAndPackage({{ $active->id }})"
                                    wire:confirm="Confirmer CQ OK, emballage et envoi en livraison ?">
                                Valider CQ → Emballer
                            </button>
                            <button type="button" class="btn btn-secondary" style="width:100%;"
                                    wire:click="rejectQc({{ $active->id }})"
                                    wire:confirm="Renvoyer en Mise en Production ?">
                                Non conforme → Retour production
                            </button>
                        </div>
                    </div>
                @elseif ($active->status === 'ready')
                    <div class="fin-prod__panel-section">
                        <div class="fin-prod__panel-section-title">3 · Livraison</div>
                        <div class="alert alert-success" style="margin:0;">
                            Emballée et prête.
                            <div style="font-size:12px;margin-top:6px;color:#166534;">
                                Remise bloquée tant que la facture n’est pas soldée (ou crédit validé) — à faire dans Livraisons.
                            </div>
                            <div style="margin-top:10px;">
                                <a class="btn btn-primary btn-sm" href="{{ route('tenant.pressing_deliveries.index', ['tenant' => $tenantCode]) }}">
                                    Ouvrir les livraisons →
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            @else
                <p style="margin:0;font-size:13px;color:#64748b;line-height:1.55;">
                    <strong style="color:#0f172a;">Étape {{ $step }}</strong><br>
                    @if ($step === 1)
                        Choisissez une commande « À contrôler » pour vérifier le contenu du lot.
                    @elseif ($step === 3)
                        Les commandes emballées se gèrent ensuite dans <strong>Livraisons</strong> (solde ou crédit requis).
                    @else
                        Sélectionnez une commande pour continuer.
                    @endif
                </p>
            @endif
        </aside>
    </div>
</div>
