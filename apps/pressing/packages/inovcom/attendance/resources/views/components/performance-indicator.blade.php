@php
    $level = $report['performance_level'] ?? 'poor';
    $percent = $report['performance_percent'] ?? 0;
    $colors = [
        'excellent' => ['bg' => '#f0fdf4', 'border' => '#86efac', 'text' => '#166534'],
        'good' => ['bg' => '#eff6ff', 'border' => '#93c5fd', 'text' => '#1d4ed8'],
        'warning' => ['bg' => '#fffbeb', 'border' => '#fcd34d', 'text' => '#b45309'],
        'poor' => ['bg' => '#fef2f2', 'border' => '#fca5a5', 'text' => '#b91c1c'],
    ];
    $c = $colors[$level] ?? $colors['poor'];
@endphp
<div class="attendance-performance attendance-performance--{{ $level }}" style="background:{{ $c['bg'] }}; border:1px solid {{ $c['border'] }}; border-radius:10px; padding:16px;">
    <div style="display:flex; flex-wrap:wrap; gap:20px; align-items:center;">
        <div style="text-align:center; min-width:100px;">
            <div style="font-size:32px; font-weight:800; color:{{ $c['text'] }};">{{ fmt_num($percent, 1) }}%</div>
            <div style="font-size:12px; font-weight:700; color:{{ $c['text'] }};">{{ $report['performance_label'] ?? '' }}</div>
        </div>
        <div style="display:flex; gap:20px; flex-wrap:wrap; font-size:13px;">
            <div><span style="color:#6b7280;">Jours ouvrés</span><br><strong>{{ $report['expected_days'] ?? 0 }}</strong></div>
            <div><span style="color:#6b7280;">Présences</span><br><strong style="color:#16a34a;">{{ $report['present_days'] ?? 0 }}</strong></div>
            <div><span style="color:#6b7280;">Jours complets</span><br><strong style="color:#1d4ed8;">{{ $report['complete_days'] ?? 0 }}</strong></div>
            <div><span style="color:#6b7280;">Absences</span><br><strong style="color:#b91c1c;">{{ $report['absent_days'] ?? 0 }}</strong></div>
        </div>
    </div>
</div>
