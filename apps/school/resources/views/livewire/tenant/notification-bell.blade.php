<div
    class="app-notifications"
    wire:poll.60s="refreshNotifications"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        class="app-notifications__trigger"
        aria-label="Notifications et alertes"
        :aria-expanded="open"
        @click="open = !open; if (open) { $wire.refreshNotifications() }"
    >
        <svg class="app-notifications__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if ($totalCount > 0)
            <span class="app-notifications__badge" aria-hidden="true">{{ $totalCount > 99 ? '99+' : $totalCount }}</span>
        @endif
    </button>

    <div
        class="app-notifications__panel"
        x-show="open"
        x-cloak
        x-transition:enter="app-notifications__panel--enter"
        x-transition:leave="app-notifications__panel--leave"
        @click.outside="open = false"
    >
        <div class="app-notifications__header">
            <strong>Alertes & actions</strong>
            @if ($totalCount > 0)
                <span class="app-notifications__header-count">{{ $totalCount }} action(s)</span>
            @endif
        </div>
        <p class="app-notifications__hint">Inclut les actions à traiter et les alertes stock / péremption. Le compteur baisse quand le problème est réglé (validation, sortie de lot, réassort…).</p>

        @if ($totalCount === 0)
            <p class="app-notifications__empty">Aucune action en attente pour le moment.</p>
        @else
            <div class="app-notifications__body">
                @foreach ($groups as $group)
                    <section class="app-notifications__group">
                        <div class="app-notifications__group-head">
                            <span class="app-notifications__group-label">{{ $group['label'] }}</span>
                            <span class="app-notifications__group-count">{{ $group['count'] }}</span>
                        </div>
                        <ul class="app-notifications__list">
                            @foreach ($group['items'] as $item)
                                @if (!empty($item['url']))
                                    <li>
                                        <a href="{{ $item['url'] }}" class="app-notifications__item" @click="open = false">
                                            <span class="app-notifications__item-title">{{ $item['title'] }}</span>
                                            <span class="app-notifications__item-sub">{{ $item['subtitle'] }}</span>
                                            @if (!empty($item['meta']))
                                                <span class="app-notifications__item-meta">{{ $item['meta'] }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                        @if (!empty($group['list_url']) && $group['count'] > count($group['items']))
                            <a href="{{ $group['list_url'] }}" class="app-notifications__more" @click="open = false">
                                Voir tout ({{ $group['count'] }}) →
                            </a>
                        @elseif (!empty($group['list_url']) && $group['count'] > 0)
                            <a href="{{ $group['list_url'] }}" class="app-notifications__more" @click="open = false">
                                Voir la liste →
                            </a>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</div>
