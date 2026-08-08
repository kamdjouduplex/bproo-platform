<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php
            $tenant = app(App\Services\TenantManager::class)->tenant();
            $appName = config('app.name', 'Bproo Pharma');
            $shopName = $tenant ? $tenant->getSetting('shop_name', $tenant->name) : $appName;
            $heroImage = '/images/login-pharmacy-hero.png';
        @endphp
        <title>Connexion — {{ $shopName }} | {{ $appName }}</title>
        @include('partials.favicon')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=fraunces:600,700&family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet">
        <style>
            :root {
                --ph-ink: #0b1f2a;
                --ph-teal: #0f766e;
                --ph-teal-deep: #115e59;
                --ph-mint: #ecfdf8;
                --ph-line: #d7ebe4;
                --ph-muted: #5b7380;
                --ph-surface: #f7fbfa;
                --ph-white: #ffffff;
                --ph-danger: #b91c1c;
                --font-display: "Fraunces", Georgia, serif;
                --font-body: "Plus Jakarta Sans", sans-serif;
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: var(--font-body);
                color: var(--ph-ink);
                background: var(--ph-surface);
                -webkit-font-smoothing: antialiased;
            }

            .login-shell {
                display: grid;
                grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
                min-height: 100vh;
            }

            /* —— Visual plane (branded pharmacy hero) —— */
            .login-hero {
                position: relative;
                overflow: hidden;
                color: #fff;
                isolation: isolate;
                background: #0b3d3a;
            }

            .login-hero__media {
                position: absolute;
                inset: 0;
                background:
                    url('{{ $heroImage }}') center / cover no-repeat;
                transform: scale(1.02);
                animation: ph-kenburns 24s ease-in-out infinite alternate;
                z-index: 0;
            }

            /* Soft edge only — image already carries brand overlays */
            .login-hero__veil {
                position: absolute;
                inset: 0;
                z-index: 1;
                pointer-events: none;
                background:
                    linear-gradient(90deg, transparent 72%, rgba(247, 251, 250, 0.55) 100%),
                    linear-gradient(to top, rgba(7, 32, 38, 0.18) 0%, transparent 28%);
            }

            .login-hero__content {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }

            /* —— Auth column —— */
            .login-panel {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: clamp(1.75rem, 4vw, 3.5rem);
                background:
                    radial-gradient(1200px 500px at 100% -10%, rgba(15, 118, 110, 0.08), transparent 55%),
                    var(--ph-surface);
                animation: ph-fade 0.75s ease 0.15s both;
            }

            .login-panel__inner {
                width: 100%;
                max-width: 400px;
            }

            .login-panel__eyebrow {
                margin: 0 0 0.65rem;
                font-size: 0.78rem;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: var(--ph-teal);
            }

            .login-panel__title {
                margin: 0 0 0.45rem;
                font-family: var(--font-display);
                font-size: clamp(1.85rem, 3vw, 2.35rem);
                font-weight: 700;
                letter-spacing: -0.02em;
                color: var(--ph-ink);
            }

            .login-panel__subtitle {
                margin: 0 0 2rem;
                font-size: 0.98rem;
                line-height: 1.5;
                color: var(--ph-muted);
            }

            .form-group { margin-bottom: 1.15rem; }

            .form-label {
                display: block;
                margin-bottom: 0.45rem;
                font-size: 0.86rem;
                font-weight: 600;
                color: var(--ph-ink);
            }

            .form-input {
                width: 100%;
                padding: 0.85rem 0.95rem;
                border: 1.5px solid var(--ph-line);
                border-radius: 10px;
                background: var(--ph-white);
                font-family: inherit;
                font-size: 0.95rem;
                color: var(--ph-ink);
                outline: none;
                transition: border-color 0.18s ease, box-shadow 0.18s ease;
            }

            .form-input::placeholder { color: #94a3b8; }

            .form-input:focus {
                border-color: var(--ph-teal);
                box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.14);
            }

            .form-checkbox {
                display: flex;
                align-items: center;
                gap: 0.55rem;
                margin: 0.35rem 0 1.4rem;
                font-size: 0.9rem;
                color: var(--ph-muted);
            }

            .form-checkbox input {
                width: 1rem;
                height: 1rem;
                accent-color: var(--ph-teal);
            }

            .login-button {
                width: 100%;
                padding: 0.95rem 1rem;
                border: none;
                border-radius: 10px;
                background: var(--ph-teal);
                color: #fff;
                font-family: inherit;
                font-size: 1rem;
                font-weight: 700;
                cursor: pointer;
                transition: background 0.18s ease, transform 0.18s ease;
            }

            .login-button:hover {
                background: var(--ph-teal-deep);
                transform: translateY(-1px);
            }

            .login-button:active { transform: translateY(0); }

            .error-message {
                margin-top: 0.4rem;
                color: var(--ph-danger);
                font-size: 0.82rem;
                font-weight: 500;
            }

            .login-panel__footnote {
                margin-top: 1.75rem;
                font-size: 0.8rem;
                color: var(--ph-muted);
                text-align: center;
            }

            @keyframes ph-kenburns {
                from { transform: scale(1.02) translate3d(0, 0, 0); }
                to { transform: scale(1.08) translate3d(-1%, -0.8%, 0); }
            }

            @keyframes ph-fade {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            @media (max-width: 900px) {
                .login-shell {
                    grid-template-columns: 1fr;
                }

                .login-hero {
                    min-height: 46vh;
                }

                .login-hero__veil {
                    background:
                        linear-gradient(to bottom, transparent 70%, rgba(247, 251, 250, 0.75) 100%);
                }

                .login-panel {
                    padding: 1.75rem 1.35rem 2.5rem;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .login-hero__media,
                .login-panel {
                    animation: none;
                }
                .login-hero__media { transform: scale(1.02); }
            }
        </style>
    </head>
    <body>
        <div class="login-shell">
            <section class="login-hero" aria-label="Bproo Pharma">
                <div class="login-hero__media" role="img" aria-label="Bproo Pharma — La gestion pharma simplifiée"></div>
                <div class="login-hero__veil" aria-hidden="true"></div>
                <div class="login-hero__content">
                    <h1>Bproo Pharma</h1>
                    <p>La gestion pharma simplifiée</p>
                </div>
            </section>

            <section class="login-panel">
                <div class="login-panel__inner">
                    <p class="login-panel__eyebrow">Accès officine</p>
                    <h2 class="login-panel__title">Connexion</h2>
                    <p class="login-panel__subtitle">Identifiez-vous pour continuer.</p>

                    <form method="POST" action="{{ route('tenant.login.submit', ['tenant' => request()->query('tenant')]) }}">
                        @csrf

                        <div class="form-group">
                            <label class="form-label" for="login">Email ou téléphone</label>
                            <input
                                class="form-input"
                                id="login"
                                name="login"
                                type="text"
                                placeholder="email@officine.com ou 670274538"
                                value="{{ old('login', old('email')) }}"
                                required
                                autofocus
                                autocomplete="username"
                            >
                            @error('login')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                            @error('email')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">Mot de passe</label>
                            <input
                                class="form-input"
                                id="password"
                                name="password"
                                type="password"
                                placeholder="••••••••"
                                required
                                autocomplete="current-password"
                            >
                            @error('password')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-checkbox">
                            <input type="checkbox" name="remember" id="remember">
                            <label for="remember">Se souvenir de moi</label>
                        </div>

                        <button class="login-button" type="submit">
                            Entrer dans la pharmacie
                        </button>
                    </form>

                    <p class="login-panel__footnote">{{ $appName }}</p>
                </div>
            </section>
        </div>
    </body>
</html>
