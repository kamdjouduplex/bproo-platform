@php
    $tenantCode = $tenantCode ?? request()->query('tenant');
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif

    <section class="card" style="padding:20px; margin-bottom:16px; max-width:720px;">
        <div class="card-title" style="margin-bottom:8px;">Réseau Wi‑Fi entreprise</div>
        <p style="color:#64748b; font-size:0.9rem; margin-bottom:16px; line-height:1.45;">
            Le <strong>nom du Wi‑Fi</strong> sert uniquement d’indication aux employés.
            Pour activer le contrôle, vous devez aussi renseigner au moins une <strong>IP / plage CIDR</strong>
            (bouton « Ajouter mon IP actuelle » tout en étant connecté au Wi‑Fi de l’entreprise).
        </p>

        @if (trim($allowed_cidrs) === '')
            <div class="alert alert-warning" style="margin-bottom:14px;">
                Aucune IP configurée : le contrôle réseau n’est <strong>pas actif</strong> (seul le nom Wi‑Fi est affiché).
            </div>
        @endif

        <div class="form-group" style="margin-bottom:14px;">
            <label class="label" for="wifi_name">Nom du Wi‑Fi (affiché aux employés)</label>
            <input id="wifi_name" class="input" type="text" wire:model="wifi_name" placeholder="Ex. Bproo-Bureau">
        </div>

        <div class="form-group" style="margin-bottom:8px;">
            <label class="label" for="allowed_cidrs">IP / CIDR autorisés (obligatoire pour bloquer hors réseau)</label>
            <textarea id="allowed_cidrs" class="input" rows="4" wire:model="allowed_cidrs" placeholder="192.168.1.0/24&#10;41.x.x.x"></textarea>
            <div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="useMyIp">Ajouter mon IP actuelle</button>
                <span style="font-size:0.8rem; color:#64748b;">IP détectée : <code>{{ $detectedIp ?: '—' }}</code></span>
            </div>
            <p style="font-size:0.8rem; color:#94a3b8; margin-top:8px;">
                Exemple local : <code>127.0.0.1</code> (tests) ou <code>192.168.0.0/16</code>. En boutique, utilisez l’IP publique fixe ou la plage LAN.
            </p>
        </div>
    </section>

    <section class="card" style="padding:20px; margin-bottom:16px; max-width:720px;">
        <div class="card-title" style="margin-bottom:8px;">Pointage public (sans connexion web)</div>
        <p style="color:#64748b; font-size:0.9rem; margin-bottom:16px; line-height:1.45;">
            Lien ouvert : l’employé saisit son <strong>numéro de téléphone</strong> et son
            <strong>code de pointage personnel</strong> (créé avec le compte utilisateur, modifiable dans Mon compte).
        </p>

        <div class="form-group" style="margin-bottom:14px;">
            <label class="label" style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" wire:model="kiosk_enabled">
                Activer le pointage public
            </label>
        </div>

        @if ($kioskUrl)
            <div style="padding:12px; background:#f8fafc; border-radius:10px; font-size:0.9rem;">
                <div style="font-weight:600; margin-bottom:4px;">Lien à partager</div>
                <a href="{{ $kioskUrl }}" target="_blank" rel="noopener">{{ $kioskUrl }}</a>
            </div>
        @endif
    </section>

    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">Enregistrer</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </button>
        @if ($tenantCode)
            <a class="btn btn-secondary" href="{{ route('tenant.attendance.index', ['tenant' => $tenantCode]) }}">Retour présence</a>
        @endif
    </div>
</div>
