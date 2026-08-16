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
    var openedFromMainWindow = !!(window.opener && !window.opener.closed);

    if (printFilename) {
        document.title = printFilename;
    }

    window.addEventListener('beforeprint', function () {
        if (printFilename) {
            document.title = printFilename;
        }
    });

    function finish() {
        if (finished) {
            return;
        }
        finished = true;

        if (openedFromMainWindow) {
            try {
                if (window.opener) {
                    window.opener.focus();
                }
            } catch (error) {
                // Ignore focus issues if the opener is not accessible anymore.
            }

            try {
                window.close();
            } catch (error) {
                // The browser may block closing the tab if it was not opened programmatically.
            }

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
