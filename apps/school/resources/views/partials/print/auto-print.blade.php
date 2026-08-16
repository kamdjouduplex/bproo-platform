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

    function finish() {
        if (finished) {
            return;
        }
        finished = true;

        // Fermer l’onglet d’impression (ouvert via target=_blank / window.open).
        try {
            window.close();
        } catch (e) {}

        // Si le navigateur refuse de fermer (rare), revenir à l’appli dans cet onglet.
        setTimeout(function () {
            if (window.closed) {
                return;
            }
            if (returnUrl) {
                window.location.replace(returnUrl);
            }
        }, 250);
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

    window.printAndClose = function () {
        if (printFilename) {
            document.title = printFilename;
        }
        window.print();
    };

    function openPrintDialog() {
        window.printAndClose();
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
