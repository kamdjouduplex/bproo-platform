<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $appName }} — Accueil</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:600,700&family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ph-ink: #0b1f2a;
            --ph-teal: #0f766e;
            --ph-teal-deep: #115e59;
            --ph-mint: #ecfdf8;
            --ph-muted: #c5d8d4;
            --ph-white: #ffffff;
            --font-display: "Fraunces", Georgia, serif;
            --font-body: "Plus Jakarta Sans", sans-serif;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            height: 100%;
            overflow: hidden;
        }

        body {
            font-family: var(--font-body);
            color: var(--ph-white);
            -webkit-font-smoothing: antialiased;
            background: #0b3d3a;
        }

        .home {
            position: relative;
            isolation: isolate;
            height: 100dvh;
            max-height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr auto;
            padding: clamp(1.25rem, 3vw, 2rem) clamp(1.25rem, 4vw, 3rem);
        }

        .home__media {
            position: absolute;
            inset: 0;
            z-index: 0;
            background:
                url('{{ $heroImage }}') center / cover no-repeat;
            transform: scale(1.04);
            animation: home-kenburns 28s ease-in-out infinite alternate;
        }

        .home__veil {
            position: absolute;
            inset: 0;
            z-index: 1;
            pointer-events: none;
            background:
                linear-gradient(160deg, rgba(7, 32, 38, 0.72) 0%, rgba(11, 61, 58, 0.55) 48%, rgba(7, 32, 38, 0.78) 100%),
                radial-gradient(ellipse at 70% 20%, rgba(15, 118, 110, 0.28), transparent 55%);
        }

        .home > *:not(.home__media):not(.home__veil) {
            position: relative;
            z-index: 2;
        }

        .home__top {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            animation: home-rise 0.7s ease both;
        }

        .home__brand {
            font-family: var(--font-display);
            font-size: clamp(1.65rem, 3.2vw, 2.35rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1.1;
            margin: 0;
        }

        .home__tag {
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--ph-muted);
            white-space: nowrap;
        }

        .home__main {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            max-width: 38rem;
            gap: 1.15rem;
            padding: 1rem 0;
        }

        .home__eyebrow {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #9fd5cb;
            animation: home-rise 0.75s 0.08s ease both;
        }

        .home__message {
            margin: 0;
            font-family: var(--font-display);
            font-size: clamp(1.85rem, 4.2vw, 3rem);
            font-weight: 700;
            line-height: 1.18;
            letter-spacing: -0.03em;
            text-wrap: balance;
            animation: home-rise 0.8s 0.14s ease both;
        }

        .home__support {
            margin: 0;
            font-size: clamp(0.95rem, 1.6vw, 1.1rem);
            line-height: 1.55;
            color: rgba(255, 255, 255, 0.82);
            max-width: 28rem;
            animation: home-rise 0.8s 0.22s ease both;
        }

        .home__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 0.35rem;
            animation: home-rise 0.85s 0.3s ease both;
        }

        .home__btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.85rem;
            padding: 0.7rem 1.35rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.98rem;
            text-decoration: none;
            transition: transform 0.15s ease, background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
        }

        .home__btn:hover { transform: translateY(-1px); }
        .home__btn:active { transform: translateY(0); }

        .home__btn--primary {
            background: var(--ph-white);
            color: var(--ph-teal-deep);
        }

        .home__btn--primary:hover {
            background: var(--ph-mint);
            color: var(--ph-ink);
        }

        .home__btn--ghost {
            background: transparent;
            color: var(--ph-white);
            border: 1.5px solid rgba(255, 255, 255, 0.45);
        }

        .home__btn--ghost:hover {
            border-color: #fff;
            background: rgba(255, 255, 255, 0.08);
        }

        .home__footer {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.75rem 1.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.14);
            animation: home-rise 0.9s 0.36s ease both;
        }

        .home__dev-label {
            margin: 0 0 0.2rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #9fd5cb;
        }

        .home__contacts {
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.55;
            color: rgba(255, 255, 255, 0.88);
        }

        .home__contacts a {
            color: inherit;
            text-decoration: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.28);
        }

        .home__contacts a:hover {
            border-bottom-color: #fff;
        }

        .home__meta {
            margin: 0;
            font-size: 0.8rem;
            color: var(--ph-muted);
            text-align: right;
        }

        @keyframes home-kenburns {
            from { transform: scale(1.02) translate3d(0, 0, 0); }
            to { transform: scale(1.08) translate3d(-1.2%, 0.6%, 0); }
        }

        @keyframes home-rise {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 640px) {
            .home__top { flex-direction: column; align-items: flex-start; gap: 0.35rem; }
            .home__meta { text-align: left; }
            .home__footer { flex-direction: column; align-items: flex-start; }
        }

        @media (prefers-reduced-motion: reduce) {
            .home__media { animation: none; }
            .home__top, .home__eyebrow, .home__message, .home__support, .home__actions, .home__footer {
                animation: none;
            }
        }
    </style>
</head>
<body>
    <div class="home">
        <div class="home__media" aria-hidden="true"></div>
        <div class="home__veil" aria-hidden="true"></div>

        <header class="home__top">
            <h1 class="home__brand">{{ $appName }}</h1>
            <p class="home__tag">Officine · poste local</p>
        </header>

        <main class="home__main">
            <p class="home__eyebrow">Bienvenue</p>
            <p class="home__message">{{ $message }}</p>
            <p class="home__support">Votre espace de gestion pharmacie est prêt. Connectez-vous pour encaisser, suivre le stock et servir vos clients.</p>
            <div class="home__actions">
                <a class="home__btn home__btn--primary" href="{{ $loginUrl }}">Connexion</a>
                <a class="home__btn home__btn--ghost" href="tel:+237670274538">Appeler le support</a>
            </div>
        </main>

        <footer class="home__footer">
            <div>
                <p class="home__dev-label">Bproo Dev</p>
                <p class="home__contacts">
                    <a href="tel:+237670274538">+237 670 27 45 38</a>
                    ·
                    <a href="tel:+237688828712">+237 688 82 87 12</a><br>
                    <a href="mailto:contact.invo-com@gmail.com">contact.invo-com@gmail.com</a>
                </p>
            </div>
            <p class="home__meta">{{ $shopLabel }}@if($appVersion) · v{{ $appVersion }}@endif</p>
        </footer>
    </div>
</body>
</html>
