<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Compte désactivé - {{ config('app.name') }}</title>
    @include('partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { margin: 0; font-family: "Inter", "Segoe UI", Arial, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f5f5f5; }
        .error-card { max-width: 440px; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center; }
        .error-icon { width: 64px; height: 64px; margin: 0 auto 24px; background: #fef3c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .error-icon svg { width: 32px; height: 32px; color: #b45309; }
        .error-title { font-size: 20px; font-weight: 600; color: #1f2937; margin-bottom: 12px; }
        .error-message { color: #6b7280; line-height: 1.6; margin-bottom: 24px; }
        .error-message strong { color: #374151; }
        .error-actions { display: flex; flex-direction: column; gap: 12px; }
        .error-actions a { display: block; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; text-align: center; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #f3f4f6; color: #374151; }
        .btn-secondary:hover { background: #e5e7eb; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
        </div>
        <h1 class="error-title">Compte désactivé</h1>
        <p class="error-message">
            @if($hasActiveSubscription ?? false)
                L'espace <strong>{{ $tenantName ?? 'ce point de vente' }}</strong> ({{ $tenantCode ?? '' }}) n'est plus accessible car l'accès a été désactivé par l'administrateur. Votre abonnement est toutefois actif. Si vous pensez qu'il s'agit d'une erreur, veuillez contacter l'administrateur pour réactiver votre accès.
            @else
                L'espace <strong>{{ $tenantName ?? 'ce point de vente' }}</strong> ({{ $tenantCode ?? '' }}) n'est plus accessible car il a été désactivé. Votre abonnement n'est pas actif. Veuillez contacter l'administrateur ou le support technique pour renouveler votre abonnement et réactiver votre accès.
            @endif
        </p>
        <p class="error-message" style="font-size: 14px; margin-bottom: 0;">
            Pour toute question, contactez l'administrateur ou le support technique.
        </p>
        <div class="error-actions" style="margin-top: 24px;">
            <a href="{{ url('/#contact') }}" class="btn-primary" target="_blank" rel="noopener noreferrer">Contacter l'Administrateur</a>
            <a href="{{ url('/') }}" class="btn-secondary">Page d'accueil</a>
        </div>
    </div>
</body>
</html>
