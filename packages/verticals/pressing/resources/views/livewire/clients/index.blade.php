<div class="page-body">
    <style>
        .client-list-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            padding: 16px 16px 0;
        }
        .client-list-head__title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
        }
        .client-list-head__actions { display: flex; gap: 8px; }
        .cli-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 80;
            background: rgba(15, 23, 42, 0.48);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .cli-modal {
            width: 100%;
            max-width: 720px;
            max-height: 90vh;
            overflow: auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.2);
            display: flex;
            flex-direction: column;
        }
        .cli-modal__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            padding: 18px 20px 12px;
            border-bottom: 1px solid #f1f5f9;
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 1;
        }
        .cli-modal__title { margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; }
        .cli-modal__hint { margin: 4px 0 0; font-size: 12px; color: #64748b; }
        .cli-modal__close {
            width: 32px; height: 32px; border-radius: 50%;
            border: 1px solid #e2e8f0; background: #fff; color: #64748b;
            font-size: 20px; line-height: 1; cursor: pointer; flex-shrink: 0;
        }
        .cli-modal__close:hover { background: #f1f5f9; color: #0f172a; }
        .cli-modal__body { padding: 16px 20px; }
        .cli-modal__foot {
            display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap;
            padding: 12px 20px 18px; border-top: 1px solid #f1f5f9;
            position: sticky;
            bottom: 0;
            background: #fff;
        }
        .cli-modal .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        @media (max-width: 640px) {
            .cli-modal .form-grid { grid-template-columns: 1fr; }
        }
        .cli-modal .field-error { color: #dc2626; font-size: 12px; margin-top: 4px; }
    </style>

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <section class="card app-table-card">
        <div class="client-list-head">
            <h2 class="client-list-head__title">{{ __('Nos Clients') }}</h2>
            <div class="client-list-head__actions">
                @if ($canCreate)
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Nouveau client') }}</button>
                @endif
            </div>
        </div>

        <div style="padding:12px 16px;display:flex;gap:8px;flex-wrap:wrap;">
            <input class="input" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Nom, WhatsApp, code…') }}" style="flex:1;min-width:200px;">
            <select class="input" wire:model.live="agenceFilter" style="max-width:220px;">
                <option value="">{{ __('Toutes agences') }}</option>
                @foreach ($agences as $agence)
                    <option value="{{ $agence->id }}">{{ $agence->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('WhatsApp') }}</th>
                        <th>{{ __('Agence (inscription)') }}</th>
                        <th>{{ __('Commandes') }}</th>
                        <th>{{ __('CA') }}</th>
                        <th>{{ __('En cours') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        <tr>
                            <td>{{ $client->code }}</td>
                            <td>{{ $client->full_name }}</td>
                            <td>{{ $client->whatsapp }}</td>
                            <td>{{ $client->agence?->name }}</td>
                            <td>{{ $client->orders_count }}</td>
                            <td>{{ number_format((float) ($client->total_revenue ?? 0), 0, ',', ' ') }}</td>
                            <td>{{ $client->open_orders_count }}</td>
                            <td style="white-space:nowrap;">
                                @if ($canUpdate)
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="edit({{ $client->id }})">{{ __('Modifier') }}</button>
                                @endif
                                @if ($canDelete)
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="delete({{ $client->id }})" wire:confirm="{{ __('Supprimer ce client ?') }}">{{ __('Suppr.') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">{{ __('Aucun client.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px 16px;">{{ $clients->links() }}</div>
    </section>

    @if ($showForm)
        <div class="cli-modal-backdrop" wire:click.self="cancel" wire:key="client-form-modal">
            <div class="cli-modal" role="dialog" aria-modal="true" aria-labelledby="cli-modal-title">
                <div class="cli-modal__head">
                    <div>
                        <h3 id="cli-modal-title" class="cli-modal__title">
                            {{ $editingId ? __('Modifier le client') : __('Nouveau client') }}
                        </h3>
                        <p class="cli-modal__hint">{{ __('Renseignez les informations du client.') }}</p>
                    </div>
                    <button type="button" class="cli-modal__close" wire:click="cancel" aria-label="{{ __('Fermer') }}">×</button>
                </div>
                <div class="cli-modal__body">
                    <div class="form-grid">
                        <div class="field">
                            <label class="field-label">{{ __('Code') }}</label>
                            <input class="input" wire:model="code">
                            @error('code')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Agence d’inscription') }}</label>
                            <select class="input" wire:model="agence_id">
                                <option value="">—</option>
                                @foreach ($agences as $agence)
                                    <option value="{{ $agence->id }}">{{ $agence->name }}</option>
                                @endforeach
                            </select>
                            @error('agence_id')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Nom') }}</label>
                            <input class="input" wire:model="last_name">
                            @error('last_name')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Prénom') }}</label>
                            <input class="input" wire:model="first_name">
                            @error('first_name')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('WhatsApp') }} *</label>
                            <input class="input" wire:model="whatsapp">
                            @error('whatsapp')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Téléphone') }}</label>
                            <input class="input" wire:model="phone">
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Email') }}</label>
                            <input class="input" wire:model="email">
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Adresse') }}</label>
                            <input class="input" wire:model="address">
                        </div>
                        <div class="field" style="grid-column:1/-1;">
                            <label class="field-label">{{ __('Observations') }}</label>
                            <textarea class="input" wire:model="notes" rows="3"></textarea>
                        </div>
                        <div class="field">
                            <label class="field-label" style="display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" wire:model="is_active"> {{ __('Actif') }}
                            </label>
                        </div>
                    </div>
                </div>
                <div class="cli-modal__foot">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">{{ __('Annuler') }}</button>
                    <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">{{ __('Enregistrer') }}</span>
                        <span wire:loading wire:target="save">{{ __('Enregistrement…') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
