<div class="page-body">
    @include('pressing::livewire.settings.partials.nav')

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif

    <section class="card" style="padding:16px;">
        <div class="client-list-head">
            <h2 class="client-list-head__title">Modèles de messages</h2>
            <div class="client-list-head__actions">
                @if ($canManage)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="resetDefaults">Réinitialiser</button>
                @endif
            </div>
        </div>

        <p style="color:#64748b;font-size:13px;margin:8px 0 16px;">
            Variables :
            <code>@{{client}}</code>,
            <code>@{{number}}</code>,
            <code>@{{amount}}</code>,
            <code>@{{balance}}</code>
        </p>

        <div class="form-grid">
            <div class="field" style="grid-column:1/-1;">
                <label class="field-label">Commande créée</label>
                <textarea class="input" rows="3" wire:model="order_created"></textarea>
                @error('order_created')<div style="color:#dc2626;">{{ $message }}</div>@enderror
            </div>
            <div class="field" style="grid-column:1/-1;">
                <label class="field-label">Commande prête</label>
                <textarea class="input" rows="3" wire:model="order_ready"></textarea>
            </div>
            <div class="field" style="grid-column:1/-1;">
                <label class="field-label">Commande livrée</label>
                <textarea class="input" rows="3" wire:model="order_delivered"></textarea>
            </div>
            <div class="field" style="grid-column:1/-1;">
                <label class="field-label">Paiement reçu</label>
                <textarea class="input" rows="3" wire:model="payment_received"></textarea>
            </div>
            <div class="field" style="grid-column:1/-1;">
                <label class="field-label">Rappel solde</label>
                <textarea class="input" rows="3" wire:model="payment_reminder"></textarea>
            </div>
            <div class="field" style="grid-column:1/-1;">
                <label class="field-label">Commande en retard</label>
                <textarea class="input" rows="3" wire:model="order_overdue"></textarea>
            </div>
        </div>

        @if ($canManage)
            <div style="margin-top:16px;">
                <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
            </div>
        @endif
    </section>
</div>
