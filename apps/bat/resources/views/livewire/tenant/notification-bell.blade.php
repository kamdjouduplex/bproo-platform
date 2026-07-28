<div class="relative inline-flex items-center" wire:poll.60s>
    {{-- Trigger --}}
    <button
        class="relative flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors duration-150 cursor-pointer"
        wire:click="toggle"
        aria-label="{{ __('Notifications') }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute top-0.5 right-0.5 min-w-[16px] h-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-0.5 leading-none">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown --}}
    @if ($open)
        <div class="absolute top-[calc(100%+8px)] right-0 w-[340px] bg-white border border-slate-200 rounded-xl shadow-xl z-[500] overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-slate-50">
                <span class="text-[13px] font-semibold text-slate-800">{{ __('Notifications') }}</span>
                @if ($unreadCount > 0)
                    <button class="text-[11px] text-blue-600 font-medium hover:underline bg-none border-none cursor-pointer p-0"
                            wire:click="markAllRead">
                        {{ __('Tout marquer lu') }}
                    </button>
                @endif
            </div>

            <ul class="list-none m-0 p-0 max-h-[360px] overflow-y-auto">
                @forelse ($notifications as $notification)
                    @php
                        $data    = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
                        $title   = $data['title'] ?? null;
                        $message = $data['message'] ?? $data['body'] ?? __('Notification');
                        $link    = $data['url'] ?? $data['link'] ?? null;
                        $unread  = is_null($notification->read_at);
                    @endphp
                    <li class="flex items-start gap-2 px-4 py-2.5 border-b border-slate-50 transition-colors duration-100 {{ $unread ? 'bg-blue-50 hover:bg-blue-100' : 'hover:bg-slate-50' }}">
                        <div class="flex-1 min-w-0">
                            @if ($link)
                                <a href="{{ $link }}"
                                   class="block text-xs text-slate-800 cursor-pointer break-words hover:text-blue-600"
                                   wire:click="markRead('{{ $notification->id }}')">
                                    @if ($title)<strong>{{ $title }}</strong><br>@endif
                                    {{ $message }}
                                </a>
                            @else
                                <span class="block text-xs text-slate-800 cursor-pointer break-words"
                                      wire:click="markRead('{{ $notification->id }}')">
                                    @if ($title)<strong>{{ $title }}</strong><br>@endif
                                    {{ $message }}
                                </span>
                            @endif
                            <span class="block text-[11px] text-slate-400 mt-0.5">
                                {{ $notification->created_at->diffForHumans() }}
                            </span>
                        </div>
                        @if ($unread)
                            <span class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0 mt-1" title="{{ __('Non lu') }}"></span>
                        @endif
                    </li>
                @empty
                    <li class="px-4 py-5 text-center text-xs text-slate-400">
                        {{ __('Aucune notification') }}
                    </li>
                @endforelse
            </ul>
        </div>

        {{-- Click-outside overlay --}}
        <div class="fixed inset-0 z-[499]" wire:click="toggle"></div>
    @endif
</div>
