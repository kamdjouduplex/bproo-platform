<div
    class="app-notifications"
    wire:poll.45s="refreshNotifications"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        class="app-notifications__trigger"
        aria-label="Notifications"
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
        style="width:min(420px,92vw);"
    >
        <div class="app-notifications__header">
            <strong>Notifications</strong>
            @if ($totalCount > 0)
                <span class="app-notifications__header-count">{{ $totalCount }}</span>
            @endif
        </div>

        @if ($hasInbox ?? false)
            <div style="display:flex;gap:4px;padding:0 12px 8px;">
                <button type="button"
                    class="btn btn-sm {{ $tab === 'actions' ? 'btn-primary' : 'btn-secondary' }}"
                    wire:click="setTab('actions')">
                    Actions
                    @if (collect($groups)->sum('count') > 0)
                        ({{ collect($groups)->sum('count') }})
                    @endif
                </button>
                <button type="button"
                    class="btn btn-sm {{ $tab === 'inbox' ? 'btn-primary' : 'btn-secondary' }}"
                    wire:click="setTab('inbox')">
                    Messages
                    @if ($inboxUnread > 0)
                        ({{ $inboxUnread }})
                    @endif
                </button>
            </div>
        @endif

        @if ($tab === 'inbox' && ($hasInbox ?? false))
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0 12px 8px;">
                <p class="app-notifications__hint" style="margin:0;">Alertes pressing destinées à votre compte.</p>
                @if ($inboxUnread > 0)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="markAllRead">Tout lire</button>
                @endif
            </div>

            @if (count($inbox) === 0)
                <p class="app-notifications__empty">Aucun message pour le moment.</p>
            @else
                <div class="app-notifications__body">
                    <ul class="app-notifications__list">
                        @foreach ($inbox as $item)
                            <li>
                                @if (!empty($item['url']))
                                    <a href="{{ $item['url'] }}"
                                       class="app-notifications__item"
                                       style="{{ ($item['unread'] ?? false) ? 'background:#f0f9ff;' : '' }}"
                                       wire:click="markRead('{{ $item['id'] }}')"
                                       @click="open = false">
                                        <span class="app-notifications__item-title">
                                            @if ($item['unread'] ?? false)
                                                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#0ea5e9;margin-right:6px;"></span>
                                            @endif
                                            {{ $item['title'] }}
                                        </span>
                                        <span class="app-notifications__item-sub">{{ \Illuminate\Support\Str::limit($item['body'] ?? '', 90) }}</span>
                                        <span class="app-notifications__item-meta">{{ $item['meta'] ?? '' }}</span>
                                    </a>
                                @else
                                    <button type="button"
                                        class="app-notifications__item"
                                        style="width:100%;text-align:left;border:0;background:{{ ($item['unread'] ?? false) ? '#f0f9ff' : 'transparent' }};"
                                        wire:click="markRead('{{ $item['id'] }}')">
                                        <span class="app-notifications__item-title">{{ $item['title'] }}</span>
                                        <span class="app-notifications__item-sub">{{ \Illuminate\Support\Str::limit($item['body'] ?? '', 90) }}</span>
                                        <span class="app-notifications__item-meta">{{ $item['meta'] ?? '' }}</span>
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @else
            <p class="app-notifications__hint">Le compteur diminue lorsque l'action est réellement traitée.</p>

            @if (count($groups) === 0)
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
                            @if (!empty($group['list_url']) && $group['count'] > 0)
                                <a href="{{ $group['list_url'] }}" class="app-notifications__more" @click="open = false">
                                    Voir la liste →
                                </a>
                            @endif
                        </section>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
