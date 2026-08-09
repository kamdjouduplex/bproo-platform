{{-- Control Center: toast-only notify (no Tailwind dependency) --}}
<style>
/* Failsafe: toast must look correct even if Vite CSS is stale */
#laravel-notify {
    position: fixed !important;
    right: 16px !important;
    bottom: 16px !important;
    left: auto !important;
    top: auto !important;
    z-index: 99999 !important;
    width: min(100vw - 32px, 22rem) !important;
    max-width: 22rem !important;
    pointer-events: none !important;
}
#laravel-notify .cc-notify { pointer-events: auto; }
#laravel-notify .cc-notify__toast {
    display: flex !important;
    align-items: flex-start !important;
    gap: 12px !important;
    padding: 12px 12px 12px 14px !important;
    border-radius: 12px !important;
    background: #fff !important;
    border: 1px solid #e2e8f0 !important;
    border-left-width: 4px !important;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14) !important;
    color: #0f172a !important;
}
#laravel-notify .cc-notify__toast--success { border-left-color: #16a34a !important; }
#laravel-notify .cc-notify__toast--warning { border-left-color: #ca8a04 !important; }
#laravel-notify .cc-notify__toast--error { border-left-color: #dc2626 !important; }
#laravel-notify .cc-notify__toast--info { border-left-color: #2563eb !important; }
#laravel-notify .cc-notify__icon { flex: 0 0 auto; width: 22px; height: 22px; color: inherit; }
#laravel-notify .cc-notify__toast--success .cc-notify__icon { color: #16a34a !important; }
#laravel-notify .cc-notify__toast--warning .cc-notify__icon { color: #ca8a04 !important; }
#laravel-notify .cc-notify__toast--error .cc-notify__icon { color: #dc2626 !important; }
#laravel-notify .cc-notify__toast--info .cc-notify__icon { color: #2563eb !important; }
#laravel-notify .cc-notify__icon svg,
#laravel-notify .cc-notify__close svg {
    display: block !important;
    width: 22px !important;
    height: 22px !important;
    max-width: 22px !important;
    max-height: 22px !important;
}
#laravel-notify .cc-notify__close svg { width: 16px !important; height: 16px !important; }
#laravel-notify .cc-notify__body { flex: 1 1 auto; min-width: 0; }
#laravel-notify .cc-notify__title { margin: 0; font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.3; }
#laravel-notify .cc-notify__message { margin: 2px 0 0; font-size: 13px; color: #64748b; line-height: 1.4; }
#laravel-notify .cc-notify__close {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    margin: -4px -4px 0 0;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
}
#laravel-notify .cc-notify-leave-end { opacity: 0; transform: translateY(6px); transition: opacity .15s ease, transform .15s ease; }
</style>
<div id="laravel-notify" aria-live="polite" aria-atomic="true">
    @if (session()->has('notify.message'))
        @include('notify::notifications.toast')
    @endif

    @php(session()->forget('notify.message'))

    <script>
        (function () {
            var ms = parseInt("{{ (int) config('notify.timeout', 5000) }}", 10) || 5000;
            function bindToast() {
                var root = document.getElementById('laravel-notify');
                if (!root) return;
                var toast = root.querySelector('[data-cc-notify-toast]');
                if (!toast) return;
                window.setTimeout(function () {
                    toast.classList.add('cc-notify-leave-end');
                    window.setTimeout(function () { toast.remove(); }, 200);
                }, ms);
                var closeBtn = toast.querySelector('[data-cc-notify-close]');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function () {
                        toast.remove();
                    });
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindToast);
            } else {
                bindToast();
            }
        })();
    </script>
</div>
