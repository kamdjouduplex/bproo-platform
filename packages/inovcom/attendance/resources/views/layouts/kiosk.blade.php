<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Pointage' }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        .att-kiosk-body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", system-ui, sans-serif;
            background:
                radial-gradient(ellipse 80% 50% at 20% 0%, rgba(15, 118, 110, 0.12), transparent),
                linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
            color: #0f172a;
        }
        .att-kiosk-wrap {
            max-width: 440px;
            margin: 0 auto;
            padding: 32px 18px 48px;
        }
        .att-kiosk-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }
        .att-kiosk-brand {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 6px;
        }
        .att-kiosk-title {
            font-size: 1.45rem;
            font-weight: 700;
            margin: 0 0 8px;
        }
        .att-kiosk-sub {
            color: #64748b;
            font-size: 0.95rem;
            margin: 0 0 20px;
            line-height: 1.45;
        }
        .att-kiosk-field { margin-bottom: 14px; }
        .att-kiosk-field label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: #334155;
        }
        .att-kiosk-field input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .att-kiosk-alert {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .att-kiosk-alert--error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .att-kiosk-alert--success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .att-kiosk-alert--warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .att-kiosk-actions { display: flex; flex-direction: column; gap: 10px; margin-top: 8px; }
        .att-kiosk-actions .btn { width: 100%; justify-content: center; padding: 12px 16px; font-size: 1rem; }
        .att-kiosk-status {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 14px;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 16px;
        }
        .att-kiosk-status strong { display: block; font-size: 1.25rem; }
        .att-kiosk-status span { font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600; }
    </style>
</head>
<body class="att-kiosk-body">
    {{ $slot }}
    @livewireScripts
</body>
</html>
