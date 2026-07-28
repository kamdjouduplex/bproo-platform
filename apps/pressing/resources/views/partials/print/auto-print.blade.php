@php
    $returnUrl = $returnUrl ?? null;
    $autoPrint = $autoPrint ?? true;
    $printTitle = $printTitle ?? 'Document';
@endphp
@if ($autoPrint)
<script>
(function () {
    var returnUrl = @json($returnUrl);
    var printFilename = @json($printTitle);
    var finished = false;

    if (printFilename) {
        document.title = printFilename;
    }

    window.addEventListener('beforeprint', function () {
        if (printFilename) {
            document.title = printFilename;
        }
    });

    function showCloseHint() {
        try {
            document.title = @json(__('Impression terminée'));
            document.body.innerHTML = '<main style="font-family:Segoe UI,system-ui,sans-serif;text-align:center;padding:48px 16px;color:#334155;">'
                + '<p style="font-size:18px;font-weight:700;margin:0 0 8px;">' + @json(__('Impression terminée')) + '</p>'
                + '<p style="margin:0;color:#64748b;">' + @json(__('Vous pouvez fermer cet onglet.')) + '</p>'
                + '</main>';
        } catch (e) {}
    }

    function finish() {
        if (finished) {
            return;
        }
        finished = true;

        // Print was opened in a new tab/window: close it.
        // Do NOT navigate to returnUrl (that reloads the app and feels like a new tab).
        var openedSeparately = !!window.opener || (window.history && window.history.length <= 1);
        if (openedSeparately) {
            try {
                window.close();
            } catch (e) {}
            setTimeout(function () {
                if (!window.closed) {
                    showCloseHint();
                }
            }, 200);
            return;
        }

        if (returnUrl) {
            window.location.replace(returnUrl);
        }
    }

    window.addEventListener('afterprint', function () {
        setTimeout(finish, 80);
    });

    if (window.matchMedia) {
        window.matchMedia('print').addEventListener('change', function (event) {
            if (!event.matches) {
                setTimeout(finish, 120);
            }
        });
    }

    function openPrintDialog() {
        if (printFilename) {
            document.title = printFilename;
        }
        window.print();
    }

    if (document.readyState === 'complete') {
        setTimeout(openPrintDialog, 150);
    } else {
        window.addEventListener('load', function () {
            setTimeout(openPrintDialog, 150);
        });
    }
})();
</script>
@endif
