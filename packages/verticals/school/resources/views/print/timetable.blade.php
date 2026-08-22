<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('partials.print.page-setup')
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; font-size: 11px; color: #111; background: #fff; padding: 8mm; }
        .toolbar { display: flex; gap: 10px; margin-bottom: 12px; }
        .toolbar a, .toolbar button { font-size: 13px; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; color: #0f2744; text-decoration: none; cursor: pointer; }
        .toolbar .primary { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { color: #64748b; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; vertical-align: top; }
        th { background: #f1f5f9; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        .time { width: 64px; text-align: right; color: #64748b; font-variant-numeric: tabular-nums; background: #f8fafc; }
        .block { border-radius: 6px; padding: 4px 6px; color: #fff; min-height: 38px; }
        .block strong { display: block; font-size: 11px; }
        .block span { display: block; font-size: 10px; opacity: .92; }
        .legend { margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px; }
        .legend span { display: inline-flex; align-items: center; gap: 6px; }
        .dot { width: 8px; height: 8px; border-radius: 999px; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ $returnUrl }}">Retour</a>
        <button type="button" class="primary" onclick="window.print()">Imprimer / PDF</button>
    </div>
    <h1>{{ $shopName }} — {{ $title }}</h1>
    <div class="meta">{{ $year?->name ?? 'Année' }} · {{ $courses->count() }} cours · grille hebdomadaire</div>
    <table>
        <thead>
            <tr>
                <th class="time">Heure</th>
                @foreach($weekdays as $day)
                    <th>{{ $day }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($periods as $period)
                @php $isBreak = ($period['type'] ?? 'lesson') === 'break'; @endphp
                <tr>
                    <td class="time">{{ $period['start'] }}<br>{{ $period['end'] }}</td>
                    @foreach($weekdays as $dayNum => $dayLabel)
                        @if($isBreak)
                            <td style="background:#fff7ed; color:#9a3412; text-align:center; font-weight:700; font-size:10px; text-transform:uppercase;">{{ $period['label'] }}</td>
                        @else
                            @php $cellSlots = $grid[$period['start']][$dayNum] ?? []; @endphp
                            <td>
                                @foreach($cellSlots as $slot)
                                    <div class="block" style="background:{{ $slot->course?->displayColor() ?? '#0f766e' }}">
                                        <strong>{{ $slot->course?->subject?->name }}</strong>
                                        <span>
                                            @if($view === 'teacher')
                                                {{ $slot->course?->schoolClass?->name }}
                                            @else
                                                {{ $slot->course?->teacher?->full_name }}
                                            @endif
                                            @if($slot->roomLabel()) · {{ $slot->roomLabel() }} @endif
                                        </span>
                                    </div>
                                @endforeach
                            </td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="legend">
        @foreach($courses as $course)
            <span><i class="dot" style="background:{{ $course->displayColor() }}; display:inline-block;"></i>{{ $course->subject?->name }}</span>
        @endforeach
    </div>
</body>
</html>
