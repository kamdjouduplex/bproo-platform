<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:500,600,700,800&display=swap" rel="stylesheet">
    <style>
        /* 12 cartes / A4 — 3 colonnes × 4 lignes */
        @page {
            size: A4 portrait;
            margin: 6mm;
        }

        :root {
            --ink: #1a1038;
            --purple: #3b1d6e;
            --purple-deep: #2a1454;
            --mint: #d8efe8;
            --muted: #5a4a7a;
            --white: #ffffff;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Manrope", "Segoe UI", Helvetica, Arial, sans-serif;
            background: #edf1f6;
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            padding: 12px 14px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        .toolbar a, .toolbar button {
            font-size: 13px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #0f2744;
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
        }
        .toolbar .primary { background: var(--purple); color: #fff; border-color: var(--purple); }

        .sheet {
            width: 198mm;
            margin: 8px auto 16px;
            display: grid;
            grid-template-columns: repeat(3, 64mm);
            grid-auto-rows: 68mm;
            justify-content: space-between;
            row-gap: 3.5mm;
        }

        .id-card {
            width: 64mm;
            height: 68mm;
            border-radius: 6px;
            overflow: hidden;
            background: var(--mint);
            border: 0.25mm solid rgba(42, 20, 84, 0.14);
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
            break-inside: avoid;
            position: relative;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .id-card:nth-child(12n) {
            page-break-after: always;
            break-after: page;
        }
        .id-card:last-child {
            page-break-after: auto;
            break-after: auto;
        }

        .id-card__header {
            height: 17.5mm;
            flex-shrink: 0;
            position: relative;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 2.4mm;
            overflow: hidden;
            background: var(--purple);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .id-card__header::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(circle at 8% 55%, rgba(255,255,255,0.11) 0 7mm, transparent 7.2mm),
                radial-gradient(circle at 92% 10%, rgba(255,255,255,0.10) 0 8mm, transparent 8.2mm),
                radial-gradient(circle at 78% 110%, rgba(255,255,255,0.08) 0 9mm, transparent 9.2mm),
                radial-gradient(circle at 20% 120%, rgba(255,255,255,0.06) 0 7mm, transparent 7.2mm);
            pointer-events: none;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .id-card__brand {
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255,255,255,0.92);
            border-radius: 2px;
            padding: 1.1mm 2.4mm;
            max-width: 82%;
            background: transparent;
        }

        .id-card__brand-name {
            color: #fff;
            font-size: 5.8px;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            line-height: 1.15;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 48mm;
        }

        .id-card__photo-wrap {
            position: absolute;
            left: 50%;
            bottom: -7mm;
            transform: translateX(-50%);
            width: 15mm;
            height: 15mm;
            border-radius: 50%;
            background: #fff;
            padding: 0.8mm;
            box-shadow: 0 1px 4px rgba(42, 20, 84, 0.22);
            z-index: 3;
            overflow: hidden;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .id-card__photo {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            object-position: center 18%;
            display: block;
            background: #e8dff5;
        }

        .id-card__avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: #6b46a8;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .id-card__avatar svg {
            width: 70%;
            height: 70%;
            display: block;
        }

        .id-card__body {
            flex: 1;
            min-height: 0;
            background: var(--mint);
            padding: 8.4mm 2.8mm 1.2mm;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .id-card__name {
            font-size: 8.2px;
            font-weight: 800;
            color: var(--ink);
            line-height: 1.12;
            margin-bottom: 0.3px;
            max-width: 100%;
            word-break: break-word;
        }

        .id-card__role {
            font-size: 6px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 1.4px;
        }

        .id-card__meta {
            font-size: 5.7px;
            font-weight: 600;
            color: var(--ink);
            line-height: 1.25;
        }

        .id-card__id {
            margin-top: 1.2px;
            font-size: 5.7px;
            color: var(--ink);
            font-weight: 600;
        }

        .id-card__id span { font-weight: 800; }

        .id-card__contact {
            margin-top: 0.8px;
            font-size: 5.2px;
            color: var(--muted);
            line-height: 1.2;
        }

        .id-card__qr {
            margin-top: auto;
            padding-top: 1mm;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .id-card__qr .qr-box {
            width: 9.5mm;
            height: 9.5mm;
            background: #fff;
            border-radius: 1px;
            padding: 0.35mm;
            border: 0.15mm solid rgba(30, 20, 64, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .id-card__qr .qr-box svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .id-card__qr-fallback {
            font-size: 5px;
            color: var(--muted);
        }

        .id-card__footer {
            background: var(--purple-deep);
            color: rgba(255,255,255,0.92);
            font-size: 3.8px;
            line-height: 1.2;
            text-align: center;
            padding: 1.1mm 2mm;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @media print {
            .toolbar { display: none !important; }

            html, body {
                background: #fff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            .sheet {
                margin: 0;
                width: 100%;
            }

            .id-card {
                border-radius: 0;
                background: #d8efe8 !important;
            }

            .id-card__header { background: #3b1d6e !important; }
            .id-card__header::before {
                background:
                    radial-gradient(circle at 8% 55%, rgba(255,255,255,0.11) 0 7mm, transparent 7.2mm),
                    radial-gradient(circle at 92% 10%, rgba(255,255,255,0.10) 0 8mm, transparent 8.2mm),
                    radial-gradient(circle at 78% 110%, rgba(255,255,255,0.08) 0 9mm, transparent 9.2mm),
                    radial-gradient(circle at 20% 120%, rgba(255,255,255,0.06) 0 7mm, transparent 7.2mm) !important;
            }
            .id-card__body { background: #d8efe8 !important; }
            .id-card__footer { background: #2a1454 !important; color: #fff !important; }
            .id-card__photo-wrap { background: #fff !important; box-shadow: none !important; }
            .id-card__avatar { background: #6b46a8 !important; }
            .id-card__qr .qr-box { background: #fff !important; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button class="primary" type="button" onclick="(window.printAndClose || window.print)()">Imprimer / PDF — 12 / A4</button>
        @if (!empty($returnUrl))
            <a href="{{ $returnUrl }}">Retour</a>
        @endif
    </div>

    <div class="sheet">
        @foreach ($cards as $card)
            @php
                $student = $card->student;
                $fullName = trim(($student->first_name ?? '').' '.($student->last_name ?? ''));
                $classLine = collect([
                    $card->class_label,
                    $card->section_label ? "Section '".$card->section_label."'" : null,
                ])->filter()->implode(' - ');
                $brand = mb_strtoupper((string) ($shopName ?: 'Bproo School'));
                $photoSrc = $card->photo_src ?? null;
                $hasPhoto = filled($photoSrc)
                    && ! str_contains((string) $photoSrc, 'placeholder')
                    && (str_starts_with((string) $photoSrc, 'data:')
                        || str_starts_with((string) $photoSrc, 'http')
                        || str_starts_with((string) $photoSrc, '/'));
            @endphp
            <article class="id-card">
                <header class="id-card__header">
                    <div class="id-card__brand">
                        <div class="id-card__brand-name">{{ $brand }}</div>
                    </div>

                    <div class="id-card__photo-wrap">
                        @if ($hasPhoto)
                            <img
                                class="id-card__photo"
                                src="{{ $photoSrc }}"
                                alt="{{ $fullName }}"
                                loading="eager"
                                decoding="sync"
                            >
                        @else
                            <div class="id-card__avatar" aria-hidden="true">
                                {{-- Silhouette claire (tête + épaules) — fill blanc explicite --}}
                                <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <circle cx="32" cy="22" r="12" fill="#ffffff"/>
                                    <path fill="#ffffff" d="M10 58c1.8-13.5 12.5-20 22-20s20.2 6.5 22 20v2H10v-2z"/>
                                </svg>
                            </div>
                        @endif
                    </div>
                </header>

                <div class="id-card__body">
                    <div class="id-card__name">{{ $fullName !== '' ? $fullName : 'Élève' }}</div>
                    <div class="id-card__role">Élève</div>

                    @if ($classLine !== '')
                        <div class="id-card__meta">{{ $classLine }}</div>
                    @endif
                    <div class="id-card__meta">{{ $card->academicYear?->name ?? '—' }}</div>

                    <div class="id-card__id">
                        Student ID: <span>{{ $student?->student_code ?? '—' }}</span>
                    </div>

                    @if ($student?->parent_phone)
                        <div class="id-card__contact">Tél. {{ $student->parent_phone }}</div>
                    @endif

                    <div class="id-card__qr">
                        @if (!empty($card->qr_svg))
                            <div class="qr-box">{!! $card->qr_svg !!}</div>
                        @else
                            <div class="id-card__qr-fallback">QR</div>
                        @endif
                    </div>
                </div>

                <footer class="id-card__footer">
                    Présenter cette carte à l’entrée. En cas de perte, signaler immédiatement à l’administration.
                </footer>
            </article>
        @endforeach
    </div>
    @php($autoPrint = false)
    <script>
    (function () {
        var returnUrl = @json($returnUrl ?? null);
        var printFilename = @json($printTitle ?? 'cartes-id');
        var finished = false;

        if (printFilename) {
            document.title = printFilename;
        }

        function finish() {
            if (finished) return;
            finished = true;
            try { window.close(); } catch (e) {}
            setTimeout(function () {
                if (!window.closed && returnUrl) {
                    window.location.replace(returnUrl);
                }
            }, 250);
        }

        window.addEventListener('afterprint', function () {
            setTimeout(finish, 80);
        });

        window.printAndClose = function () {
            if (printFilename) document.title = printFilename;
            window.print();
        };

        function whenImagesReady(cb) {
            var called = false;
            var finishReady = function () {
                if (called) return;
                called = true;
                cb();
            };
            var imgs = Array.prototype.slice.call(document.querySelectorAll('.id-card__photo'));
            if (!imgs.length) {
                finishReady();
                return;
            }
            var pending = imgs.length;
            var done = function () {
                pending -= 1;
                if (pending <= 0) finishReady();
            };
            imgs.forEach(function (img) {
                if (img.complete && img.naturalWidth > 0) {
                    done();
                    return;
                }
                img.addEventListener('load', done, { once: true });
                img.addEventListener('error', done, { once: true });
            });
            // Safety timeout (data-URIs / slow decode)
            setTimeout(finishReady, 2500);
        }

        function openPrintDialog() {
            whenImagesReady(function () {
                setTimeout(function () { window.printAndClose(); }, 120);
            });
        }

        if (document.readyState === 'complete') {
            openPrintDialog();
        } else {
            window.addEventListener('load', openPrintDialog);
        }
    })();
    </script>
</body>
</html>
