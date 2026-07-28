<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Réservez une démo Inov-Com — Découvrez notre solution de gestion commerciale et point de vente.">
    <title>Réservez une démo | Inov-Com</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">
    <style>
        :root {
            --inv-primary: #0d9488;
            --inv-primary-dark: #0f766e;
            --inv-accent: #1e3a5f;
            --inv-light: #f0fdfa;
            --inv-dark: #0f172a;
            --inv-muted: #64748b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            color: var(--inv-dark);
            height: 100vh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1rem 1.5rem;
            background: linear-gradient(180deg, var(--inv-light) 0%, #fff 100%);
            overflow: hidden;
        }
        .demo-wrap {
            width: 100%;
            max-width: 480px;
            text-align: center;
        }
        .demo-back {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--inv-muted);
            margin-bottom: 1.25rem;
            transition: color 0.2s;
        }
        .demo-back:hover { color: var(--inv-primary); }
        .demo-back svg { width: 18px; height: 18px; }
        .demo-logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--inv-primary);
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }
        .demo-title {
            font-size: clamp(1.75rem, 4vw, 2.25rem);
            font-weight: 800;
            line-height: 1.2;
            color: var(--inv-dark);
            margin: 0 0 0.75rem;
        }
        .demo-sub {
            font-size: 1rem;
            color: var(--inv-muted);
            line-height: 1.5;
            margin: 0 0 1.5rem;
        }
        .demo-actions {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            margin-bottom: 1.5rem;
        }
        .demo-btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem 1.5rem;
            background: var(--inv-primary);
            color: #fff !important;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: background 0.2s, transform 0.15s;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .demo-btn-primary:hover {
            background: var(--inv-primary-dark);
            color: #fff !important;
            transform: translateY(-2px);
        }
        .demo-btn-primary svg { width: 20px; height: 20px; flex-shrink: 0; }
        .demo-btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            color: var(--inv-muted);
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s;
            text-decoration: none;
        }
        .demo-btn-secondary:hover { color: var(--inv-primary); }
        .demo-divider {
            font-size: 0.8rem;
            color: var(--inv-muted);
            margin: 0 0 1rem;
        }
        .demo-contact {
            font-size: 0.85rem;
            color: var(--inv-muted);
            line-height: 1.6;
        }
        .demo-contact a {
            color: var(--inv-primary);
            font-weight: 600;
            text-decoration: none;
        }
        .demo-contact a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="demo-wrap">
        <a href="{{ route('landing') }}" class="demo-back" aria-label="Retour à l'accueil">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Retour à l'accueil
        </a>
        <div class="demo-logo">Inov-Com</div>
        <h1 class="demo-title">Réservez une démo</h1>
        <p class="demo-sub">Choisissez un créneau qui vous convient via Google Calendar pour une démonstration personnalisée d'Inov-Com.</p>
        <div class="demo-actions">
            <a href="{{ config('services.demo_calendar_url', 'https://calendar.google.com/calendar/appointments/schedules') }}" target="_blank" rel="noopener noreferrer" class="demo-btn-primary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Choisir un créneau (Google Calendar)
            </a>
            <span class="demo-divider">Vous avez un problème pour accéder au calendrier ?</span>
            <a href="#contact-direct" class="demo-btn-secondary">Nous contacter directement</a>
        </div>
        <div class="demo-contact" id="contact-direct">
            <strong>Contact direct</strong><br>
            <a href="tel:+237670274538">+237 670 27 45 38</a> - <a href="tel:+237688828712">+237 688 82 87 12</a><br>
            <a href="mailto:contact.invo-com@gmail.com">contact.invo-com@gmail.com</a>
        </div>
    </div>
</body>
</html>
