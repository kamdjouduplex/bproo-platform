<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('partials.print.page-setup')
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; font-size: 11px; color: #111; background: #fff; padding: 10mm; }
        .toolbar { display: flex; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
        .toolbar a, .toolbar button { font-size: 13px; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; color: #0f2744; text-decoration: none; cursor: pointer; }
        .toolbar .primary { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
        .card { max-width: 190mm; margin: 0 auto 16mm; border: 1.5px solid #111; padding: 14px 16px; page-break-after: always; }
        .card:last-child { page-break-after: auto; }
        .brand-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 10px; }
        .brand { font-size: 16px; font-weight: 800; }
        .tagline { color: #64748b; font-size: 10px; }
        .logo { max-height: 42px; max-width: 90px; object-fit: contain; }
        h1 { text-align: center; font-size: 13px; letter-spacing: 0.06em; text-transform: uppercase; border-top: 1px solid #d1d5db; border-bottom: 2px solid #111; padding: 8px 0; margin: 8px 0 12px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 16px; margin-bottom: 12px; }
        .meta div { display: flex; justify-content: space-between; border-bottom: 1px dashed #e5e7eb; padding: 3px 0; }
        .meta span { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; }
        th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; }
        td.num, th.num { text-align: right; }
        .summary { margin-top: 12px; display: flex; justify-content: space-between; gap: 12px; }
        .summary strong { font-size: 13px; }
        .footer { margin-top: 14px; font-size: 9px; color: #64748b; text-align: center; }
        @media print { .toolbar { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>
@php
    $titles = ['bulletin' => 'Bulletin scolaire', 'transcript' => 'Relevé académique', 'sheet' => 'Feuille de notes'];
@endphp
<div class="toolbar">
    <button class="primary" type="button" onclick="(window.printAndClose || window.print)()">Imprimer / PDF</button>
    @if(!empty($returnUrl))
        <a href="{{ $returnUrl }}">Retour</a>
    @endif
</div>

@foreach($cards as $card)
    @php
        $enrollment = $card['enrollment'];
        $student = $card['student'];
        $result = $card['result'];
    @endphp
    <article class="card">
        <div class="brand-row">
            <div>
                <div class="brand">{{ $shopName }}</div>
                <div class="tagline">Document officiel — {{ $titles[$type] ?? 'Bulletin' }}</div>
            </div>
            @if(!empty($logoSrc))
                <img class="logo" src="{{ $logoSrc }}" alt="Logo">
            @endif
        </div>
        <h1>{{ $titles[$type] ?? 'Bulletin' }}</h1>
        <div class="meta">
            <div><span>Élève</span><strong>{{ $student?->full_name }}</strong></div>
            <div><span>ID</span><strong>{{ $student?->student_code }}</strong></div>
            <div><span>Année</span><strong>{{ $enrollment->academicYear?->name }}</strong></div>
            <div><span>Classe</span><strong>{{ $enrollment->schoolClass?->name ?? '—' }}</strong></div>
        </div>

        @if($type === 'transcript')
            <table>
                <thead><tr><th>Année</th><th>Classe</th><th class="num">Moyenne</th><th>Mention</th><th>Passage</th><th>Promotion</th></tr></thead>
                <tbody>
                @forelse($card['history'] as $h)
                    <tr>
                        <td>{{ $h['year'] }}</td>
                        <td>{{ $h['class'] ?? '—' }}</td>
                        <td class="num">{{ $h['average'] !== null ? number_format((float)$h['average'], 2) : '—' }}</td>
                        <td>{{ $h['grade_label'] ?? '—' }}</td>
                        <td>{{ $h['passed'] ? 'Oui' : 'Non' }}</td>
                        <td>{{ $h['promoted'] ? 'Oui' : 'Non' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Aucun historique.</td></tr>
                @endforelse
                </tbody>
            </table>
        @elseif($type === 'sheet')
            <table>
                <thead><tr><th>Examen</th><th>Matière</th><th class="num">Note</th><th>Absent</th></tr></thead>
                <tbody>
                @forelse($card['marks'] as $m)
                    <tr>
                        <td>{{ $m->exam?->title }}</td>
                        <td>{{ $m->exam?->subject?->name ?? '—' }}</td>
                        <td class="num">{{ $m->is_absent ? '—' : number_format((float)$m->score, 2) }}</td>
                        <td>{{ $m->is_absent ? 'Oui' : 'Non' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Aucune note.</td></tr>
                @endforelse
                </tbody>
            </table>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th class="num">Moyenne</th>
                        <th class="num">Coef.</th>
                        <th class="num">Pondéré</th>
                        <th>Validé</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($result['subjects'] ?? [] as $subj)
                    <tr>
                        <td>{{ $subj['subject_name'] ?? '—' }}</td>
                        <td class="num">{{ $subj['average'] !== null ? number_format((float)$subj['average'], 2) : '—' }}</td>
                        <td class="num">{{ number_format((float)$subj['coefficient'], 2) }}</td>
                        <td class="num">{{ $subj['weighted'] !== null ? number_format((float)$subj['weighted'], 2) : '—' }}</td>
                        <td>{{ $subj['passed'] === null ? '—' : ($subj['passed'] ? 'Oui' : 'Non') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Aucune matière / note calculée.</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="summary">
                <div>Moyenne générale : <strong>{{ $result['average'] !== null ? number_format((float)$result['average'], 2) : '—' }}</strong>
                    / {{ number_format((float)($result['scale_base'] ?? 20), 0) }}</div>
                <div>Mention : <strong>{{ $result['grade_label'] ?? '—' }}</strong></div>
                <div>Passage : <strong>{{ !empty($result['passed']) ? 'Oui' : 'Non' }}</strong></div>
                <div>Promotion : <strong>{{ !empty($result['promoted']) ? 'Oui' : 'Non' }}</strong></div>
            </div>
        @endif

        <div class="footer">Généré le {{ now()->format('d/m/Y H:i') }} — {{ $shopName }}</div>
    </article>
@endforeach
@include('partials.print.auto-print')
</body>
</html>
