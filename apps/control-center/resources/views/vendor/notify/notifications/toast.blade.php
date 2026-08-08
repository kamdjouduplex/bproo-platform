@php
    $type = session()->get('notify.type', 'info');
    $title = session()->get('notify.title') ?: match ($type) {
        'success' => 'Succès',
        'error' => 'Erreur',
        'warning' => 'Attention',
        default => 'Info',
    };
    $message = session()->get('notify.message');
@endphp

<div class="cc-notify cc-notify-enter cc-notify-enter-end" data-cc-notify-toast>
    <div class="cc-notify__toast cc-notify__toast--{{ $type }}">
        <div class="cc-notify__icon" aria-hidden="true">
            @if ($type === 'success')
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            @elseif ($type === 'error')
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            @elseif ($type === 'warning')
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            @else
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                </svg>
            @endif
        </div>
        <div class="cc-notify__body">
            <p class="cc-notify__title">{{ $title }}</p>
            @if ($message)
                <p class="cc-notify__message">{{ $message }}</p>
            @endif
        </div>
        <button type="button" class="cc-notify__close" data-cc-notify-close aria-label="Fermer">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>
