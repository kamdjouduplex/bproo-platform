<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('partials.print.page-setup')
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; font-size: 12px; color: #111; background: #fff; padding: 12mm; }
        .toolbar { display: flex; gap: 10px; margin-bottom: 14px; }
        .toolbar a, .toolbar button { font-size: 13px; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; color: #0f2744; text-decoration: none; cursor: pointer; }
        .toolbar .primary { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
        .doc { max-width: 190mm; margin: 0 auto; border: 1.5px solid #111; padding: 18px 20px; }
        .brand { font-size: 18px; font-weight: 800; margin-bottom: 2px; }
        .tagline { color: #64748b; font-size: 11px; margin-bottom: 12px; }
        h1 { text-align: center; font-size: 15px; letter-spacing: 0.08em; text-transform: uppercase; border-top: 1px solid #d1d5db; border-bottom: 2px solid #111; padding: 10px 0; margin-bottom: 16px; }
        .identity { display: flex; gap: 18px; margin-bottom: 16px; }
        .photo { width: 28mm; height: 36mm; object-fit: cover; border: 1px solid #cbd5e1; background: #f8fafc; }
        .photo-ph { width: 28mm; height: 36mm; border: 1px dashed #94a3b8; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 10px; text-align: center; }
        .meta { flex: 1; }
        .row { display: flex; justify-content: space-between; gap: 12px; padding: 5px 0; border-bottom: 1px dashed #e5e7eb; }
        .row span { color: #64748b; }
        .sign { display: flex; justify-content: space-between; margin-top: 28px; gap: 24px; }
        .sign div { flex: 1; text-align: center; font-size: 11px; }
        .sign .line { margin-top: 36px; border-top: 1px solid #111; padding-top: 6px; }
        .footer { margin-top: 18px; font-size: 10px; color: #64748b; text-align: center; }
        @media print { .toolbar { display: none; } body { padding: 0; } .doc { border: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ $returnUrl }}">Retour</a>
        <button type="button" class="primary" onclick="window.print()">Imprimer / PDF</button>
    </div>
    <div class="doc">
        <div class="brand">{{ $shopName }}</div>
        <div class="tagline">{{ $settings['shop_address'] ?? '' }} {{ !empty($settings['shop_phone']) ? '· '.$settings['shop_phone'] : '' }}</div>
        <h1>Fiche d’inscription</h1>
        <div class="identity">
            @if($photoUrl)
                <img class="photo" src="{{ $photoUrl }}" alt="">
            @else
                <div class="photo-ph">Photo</div>
            @endif
            <div class="meta">
                <div class="row"><span>Matricule</span><strong>{{ $student?->student_code }}</strong></div>
                <div class="row"><span>NISU</span><strong>{{ $student?->nisu ?: '—' }}</strong></div>
                <div class="row"><span>Élève</span><strong>{{ $student?->full_name }}</strong></div>
                <div class="row"><span>Genre</span><strong>{{ $student?->gender ?: '—' }}</strong></div>
                <div class="row"><span>Naissance</span><strong>{{ $student?->birth_date?->format('d/m/Y') ?: '—' }}</strong></div>
                <div class="row"><span>Adresse</span><strong>{{ $student?->address ?: '—' }}</strong></div>
            </div>
        </div>
        <div class="row"><span>Année académique</span><strong>{{ $enrollment->academicYear?->name }}</strong></div>
        <div class="row"><span>Classe</span><strong>{{ $enrollment->schoolClass?->name }} {{ $enrollment->section ? '· '.$enrollment->section : '' }}</strong></div>
        <div class="row"><span>Statut</span><strong>{{ $enrollment->status }}</strong></div>
        <div class="row"><span>Parent / Tuteur</span><strong>{{ $student?->parent_full_name ?: '—' }}</strong></div>
        <div class="row"><span>Téléphone parent</span><strong>{{ $student?->parent_phone ?: '—' }}</strong></div>
        <div class="row"><span>Contact d’urgence</span><strong>{{ trim(($student?->emergency_contact_name ?? '').' '.($student?->emergency_contact_phone ?? '')) ?: '—' }}</strong></div>
        <div class="row"><span>Date d’inscription</span><strong>{{ $enrollment->created_at?->format('d/m/Y') }}</strong></div>
        <div class="sign">
            <div><div class="line">Le parent / tuteur</div></div>
            <div><div class="line">L’administration</div></div>
        </div>
        <div class="footer">Document généré par Bproo School — {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</body>
</html>
