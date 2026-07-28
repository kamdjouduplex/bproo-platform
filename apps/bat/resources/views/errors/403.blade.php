<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        try {
            $tenant = app(App\Services\TenantManager::class)->tenant();
            $shopName = $tenant ? $tenant->getSetting('shop_name', $tenant->name) : config('app.name');
        } catch (\Throwable $e) {
            $tenant   = null;
            $shopName = config('app.name');
        }
        $tenantCode   = $tenant?->code ?? request()->query('tenant') ?? session('tenant_code');
        $dashboardUrl = $tenantCode
            ? route('tenant.dashboard', ['tenant' => $tenantCode])
            : (auth()->check() ? route('system.dashboard') : '/');
        $backUrl      = url()->previous() !== url()->current() ? url()->previous() : $dashboardUrl;
    @endphp
    <title>403 — Accès refusé | {{ $shopName }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* ── Animated background ─────────────────────────── */
        .bg-layer {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse 70% 60% at 15% 110%, rgba(239,68,68,.20) 0%, transparent 65%),
                radial-gradient(ellipse 55% 55% at 85% -5%,  rgba(99,102,241,.18) 0%, transparent 65%),
                radial-gradient(ellipse 80% 80% at 50% 50%,  #111827 30%, #0f172a 100%);
        }
        .bg-grid {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
            background-size: 48px 48px;
        }

        /* ── Card ────────────────────────────────────────── */
        .card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 520px;
            margin: 24px;
            background: rgba(30,41,59,.75);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 20px;
            padding: 56px 48px 48px;
            text-align: center;
            backdrop-filter: blur(16px);
            box-shadow:
                0 0 0 1px rgba(255,255,255,.04),
                0 24px 64px rgba(0,0,0,.5);
        }

        /* ── Shield icon ─────────────────────────────────── */
        .icon-wrap {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(239,68,68,.20), rgba(239,68,68,.08));
            border: 1px solid rgba(239,68,68,.25);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 32px;
            box-shadow: 0 0 40px rgba(239,68,68,.15);
        }
        .icon-wrap svg {
            width: 40px;
            height: 40px;
            color: #ef4444;
        }

        /* ── Code badge ──────────────────────────────────── */
        .code-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(239,68,68,.12);
            border: 1px solid rgba(239,68,68,.22);
            color: #f87171;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 999px;
            margin-bottom: 20px;
        }
        .code-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #ef4444;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: .3; }
        }

        /* ── Typography ──────────────────────────────────── */
        h1 {
            font-size: 28px;
            font-weight: 800;
            color: #f1f5f9;
            letter-spacing: -.5px;
            line-height: 1.2;
            margin-bottom: 12px;
        }
        .subtitle {
            font-size: 14px;
            color: #94a3b8;
            line-height: 1.7;
            max-width: 360px;
            margin: 0 auto 36px;
        }
        .subtitle strong {
            color: #cbd5e1;
            font-weight: 600;
        }

        /* ── Message box ─────────────────────────────────── */
        .message-box {
            background: rgba(239,68,68,.06);
            border: 1px solid rgba(239,68,68,.15);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 32px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-align: left;
        }
        .message-box svg {
            flex-shrink: 0;
            width: 16px;
            height: 16px;
            color: #f87171;
            margin-top: 2px;
        }
        .message-box p {
            font-size: 13px;
            color: #94a3b8;
            line-height: 1.6;
        }
        .message-box p strong { color: #f87171; }

        /* ── Buttons ─────────────────────────────────────── */
        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all .15s;
            cursor: pointer;
            border: none;
        }
        .btn svg { width: 15px; height: 15px; }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            box-shadow: 0 4px 14px rgba(99,102,241,.35);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(99,102,241,.45);
        }

        .btn-ghost {
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            color: #94a3b8;
        }
        .btn-ghost:hover {
            background: rgba(255,255,255,.1);
            color: #e2e8f0;
            transform: translateY(-1px);
        }

        /* ── Footer ──────────────────────────────────────── */
        .footer {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid rgba(255,255,255,.06);
            font-size: 11.5px;
            color: #475569;
        }
        .footer a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 500;
        }
        .footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="bg-layer"></div>
    <div class="bg-grid"></div>

    <div class="card">
        <div class="icon-wrap">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v3.75m0-10.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.25-8.25-3.286zm0 13.036h.008v.008H12v-.008z" />
            </svg>
        </div>

        <div class="code-badge">Erreur 403</div>

        <h1>Accès non autorisé</h1>

        <p class="subtitle">
            Vous n'avez pas les <strong>permissions nécessaires</strong> pour accéder à cette page.
            Contactez votre administrateur si vous pensez qu'il s'agit d'une erreur.
        </p>

        @if($exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.')
        <div class="message-box">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p><strong>Détail :</strong> {{ $exception->getMessage() }}</p>
        </div>
        @endif

        <div class="btn-group">
            @if($backUrl !== $dashboardUrl)
            <a href="{{ $backUrl }}" class="btn btn-ghost">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Retour
            </a>
            @endif
            <a href="{{ $dashboardUrl }}" class="btn btn-primary">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Tableau de bord
            </a>
        </div>

        <div class="footer">
            {{ $shopName }} &mdash;
            Si ce problème persiste, contactez votre
            <a href="mailto:{{ $tenant?->getSetting('company_email') ?: 'support@inovcom.cm' }}">administrateur</a>.
        </div>
    </div>
</body>
</html>
