<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale ?? app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('Suivi commande') }} {{ $order->number }} — {{ $shopName }}</title>
    @include('partials.favicon')
    <style>
        :root {
            --teal: #3fa796;
            --teal-dark: #0f766e;
            --bg: #f0f4f8;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --ok: #16a34a;
            --warn: #d97706;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            background:
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(63,167,150,.18), transparent),
                var(--bg);
            color: var(--text);
            line-height: 1.45;
        }
        .wrap {
            max-width: 520px;
            margin: 0 auto;
            padding: 24px 16px 40px;
        }
        .brand {
            text-align: center;
            margin-bottom: 20px;
        }
        .brand__logo {
            width: 56px;
            height: 56px;
            object-fit: contain;
            border-radius: 12px;
            background: #fff;
            border: 1px solid var(--line);
            margin-bottom: 8px;
        }
        .brand__name {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--teal-dark);
            margin: 0;
        }
        .brand__tag {
            margin: 4px 0 0;
            font-size: 12px;
            color: var(--muted);
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            margin-bottom: 14px;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            background: #ecfdf5;
            color: var(--teal-dark);
            border: 1px solid #a7f3d0;
        }
        .status-pill--ready { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .status-pill--done { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
        .order-num {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 10px 0 4px;
        }
        .meta { font-size: 13px; color: var(--muted); margin: 0; }
        .meta strong { color: var(--text); font-weight: 600; }

        .pipeline {
            display: flex;
            align-items: flex-start;
            margin: 22px 0 8px;
        }
        .step {
            position: relative;
            flex: 1;
            text-align: center;
            font-size: 10px;
            color: var(--muted);
            font-weight: 600;
            min-width: 0;
        }
        .step__dot {
            position: relative;
            z-index: 1;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            margin: 0 auto 6px;
            background: #fff;
            border: 3px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: var(--muted);
        }
        /* Connector only between steps — never after the last one */
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 12px;
            left: calc(50% + 14px);
            right: calc(-50% + 14px);
            height: 3px;
            background: var(--line);
            z-index: 0;
            border-radius: 2px;
        }
        .step--done:not(:last-child)::after {
            background: var(--teal);
        }
        .step--done .step__dot,
        .step--current .step__dot {
            border-color: var(--teal);
            background: var(--teal);
            color: #fff;
        }
        .step--current { color: var(--teal-dark); }
        .step--done { color: var(--teal-dark); }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 8px;
        }
        .detail {
            background: #f8fafc;
            border-radius: 10px;
            padding: 10px 12px;
        }
        .detail__label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--muted);
            font-weight: 700;
            margin: 0 0 2px;
        }
        .detail__value { margin: 0; font-size: 13px; font-weight: 600; }

        h3 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--muted);
            margin: 0 0 10px;
        }
        .items { list-style: none; margin: 0; padding: 0; }
        .items li {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 8px 0;
            border-bottom: 1px solid var(--line);
            font-size: 14px;
        }
        .items li:last-child { border-bottom: 0; }
        .items__qty {
            color: var(--teal-dark);
            font-weight: 700;
            white-space: nowrap;
        }

        .pay {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #f8fafc;
            font-size: 13px;
        }
        .pay--ok { background: #f0fdf4; color: #15803d; }
        .pay--due { background: #fffbeb; color: #92400e; }

        .note {
            margin-top: 14px;
            font-size: 12px;
            color: var(--muted);
            text-align: center;
        }
        .footer {
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            margin-top: 18px;
        }
    </style>
</head>
<body>
@php
    $lastIndex = max(count($pipeline) - 1, 0);
    $pillClass = match (true) {
        $order->status === 'delivered' => 'status-pill--done',
        $order->status === 'ready' => 'status-pill--ready',
        default => '',
    };
    $paid = (float) $order->balance <= 0.009;
@endphp

<div class="wrap">
    <header class="brand">
        @if (!empty($logoUrl))
            <img class="brand__logo" src="{{ $logoUrl }}" alt="{{ $shopName }}">
        @endif
        <h1 class="brand__name">{{ $shopName }}</h1>
        <p class="brand__tag">{{ __('Suivi de votre commande') }} · {{ __('lecture seule') }}</p>
    </header>

    <section class="card">
        <span class="status-pill {{ $pillClass }}">{{ $statusLabel }}</span>
        <div class="order-num">{{ $order->number }}</div>
        <p class="meta">
            {{ __('Client') }} :
            <strong>{{ $order->client?->full_name ?? '—' }}</strong>
            @if ($order->agence)
                · {{ $order->agence->name }}
            @endif
        </p>

        <div class="pipeline" aria-label="{{ __('Progression') }}">
            @foreach ($pipeline as $i => $step)
                @php
                    // Final step reached → mark all as done (no dangling “current” after Livré)
                    $isComplete = $currentIndex >= $lastIndex;
                    $state = $isComplete || $i < $currentIndex
                        ? 'done'
                        : ($i === $currentIndex ? 'current' : '');
                @endphp
                <div class="step {{ $state ? 'step--'.$state : '' }}">
                    <div class="step__dot">{{ ($isComplete || $i < $currentIndex) ? '✓' : ($i + 1) }}</div>
                    {{ $step['label'] }}
                </div>
            @endforeach
        </div>

        <div class="detail-grid">
            <div class="detail">
                <p class="detail__label">{{ __('Déposée le') }}</p>
                <p class="detail__value">{{ optional($order->received_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div class="detail">
                <p class="detail__label">{{ __('Prévue pour') }}</p>
                <p class="detail__value">{{ optional($order->due_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            @if ($order->currentStage)
                <div class="detail" style="grid-column:1/-1;">
                    <p class="detail__label">{{ __('Étape actuelle') }}</p>
                    <p class="detail__value">{{ __($order->currentStage->name) }}</p>
                </div>
            @endif
        </div>
    </section>

    <section class="card">
        <h3>{{ __('Articles') }}</h3>
        <ul class="items">
            @forelse ($order->items as $item)
                <li>
                    <span>{{ $item->articleType?->name ?? __('Article') }}</span>
                    <span class="items__qty">× {{ (float) $item->quantity == (int) $item->quantity ? (int) $item->quantity : $item->quantity }}</span>
                </li>
            @empty
                <li>{{ __('Aucun article listé') }}</li>
            @endforelse
        </ul>
    </section>

    <section class="card">
        <h3>{{ __('Paiement') }}</h3>
        <div class="pay {{ $paid ? 'pay--ok' : 'pay--due' }}">
            @if ($paid)
                <span>✓ {{ __('Commande soldée') }}</span>
            @else
                <span>{{ __('Reste à régler') }}</span>
                <strong>{{ number_format((float) $order->balance, 0, ',', ' ') }} {{ $currency }}</strong>
            @endif
        </div>
        <p class="note">
            {{ __('Cette page est publique et en lecture seule. Aucune action n’est possible ici.') }}
            @if (!empty($phone))
                <br>{{ __('Contact') }} : {{ $phone }}
            @endif
        </p>
    </section>

    <p class="footer">{{ $shopName }} · {{ __('Suivi commande') }}</p>
</div>
</body>
</html>
