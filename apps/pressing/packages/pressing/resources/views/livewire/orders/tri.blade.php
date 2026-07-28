@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code');
    $progress = $this->progress;
    $active = $this->activeLine;
    $sorting = app(\Pressing\Services\PressingSortingService::class);
@endphp

<div class="page-body order-tri">
    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <style>
        .order-tri__layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 16px;
            align-items: start;
        }
        @media (max-width: 960px) {
            .order-tri__layout { grid-template-columns: 1fr; }
        }
        .order-tri__card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 14px;
        }
        .order-tri__card-title {
            margin: 0 0 12px;
            font-size: .95rem;
            font-weight: 700;
            color: #0f172a;
        }
        .tri-tasks {
            display: grid;
            gap: 8px;
        }
        .tri-task {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            width: 100%;
            text-align: left;
            border: 2px solid #e2e8f0;
            background: #fff;
            border-radius: 12px;
            padding: 12px 14px;
            cursor: pointer;
            transition: border-color .12s, background .12s, box-shadow .12s;
        }
        .tri-task:hover { border-color: #94a3b8; background: #f8fafc; }
        .tri-task--active {
            border-color: #2563eb;
            background: #eff6ff;
            box-shadow: 0 0 0 1px rgba(37,99,235,.2);
        }
        .tri-task--done {
            border-color: #86efac;
            background: #f0fdf4;
        }
        .tri-task--done.tri-task--active {
            border-color: #2563eb;
            background: #eff6ff;
        }
        .tri-task__label { font-weight: 800; font-size: 14px; color: #0f172a; }
        .tri-task__meta { font-size: 12px; color: #64748b; margin-top: 2px; }
        .tri-task__badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 999px;
            white-space: nowrap;
        }
        .tri-task__badge--todo { background: #ffedd5; color: #c2410c; }
        .tri-task__badge--done { background: #dcfce7; color: #15803d; }
        .tri-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .tri-chip {
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
        }
        .tri-chip:hover { border-color: #94a3b8; background: #f8fafc; }
        .tri-chip--active {
            border-color: #2563eb;
            background: #eff6ff;
            color: #1d4ed8;
        }
        .tri-qty {
            display: inline-flex;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .tri-qty button {
            border: 0;
            background: #f8fafc;
            width: 36px;
            height: 36px;
            font-size: 18px;
            cursor: pointer;
            color: #334155;
        }
        .tri-qty input {
            width: 48px;
            border: 0;
            text-align: center;
            font-weight: 700;
            font-size: 15px;
        }
        .tri-detail-hero {
            padding: 14px 16px;
            border-radius: 12px;
            background: linear-gradient(180deg, #eff6ff, #fff);
            border: 1px solid #bfdbfe;
            margin-bottom: 14px;
        }
        .tri-detail-hero strong {
            display: block;
            font-size: 1.15rem;
            color: #0f172a;
        }
        .tri-preview {
            position: sticky;
            top: 16px;
            background: linear-gradient(180deg, #eff6ff 0%, #fff 100%);
            border: 1px solid #bfdbfe;
            border-radius: 14px;
            padding: 16px;
        }
        .tri-preview__list {
            margin: 12px 0 0;
            padding: 0;
            list-style: none;
        }
        .tri-preview__list li {
            padding: 8px 10px;
            margin-bottom: 6px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
        }
        .tri-preview__empty {
            color: #64748b;
            font-size: 13px;
            font-style: italic;
        }
        .tri-preview__total {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed #cbd5e1;
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }
    </style>

    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
        <div>
            <h2 class="client-list-head__title" style="margin:0;">Constituer la commande</h2>
            <p style="margin:4px 0 0;font-size:13px;color:#64748b;">
                <strong>{{ $order->number }}</strong>
                · {{ $order->client?->full_name }}
                · {{ number_format((float) $order->total, 0, ',', ' ') }} FCFA
            </p>
            @if ($order->assignee)
                <p style="margin:4px 0 0;font-size:12px;color:#0ea5e9;">Assignée à {{ $order->assignee->name }}</p>
            @endif
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @if ($order->status !== 'delivered')
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.pressing_orders.edit', ['pressingOrder' => $order->id, 'tenant' => $tenantCode]) }}">Modifier réception</a>
            @endif
            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.pressing_workflow.index', ['tenant' => $tenantCode]) }}">Mon workflow</a>
            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.pressing_orders.index', ['tenant' => $tenantCode]) }}">Commandes</a>
        </div>
    </div>

    @if ($canComplete)
        <div class="order-tri__layout">
            <div>
                @if ($canSort)
                    <div class="order-tri__card">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px;">
                            <h3 class="order-tri__card-title" style="margin:0;">Lignes de la réception</h3>
                            <span style="font-size:12px;color:#64748b;">{{ $progress['done'] }}/{{ $progress['total'] }} complétée(s)</span>
                        </div>
                        <p style="font-size:12px;color:#64748b;margin:0 0 12px;line-height:1.45;">
                            Cliquez une ligne : le <strong>type</strong> et la <strong>quantité</strong> sont déjà pris.
                            Ajoutez une ou plusieurs <strong>couleurs</strong> et <strong>descriptifs</strong>, puis validez.
                        </p>

                        <div class="tri-tasks">
                            @foreach ($lines as $index => $line)
                                @php
                                    $valid = $sorting->isLineValid($line);
                                    $typeName = $line['type_name'] ?: ($articleTypes->firstWhere('id', $line['article_type_id'])?->name ?? 'Article');
                                    $preview = \Pressing\Models\PressingOrderConstitutionLine::formatLabel(
                                        $typeName,
                                        $line['color'] ?? '',
                                        $line['pattern'] ?? '',
                                        (int) ($line['quantity'] ?? 1)
                                    );
                                @endphp
                                <button type="button"
                                    class="tri-task {{ $activeLineIndex === $index ? 'tri-task--active' : '' }} {{ $valid ? 'tri-task--done' : '' }}"
                                    wire:key="task-{{ $index }}"
                                    wire:click="selectLine({{ $index }})">
                                    <div>
                                        <div class="tri-task__label">{{ $typeName }} × {{ (int) ($line['quantity'] ?? 1) }}</div>
                                        <div class="tri-task__meta">
                                            @if ($valid)
                                                {{ $preview }}
                                            @else
                                                Couleur et descriptif à compléter
                                            @endif
                                        </div>
                                    </div>
                                    <span class="tri-task__badge {{ $valid ? 'tri-task__badge--done' : 'tri-task__badge--todo' }}">
                                        {{ $valid ? 'OK' : 'À faire' }}
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        <div style="margin-top:12px;">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="addBlankLine">+ Ligne supplémentaire</button>
                        </div>
                    </div>

                    @if ($active !== null && $activeLineIndex !== null)
                        @php
                            $activeType = $active['type_name'] ?: ($articleTypes->firstWhere('id', $active['article_type_id'])?->name ?? 'Article');
                            $activeValid = $sorting->isLineValid($active);
                        @endphp
                        <div class="order-tri__card" wire:key="detail-{{ $activeLineIndex }}">
                            <h3 class="order-tri__card-title">Compléter la ligne sélectionnée</h3>

                            <div class="tri-detail-hero">
                                <strong>{{ $activeType }} × {{ (int) ($active['quantity'] ?? 1) }}</strong>
                                <span style="font-size:12px;color:#64748b;">Type et quantité repris de la réception — ajustables si besoin</span>
                            </div>

                            <div style="display:grid;gap:14px;">
                                <div>
                                    <label class="field-label">Type</label>
                                    <select class="input" wire:model.live="lines.{{ $activeLineIndex }}.article_type_id">
                                        @foreach ($articleTypes as $type)
                                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="field-label">Quantité</label>
                                    <div class="tri-qty">
                                        <button type="button" wire:click="decrementQty({{ $activeLineIndex }})">−</button>
                                        <input type="number" min="1" wire:model.live="lines.{{ $activeLineIndex }}.quantity">
                                        <button type="button" wire:click="incrementQty({{ $activeLineIndex }})">+</button>
                                    </div>
                                </div>

                                <div>
                                    <label class="field-label">Couleur(s) *</label>
                                    <p style="margin:0 0 8px;font-size:11px;color:#64748b;">Plusieurs possibles — cliquez pour activer / désactiver.</p>
                                    @if ($quick_colors !== [])
                                        <div class="tri-chips" style="margin-bottom:8px;">
                                            @foreach ($quick_colors as $selectedColor)
                                                <button type="button" class="tri-chip tri-chip--active"
                                                        wire:click="removeColor(@js($selectedColor))">
                                                    {{ ucfirst($selectedColor) }} ×
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="tri-chips">
                                        @foreach ($colorPresets as $color)
                                            <button type="button"
                                                class="tri-chip {{ collect($quick_colors)->contains(fn ($c) => mb_strtolower($c) === $color) ? 'tri-chip--active' : '' }}"
                                                wire:click="toggleColor('{{ $color }}')">{{ ucfirst($color) }}</button>
                                        @endforeach
                                    </div>
                                    <div style="display:flex;gap:8px;margin-top:8px;">
                                        <input class="input" style="flex:1;" wire:model="quick_color_custom"
                                               wire:keydown.enter.prevent="addCustomColor"
                                               placeholder="Autre couleur (virgules ok)…">
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="addCustomColor">+</button>
                                    </div>
                                    @error('quick_colors')<div class="text-error" style="font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                                </div>

                                <div>
                                    <label class="field-label">Descriptif(s)</label>
                                    <p style="margin:0 0 8px;font-size:11px;color:#64748b;">Plusieurs possibles — jean + rayée, etc.</p>
                                    @if ($quick_patterns !== [])
                                        <div class="tri-chips" style="margin-bottom:8px;">
                                            @foreach ($quick_patterns as $selectedPattern)
                                                <button type="button" class="tri-chip tri-chip--active"
                                                        wire:click="removePattern(@js($selectedPattern))">
                                                    {{ ucfirst($selectedPattern) }} ×
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="tri-chips">
                                        @foreach ($patternPresets as $pattern)
                                            <button type="button"
                                                class="tri-chip {{ collect($quick_patterns)->contains(fn ($p) => mb_strtolower($p) === mb_strtolower($pattern)) ? 'tri-chip--active' : '' }}"
                                                wire:click="togglePattern('{{ $pattern }}')">{{ ucfirst($pattern) }}</button>
                                        @endforeach
                                    </div>
                                    <div style="display:flex;gap:8px;margin-top:8px;">
                                        <input class="input" style="flex:1;" wire:model="quick_pattern_custom"
                                               wire:keydown.enter.prevent="addCustomPattern"
                                               placeholder="Autre descriptif (virgules ok)…">
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="addCustomPattern">+</button>
                                    </div>
                                </div>

                                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                    <button type="button" class="btn btn-primary" wire:click="confirmActiveLine">
                                        @if ($progress['remaining'] > 1 && ! $activeValid)
                                            Valider → ligne suivante
                                        @elseif ($progress['remaining'] === 1 && ! $activeValid)
                                            Valider cette ligne
                                        @else
                                            Mettre à jour
                                        @endif
                                    </button>
                                    @if (count($lines) > 1)
                                        <button type="button" class="btn btn-secondary" wire:click="removeLine({{ $activeLineIndex }})">Supprimer la ligne</button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                @endif

                <div class="order-tri__card">
                    @if ($progress['remaining'] === 0 && $progress['total'] > 0)
                        <div class="alert alert-success" style="margin:0 0 14px;">
                            Toutes les lignes ont une couleur ou un descriptif. Vous pouvez lancer la production.
                        </div>
                    @else
                        <p style="margin:0 0 14px;font-size:13px;color:#64748b;">
                            Encore <strong>{{ $progress['remaining'] }}</strong> ligne(s) à détailler.
                        </p>
                    @endif

                    @if ($canSort)
                        <div class="field" style="margin-bottom:14px;">
                            <label class="field-label" for="production_user_id">{{ __('Assigner à (production)') }}</label>
                            <select id="production_user_id" class="input" wire:model="production_user_id">
                                <option value="">{{ __('— Choisir un employé —') }}</option>
                                @forelse ($productionEmployees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @empty
                                    <option value="" disabled>{{ __('Aucun employé production disponible') }}</option>
                                @endforelse
                            </select>
                            @error('production_user_id')
                                <p class="field-error" style="margin:6px 0 0;font-size:12px;color:#b91c1c;">{{ $message }}</p>
                            @enderror
                            <p style="margin:6px 0 0;font-size:12px;color:#64748b;">
                                {{ __('La commande apparaîtra sur le workflow de cet employé.') }}
                            </p>
                        </div>
                        <button type="button" class="btn btn-primary" wire:click="completeSorting" wire:loading.attr="disabled"
                                wire:confirm="{{ __('Valider la constitution et assigner à la production ?') }}">
                            <span wire:loading.remove wire:target="completeSorting">{{ __('Valider → Production') }}</span>
                            <span wire:loading wire:target="completeSorting">{{ __('Validation…') }}</span>
                        </button>
                        <span style="margin-left:8px;font-size:12px;color:#64748b;">{{ $progress['pieces'] }} {{ __('pièce(s)') }}</span>
                    @endif
                </div>
            </div>

            <aside class="tri-preview">
                <div style="font-size:12px;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:.04em;">Aperçu client</div>
                <p style="font-size:13px;color:#475569;margin:8px 0 0;line-height:1.45;">
                    Récapitulatif du contenu du lot pour la remise.
                </p>

                @if ($this->preview !== '')
                    <ul class="tri-preview__list">
                        @foreach ($lines as $line)
                            @if ($sorting->isLineValid($line))
                                @php
                                    $typeName = $line['type_name'] ?: ($articleTypes->firstWhere('id', $line['article_type_id'])?->name ?? 'Article');
                                @endphp
                                <li>{{ \Pressing\Models\PressingOrderConstitutionLine::formatLabel($typeName, $line['color'] ?? '', $line['pattern'] ?? '', (int) ($line['quantity'] ?? 1)) }}</li>
                            @endif
                        @endforeach
                    </ul>
                    <div class="tri-preview__total">Total : {{ $progress['pieces'] }} pièce(s)</div>
                @else
                    <p class="tri-preview__empty" style="margin-top:12px;">Sélectionnez une ligne et ajoutez couleur / descriptif.</p>
                @endif
            </aside>
        </div>
    @else
        <div class="alert alert-success">
            Constitution validée le {{ $order->sorting_completed_at?->format('d/m/Y H:i') ?? '—' }}.
            @if ($order->constitutionSummary())
                <div style="margin-top:8px;font-weight:600;">{{ $order->constitutionSummary() }}</div>
            @endif
        </div>
    @endif
</div>
