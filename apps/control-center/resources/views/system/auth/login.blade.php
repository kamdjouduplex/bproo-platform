<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Connexion admin</title>
        @include('partials.favicon')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-page">
        <div class="auth-shell">
            <section class="auth-card">
                <div class="auth-header">
                    <span class="auth-badge">Bproo</span>
                    <div class="auth-title">Control Center</div>
                    <div class="auth-subtitle">Platform operations — companies, billing, modules</div>
                    <div class="auth-meta">
                        <div>Provisioning · health · subscriptions</div>
                        <div>Same landlord DB as product Admin (parallel run)</div>
                    </div>
                </div>
                <div class="card">
                    <form method="POST" action="{{ route('system.login.submit') }}">
                        @csrf
                        <div class="form-grid">
                            <div class="field">
                                <label class="field-label">Email</label>
                                <input class="input" name="email" type="email" required>
                                @error('email')
                                    <div class="field-label" style="color:#dc2626;">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="field">
                                <label class="field-label">Mot de passe</label>
                                <input class="input" name="password" type="password" required>
                            </div>
                            <label class="field-toggle">
                                <input type="checkbox" name="remember">
                                Se souvenir de moi
                            </label>
                        </div>
                        <div class="auth-actions">
                            <button class="btn btn-primary" type="submit">Se connecter</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </body>
</html>
