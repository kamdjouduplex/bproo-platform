@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    <section class="card" style="margin-bottom: 16px;">
        <h2 class="card-title" style="margin-bottom: 8px;">Importer des {{ $catalogNoun['plural'] }}</h2>
        <p style="margin: 0 0 14px; color: #64748b; font-size: 14px; line-height: 1.5; max-width: 46rem;">
            Préparez le catalogue client (Word / Excel) dans le modèle ci-dessous, puis chargez le fichier.
            Colonnes minimales : <strong>name</strong> (PRODUITS). Recommandé : <strong>quantity</strong>, <strong>cost</strong> (P.U), <strong>price</strong> (P.V.U), <strong>expiry_date</strong> (DATE DE P).
        </p>

        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom: 18px;">
            <button type="button" class="btn btn-secondary" wire:click="downloadTemplate">
                Télécharger le modèle Excel
            </button>
            <a class="btn btn-secondary" href="{{ route('tenant.items.index', ['tenant' => $tenantCode]) }}">Retour catalogue</a>
        </div>

        <div style="padding: 14px; border: 1px dashed #cbd5e1; border-radius: 10px; background: #f8fafc;">
            <label class="label">Fichier à importer (.xlsx, .xls ou .csv)</label>
            <input type="file" class="input" wire:model="importFile" accept=".xlsx,.xls,.csv,.txt">
            @error('importFile') <span class="text-error">{{ $message }}</span> @enderror
            <div wire:loading wire:target="importFile" style="margin-top:8px; font-size:13px; color:#64748b;">Envoi du fichier…</div>
            <div style="margin-top: 12px; display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" class="btn btn-primary" wire:click="analyze" wire:loading.attr="disabled">
                    Analyser le fichier
                </button>
                @if ($showPreview)
                    <button type="button" class="btn btn-secondary" wire:click="resetImportState">Recommencer</button>
                @endif
            </div>
        </div>
    </section>

    <section class="card" style="margin-bottom: 16px;">
        <h3 class="card-title" style="font-size:15px;">Colonnes du modèle</h3>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Colonne</th>
                        <th>Rôle</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>name</code></td><td>PRODUITS — obligatoire</td></tr>
                    <tr><td><code>sku</code></td><td>Référence (auto si vide)</td></tr>
                    <tr><td><code>quantity</code></td><td>Qté / stock initial</td></tr>
                    <tr><td><code>cost</code></td><td>P.U — prix d’achat</td></tr>
                    <tr><td><code>price</code></td><td>P.V.U — prix de vente</td></tr>
                    <tr><td><code>expiry_date</code></td><td>DATE DE P — péremption (AAAA-MM-JJ)</td></tr>
                    <tr><td><code>batch_number</code></td><td>N° de lot (optionnel)</td></tr>
                    <tr><td><code>unit</code></td><td>Unité (Boîte, Flacon…)</td></tr>
                    <tr><td><code>barcode</code></td><td>Code-barres (optionnel)</td></tr>
                </tbody>
            </table>
        </div>
        <p style="margin: 10px 0 0; font-size: 12px; color: #64748b;">
            Les colonnes P.T / P.V.T du tableau Word ne sont pas requises (qty × prix). Les en-têtes FR du client (PRODUITS, Qté, P.U, P.V.U, DATE DE P) sont aussi reconnus.
        </p>
    </section>

    @if ($showPreview)
        <section class="card app-table-card">
            <div class="table-toolbar" style="flex-wrap:wrap; gap:10px;">
                <div class="table-title">Aperçu</div>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <span class="badge badge-success">{{ $okCount }} OK</span>
                    <span class="badge badge-danger">{{ $errorCount }} erreur(s)</span>
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        wire:click="commitImport"
                        wire:confirm="Importer {{ $okCount }} ligne(s) valide(s) dans le catalogue ?"
                        @disabled($okCount === 0)
                    >
                        Importer les lignes OK
                    </button>
                </div>
            </div>

            @if ($parseErrors)
                <div style="margin-bottom:12px; padding:10px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; color:#991b1b; font-size:13px;">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach (array_slice($parseErrors, 0, 15) as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                        @if (count($parseErrors) > 15)
                            <li>… et {{ count($parseErrors) - 15 }} autre(s)</li>
                        @endif
                    </ul>
                </div>
            @endif

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Ligne</th>
                            <th>Statut</th>
                            <th>Produit</th>
                            <th>Qté</th>
                            <th>P.U</th>
                            <th>P.V.U</th>
                            <th>Péremption</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($previewRows as $row)
                            <tr>
                                <td>{{ $row['excel_row'] ?? '—' }}</td>
                                <td>
                                    @if (($row['status'] ?? '') === 'ok')
                                        <span class="badge badge-success">OK</span>
                                    @elseif (($row['status'] ?? '') === 'skip')
                                        <span class="badge badge-secondary">Ignoré</span>
                                    @else
                                        <span class="badge badge-danger">Erreur</span>
                                    @endif
                                </td>
                                <td>{{ $row['name'] ?? '—' }}</td>
                                <td>{{ isset($row['quantity']) ? fmt_num($row['quantity']) : '—' }}</td>
                                <td>{{ isset($row['cost']) ? fmt_money($row['cost']) : '—' }}</td>
                                <td>{{ isset($row['price']) ? fmt_money($row['price']) : '—' }}</td>
                                <td>{{ $row['expiry_date'] ?? '—' }}</td>
                                <td style="font-size:12px; color:#64748b;">{{ implode(' · ', $row['messages'] ?? []) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
