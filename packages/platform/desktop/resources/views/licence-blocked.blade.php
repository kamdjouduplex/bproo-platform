<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Licence requise — Bproo Pharma</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #0b1f2a; color: #ecfdf8; min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; }
        .card { max-width: 32rem; }
        h1 { font-size: 1.5rem; margin: 0 0 0.75rem; }
        p { line-height: 1.55; color: #c5d8d4; }
        code { background: rgba(255,255,255,0.08); padding: 0.1rem 0.35rem; border-radius: 4px; }
        a { color: #5eead4; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Accès licence bloqué</h1>
        <p>{{ $message }}</p>
        <p>Code: <code>{{ $reason }}</code></p>
        <p>Contactez Bproo Dev pour réactiver, ou lancez <code>desktop:activate</code> / <code>desktop:heartbeat</code> avec la bonne clé <code>DESKTOP_LICENCE_SIGNING_KEY</code>.</p>
    </div>
</body>
</html>
