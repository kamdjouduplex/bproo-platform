<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Connexion · Bproo Control Center</title>
        @include('partials.favicon')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-page">
        <div class="auth-stage" aria-hidden="true">
            <span class="auth-stage__glow auth-stage__glow--a"></span>
            <span class="auth-stage__glow auth-stage__glow--b"></span>
            <span class="auth-stage__grid"></span>
        </div>

        <main class="auth-layout">
            <section class="auth-brand">
                <p class="auth-brand__mark">Bproo</p>
                <h1 class="auth-brand__title">Control Center</h1>
                <p class="auth-brand__lead">
                    Pilotez clients, facturation et modules depuis un seul poste d’exploitation.
                </p>
                <ul class="auth-brand__lines">
                    <li>Prospects → opportunités → clients</li>
                    <li>Abonnements, santé et provisioning</li>
                </ul>
            </section>

            <section class="auth-panel">
                <div class="auth-panel__inner">
                    <header class="auth-panel__head">
                        <h2 class="auth-panel__title">Connexion</h2>
                        <p class="auth-panel__hint">Accès réservé à l’équipe plateforme.</p>
                    </header>

                    <form class="auth-form" method="POST" action="{{ route('system.login.submit') }}">
                        @csrf

                        <div class="auth-field">
                            <label class="auth-field__label" for="email">Email</label>
                            <input
                                class="auth-field__input"
                                id="email"
                                name="email"
                                type="email"
                                autocomplete="username"
                                required
                                autofocus
                                value="{{ old('email') }}"
                            >
                            @error('email')
                                <p class="auth-field__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="auth-field">
                            <label class="auth-field__label" for="password">Mot de passe</label>
                            <input
                                class="auth-field__input"
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                            >
                            @error('password')
                                <p class="auth-field__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="auth-remember">
                            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                            <span>Se souvenir de moi</span>
                        </label>

                        <button class="auth-submit" type="submit">Se connecter</button>
                    </form>
                </div>
            </section>
        </main>
    </body>
</html>
