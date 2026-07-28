@if ($canUpdate)
        <div class="form-grid" style="margin-bottom:12px;">
            <div class="field">
                <label class="field-label">Type</label>
                <select class="input" wire:model="newDocType">
                    @foreach ($documentTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field-label">Libellé</label>
                <input class="input" wire:model="newDocLabel" placeholder="Ex: Contrat 2026">
            </div>
            <div class="field">
                <label class="field-label">Fichier (PDF/Image/Office, max 10 Mo)</label>
                <input class="input" type="file" wire:model="newDocument">
                @error('newDocument') <span class="field-error">{{ $message }}</span> @enderror
                <div wire:loading wire:target="newDocument" style="font-size:12px; color:#6b7280;">Chargement…</div>
            </div>
            <div class="field" style="display:flex; align-items:flex-end;">
                <button type="button" class="btn btn-primary" wire:click="uploadDocument">Ajouter le document</button>
            </div>
        </div>
        @endif

        @if ($documents->count() > 0)
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Type</th><th>Libellé</th><th>Taille</th><th>Ajouté le</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($documents as $document)
                            <tr>
                                <td><span class="badge badge-info">{{ $document->typeLabel() }}</span></td>
                                <td>{{ $document->label }}</td>
                                <td>{{ $document->humanSize() }}</td>
                                <td>{{ optional($document->created_at)->format('d/m/Y H:i') }}</td>
                                <td style="display:flex; gap:6px;">
                                    <a class="btn btn-secondary btn-sm" target="_blank" href="{{ url('/storage/' . ltrim(str_replace('\\','/',$document->path),'/')) }}">Ouvrir</a>
                                    @if ($canUpdate)
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="deleteDocument({{ $document->id }})" wire:confirm="Supprimer ce document ?">Suppr.</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="alert" style="margin:0;">Aucun document.</div>
        @endif
