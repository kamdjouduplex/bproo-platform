@if ($client->notes)
    <div class="alert" style="margin-bottom:12px;"><strong>Note fiche :</strong> <span style="white-space:pre-wrap;">{{ $client->notes }}</span></div>
@endif

@if ($canUpdate)
        <div class="form-grid" style="margin-bottom:12px;">
            <div class="field">
                <label class="field-label">Type</label>
                <select class="input" wire:model="newNoteType">
                    @foreach ($noteTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="grid-column: span 2;">
                <label class="field-label">Note</label>
                <input class="input" wire:model="newNoteBody" placeholder="Ajouter une note horodatée…">
                @error('newNoteBody') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field" style="display:flex; align-items:flex-end;">
                <button type="button" class="btn btn-primary" wire:click="addNote">Ajouter</button>
            </div>
        </div>
        @endif

        @if ($notes->count() > 0)
            <div style="display:flex; flex-direction:column; gap:8px;">
                @foreach ($notes as $note)
                    <div style="border-left:3px solid #2563eb; padding:8px 12px; background:#f8fafc; border-radius:4px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span class="badge badge-info">{{ $note->typeLabel() }}</span>
                            <span style="font-size:12px; color:#6b7280;">{{ optional($note->created_at)->format('d/m/Y H:i') }}</span>
                        </div>
                        <div style="margin-top:4px; white-space:pre-wrap;">{{ $note->body }}</div>
                        @if ($canUpdate)
                            <button type="button" class="btn btn-danger btn-sm" style="margin-top:6px;" wire:click="deleteNote({{ $note->id }})" wire:confirm="Supprimer cette note ?">Suppr.</button>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="alert" style="margin:0;">Aucune note pour le moment.</div>
        @endif
