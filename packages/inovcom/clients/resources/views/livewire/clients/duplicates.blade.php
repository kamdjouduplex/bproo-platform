@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <section class="card" style="margin-bottom:16px;">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <h2 class="card-title" style="margin:0;">Doublons détectés ({{ $groups->count() }})</h2>
            <a class="btn btn-secondary" href="{{ route('tenant.clients.index', ['tenant' => $tenantCode]) }}">Retour</a>
        </div>
        <p class="field-hint" style="margin-top:8px;">Détection par NIU, téléphone et nom identiques. La fusion réaffecte contacts, adresses, ventes, dettes et factures vers le client conservé, puis archive les doublons.</p>
    </section>

    @forelse ($groups as $group)
        <section class="card" style="margin-bottom:16px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                <h3 class="card-title" style="margin:0;">
                    <span class="badge badge-warning">{{ $group['reason'] }}</span>
                    <span style="font-size:13px; color:#6b7280;">« {{ $group['key'] }} »</span>
                </h3>
                @if ($canUpdate)
                    <button type="button" class="btn btn-primary btn-sm"
                        wire:click="mergeGroup('{{ $group['signature'] }}')"
                        wire:confirm="Fusionner ce groupe dans le client sélectionné ? Action irréversible.">
                        Fusionner
                    </button>
                @endif
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Conserver</th>
                            <th>Code</th>
                            <th>Nom</th>
                            <th>Téléphone</th>
                            <th>NIU</th>
                            <th>Email</th>
                            <th>Créé le</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['clients'] as $client)
                            <tr>
                                <td>
                                    <input type="radio" wire:model="targets.{{ $group['signature'] }}" value="{{ $client->id }}">
                                </td>
                                <td><a href="{{ route('tenant.clients.show', [$client->id, 'tenant' => $tenantCode]) }}">{{ $client->code }}</a></td>
                                <td>{{ $client->name }}</td>
                                <td>{{ $client->phone ?? '—' }}</td>
                                <td>{{ $client->niu ?? '—' }}</td>
                                <td>{{ $client->email ?? '—' }}</td>
                                <td>{{ optional($client->created_at)->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <section class="card">
            <div class="alert" style="margin:0;">Aucun doublon détecté. 🎉</div>
        </section>
    @endforelse
</div>
