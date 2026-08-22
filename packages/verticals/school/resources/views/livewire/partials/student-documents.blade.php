{{-- Shared student document dossier. Pass $listOnly = true to hide chips, upload and delete. --}}
@php
    $canManageDocuments = $canManageDocuments ?? false;
    $documentChecklist = $documentChecklist ?? [];
    $studentDocuments = $studentDocuments ?? collect();
    $documentTypes = $documentTypes ?? [];
    $tenantCode = $tenantCode ?? null;
    $listOnly = $listOnly ?? false;
@endphp

@unless($listOnly)
    <div class="sch-doc-checklist" style="display:flex; flex-wrap:wrap; gap:6px; padding:0 16px 12px;">
        @foreach($documentChecklist as $item)
            @php
                $chipBg = $item['has'] ? '#ecfdf5' : ($item['required'] ? '#fef2f2' : '#f8fafc');
                $chipBd = $item['has'] ? '#a7f3d0' : ($item['required'] ? '#fecaca' : '#e2e8f0');
                $chipFg = $item['has'] ? '#047857' : ($item['required'] ? '#b91c1c' : '#64748b');
            @endphp
            <button
                type="button"
                @if($canManageDocuments) wire:click="pickDocumentType('{{ $item['value'] }}')" @endif
                style="border:1px solid {{ $chipBd }}; background:{{ $chipBg }}; color:{{ $chipFg }}; border-radius:999px; padding:4px 10px; font-size:12px; cursor:{{ $canManageDocuments ? 'pointer' : 'default' }};"
            >
                {{ $item['has'] ? '✓' : ($item['required'] ? '!' : '○') }}
                {{ $item['label'] }}
                @if($item['required'] && ! $item['has'])
                    <span style="font-weight:700;">obligatoire</span>
                @endif
            </button>
        @endforeach
    </div>

    @if($canManageDocuments)
        <div style="padding:0 16px 16px;">
            <div class="form-grid" style="border:1px dashed #cbd5e1; border-radius:10px; padding:12px; background:#f8fafc;">
                <div>
                    <label class="label">Type de pièce</label>
                    <select class="input" wire:model="documentType">
                        @foreach($documentTypes as $type)
                            <option value="{{ $type['value'] }}">{{ $type['label'] }}{{ $type['required'] ? ' *' : '' }}</option>
                        @endforeach
                    </select>
                    @error('documentType') <span class="text-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Fichier (PDF / image, 8 Mo max)</label>
                    <input class="input" type="file" wire:model="documentFile" accept=".pdf,.jpg,.jpeg,.png,.webp" wire:key="doc-file-{{ $documentUploadKey }}">
                    <div wire:loading wire:target="documentFile" style="font-size:12px; color:#0f766e;">Téléversement…</div>
                    @error('documentFile') <span class="text-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Titre (optionnel)</label>
                    <input class="input" type="text" wire:model="documentTitle" placeholder="Ex. Acte n° 123">
                </div>
                <div>
                    <label class="label">Date du document</label>
                    <input class="input" type="date" wire:model="documentIssuedOn">
                </div>
                <div class="form-span-2">
                    <label class="label">Note</label>
                    <input class="input" type="text" wire:model="documentNotes" placeholder="Remarque interne…">
                </div>
                <div class="form-span-2">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="saveStudentDocument" wire:loading.attr="disabled">
                        Ajouter au dossier
                    </button>
                </div>
            </div>
        </div>
    @endif
@endunless

<div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>Pièce</th>
                <th>Fichier</th>
                <th>Date</th>
                <th>Ajouté le</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($studentDocuments as $doc)
                @php $url = $doc->viewUrl($tenantCode); @endphp
                <tr>
                    <td>
                        <strong>{{ $doc->typeLabel() }}</strong>
                        @if($doc->title)
                            <div style="font-size:12px; color:#64748b;">{{ $doc->title }}</div>
                        @endif
                        @if(! $listOnly && $doc->notes)
                            <div style="font-size:12px; color:#94a3b8;">{{ $doc->notes }}</div>
                        @endif
                    </td>
                    <td>
                        @if($url)
                            <a href="{{ $url }}" target="_blank" rel="noopener">{{ $doc->displayTitle() }}</a>
                            <div style="font-size:12px; color:#94a3b8;">{{ $doc->sizeLabel() }}</div>
                        @else
                            {{ $doc->displayTitle() }}
                        @endif
                    </td>
                    <td>{{ $doc->issued_on?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $doc->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="sch-row-actions">
                        @if($url)
                            <a class="btn btn-secondary btn-sm" href="{{ $url }}" target="_blank" rel="noopener">Ouvrir</a>
                        @endif
                        @if(! $listOnly && $canManageDocuments)
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="deleteStudentDocument({{ $doc->id }})" wire:confirm="Retirer cette pièce du dossier ?">Retirer</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        Aucune pièce n’a encore été déposée pour cet élève.
                        @if($listOnly && ($hasStudentShow ?? false) && isset($studentRow))
                            <a href="{{ route('tenant.school.students.show', ['tenant' => $tenantCode, 'id' => $studentRow->id, 'tab' => 'pieces']) }}">Ajouter depuis la fiche</a>
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
