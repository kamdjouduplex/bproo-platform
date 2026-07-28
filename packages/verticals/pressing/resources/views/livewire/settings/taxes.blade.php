<div class="page-body">
    @include('pressing::livewire.settings.partials.nav')

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif

    <section class="card" style="padding:16px;max-width:560px;">
        <h2 style="margin-top:0;">Taxes</h2>
        <p style="color:#64748b;font-size:14px;">
            Activez la TVA pour la calculer automatiquement à la réception.
        </p>
        <div class="form-grid" style="margin-top:12px;">
            <div class="field">
                <label class="field-label"><input type="checkbox" wire:model="tax_enabled"> TVA activée</label>
            </div>
            <div class="field">
                <label class="field-label">Taux de TVA (%)</label>
                <input class="input" type="number" step="0.01" min="0" max="100" wire:model="tax_rate">
                @error('tax_rate')<div style="color:#dc2626;">{{ $message }}</div>@enderror
            </div>
        </div>
        <div style="margin-top:16px;">
            <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
        </div>
    </section>
</div>
