@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    <section class="card">
        <h2 class="card-title">{{ $prescriptionId ? 'Modifier l\'ordonnance' : 'Nouvelle ordonnance' }}</h2>
        <div class="form-grid">
            <div class="field">
                <label class="field-label">N° ordonnance</label>
                <input class="input" wire:model="number" placeholder="RX-...">
            </div>
            <div class="field">
                <label class="field-label">Client (patient)</label>
                <select class="input" wire:model="client_id">
                    <option value="">— Choisir —</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} @if($c->phone) ({{ $c->phone }}) @endif</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field-label">Prescripteur</label>
                <input class="input" wire:model="prescriber_name" placeholder="Dr. ...">
            </div>
            <div class="field">
                <label class="field-label">Contact prescripteur</label>
                <input class="input" wire:model="prescriber_contact" placeholder="Tél / cabinet">
            </div>
            <div class="field">
                <label class="field-label">Valide du</label>
                <input class="input" type="date" wire:model="valid_from">
            </div>
            <div class="field">
                <label class="field-label">Valide jusqu'au</label>
                <input class="input" type="date" wire:model="valid_until">
            </div>
            @if($prescriptionId)
                <div class="field">
                    <label class="field-label">Statut</label>
                    <select class="input" wire:model="status">
                        <option value="draft">Brouillon</option>
                        <option value="active">Active</option>
                        <option value="dispensed">Dispensée</option>
                        <option value="expired">Expirée</option>
                        <option value="cancelled">Annulée</option>
                    </select>
                </div>
            @endif
            <div class="field" style="grid-column: 1 / -1;">
                <label class="field-label">Notes</label>
                <textarea class="input" wire:model="notes" rows="2"></textarea>
            </div>
        </div>

        <h3 style="margin: 24px 0 12px; font-size: 1rem;">Lignes (médicaments)</h3>
        <div style="overflow-x: auto;">
            <table style="min-width: 600px;">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Quantité</th>
                        <th>Posologie / instructions</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $index => $line)
                        <tr>
                            <td>
                                <select class="input input-sm" wire:model.live="lines.{{ $index }}.item_id" style="min-width: 200px;">
                                    <option value="">— Choisir —</option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}">{{ item_display($item->sku, $item->name) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input class="input input-sm" type="number" step="any" min="0.001" wire:model="lines.{{ $index }}.quantity" style="width: 80px;">
                            </td>
                            <td>
                                <input class="input input-sm" wire:model="lines.{{ $index }}.instructions" placeholder="1 cp x 2/j">
                            </td>
                            <td>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="removeLine({{ $index }})">Suppr.</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-secondary mt-2" wire:click="addLine">Ajouter une ligne</button>

        <div class="page-actions" style="margin-top: 24px;">
            <a class="btn btn-secondary" href="{{ route('tenant.prescriptions.index', ['tenant' => $tenantCode]) }}">Retour</a>
            <button class="btn btn-primary" wire:click="save">Enregistrer</button>
        </div>
    </section>
</div>
