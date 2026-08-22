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
        .toolbar { display: flex; gap: 10px; margin-bottom: 14px; }
        .toolbar a, .toolbar button { font-size: 13px; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; color: #0f2744; text-decoration: none; cursor: pointer; }
        .toolbar .primary { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { color: #64748b; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #111; padding: 5px 7px; text-align: left; }
        th { background: #f1f5f9; }
        .c { text-align: center; width: 18px; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ $returnUrl }}">Retour</a>
        <button type="button" class="primary" onclick="window.print()">Imprimer / PDF</button>
    </div>
    <h1>{{ $shopName }} — Feuille de présence</h1>
    <div class="meta">
        {{ $year?->name ?? 'Année' }}
        · {{ $class?->name ?? 'Classe' }}
        · {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}
        @if(! empty($slot))
            · {{ $slot->sessionLabel() }}
        @else
            · Appel général
        @endif
        · {{ $roster->count() }} élève(s)
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Matricule</th>
                <th>Élève</th>
                <th class="c">P</th>
                <th class="c">A</th>
                <th class="c">R</th>
                <th class="c">E</th>
                <th>Remarque</th>
            </tr>
        </thead>
        <tbody>
            @foreach($roster as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['student']?->student_code }}</td>
                    <td>{{ $row['student']?->full_name }}</td>
                    <td class="c">{{ $row['status'] === 'present' ? '✓' : '' }}</td>
                    <td class="c">{{ $row['status'] === 'absent' ? '✓' : '' }}</td>
                    <td class="c">{{ $row['status'] === 'late' ? '✓' : '' }}</td>
                    <td class="c">{{ $row['status'] === 'excused' ? '✓' : '' }}</td>
                    <td>{{ $row['remark'] ?: '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p style="margin-top:10px; color:#64748b;">P = Présent · A = Absent · R = Retard · E = Excusé</p>
</body>
</html>
