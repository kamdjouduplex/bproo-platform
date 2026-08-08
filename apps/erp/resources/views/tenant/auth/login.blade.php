<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php
            $tenant = app(App\Services\TenantManager::class)->tenant();
            $shopName = $tenant ? $tenant->getSetting('shop_name', $tenant->name) : 'Inov-Com';
            $welcomeMessage = $tenant ? $tenant->getSetting('login_welcome_message', 'Bienvenue dans votre espace de gestion') : 'Bienvenue dans votre espace de gestion';
            $loginLogoUrl = $tenant ? app(\App\Services\TenantBrandingService::class)->url($tenant, 'main') : null;
        @endphp
        <title>Connexion - {{ $shopName }}</title>
        @include('partials.favicon')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: "Inter", "Segoe UI", Arial, sans-serif;
            }
            .login-container {
                display: flex;
                min-height: 100vh;
            }
            .login-left {
                flex: 1;
                background: #ffffff;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 60px;
                position: relative;
                overflow: hidden;
                border-right: 1px solid #e2e8f0;
            }
            .login-left::before {
                content: '';
                position: absolute;
                width: 300px;
                height: 300px;
                background: rgba(102, 126, 234, 0.06);
                border-radius: 50%;
                top: -100px;
                right: -100px;
            }
            .login-left::after {
                content: '';
                position: absolute;
                width: 200px;
                height: 200px;
                background: rgba(118, 75, 162, 0.05);
                border-radius: 50%;
                bottom: -50px;
                left: -50px;
            }
            .branding-content {
                max-width: 480px;
                color: #2d3748;
                position: relative;
                z-index: 1;
            }
            .shop-logo {
                font-size: 42px;
                font-weight: 700;
                margin-bottom: 32px;
                letter-spacing: -0.5px;
                text-align: center;
                color: #1a202c;
            }
            .shop-logo-img {
                max-height: 120px;
                max-width: 300px;
                object-fit: contain;
                margin: 0 auto 32px;
                display: block;
            }
            .welcome-card {
                background: #f8fafc;
                border-radius: 16px;
                padding: 32px;
                border: 1px solid #e2e8f0;
            }
            .welcome-icon {
                width: 56px;
                height: 56px;
                background: #eef2ff;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 24px;
                font-size: 28px;
            }
            .welcome-message {
                font-size: 18px;
                line-height: 1.7;
                text-align: center;
                margin: 0;
                color: #4a5568;
            }
            .login-right {
                flex: 1;
                background: #ffffff;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 60px;
            }
            .login-form-container {
                width: 100%;
                max-width: 420px;
            }
            .login-title {
                font-size: 32px;
                font-weight: 700;
                color: #1a202c;
                margin-bottom: 8px;
            }
            .login-subtitle {
                font-size: 15px;
                color: #718096;
                margin-bottom: 40px;
            }
            .form-group {
                margin-bottom: 24px;
            }
            .form-label {
                display: block;
                font-size: 14px;
                font-weight: 600;
                color: #2d3748;
                margin-bottom: 8px;
            }
            .form-input {
                width: 100%;
                padding: 14px 16px;
                border: 2px solid #e2e8f0;
                border-radius: 10px;
                font-size: 15px;
                transition: all 0.2s;
                outline: none;
            }
            .form-input:focus {
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            .form-checkbox {
                display: flex;
                align-items: center;
                margin-bottom: 24px;
            }
            .form-checkbox input {
                margin-right: 8px;
            }
            .form-checkbox label {
                font-size: 14px;
                color: #4a5568;
            }
            .login-button {
                width: 100%;
                padding: 16px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .login-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            }
            .error-message {
                color: #e53e3e;
                font-size: 13px;
                margin-top: 6px;
            }
            @media (max-width: 968px) {
                .login-container {
                    flex-direction: column;
                }
                .login-left, .login-right {
                    padding: 40px 24px;
                }
                .login-left {
                    min-height: 40vh;
                }
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <!-- Left side - Branding -->
            <div class="login-left">
                <div class="branding-content">
                    @if ($loginLogoUrl)
                        <img src="{{ $loginLogoUrl }}" alt="{{ $shopName }}" class="shop-logo-img">
                    @else
                        <div class="shop-logo">{{ $shopName }}</div>
                    @endif
                    <div class="welcome-card">
                        <p class="welcome-message">{{ $welcomeMessage }}</p>
                    </div>
                </div>
            </div>

            <!-- Right side - Login Form -->
            <div class="login-right">
                <div class="login-form-container">
                    <h1 class="login-title">Connexion</h1>
                    <p class="login-subtitle">Accédez à votre espace de gestion</p>

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
                            >
                        </div>

                        <div class="form-checkbox">
                            <input type="checkbox" name="remember" id="remember">
                            <label for="remember">Se souvenir de moi</label>
                        </div>

                        <button class="login-button" type="submit">
                            Se connecter
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </body>
</html>
