{{-- Control Center: toast-only notify (no Tailwind dependency) --}}
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
