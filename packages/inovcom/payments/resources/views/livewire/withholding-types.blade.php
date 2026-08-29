@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div class="page-actions" style="margin-bottom:16px;">
        <a class="btn btn-secondary" href="{{ route('tenant.invoice_payments.index', ['tenant' => $tenantCode]) }}">← Paiements factures</a>
    </div>

    <p style="margin-bottom:16px;color:#6b7280;font-size:13px;">
        Ces types sont proposés au moment de l’encaissement. Ils ne créent pas un second type de facture :
        la retenue reste une opération de règlement, identifiable et traçable.
    </p>

    @if ($canManage)
        <form wire:submit.prevent="save" class="card" style="padding:16px;margin-bottom:16px;">
            <h3 style="margin:0 0 12px;">{{ $editingId ? 'Modifier le type' : 'Nouveau type de retenue' }}</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="field-label">Libellé *</label>
                    <input class="input" wire:model="name" placeholder="TVA retenue">
                    @error('name') <span class="text-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label class="field-label">Code</label>
                    <input class="input" wire:model="code" placeholder="tva_retenue">
                </div>
                <div class="form-group">
                    <label class="field-label">Taux par défaut (%)</label>
                    <input class="input" type="number" step="0.01" min="0" wire:model="default_rate">
                </div>
                <div class="form-group">
                    <label class="field-label">Compte comptable</label>
                    <input class="input" wire:model="default_account" placeholder="4456">
                </div>
            </div>
            <div class="form-group" style="margin-top:12px;">
                <label class="field-label">Description</label>
                <textarea class="input" rows="2" wire:model="description"></textarea>
            </div>
            <label style="display:flex;gap:8px;align-items:center;margin-top:10px;">
                <input type="checkbox" wire:model="is_active"> Actif
            </label>
            <div class="page-actions" style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">{{ $editingId ? 'Enregistrer' : 'Ajouter' }}</button>
                @if ($editingId)
                    <button type="button" class="btn btn-secondary" wire:click="startCreate">Annuler</button>
                @endif
            </div>
        </form>
    @endif

    <section class="card app-table-card">
        <div class="table-title" style="padding:12px 16px;"><strong>Types configurés</strong></div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Libellé</th>
                        <th>Code</th>
                        <th>Taux</th>
                        <th>Compte</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($types as $type)
                        <tr>
                            <td><strong>{{ $type->name }}</strong></td>
                            <td><code>{{ $type->code }}</code></td>
                            <td>{{ fmt_num((float) $type->default_rate, 2) }} %</td>
                            <td>{{ $type->default_account ?: '—' }}</td>
                            <td>
                                <span class="badge {{ $type->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $type->is_active ? 'Actif' : 'Inactif' }}
                                </span>
                            </td>
                            <td>
                                @if ($canManage)
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="edit({{ $type->id }})">Modifier</button>
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="toggleActive({{ $type->id }})">
                                        {{ $type->is_active ? 'Désactiver' : 'Activer' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
