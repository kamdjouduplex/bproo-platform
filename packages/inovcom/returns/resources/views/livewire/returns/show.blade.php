@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:16px;">
        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.returns.index', ['tenant' => $tenantCode]) }}">&larr; Retours</a>
        <span class="badge {{ $status?->badgeClass() }}" style="font-size:13px;">{{ $status?->label() }}</span>
    </div>

    {{-- Actions workflow --}}
    <section class="card" style="padding:16px; margin-bottom:16px;">
        <div class="table-title" style="margin-bottom:12px;">Workflow</div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            @if ($status === $S::Draft && $can['request'])
                <button class="btn btn-primary" wire:click="submit">Soumettre la demande</button>
            @endif

            @if (in_array($status, [$S::Requested, $S::PendingApproval], true))
                @if ($can['approve'])<button class="btn btn-primary" wire:click="approve" onclick="return confirm('Approuver ce retour ?')">Approuver</button>@endif
                @if ($can['reject'])
                    <div style="display:flex; gap:4px; align-items:center;">
                        <input class="input input-sm" type="text" wire:model="rejectReason" placeholder="Motif de refus" style="min-width:180px;">
                        <button class="btn btn-secondary" wire:click="reject">Refuser</button>
                    </div>
                @endif
            @endif

            @if ($status === $S::Approved && $can['receive'])
                <button class="btn btn-primary" wire:click="receive">Marquer comme reçu</button>
            @endif

            @if ($status === $S::Inspected && $can['creditNote'] && ! $return->creditNote)
                <button class="btn btn-primary" wire:click="generateCreditNote" onclick="return confirm('Générer l’avoir pour ce retour ?')">Générer l'avoir</button>
            @endif

            @if ($return->creditNote)
                <a class="btn btn-primary" href="{{ route('tenant.returns.credit_notes.show', [$return->creditNote->id, 'tenant' => $tenantCode]) }}">Voir l'avoir {{ $return->creditNote->credit_note_number }}</a>
            @endif

            @if ($can['cancel'] && ! $status?->isTerminal())
                <button class="btn btn-secondary" wire:click="cancel" onclick="return confirm('Annuler ce retour ?')" style="margin-left:auto;">Annuler le retour</button>
            @endif
        </div>
    </section>

    <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px; align-items:start;">
        <div>
            {{-- Infos générales --}}
            <section class="card" style="padding:16px; margin-bottom:16px;">
                <div class="table-title" style="margin-bottom:12px;">Informations générales</div>
                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:12px;">
                    <div><span style="color:#666;">N° retour</span><br><strong>{{ $return->return_number }}</strong></div>
                    <div><span style="color:#666;">Type</span><br>{{ $return->type?->label() }}</div>
                    <div><span style="color:#666;">Client</span><br>{{ $return->client?->name ?? '—' }}</div>
                    <div><span style="color:#666;">Date</span><br>{{ $return->return_date?->format('d/m/Y') }}</div>
                    <div><span style="color:#666;">Facture d'origine</span><br>
                        @if ($return->source_id && \Illuminate\Support\Facades\Route::has('tenant.invoicing.edit'))
                            <a href="{{ route('tenant.invoicing.edit', [$return->source_id, 'tenant' => $tenantCode]) }}">{{ $return->source_number }}</a>
                        @else {{ $return->source_number ?? '—' }} @endif
                    </div>
                    <div><span style="color:#666;">Motif</span><br>{{ $return->reason?->label ?? '—' }}</div>
                    <div><span style="color:#666;">Résolution</span><br>{{ $return->resolution_type?->label() ?? '—' }}</div>
                    <div><span style="color:#666;">Montant total</span><br><strong>{{ fmt_money($return->total_amount) }}</strong></div>
                </div>
                @if ($return->notes)<div style="margin-top:12px;"><span style="color:#666;">Notes</span><br>{{ $return->notes }}</div>@endif
            </section>

            {{-- Articles --}}
            <section class="card app-table-card" style="margin-bottom:16px;">
                <div class="table-toolbar"><div class="table-title">Articles retournés</div></div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Article</th><th>Qté</th><th>PU</th><th>Total</th><th>État</th><th>Réintégré</th></tr></thead>
                        <tbody>
                            @foreach ($return->items as $item)
                            <tr>
                                <td><x-item-label :reference="$item->item_sku" :name="$item->item_name" /></td>
                                <td>{{ fmt_num($item->quantity) }}</td>
                                <td>{{ fmt_money($item->unit_price) }}</td>
                                <td>{{ fmt_money($item->line_total) }}</td>
                                <td>@if($item->condition)<span class="badge {{ $item->condition->badgeClass() }}">{{ $item->condition->label() }}</span>@else — @endif</td>
                                <td>{{ $item->restocked_quantity > 0 ? fmt_num($item->restocked_quantity) : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Contrôle qualité --}}
            @if ($status === $S::Received && $can['inspect'])
            <section class="card" style="padding:16px; margin-bottom:16px;">
                <div class="table-title" style="margin-bottom:12px;">Contrôle produit (inspection)</div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Article</th><th>État</th><th>Réintégrer au stock ?</th></tr></thead>
                        <tbody>
                            @foreach ($return->items as $item)
                            <tr>
                                <td><x-item-label :reference="$item->item_sku" :name="$item->item_name" /> ({{ fmt_num($item->quantity) }})</td>
                                <td>
                                    <select class="input input-sm" wire:model="inspection.{{ $item->id }}.condition">
                                        @foreach ($conditions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                    </select>
                                </td>
                                <td><input type="checkbox" wire:model="inspection.{{ $item->id }}.restock"></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:12px;">
                    <button class="btn btn-primary" wire:click="runInspection" onclick="return confirm('Valider le contrôle ? Le stock vendable sera réintégré.')">Valider le contrôle & réintégrer le stock</button>
                </div>
            </section>
            @endif

            {{-- Commentaires --}}
            <section class="card" style="padding:16px;">
                <div class="table-title" style="margin-bottom:12px;">Commentaires</div>
                <div style="display:flex; gap:8px; margin-bottom:12px;">
                    <input class="input" type="text" wire:model="commentBody" placeholder="Ajouter un commentaire...">
                    <button class="btn btn-secondary" wire:click="addComment">Ajouter</button>
                </div>
                @forelse ($return->comments as $comment)
                    <div style="border-top:1px solid #eee; padding:8px 0;">
                        <div style="font-size:12px; color:#666;">{{ $comment->author?->name ?? 'Utilisateur' }} — {{ $comment->created_at?->format('d/m/Y H:i') }}</div>
                        <div>{{ $comment->body }}</div>
                    </div>
                @empty
                    <div style="color:#666;">Aucun commentaire.</div>
                @endforelse
            </section>
        </div>

        {{-- Historique --}}
        <div>
            <section class="card" style="padding:16px;">
                <div class="table-title" style="margin-bottom:12px;">Historique</div>
                @forelse ($return->statusHistory as $h)
                    <div style="border-left:2px solid #ddd; padding:4px 0 12px 12px; position:relative;">
                        <div style="font-weight:600;">{{ \InovCom\Returns\Enums\ReturnStatus::tryFrom($h->to_status)?->label() ?? $h->to_status }}</div>
                        <div style="font-size:12px; color:#666;">{{ $h->performer?->name ?? 'Système' }} — {{ $h->performed_at?->format('d/m/Y H:i') }}</div>
                        @if ($h->note)<div style="font-size:12px;">{{ $h->note }}</div>@endif
                    </div>
                @empty
                    <div style="color:#666;">Aucun historique.</div>
                @endforelse
            </section>
        </div>
    </div>
</div>
