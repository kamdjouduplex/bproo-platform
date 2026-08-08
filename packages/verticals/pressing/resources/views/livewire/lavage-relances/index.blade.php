<div class="page-body">
    <section class="card app-table-card">
        <div class="client-list-head">
            <h2 class="client-list-head__title">{{ __('Relances dépôt lavage') }}</h2>
            <div class="client-list-head__actions">
                @php
                    $printUrl = route('tenant.pressing_lavage_relances.print', array_merge(
                        ['tenant' => $tenantCode],
                        [
                            'since' => $sinceDate,
                            'agence' => $agenceFilter,
                            'search' => $search,
                            'onlyActive' => $onlyActive ? 1 : 0,
                        ]
                    ));
                @endphp

                @if ($canPrint)
                    <a class="btn btn-secondary btn-sm" href="{{ $printUrl }}"
                        onclick="event.preventDefault(); window.open(this.href, 'pressing-lavage-relances-print', 'width=980,height=720');">
                        {{ __('Imprimer') }}
                    </a>
                @endif
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success" style="margin:0 16px 16px;">
                {{ session('success') }}
                @if (session('ticket_url'))
                    <div style="margin-top:8px;">
                        <a href="{{ session('ticket_url') }}" class="btn btn-secondary btn-sm">
                            {{ __('Ouvrir :n', ['n' => session('ticket_number')]) }}
                        </a>
                    </div>
                @endif
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger" style="margin:0 16px 16px;">{{ session('error') }}</div>
        @endif

        <div style="padding:12px 16px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
            <input class="input" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Nom, WhatsApp, code…') }}" style="flex:1;min-width:200px;">

            <select class="input" wire:model.live="agenceFilter" style="max-width:220px;">
                @if ($canViewAllAgences)
                    <option value="">{{ __('Toutes agences') }}</option>
                @endif
                @foreach ($agences as $agence)
                    <option value="{{ $agence->id }}">{{ $agence->name }}</option>
                @endforeach
            </select>

            <div style="display:flex;flex-direction:column;gap:6px;">
                <label style="font-size:12px;color:#64748b;font-weight:600;">{{ __('Depuis') }}</label>
                <input class="input" type="date" wire:model.live="sinceDate" style="max-width:210px;">
            </div>

            <label style="display:flex;align-items:center;gap:8px;font-size:13px;">
                <input type="checkbox" wire:model.live="onlyActive" {{ $onlyActive ? 'checked' : '' }}>
                {{ __('Actifs uniquement') }}
            </label>
        </div>

        <div style="padding:0 16px 12px;font-size:12px;color:#64748b;">
            {{ __('Nombre de clients affichés (selon pagination) dont le dernier “Lavage” est avant la date (ou jamais).') }}
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('WhatsApp') }}</th>
                        <th>{{ __('Dernier dépôt lavage') }}</th>
                        <th>{{ __('Commande') }}</th>
                        <th>{{ __('Agence') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        @php
                            $fullName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
                            $lastAt = $client->last_lavage_at ? \Carbon\Carbon::parse($client->last_lavage_at)->startOfDay() : null;
                            $days = $lastAt ? (int) $lastAt->diffInDays(now()->startOfDay()) : null;
                            $daysLabel = $days === null
                                ? __('Jamais')
                                : ($days === 0
                                    ? __('Aujourd’hui')
                                    : trans_choice(':count jour|:count jours', $days, ['count' => $days]));
                            $dateHint = $lastAt ? $lastAt->format('d/m/Y') : null;
                            $lastOrder = $lastOrderByClient[$client->id]['order_number'] ?? null;
                        @endphp
                        <tr>
                            <td>{{ $client->code }}</td>
                            <td>{{ $fullName ?: '—' }}</td>
                            <td>{{ $client->whatsapp ?: '—' }}</td>
                            <td title="{{ $dateHint }}">
                                <strong>{{ $daysLabel }}</strong>
                                @if ($dateHint)
                                    <div style="font-size:11px;color:#64748b;margin-top:2px;">{{ $dateHint }}</div>
                                @endif
                            </td>
                            <td>{{ $lastOrder ?: '—' }}</td>
                            <td>{{ $client->agence_name ?: '—' }}</td>
                            <td style="white-space:nowrap;">
                                @if ($canRelaunch)
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="relaunch({{ $client->id }})"
                                        wire:loading.attr="disabled">
                                        {{ __('Relancer') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ __('Aucun client à relancer pour ces critères.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="padding:12px 16px;">{{ $clients->links() }}</div>
    </section>
</div>

