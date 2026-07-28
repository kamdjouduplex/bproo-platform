<div class="page-body">
    @include('pressing::livewire.settings.partials.nav')

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif

    <section class="card" style="padding:16px;max-width:560px;">
        <h2 style="margin-top:0;">Délais de traitement</h2>
        <p style="color:#64748b;font-size:14px;">
            Ce délai est appliqué par défaut à la création d’une commande (date prévue de sortie).
        </p>
        <div class="form-grid" style="margin-top:12px;">
            <div class="field">
                <label class="field-label">Délai par défaut (heures)</label>
                <input class="input" type="number" min="0" max="720" wire:model="default_delay_hours">
                @error('default_delay_hours')<div style="color:#dc2626;">{{ $message }}</div>@enderror
            </div>
        </div>
        <div style="margin-top:16px;">
            <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
        </div>
    </section>
</div>
