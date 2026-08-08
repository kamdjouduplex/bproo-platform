<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php
            $tenant = app(App\Services\TenantManager::class)->tenant();
            $shopName = $tenant ? $tenant->getSetting('shop_name', $tenant->name) : config('app.name', 'Bproo Pressing');
            $welcomeMessage = $tenant
                ? $tenant->getSetting('login_welcome_message', 'Bienvenue dans votre espace de gestion')
                : 'Bienvenue dans votre espace de gestion';
            $loginLogoUrl = $tenant ? app(\App\Services\TenantBrandingService::class)->url($tenant, 'main') : null;
            $heroUrl = asset('images/auth-hero-pressing.png');
        @endphp
        <title>Connexion - {{ $shopName }}</title>
        @include('partials.favicon')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                --pressing-blue: #0b4fbf;
                --pressing-blue-deep: #063a8f;
                --pressing-navy: #0b1f3a;
                --pressing-sky: #e8f1ff;
                --pressing-mist: #f5f8fc;
                --pressing-line: #d7e3f4;
                --pressing-muted: #5b6b82;
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: "Source Sans 3", "Segoe UI", sans-serif;
                color: var(--pressing-navy);
                background: #eef3f9;
            }

            .login-shell {
                display: grid;
                grid-template-columns: minmax(0, 1.15fr) minmax(360px, 0.85fr);
                min-height: 100vh;
            }

            .login-hero {
                position: relative;
                overflow: hidden;
                background: linear-gradient(145deg, #d9e8ff 0%, #b7d0f5 45%, #8eb6ef 100%);
            }

            .login-hero__media {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: left center;
                transform: scale(1.02);
                animation: heroSettle 1.2s ease-out both;
            }

            .login-hero__veil {
                position: absolute;
                inset: 0;
                background:
                    linear-gradient(90deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0) 42%),
                    linear-gradient(180deg, rgba(11, 31, 58, 0.08) 0%, rgba(11, 31, 58, 0) 28%, rgba(11, 31, 58, 0.18) 100%);
                pointer-events: none;
            }

            .login-hero__badge {
                position: absolute;
                left: 28px;
                bottom: 28px;
                z-index: 2;
                display: inline-flex;
                align-items: center;
                gap: 10px;
                padding: 10px 14px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.88);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.7);
                box-shadow: 0 10px 30px rgba(11, 31, 58, 0.12);
                animation: riseIn 0.7s ease 0.25s both;
            }

            .login-hero__badge-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #16a34a;
                box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.18);
            }

            .login-hero__badge span {
                font-family: Manrope, sans-serif;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.02em;
                color: var(--pressing-navy);
            }

            .login-panel {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 48px 40px;
                background:
                    radial-gradient(ellipse 80% 50% at 100% 0%, rgba(11, 79, 191, 0.08), transparent 55%),
                    linear-gradient(180deg, #ffffff 0%, var(--pressing-mist) 100%);
            }

            .login-panel__inner {
                width: 100%;
                max-width: 420px;
                animation: riseIn 0.65s ease both;
            }

            .login-brand {
                display: flex;
                align-items: center;
                gap: 14px;
                margin-bottom: 28px;
            }

            .login-brand__logo {
                width: 52px;
                height: 52px;
                object-fit: contain;
                border-radius: 14px;
                background: #fff;
                border: 1px solid var(--pressing-line);
                box-shadow: 0 8px 20px rgba(11, 79, 191, 0.08);
            }

            .login-brand__mark {
                width: 52px;
                height: 52px;
                border-radius: 14px;
                display: grid;
                place-items: center;
                background: linear-gradient(145deg, var(--pressing-blue), var(--pressing-blue-deep));
                color: #fff;
                font-family: Manrope, sans-serif;
                font-weight: 800;
                font-size: 20px;
                box-shadow: 0 10px 24px rgba(11, 79, 191, 0.28);
            }

            .login-brand__meta {
                display: flex;
                flex-direction: column;
                gap: 2px;
                min-width: 0;
            }

            .login-brand__name {
                margin: 0;
                font-family: Manrope, sans-serif;
                font-size: 1.05rem;
                font-weight: 800;
                letter-spacing: -0.02em;
                color: var(--pressing-navy);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .login-brand__tag {
                margin: 0;
                font-size: 13px;
                color: var(--pressing-muted);
            }

            .login-card {
                background: rgba(255, 255, 255, 0.92);
                border: 1px solid rgba(215, 227, 244, 0.95);
                border-radius: 22px;
                padding: 28px 26px 26px;
                box-shadow:
                    0 1px 0 rgba(255, 255, 255, 0.9) inset,
                    0 18px 50px rgba(11, 31, 58, 0.08);
            }

            .login-title {
                margin: 0 0 6px;
                font-family: Manrope, sans-serif;
                font-size: 1.75rem;
                font-weight: 800;
                letter-spacing: -0.03em;
                color: var(--pressing-navy);
            }

            .login-subtitle {
                margin: 0 0 26px;
                font-size: 0.98rem;
                line-height: 1.5;
                color: var(--pressing-muted);
            }

            .form-group {
                margin-bottom: 18px;
            }

            .form-label {
                display: block;
                margin-bottom: 7px;
                font-size: 13px;
                font-weight: 700;
                color: #24344f;
            }

            .form-input {
                width: 100%;
                padding: 13px 14px;
                border: 1.5px solid var(--pressing-line);
                border-radius: 12px;
                font-size: 15px;
                font-family: inherit;
                color: var(--pressing-navy);
                background: #fff;
                outline: none;
                transition: border-color 0.18s ease, box-shadow 0.18s ease;
            }

            .form-input::placeholder {
                color: #93a1b5;
            }

            .form-input:focus {
                border-color: var(--pressing-blue);
                box-shadow: 0 0 0 4px rgba(11, 79, 191, 0.12);
            }

            .form-checkbox {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 4px 0 22px;
                font-size: 14px;
                color: #40526c;
            }

            .form-checkbox input {
                width: 16px;
                height: 16px;
                accent-color: var(--pressing-blue);
            }

            .login-button {
                width: 100%;
                padding: 14px 16px;
                border: 0;
                border-radius: 12px;
                font-family: Manrope, sans-serif;
                font-size: 15px;
                font-weight: 700;
                color: #fff;
                cursor: pointer;
                background: linear-gradient(135deg, var(--pressing-blue) 0%, var(--pressing-blue-deep) 100%);
                box-shadow: 0 12px 28px rgba(11, 79, 191, 0.28);
                transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
            }

            .login-button:hover {
                transform: translateY(-1px);
                filter: brightness(1.03);
                box-shadow: 0 16px 34px rgba(11, 79, 191, 0.34);
            }

            .login-button:active {
                transform: translateY(0);
            }

            .error-message {
                margin-top: 6px;
                font-size: 13px;
                color: #dc2626;
            }

            .login-foot {
                margin-top: 18px;
                text-align: center;
                font-size: 12px;
                color: #8090a8;
            }

            @keyframes riseIn {
                from { opacity: 0; transform: translateY(14px); }
                to { opacity: 1; transform: translateY(0); }
            }

            @keyframes heroSettle {
                from { opacity: 0.65; transform: scale(1.06); }
                to { opacity: 1; transform: scale(1.02); }
            }

            @media (max-width: 980px) {
                .login-shell {
                    grid-template-columns: 1fr;
                }

                .login-hero {
                    min-height: 34vh;
                }

                .login-hero__media {
                    object-position: left top;
                }

                .login-panel {
                    padding: 28px 20px 40px;
                }

                .login-card {
                    padding: 22px 18px;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .login-hero__media,
                .login-hero__badge,
                .login-panel__inner {
                    animation: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="login-shell">
            <aside class="login-hero" aria-hidden="true">
                <img
                    class="login-hero__media"
                    src="{{ $heroUrl }}"
                    alt=""
                    width="1600"
                    height="900"
                    decoding="async"
                    fetchpriority="high"
                >
                <div class="login-hero__veil"></div>
                <div class="login-hero__badge">
                    <span class="login-hero__badge-dot" aria-hidden="true"></span>
                    <span>Propreté · Qualité · Confiance</span>
                </div>
            </aside>

            <main class="login-panel">
                <div class="login-panel__inner">
                    <div class="login-brand">
                        @if ($loginLogoUrl)
                            <img class="login-brand__logo" src="{{ $loginLogoUrl }}" alt="{{ $shopName }}">
                        @else
                            <div class="login-brand__mark" aria-hidden="true">B</div>
                        @endif
                        <div class="login-brand__meta">
                            <p class="login-brand__name">{{ $shopName }}</p>
                            <p class="login-brand__tag">Espace de gestion pressing</p>
                        </div>
                    </div>

                    <section class="login-card">
                        <h1 class="login-title">Connexion</h1>
                        <p class="login-subtitle">{{ $welcomeMessage }}</p>

                        <form method="POST" action="{{ route('tenant.login.submit', ['tenant' => request()->query('tenant')]) }}">
                            @csrf

                            <div class="form-group">
                                <label class="form-label" for="login">Email ou téléphone</label>
                                <input
                                    class="form-input"
                                    id="login"
                                    name="login"
                                    type="text"
                                    placeholder="votre@email.com ou 670274538"
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

                            <label class="form-checkbox">
                                <input type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                                <span>Se souvenir de moi</span>
                            </label>

                            <button class="login-button" type="submit">Se connecter</button>
                        </form>
                    </section>

                    <p class="login-foot">Bproo Pressing · Accès sécurisé</p>
                </div>
            </main>
        </div>
    </body>
</html>
