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
            <table style="min-width: 720px;">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Prescrit</th>
                        <th>Délivré</th>
                        <th>Reste</th>
                        <th>Posologie / instructions</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $index => $line)
                        @php
                            $prescribed = (float) ($line['quantity'] ?? 0);
                            $dispensed = (float) ($line['quantity_dispensed'] ?? 0);
                            $remaining = max(0, $prescribed - $dispensed);
                        @endphp
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
                            <td style="white-space: nowrap; font-size: 13px; color: {{ $dispensed > 0 ? '#166534' : '#64748b' }};">
                                {{ fmt_num($dispensed) }}
                            </td>
                            <td style="white-space: nowrap; font-size: 13px; font-weight: 600; color: {{ $remaining > 0.0001 ? '#b45309' : '#166534' }};">
                                {{ fmt_num($remaining) }}
                            </td>
                            <td>
                                <input class="input input-sm" wire:model="lines.{{ $index }}.instructions" placeholder="1 cp x 2/j">
                            </td>
                            <td>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="removeLine({{ $index }})" @disabled($dispensed > 0.0001 && count($lines) <= 1)>Suppr.</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="field-hint" style="margin-top: 8px;">Les quantités déjà délivrées sont conservées à l’enregistrement (délivrance partielle).</p>
        <button type="button" class="btn btn-secondary mt-2" wire:click="addLine">Ajouter une ligne</button>

        <div class="page-actions" style="margin-top: 24px; display: flex; flex-wrap: wrap; gap: 8px;">
            <a class="btn btn-secondary" href="{{ route('tenant.prescriptions.index', ['tenant' => $tenantCode]) }}">Retour</a>
            @if($prescriptionId)
                <a class="btn btn-secondary" href="{{ route('tenant.prescriptions.print', ['prescription' => $prescriptionId, 'tenant' => $tenantCode]) }}" target="_blank">Imprimer</a>
                @php
                    $hasRemaining = collect($lines)->contains(function ($line) {
                        return max(0, (float) ($line['quantity'] ?? 0) - (float) ($line['quantity_dispensed'] ?? 0)) > 0.0001;
                    });
                @endphp
                @if($hasRemaining && in_array($status, ['active', 'draft'], true))
                    <button type="button"
                            class="btn btn-secondary"
                            wire:click="closeRemaining"
                            wire:confirm="Clôturer le reste ? Le patient ne pourra plus retirer la quantité restante. Les quantités déjà délivrées restent en historique.">
                        Clôturer le reste
                    </button>
                @endif
            @endif
            <button class="btn btn-primary" wire:click="save">Enregistrer</button>
        </div>
    </section>
</div>
