@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div class="page-actions" style="margin-bottom:16px;">
        <a class="btn btn-secondary" href="{{ route('tenant.treasury.index', ['tenant' => $tenantCode]) }}">← Échéancier</a>
    </div>

    <form wire:submit.prevent="save" class="card" style="padding:16px;max-width:640px;">
        <p style="margin:0 0 16px;color:#6b7280;font-size:13px;">
            🟢 plus de {{ $upcoming_days }} jours : planifié ·
            🟠 entre {{ $urgent_days }} et {{ $upcoming_days }} jours : à anticiper ·
            🔴 moins de {{ $urgent_days }} jours : urgent ·
            ⚠️ date dépassée : en retard.
            Une notification interne apparaît {{ $alert_days }} jours avant l’échéance.
        </p>
        <div class="form-group">
            <label class="field-label">Seuil urgent (jours)</label>
            <input class="input" type="number" min="1" wire:model="urgent_days" {{ $canManage ? '' : 'disabled' }}>
        </div>
        <div class="form-group">
            <label class="field-label">Seuil à anticiper (jours)</label>
            <input class="input" type="number" min="1" wire:model="upcoming_days" {{ $canManage ? '' : 'disabled' }}>
        </div>
        <div class="form-group">
            <label class="field-label">Délai d'alerte (jours)</label>
            <input class="input" type="number" min="1" wire:model="alert_days" {{ $canManage ? '' : 'disabled' }}>
        </div>
        @if ($canManage)
            <div class="page-actions" style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        @endif
    </form>
</div>
