<div class="page-body">
    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <style>
        .bs-head { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:16px; align-items:flex-start; }
        .bs-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px; padding:4px; background:#f1f5f9; border-radius:12px; width:fit-content; max-width:100%; }
        .bs-tab {
            border:0; background:transparent; border-radius:10px;
            padding:9px 16px; font-size:13px; font-weight:600; cursor:pointer; color:#64748b;
        }
        .bs-tab--active { background:#fff; color:#0f172a; box-shadow:0 1px 2px rgba(15,23,42,.08); }
        .bs-badge {
            display:inline-block; font-size:10px; font-weight:700; padding:2px 8px; border-radius:999px;
        }
        .bs-badge--atelier { background:#e0e7ff; color:#3730a3; }
        .bs-badge--livraison { background:#dcfce7; color:#166534; }
        .bs-form {
            background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:18px;
            max-width:820px;
        }
        .bs-form__title { margin:0 0 4px; font-size:1.05rem; font-weight:700; color:#0f172a; }
        .bs-form__hint { margin:0 0 16px; font-size:13px; color:#64748b; line-height:1.45; }
        .bs-lines { margin-top:16px; padding-top:14px; border-top:1px solid #e2e8f0; }
        .bs-line {
            display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:8px;
        }
        .bs-stock-link {
            font-size:12px; color:#64748b; margin-top:10px;
        }
        .bs-stock-link a { color:#2563eb; font-weight:600; text-decoration:none; }
        .bs-empty { text-align:center; padding:36px 16px; color:#64748b; }
    </style>

    <div class="bs-head">
        <div>
            <h2 class="client-list-head__title" style="margin:0;">{{ __('Bons de sortie atelier') }}</h2>
            <p style="margin:4px 0 0;font-size:13px;color:#64748b;">
                {{ __('Qui a pris quoi, pour quel travail — le stock se gère dans le module Stock.') }}
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @if ($hasStockRoute)
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.stock.index', ['tenant' => $tenantCode]) }}">{{ __('Ouvrir le stock') }}</a>
            @endif
            @if ($canConsume && $viewMode === 'liste')
                <button type="button" class="btn btn-primary btn-sm" wire:click="showForm">{{ __('Nouveau bon') }}</button>
            @endif
            @if ($viewMode === 'nouveau')
                <button type="button" class="btn btn-secondary btn-sm" wire:click="showList">{{ __('Retour à la liste') }}</button>
            @endif
        </div>
    </div>

    @if ($viewMode === 'nouveau')
        <div class="bs-form">
            <h3 class="bs-form__title">{{ __('Nouveau bon de sortie') }}</h3>
            <p class="bs-form__hint">
                {{ __('Renseignez l’employé, l’usage et les articles (lessive, savon, parfum…). La validation débite automatiquement le stock.') }}
            </p>

            @if ($canConsume)
                <div class="form-grid">
                    <div class="field">
                        <label class="field-label">{{ __('Pris / utiliséé par') }} *</label>
                        <select class="input" wire:model="taken_by">
                            <option value="">—</option>
                            @foreach ($employees as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @error('taken_by')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label class="field-label">{{ __('Usage') }} *</label>
                        <select class="input" wire:model="purpose">
                            @foreach ($purposes as $key => $label)
                                <option value="{{ $key }}">{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">{{ __('Contexte de travail') }}</label>
                        <input class="input" wire:model="work_context" placeholder="{{ __('ex. Lot matin, machine 2…') }}">
                    </div>
                    <div class="field">
                        <label class="field-label">{{ __('Pièces traitées (rendement)') }}</label>
                        <input class="input" type="number" min="0" wire:model="pieces_processed" placeholder="{{ __('ex. 24') }}">
                    </div>
                    <div class="field">
                        <label class="field-label">{{ __('Commande liée (optionnel)') }}</label>
                        <select class="input" wire:model="order_id">
                            <option value="">—</option>
                            @foreach ($openOrders as $order)
                                <option value="{{ $order->id }}">{{ $order->number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="grid-column:1/-1;">
                        <label class="field-label">{{ __('Notes') }}</label>
                        <textarea class="input" rows="2" wire:model="notes" placeholder="{{ __('Observation…') }}"></textarea>
                    </div>
                </div>

                <div class="bs-lines">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                        <strong style="font-size:13px;">{{ __('Articles sortis') }}</strong>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="addIssueLine">{{ __('+ Ligne') }}</button>
                    </div>
                    @foreach ($issueLines as $index => $line)
                        <div class="bs-line" wire:key="iss-line-{{ $index }}">
                            <select class="input" style="flex:1;min-width:200px;" wire:model="issueLines.{{ $index }}.item_id">
                                <option value="">{{ __('Article…') }}</option>
                                @foreach ($atelierItems as $item)
                                    <option value="{{ $item['id'] }}">
                                        {{ $item['name'] }} — {{ __('dispo') }} {{ number_format($item['quantity'], 2, ',', ' ') }} {{ $item['unit'] }}
                                    </option>
                                @endforeach
                            </select>
                            <input class="input" style="width:110px;" type="number" step="0.001" min="0.001"
                                   wire:model="issueLines.{{ $index }}.quantity" placeholder="{{ __('Qté') }}">
                            @if (count($issueLines) > 1)
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="removeIssueLine({{ $index }})">×</button>
                            @endif
                        </div>
                    @endforeach
                    @error('issueLines')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror
                    @error('issueLines.0.item_id')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror
                    @error('issueLines.0.quantity')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror

                    @if (count($atelierItems) === 0)
                        <div class="alert alert-warning" style="margin-top:8px;">
                            {{ __('Aucun article atelier. Créez-les / réapprovisionnez-les dans le module Stock.') }}
                        </div>
                    @endif
                </div>

                <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="btn btn-primary" wire:click="submitIssue"
                            wire:confirm="{{ __('Valider ce bon et débiter le stock ?') }}">
                        {{ __('Valider le bon') }}
                    </button>
                    <button type="button" class="btn btn-secondary" wire:click="showList">{{ __('Annuler') }}</button>
                </div>
                <p class="bs-stock-link">
                    {{ __('Niveaux et entrées') }} :
                    @if ($hasStockRoute)
                        <a href="{{ route('tenant.stock.index', ['tenant' => $tenantCode]) }}">{{ __('module Stock') }}</a>
                    @else
                        {{ __('module Stock') }}
                    @endif
                </p>
            @else
                <div class="alert alert-warning">{{ __('Permission insuffisante pour enregistrer une sortie.') }}</div>
            @endif
        </div>
    @else
        <div class="bs-tabs">
            <button type="button" class="bs-tab {{ $listFilter === 'atelier' ? 'bs-tab--active' : '' }}" wire:click="$set('listFilter', 'atelier')">{{ __('Atelier') }}</button>
            <button type="button" class="bs-tab {{ $listFilter === 'livraison' ? 'bs-tab--active' : '' }}" wire:click="$set('listFilter', 'livraison')">{{ __('Remises (livraison)') }}</button>
            <button type="button" class="bs-tab {{ $listFilter === 'all' ? 'bs-tab--active' : '' }}" wire:click="$set('listFilter', 'all')">{{ __('Tous') }}</button>
        </div>

        <section class="card app-table-card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('N°') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Pris par') }}</th>
                            <th>{{ __('Usage / contexte') }}</th>
                            <th>{{ __('Rendement') }}</th>
                            <th>{{ __('Articles') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($issues as $issue)
                            <tr wire:key="issue-{{ $issue->id }}">
                                <td><strong>{{ $issue->number }}</strong></td>
                                <td style="white-space:nowrap;font-size:12px;color:#64748b;">{{ $issue->issued_at?->format('d/m/Y H:i') }}</td>
                                <td>
                                    <span class="bs-badge bs-badge--{{ $issue->type === 'livraison' ? 'livraison' : 'atelier' }}">
                                        {{ $issue->type === 'livraison' ? __('Remise') : __('Atelier') }}
                                    </span>
                                </td>
                                <td>{{ $issue->taker?->name ?? '—' }}</td>
                                <td>
                                    {{ $issue->purposeLabel() }}
                                    @if ($issue->work_context)
                                        <div style="font-size:11px;color:#64748b;">{{ $issue->work_context }}</div>
                                    @endif
                                    @if ($issue->order)
                                        <div style="font-size:11px;color:#2563eb;">{{ $issue->order->number }}</div>
                                    @endif
                                </td>
                                <td>{{ $issue->pieces_processed !== null ? $issue->pieces_processed.' '.__('pcs') : '—' }}</td>
                                <td style="font-size:12px;">
                                    @foreach ($issue->lines as $line)
                                        <div>{{ $line->item?->name }} × {{ number_format((float) $line->quantity, 2, ',', ' ') }} {{ $line->unit_label }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="bs-empty">
                                        {{ __('Aucun bon pour ce filtre.') }}
                                        @if ($canConsume && $listFilter !== 'livraison')
                                            <div style="margin-top:10px;">
                                                <button type="button" class="btn btn-primary btn-sm" wire:click="showForm">{{ __('Créer un bon de sortie') }}</button>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
