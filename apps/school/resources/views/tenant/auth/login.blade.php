<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php
            $tenant = app(App\Services\TenantManager::class)->tenant();
            $appName = config('app.name', 'Bproo School');
            $shopName = $tenant ? $tenant->getSetting('shop_name', $tenant->name) : $appName;
            $heroImage = '/images/login-school-hero.png?v=20260815';
        @endphp
        <title>Connexion — {{ $shopName }} | {{ $appName }}</title>
        @include('partials.favicon')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=manrope:500,600,700,800&family=source-serif-4:600,700&display=swap" rel="stylesheet">
        <style>
            :root {
                --sc-ink: #0f2744;
                --sc-blue: #1d4ed8;
                --sc-blue-deep: #1e3a8a;
                --sc-teal: #0d9488;
                --sc-line: #dbe7f5;
                --sc-muted: #5b7390;
                --sc-surface: #f4f8fc;
                --sc-white: #ffffff;
                --sc-danger: #b91c1c;
                --font-display: "Source Serif 4", Georgia, serif;
                --font-body: "Manrope", sans-serif;
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: var(--font-body);
                color: var(--sc-ink);
                background: var(--sc-surface);
                -webkit-font-smoothing: antialiased;
            }

            .login-shell {
                display: grid;
                grid-template-columns: 1fr 1fr;
                min-height: 100vh;
            }

            .login-hero {
                position: relative;
                overflow: hidden;
                color: #fff;
                isolation: isolate;
                background: #1e3a8a;
            }

            .login-hero__media {
                position: absolute;
                inset: 0;
                /* Anchor on the brand block (logo + slogan) in the upper-left of the artwork */
                background: url('{{ $heroImage }}') left 8% top 12% / cover no-repeat;
                transform: scale(1.04);
                animation: sc-kenburns 26s ease-in-out infinite alternate;
                z-index: 0;
            }

            .login-hero__veil {
                position: absolute;
                inset: 0;
                z-index: 1;
                pointer-events: none;
                /* Light edge only — keep logo area clear */
                background:
                    linear-gradient(90deg, transparent 78%, rgba(244, 248, 252, 0.35) 100%);
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

            .login-panel {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: clamp(1.75rem, 4vw, 3.5rem);
                background:
                    radial-gradient(1100px 480px at 100% -10%, rgba(29, 78, 216, 0.08), transparent 55%),
                    radial-gradient(900px 420px at 0% 100%, rgba(13, 148, 136, 0.06), transparent 50%),
                    var(--sc-surface);
                animation: sc-fade 0.75s ease 0.15s both;
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
                color: var(--sc-blue);
            }

            .login-panel__title {
                margin: 0 0 0.45rem;
                font-family: var(--font-display);
                font-size: clamp(1.85rem, 3vw, 2.35rem);
                font-weight: 700;
                letter-spacing: -0.02em;
                color: var(--sc-ink);
            }

            .login-panel__subtitle {
                margin: 0 0 2rem;
                font-size: 0.98rem;
                line-height: 1.5;
                color: var(--sc-muted);
            }

            .form-group { margin-bottom: 1.15rem; }

            .form-label {
                display: block;
                margin-bottom: 0.45rem;
                font-size: 0.86rem;
                font-weight: 600;
                color: var(--sc-ink);
            }

            .form-input {
                width: 100%;
                padding: 0.85rem 0.95rem;
                border: 1.5px solid var(--sc-line);
                border-radius: 10px;
                background: var(--sc-white);
                font-family: inherit;
                font-size: 0.95rem;
                color: var(--sc-ink);
                outline: none;
                transition: border-color 0.18s ease, box-shadow 0.18s ease;
            }

            .form-input::placeholder { color: #94a3b8; }

            .form-input:focus {
                border-color: var(--sc-blue);
                box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.14);
            }

            .form-checkbox {
                display: flex;
                align-items: center;
                gap: 0.55rem;
                margin: 0.35rem 0 1.4rem;
                font-size: 0.9rem;
                color: var(--sc-muted);
            }

            .form-checkbox input {
                width: 1rem;
                height: 1rem;
                accent-color: var(--sc-blue);
            }

            .login-button {
                width: 100%;
                padding: 0.95rem 1rem;
                border: none;
                border-radius: 10px;
                background: linear-gradient(135deg, var(--sc-blue) 0%, var(--sc-teal) 100%);
                color: #fff;
                font-family: inherit;
                font-size: 1rem;
                font-weight: 700;
                cursor: pointer;
                transition: filter 0.18s ease, transform 0.18s ease;
            }

            .login-button:hover {
                filter: brightness(1.05);
                transform: translateY(-1px);
            }

            .login-button:active { transform: translateY(0); }

            .error-message {
                margin-top: 0.4rem;
                color: var(--sc-danger);
                font-size: 0.82rem;
                font-weight: 500;
            }

            .login-panel__footnote {
                margin-top: 1.75rem;
                font-size: 0.8rem;
                color: var(--sc-muted);
                text-align: center;
            }

            @keyframes sc-kenburns {
                from { transform: scale(1.04) translate3d(0, 0, 0); }
                to { transform: scale(1.1) translate3d(0.6%, -0.4%, 0); }
            }

            @keyframes sc-fade {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            @media (max-width: 900px) {
                .login-shell {
                    grid-template-columns: 1fr;
                }

                .login-hero {
                    min-height: 42vh;
                }

                .login-hero__media {
                    background-position: left 10% top 8%;
                }

                .login-hero__veil {
                    background:
                        linear-gradient(to bottom, transparent 72%, rgba(244, 248, 252, 0.85) 100%);
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
                .login-hero__media { transform: scale(1.04); }
            }
        </style>
    </head>
    <body>
        <div class="login-shell">
            <section class="login-hero" aria-label="Bproo School">
                <div class="login-hero__media" role="img" aria-label="Bproo School — La gestion scolaire simplifiée"></div>
                <div class="login-hero__veil" aria-hidden="true"></div>
                <div class="login-hero__content">
                    <h1>Bproo School</h1>
                    <p>La gestion scolaire simplifiée</p>
                </div>
            </section>

            <section class="login-panel">
                <div class="login-panel__inner">
                    <p class="login-panel__eyebrow">Accès établissement</p>
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
                                placeholder="email@ecole.com ou 670274538"
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
                            Entrer dans l’école
                        </button>
                    </form>

                    <p class="login-panel__footnote">{{ $appName }}</p>
                </div>
            </section>
        </div>
    </body>
</html>
