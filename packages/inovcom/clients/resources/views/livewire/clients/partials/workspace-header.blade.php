@php
    $tenantCode = $tenantCode ?? request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
    $currentView = $currentView ?? 'simple';
@endphp

<section class="client-workspace-header card">
    <div class="client-workspace-header__main">
        <div>
            <h2 class="card-title client-workspace-header__title">{{ $client->name }}</h2>
            <div class="client-workspace-header__badges">
                <span class="badge badge-info">{{ $client->code }}</span>
                <span class="badge badge-info">{{ $client->type === 'company' ? 'Entreprise' : 'Particulier' }}</span>
                @if ($client->is_active)
                    <span class="badge badge-success">Actif</span>
                @else
                    <span class="badge badge-warning">Inactif</span>
                @endif
                @if ($client->is_blocked)
                    <span class="badge badge-error" title="{{ $client->block_reason }}">Bloqué</span>
                @endif
                @if ($client->segment)
                    <span class="badge badge-info">{{ $client->segment->name }}</span>
                @endif
                @if ($client->category)
                    <span class="badge badge-info">{{ $client->category->name }}</span>
                @endif
            </div>
        </div>
        <div class="client-workspace-header__actions">
            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.clients.index', ['tenant' => $tenantCode]) }}">Liste</a>
            @if ($currentView === 'simple')
                <a class="btn btn-primary btn-sm" href="{{ route('tenant.clients.show360', [$client->id, 'tenant' => $tenantCode]) }}">Vue 360°</a>
            @else
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.clients.show', [$client->id, 'tenant' => $tenantCode]) }}">Fiche simple</a>
            @endif
            @if ($canUpdate ?? false)
                <button type="button"
                    class="btn btn-sm {{ $client->is_blocked ? 'btn-secondary' : 'btn-danger' }}"
                    wire:click="toggleBlock"
                    wire:confirm="{{ $client->is_blocked ? 'Débloquer ce client ?' : 'Bloquer ce client (aucune vente à crédit) ?' }}">
                    {{ $client->is_blocked ? 'Débloquer' : 'Bloquer' }}
                </button>
            @endif
            <a class="btn btn-primary btn-sm" href="{{ route('tenant.clients.edit', [$client->id, 'tenant' => $tenantCode]) }}">Modifier</a>
        </div>
    </div>

    <nav class="client-workspace-nav" aria-label="Vue client">
        <a href="{{ route('tenant.clients.show', [$client->id, 'tenant' => $tenantCode]) }}"
           class="client-workspace-nav__link {{ $currentView === 'simple' ? 'client-workspace-nav__link--active' : '' }}">
            Fiche
        </a>
        <a href="{{ route('tenant.clients.show360', [$client->id, 'tenant' => $tenantCode]) }}"
           class="client-workspace-nav__link {{ $currentView === '360' ? 'client-workspace-nav__link--active' : '' }}">
            Vue 360°
        </a>
    </nav>

    @if ($client->is_blocked && $client->block_reason)
        <div class="alert alert-danger client-workspace-header__alert">
            Client bloqué — {{ $client->block_reason }}
            @if ($client->blocked_at)
                (le {{ \Carbon\Carbon::parse($client->blocked_at)->format('d/m/Y H:i') }})
            @endif
        </div>
    @endif
</section>
