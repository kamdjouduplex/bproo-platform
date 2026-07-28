<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Inov-Com — Solution de gestion commerciale et point de vente. Ventes, stocks, rapports, multi-établissements. Innovation Commercial.">
    <title>Inov-Com — Innovation Commercial | Gestion & Point de vente</title>
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
        .landing { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; margin: 0; color: var(--inv-dark); }
        .landing * { box-sizing: border-box; }
        .landing a { color: inherit; text-decoration: none; }

        /* Header */
        .landing-header {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            background: rgba(255,255,255,0.95); backdrop-filter: saturate(180%) blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.06);
            padding: 0.75rem 1.5rem;
        }
        .landing-header-inner {
            max-width: 1200px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        }
        .landing-logo { font-size: 1.35rem; font-weight: 800; color: var(--inv-primary); letter-spacing: -0.02em; }
        .landing-nav { display: flex; align-items: center; gap: 1.5rem; }
        .landing-nav a { font-weight: 600; font-size: 0.9rem; color: var(--inv-muted); transition: color 0.2s; }
        .landing-nav a:hover { color: var(--inv-primary); }
        .landing-cta-header {
            display: inline-flex; align-items: center; padding: 0.5rem 1rem;
            background: var(--inv-primary); color: #fff !important; border-radius: 10px; font-weight: 600; font-size: 0.9rem;
            transition: background 0.2s, transform 0.15s;
        }
        .landing-cta-header:hover { background: var(--inv-primary-dark); color: #fff !important; transform: translateY(-1px); }
        .landing-menu-btn { display: none; background: none; border: none; cursor: pointer; padding: 0.5rem; }
        .landing-menu-btn svg { width: 24; height: 24; }

        /* Hero */
        .landing-hero {
            min-height: 100vh; display: flex; align-items: center; padding: 6rem 1.5rem 4rem;
            background: linear-gradient(180deg, var(--inv-light) 0%, #fff 50%);
        }
        .landing-hero-inner { max-width: 1200px; margin: 0 auto; width: 100%; }
        .landing-hero-grid {
            display: grid; gap: 3rem; align-items: center;
            grid-template-columns: 1fr 1fr;
        }
        @media (max-width: 900px) { .landing-hero-grid { grid-template-columns: 1fr; text-align: center; } }
        .landing-hero-badge {
            display: inline-block; padding: 0.35rem 0.85rem; background: rgba(13, 148, 136, 0.12);
            color: var(--inv-primary); border-radius: 999px; font-size: 0.8rem; font-weight: 600; margin-bottom: 1rem;
        }
        .landing-hero h1 {
            font-size: clamp(2.25rem, 5vw, 3.5rem); font-weight: 800; line-height: 1.15; letter-spacing: -0.03em;
            color: var(--inv-dark); margin: 0 0 1rem;
        }
        .landing-hero h1 span { color: var(--inv-primary); }
        .landing-hero-sub {
            font-size: 1.15rem; color: var(--inv-muted); line-height: 1.6; max-width: 480px; margin-bottom: 1.75rem;
        }
        @media (max-width: 900px) { .landing-hero-sub { margin-left: auto; margin-right: auto; } }
        .landing-hero-buttons { display: flex; flex-wrap: wrap; gap: 0.75rem; }
        @media (max-width: 900px) { .landing-hero-buttons { justify-content: center; } }
        .landing-btn-primary {
            display: inline-flex; align-items: center; padding: 0.85rem 1.5rem; background: var(--inv-primary);
            color: #fff !important; border-radius: 12px; font-weight: 600; font-size: 1rem; transition: background 0.2s, transform 0.15s;
        }
        .landing-btn-primary:hover { background: var(--inv-primary-dark); color: #fff !important; transform: translateY(-2px); }
        .landing-btn-secondary {
            display: inline-flex; align-items: center; padding: 0.85rem 1.5rem; background: #fff;
            color: var(--inv-primary); border: 2px solid var(--inv-primary); border-radius: 12px;
            font-weight: 600; font-size: 1rem; transition: all 0.2s;
        }
        .landing-btn-secondary:hover { background: var(--inv-light); }
        .landing-hero-visual {
            position: relative; border-radius: 16px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15);
            aspect-ratio: 4/3; background: linear-gradient(135deg, #e0f2f1 0%, #b2dfdb 100%);
        }
        .landing-hero-visual img { width: 100%; height: 100%; object-fit: cover; }
        .landing-hero-visual-placeholder {
            width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
            color: var(--inv-primary); font-weight: 700; font-size: 1.1rem; text-align: center; padding: 2rem;
        }

        /* Section common */
        .landing-section { padding: 4rem 1.5rem; max-width: 1200px; margin: 0 auto; }
        .landing-section-title { font-size: clamp(1.75rem, 3vw, 2.25rem); font-weight: 800; text-align: center; margin-bottom: 0.5rem; color: var(--inv-dark); }
        .landing-section-sub { text-align: center; color: var(--inv-muted); font-size: 1.05rem; margin-bottom: 2.5rem; max-width: 560px; margin-left: auto; margin-right: auto; }

        /* Features */
        .landing-features { background: #fff; }
        .landing-features-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem;
        }
        .landing-feature-card {
            padding: 1.5rem; border-radius: 16px; background: #fff; border: 1px solid rgba(0,0,0,0.06);
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .landing-feature-card:hover { box-shadow: 0 20px 40px -15px rgba(13, 148, 136, 0.2); transform: translateY(-2px); }
        .landing-feature-icon {
            width: 48px; height: 48px; border-radius: 12px; background: var(--inv-light); color: var(--inv-primary);
            display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; font-weight: 700; font-size: 1.25rem;
        }
        .landing-feature-card h3 { font-size: 1.1rem; font-weight: 700; margin: 0 0 0.4rem; color: var(--inv-dark); }
        .landing-feature-card p { margin: 0; font-size: 0.9rem; color: var(--inv-muted); line-height: 1.5; }

        /* Business types */
        .landing-business-types { background: var(--inv-light); }
        .landing-business-types-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1.25rem; justify-items: center;
        }
        @media (min-width: 640px) { .landing-business-types-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); } }
        .landing-business-type {
            display: flex; flex-direction: column; align-items: center; gap: 0.75rem;
            padding: 1.25rem 1rem; border-radius: 16px; background: #fff; border: 1px solid rgba(0,0,0,0.06);
            min-width: 0; transition: box-shadow 0.2s, transform 0.2s;
        }
        .landing-business-type:hover { box-shadow: 0 12px 30px -10px rgba(13, 148, 136, 0.25); transform: translateY(-2px); }
        .landing-business-type-icon {
            width: 56px; height: 56px; border-radius: 14px; background: rgba(13, 148, 136, 0.1);
            color: var(--inv-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .landing-business-type-icon svg { width: 28px; height: 28px; }
        .landing-business-type span { font-weight: 600; font-size: 0.9rem; text-align: center; color: var(--inv-dark); line-height: 1.3; }

        /* Gallery */
        .landing-gallery { background: #fff; }
        .landing-gallery-grid {
            display: grid; grid-template-columns: 1fr; gap: 1.5rem; max-width: 900px; margin: 0 auto;
        }
        .landing-gallery-item {
            border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.08);
            aspect-ratio: 16/10; background: #f1f5f9; transition: box-shadow 0.2s, transform 0.2s;
        }
        .landing-gallery-item:hover { box-shadow: 0 20px 40px -15px rgba(0,0,0,0.15); transform: scale(1.02); }
        .landing-gallery-item img { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* Social proof / stats */
        .landing-stats { background: var(--inv-accent); color: #fff; padding: 3rem 1.5rem; }
        .landing-stats-inner { max-width: 1200px; margin: 0 auto; }
        .landing-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 2rem; text-align: center; }
        .landing-stats-item strong { display: block; font-size: 1.75rem; font-weight: 800; margin-bottom: 0.25rem; }
        .landing-stats-item span { font-size: 0.9rem; opacity: 0.9; }

        /* Benefits */
        .landing-benefits { background: var(--inv-light); }
        .landing-benefits-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; align-items: start; }
        .landing-benefit { display: flex; gap: 1rem; }
        .landing-benefit-num { flex-shrink: 0; width: 40px; height: 40px; border-radius: 10px; background: var(--inv-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; }
        .landing-benefit h3 { font-size: 1.05rem; font-weight: 700; margin: 0 0 0.35rem; }
        .landing-benefit p { margin: 0; font-size: 0.95rem; color: var(--inv-muted); line-height: 1.5; }

        /* Testimonials placeholder */
        .landing-testimonials { background: #fff; }
        .landing-testimonials-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .landing-testimonial {
            padding: 1.5rem; border-radius: 16px; background: #f8fafc; border: 1px solid rgba(0,0,0,0.05);
        }
        .landing-testimonial p { font-style: italic; color: var(--inv-dark); margin: 0 0 1rem; font-size: 0.95rem; line-height: 1.6; }
        .landing-testimonial-author { font-weight: 600; font-size: 0.9rem; color: var(--inv-muted); }

        /* CTA */
        .landing-cta-section {
            padding: 4rem 1.5rem; background: linear-gradient(135deg, var(--inv-primary) 0%, var(--inv-primary-dark) 100%);
            color: #fff; text-align: center;
        }
        .landing-cta-section h2 { font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 800; margin: 0 0 0.5rem; }
        .landing-cta-section p { margin: 0 0 1.5rem; opacity: 0.95; font-size: 1.05rem; }
        .landing-cta-section .landing-btn-primary { background: rgba(255,255,255,0.95); color: var(--inv-primary) !important; }
        .landing-cta-section .landing-btn-primary:hover { background: #fff; color: var(--inv-primary-dark) !important; }

        /* Footer / Contact */
        .landing-footer {
            background: var(--inv-dark); color: #fff; padding: 3rem 1.5rem 2rem;
        }
        .landing-footer-inner { max-width: 1200px; margin: 0 auto; text-align: center; }
        .landing-footer-logo { font-size: 1.25rem; font-weight: 800; color: var(--inv-primary); margin-bottom: 0.5rem; }
        .landing-footer-tagline { font-size: 0.9rem; color: rgba(255,255,255,0.6); margin-bottom: 1.5rem; }
        .landing-contact { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem 2rem; margin-bottom: 1.5rem; }
        .landing-contact a {
            display: inline-flex; align-items: center; gap: 0.5rem; color: rgba(255,255,255,0.9);
            font-weight: 500; font-size: 0.95rem; transition: color 0.2s;
        }
        .landing-contact a:hover { color: var(--inv-primary); }
        .landing-footer-bottom { padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.85rem; color: rgba(255,255,255,0.5); }
        .landing-footer-bottom a { color: rgba(255,255,255,0.7); }
        .landing-footer-bottom a:hover { color: #fff; }

        @media (max-width: 768px) {
            .landing-nav { display: none; }
            .landing-nav.landing-nav-open { display: flex; flex-direction: column; position: absolute; top: 100%; left: 0; right: 0; background: #fff; padding: 1rem; border-bottom: 1px solid rgba(0,0,0,0.06); }
            .landing-menu-btn { display: block; }
            .landing-hero { padding-top: 5rem; }
        }
    </style>
</head>
<body class="landing">
    @php $isLoggedIn = auth()->check(); @endphp
    <header class="landing-header">
        <div class="landing-header-inner">
            <a href="{{ url('/') }}" class="landing-logo">Inov-Com</a>
            <nav class="landing-nav" id="landing-nav" aria-label="Main">
                <a href="{{ url('/') }}#features">Fonctionnalités</a>
                <a href="{{ url('/') }}#business-types">Commerces</a>
                <a href="{{ url('/') }}#gallery">Galerie</a>
                <a href="{{ url('/') }}#benefits">Avantages</a>
                <a href="{{ url('/') }}#contact">Contact</a>
                @if($isLoggedIn)
                    <a href="{{ route('system.dashboard') }}" class="landing-cta-header">Tableau de bord</a>
                @else
                    <a href="{{ route('demo') }}" class="landing-cta-header">Réservez une démo</a>
                @endif
            </nav>
            <button type="button" class="landing-menu-btn" aria-label="Menu" onclick="document.getElementById('landing-nav').classList.toggle('landing-nav-open')">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </header>

    <section class="landing-hero">
        <div class="landing-hero-inner">
            <div class="landing-hero-grid">
                <div>
                    <span class="landing-hero-badge">Innovation Commercial</span>
                    <h1>Gérez vos ventes et votre commerce avec <span>Inov-Com</span></h1>
                    <p class="landing-hero-sub">
                        Solution complète de point de vente et de gestion commerciale : ventes, stocks, achats, rapports et multi-établissements. Simple, fiable, évolutif.
                    </p>
                    <div class="landing-hero-buttons">
                        <a href="{{ route('demo') }}" class="landing-btn-primary">Réservez une démo</a>
                        <a href="{{ url('/') }}#features" class="landing-btn-secondary">Découvrir les fonctionnalités</a>
                    </div>
                </div>
                <div class="landing-hero-visual">
                    <img src="images/Hero-image-v1.png" alt="Aperçu interface Inov-Com" width="800" height="600" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="landing-section landing-features" id="features">
        <h2 class="landing-section-title">Tout ce dont vous avez besoin</h2>
        <p class="landing-section-sub">Des ventes à la paie, en passant par les stocks et les rapports : une seule plateforme pour votre activité.</p>
        <div class="landing-features-grid">
            <div class="landing-feature-card">
                <div class="landing-feature-icon">🛒</div>
                <h3>Ventes & caisse</h3>
                <p>Enregistrez les ventes, factures et reçus en quelques clics. Gestion des remises et des paiements multiples.</p>
            </div>
            <div class="landing-feature-card">
                <div class="landing-feature-icon">📦</div>
                <h3>Stocks & inventaire</h3>
                <p>Suivi des entrées/sorties, alertes de rupture, inventaire et valorisation en temps réel.</p>
            </div>
            <div class="landing-feature-card">
                <div class="landing-feature-icon">📊</div>
                <h3>Rapports & tableaux de bord</h3>
                <p>Chiffre d'affaires, marges, ventes par période. Des indicateurs clairs pour piloter votre activité.</p>
            </div>
            <div class="landing-feature-card">
                <div class="landing-feature-icon">🏢</div>
                <h3>Multi-établissements</h3>
                <p>Gérez plusieurs points de vente ou magasins depuis une seule interface avec des rôles et permissions.</p>
            </div>
            <div class="landing-feature-card">
                <div class="landing-feature-icon">👥</div>
                <h3>Clients & fournisseurs</h3>
                <p>Fiches clients et fournisseurs, crédit, dettes et historique des opérations.</p>
            </div>
            <div class="landing-feature-card">
                <div class="landing-feature-icon">💰</div>
                <h3>Dépenses & paie</h3>
                <p>Suivi des dépenses, pertes et gestion de la paie pour une vision complète des coûts.</p>
            </div>
        </div>
    </section>

    <section class="landing-section landing-business-types" id="business-types">
        <h2 class="landing-section-title">Pour tous les types de commerces</h2>
        <p class="landing-section-sub">Inov-Com s'adapte à votre secteur : retail, alimentaire, services ou vente au détail.</p>
        <div class="landing-business-types-grid">
            <div class="landing-business-type">
                <div class="landing-business-type-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><path d="M9 22V12h6v10"/></svg>
                </div>
                <span>Superette</span>
            </div>
            <div class="landing-business-type">
                <div class="landing-business-type-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <span>Mini-marché</span>
            </div>
            <div class="landing-business-type">
                <div class="landing-business-type-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <span>Quincaillerie</span>
            </div>
            <div class="landing-business-type">
                <div class="landing-business-type-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z"/></svg>
                </div>
                <span>Boulangerie</span>
            </div>
            <div class="landing-business-type">
                <div class="landing-business-type-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </div>
                <span>Pharmacie</span>
            </div>
            <div class="landing-business-type">
                <div class="landing-business-type-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                </div>
                <span>Restaurant</span>
            </div>
            <div class="landing-business-type">
                <div class="landing-business-type-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                </div>
                <span>Magasin de meubles</span>
            </div>
            <div class="landing-business-type">
                <div class="landing-business-type-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <span>Salon / Coiffure</span>
            </div>
            <div class="landing-business-type">
                <div class="landing-business-type-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                </div>
                <span>Boutique</span>
            </div>
            <div class="landing-business-type">
                <div class="landing-business-type-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <span>Épicerie</span>
            </div>
        </div>
    </section>

    <section class="landing-stats">
        <div class="landing-stats-inner">
            <div class="landing-stats-grid">
                <div class="landing-stats-item"><strong>+Rapide</strong><span>Mise en place simple</span></div>
                <div class="landing-stats-item"><strong>Fiable</strong><span>Données sécurisées</span></div>
                <div class="landing-stats-item"><strong>Évolutif</strong><span>Modules à la carte</span></div>
                <div class="landing-stats-item"><strong>Support</strong><span>À vos côtés</span></div>
            </div>
        </div>
    </section>

    <section class="landing-section landing-benefits" id="benefits">
        <h2 class="landing-section-title">Pourquoi choisir Inov-Com ?</h2>
        <p class="landing-section-sub">Conçu pour les commerces et PME qui veulent professionnaliser leur gestion sans complexité.</p>
        <div class="landing-benefits-grid">
            <div class="landing-benefit">
                <div class="landing-benefit-num">1</div>
                <div>
                    <h3>Accessible sans gros budget</h3>
                    <p>Pas besoin de gros moyens pour démarrer : <strong>à partir de 10 000 CFA</strong> vous pouvez commencer et accéder à une solution professionnelle pour gérer votre commerce.</p>
                </div>
            </div>
            <div class="landing-benefit">
                <div class="landing-benefit-num">2</div>
                <div>
                    <h3>Gain de temps au quotidien</h3>
                    <p>Moins de papier, moins d'erreurs. Les ventes et les stocks sont synchronisés et traçables.</p>
                </div>
            </div>
            <div class="landing-benefit">
                <div class="landing-benefit-num">3</div>
                <div>
                    <h3>Pilotage en temps réel & évolutivité</h3>
                    <p>Tableaux de bord et rapports pour piloter votre activité. Commencez avec l'essentiel et activez de nouveaux modules (paie, multi-sites, etc.) selon vos besoins.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="landing-section landing-testimonials">
        <h2 class="landing-section-title">Ils nous font confiance</h2>
        <p class="landing-section-sub">Témoignages et retours d'expérience (placeholder — à remplacer par de vrais témoignages).</p>
        <div class="landing-testimonials-grid">
            <div class="landing-testimonial">
                <p>« Solution claire et réactive. La gestion des ventes et des stocks est devenue beaucoup plus simple. »</p>
                <div class="landing-testimonial-author">— Commerce de détail (placeholder)</div>
            </div>
            <div class="landing-testimonial">
                <p>« Nous gérons plusieurs points de vente avec un seul tableau de bord. Gain de temps énorme. »</p>
                <div class="landing-testimonial-author">— Multi-sites (placeholder)</div>
            </div>
            <div class="landing-testimonial">
                <p>« Rapport qualité/prix au top. L'équipe répond vite et les mises à jour sont régulières. »</p>
                <div class="landing-testimonial-author">— PME (placeholder)</div>
            </div>
        </div>
    </section>

    <section class="landing-section landing-gallery" id="gallery">
        <h2 class="landing-section-title">Aperçu du système</h2>
        <p class="landing-section-sub">Découvrez Inov-Com en images.</p>
        <div class="landing-gallery-grid">
            <div class="landing-gallery-item">
                <img src="{{ asset('images/inov-com-gallerie-v1.png') }}" alt="Inov-Com — Aperçu 1" loading="lazy">
            </div>
            <div class="landing-gallery-item">
                <img src="{{ asset('images/inov-com-gallerie-v2.png') }}" alt="Inov-Com — Aperçu 2" loading="lazy">
            </div>
        </div>
    </section>

    <section class="landing-cta-section">
        <h2>Prêt à simplifier votre gestion commerciale ?</h2>
        <p>Contactez-nous ou accédez directement à la plateforme si vous avez déjà un accès.</p>
        
            <a href="{{ route('demo') }}" class="landing-btn-primary">Réservez une démo</a>
    </section>

    <footer class="landing-footer" id="contact">
        <div class="landing-footer-inner">
            <div class="landing-footer-logo">Inov-Com</div>
            <p class="landing-footer-tagline">Innovation Commercial — Gestion & point de vente</p>
            <div class="landing-contact">
                <a href="tel:+237670274538">+237 670 27 45 38</a>
                <a href="tel:+237688828712">+237 688 82 87 12</a>
                <a href="mailto:contact.invo-com@gmail.com">contact.invo-com@gmail.com</a>
            </div>
            <div class="landing-footer-bottom">
                <a href="{{ url('/') }}">Accueil</a>
                <a href="{{ route('demo') }}">Réservez une démo</a>
            </div>
        </div>
    </footer>
</body>
</html>
