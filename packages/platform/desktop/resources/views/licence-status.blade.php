<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Licence desktop — Bproo Pharma</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #0b1f2a; color: #ecfdf8; min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; }
        .card { max-width: 34rem; }
        h1 { font-size: 1.5rem; margin: 0 0 0.75rem; }
        p, li { line-height: 1.55; color: #c5d8d4; }
        code { background: rgba(255,255,255,0.08); padding: 0.1rem 0.35rem; border-radius: 4px; }
        .ok { color: #5eead4; }
        .bad { color: #fca5a5; }
        a { color: #5eead4; }
    </style>
</head>
<body>
    <div class="card">
        <h1>État de la licence</h1>
        @if(($result['ok'] ?? false))
            <p class="ok">Licence valide — mode <strong>{{ $result['access_mode'] ?? '?' }}</strong></p>
            <p>Accès offline jusqu’au : <code>{{ $result['expires_at'] ?? '—' }}</code></p>
            @if(($result['access_mode'] ?? '') === 'read_only')
                <p class="bad">Fenêtre offline expirée. Reconnectez Internet et lancez <code>desktop:heartbeat</code>.</p>
            @endif
        @else
            <p class="bad">Licence refusée : <code>{{ $result['reason'] ?? 'unknown' }}</code></p>
            <p>Activez l’installation ou corrigez <code>DESKTOP_LICENCE_SIGNING_KEY</code>.</p>
        @endif
        <p><a href="{{ url('/app/login') }}">Retour connexion</a></p>
    </div>
</body>
</html>
