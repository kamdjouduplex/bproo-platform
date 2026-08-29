@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <form wire:submit.prevent="save" class="card" style="padding:16px;">
        <div class="form-grid">
            <div class="form-group" style="grid-column:1/-1;">
                <label class="field-label">Libellé *</label>
                <input class="input" wire:model="label" placeholder="Loyer bureau">
                @error('label') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-group">
                <label class="field-label">Catégorie</label>
                <select class="input" wire:model="category">
                    @foreach ($categories as $code => $name)
                        <option value="{{ $code }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="field-label">Montant *</label>
                <input class="input" type="number" step="0.01" min="0.01" wire:model="amount">
                @error('amount') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-group">
                <label class="field-label">Date d'échéance *</label>
                <input class="input" type="date" wire:model="due_date">
            </div>
            <div class="form-group">
                <label class="field-label">Fréquence</label>
                <select class="input" wire:model="frequency">
                    <option value="once">Unique</option>
                    <option value="weekly">Hebdomadaire</option>
                    <option value="monthly">Mensuelle</option>
                    <option value="yearly">Annuelle</option>
                </select>
            </div>
            <div class="form-group">
                <label class="field-label">Compte / catégorie comptable</label>
                <input class="input" wire:model="account_code" placeholder="6132">
            </div>
            <div class="form-group">
                <label class="field-label">Priorité</label>
                <select class="input" wire:model="priority">
                    <option value="low">Basse</option>
                    <option value="normal">Normale</option>
                    <option value="high">Haute</option>
                </select>
            </div>
            <div class="form-group">
                <label class="field-label">Fournisseur</label>
                <select class="input" wire:model="provider_id">
                    <option value="">—</option>
                    @foreach ($providers as $provider)
                        <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="field-label">Bénéficiaire</label>
                <input class="input" wire:model="beneficiary" placeholder="Si pas de fournisseur">
            </div>
            <div class="form-group">
                <label class="field-label">Alerte (jours avant)</label>
                <input class="input" type="number" min="1" wire:model="alert_days" placeholder="Défaut paramétré">
            </div>
            <div class="form-group" style="grid-column:1/-1;">
                <label class="field-label">Commentaire</label>
                <textarea class="input" rows="3" wire:model="comment"></textarea>
            </div>
        </div>
        <div class="page-actions" style="margin-top:16px;">
            <a class="btn btn-secondary" href="{{ route('tenant.treasury.index', ['tenant' => $tenantCode]) }}">Retour</a>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            @if ($canDelete)
                <button type="button" class="btn btn-secondary" wire:click="cancelCommitment" wire:confirm="Annuler cet engagement ?">Annuler l'engagement</button>
            @endif
        </div>
    </form>
</div>
