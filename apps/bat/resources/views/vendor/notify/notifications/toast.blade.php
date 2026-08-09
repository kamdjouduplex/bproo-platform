@if (session()->get('notify.model') === 'toast')
@php
    $type    = session()->get('notify.type', 'info');
    $title   = session()->get('notify.title') ?? ucfirst($type);
    $message = session()->get('notify.message', '');
    $timeout = (int) config('notify.timeout', 4500);

    $palette = [
        'success' => ['border' => '#22c55e', 'icon_bg' => '#dcfce7', 'icon_color' => '#16a34a'],
        'error'   => ['border' => '#ef4444', 'icon_bg' => '#fee2e2', 'icon_color' => '#dc2626'],
        'warning' => ['border' => '#f59e0b', 'icon_bg' => '#fef3c7', 'icon_color' => '#d97706'],
        'info'    => ['border' => '#3b82f6', 'icon_bg' => '#dbeafe', 'icon_color' => '#2563eb'],
    ];
    $c = $palette[$type] ?? $palette['info'];
@endphp
<div id="inovcom-toast"
     style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;width:100%;max-width:22rem;
            background:#fff;border-radius:.75rem;box-shadow:0 8px 24px rgba(0,0,0,.12);
            border-left:4px solid {{ $c['border'] }};
            opacity:0;transform:translateY(10px);
            transition:opacity .25s ease,transform .25s ease;pointer-events:auto;">
    <div style="padding:1rem;">
        <div style="display:flex;align-items:flex-start;gap:.75rem;">

            {{-- Colored icon badge --}}
            <div style="flex-shrink:0;width:2rem;height:2rem;border-radius:.5rem;
                        background:{{ $c['icon_bg'] }};display:flex;align-items:center;justify-content:center;">
                @if($type === 'success')
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="{{ $c['icon_color'] }}" stroke-width="2.2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="9 12 11 14 15 10"/>
                    </svg>
                @elseif($type === 'error')
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="{{ $c['icon_color'] }}" stroke-width="2.2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                @elseif($type === 'warning')
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="{{ $c['icon_color'] }}" stroke-width="2.2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                @else
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="{{ $c['icon_color'] }}" stroke-width="2.2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                @endif
            </div>

            {{-- Text --}}
            <div style="flex:1;min-width:0;">
                <p style="margin:0;font-size:.875rem;font-weight:600;color:#0f172a;line-height:1.4;text-transform:capitalize;">
                    {{ $title }}
                </p>
                <p style="margin:.2rem 0 0;font-size:.8125rem;color:#64748b;line-height:1.5;">
                    {{ $message }}
                </p>
            </div>

            {{-- Close button — pure vanilla JS, zero Alpine --}}
            <button type="button"
                    onclick="(function(){var t=document.getElementById('inovcom-toast');if(!t)return;t.style.opacity='0';t.style.transform='translateY(10px)';setTimeout(function(){t.remove()},260);})()"
                    style="flex-shrink:0;padding:.25rem;border:none;background:none;cursor:pointer;
                           color:#94a3b8;border-radius:.375rem;line-height:0;transition:color .15s;"
                    onmouseover="this.style.color='#475569'" onmouseout="this.style.color='#94a3b8'"
                    aria-label="{{ __('Fermer') }}">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                </svg>
            </button>
        </div>
    </div>

    <script>
        (function () {
            var el = document.getElementById('inovcom-toast');
            if (!el) return;
            // Animate in after paint
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                });
            });
            // Auto-dismiss
            setTimeout(function () {
                el.style.opacity = '0';
                el.style.transform = 'translateY(10px)';
                setTimeout(function () { el.remove(); }, 260);
            }, {{ $timeout }});
        })();
    </script>
</div>
@endif
