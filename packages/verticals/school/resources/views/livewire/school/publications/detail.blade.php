<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')
    @include('school::livewire.school.publications._nav')

    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div>
                <h2 class="sch-detail-toolbar__title">{{ $publication->title }}</h2>
                <p class="sch-detail-toolbar__hint">
                    {{ $statusLabels[$publication->status] ?? $publication->status }}
                    @if($publication->approved_at)
                        · Approuvé par {{ $publication->approved_by_name }} ({{ $publication->approved_at->format('d/m/Y H:i') }})
                    @endif
                </p>
            </div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.publications.index', ['tenant'=>$tenantCode]) }}">Retour</a>
                @if($isManage)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.publications.show', ['tenant'=>$tenantCode,'id'=>$publication->id]) }}">Voir</a>
                    @if($publication->isEditable())
                        <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                    @endif
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.publications.manage', ['tenant'=>$tenantCode,'id'=>$publication->id]) }}">Gérer</a>
                @endif
            </div>
        </div>
        <div class="sch-info-grid">
            <div class="sch-info-item"><span class="sch-info-item__label">Année</span><div class="sch-info-item__value">{{ $publication->academicYear?->name }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Classe</span><div class="sch-info-item__value">{{ $publication->schoolClass?->name ?? 'Toutes' }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Règle</span><div class="sch-info-item__value">{{ $publication->rule?->name ?? '—' }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Statut</span><div class="sch-info-item__value">{{ $statusLabels[$publication->status] ?? $publication->status }}</div></div>
            <div class="sch-info-item sch-info-item--wide"><span class="sch-info-item__label">Notes</span><div class="sch-info-item__value">{{ $publication->notes ?? '—' }}</div></div>
        </div>

        @if($isManage && $publication->status !== 'published')
            <div class="sch-actions-panel">
                <div class="sch-actions-panel__label">Workflow</div>
                <button class="btn btn-secondary btn-sm" wire:click="syncRoster">Évaluer les élèves (règles)</button>
                @if($publication->isEditable() || $publication->status === 'draft')
                    <button class="btn btn-secondary btn-sm" wire:click="submitForApproval">Soumettre au directeur</button>
                @endif
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; width:100%; margin-top:8px;">
                    <input class="input" style="max-width:220px;" placeholder="Nom du directeur" wire:model="approverName">
                    <button class="btn btn-secondary btn-sm" wire:click="approve">Approuver</button>
                    <button class="btn btn-secondary btn-sm" wire:click="reject" wire:confirm="Rejeter cette publication ?">Rejeter</button>
                    <button class="btn btn-primary btn-sm" wire:click="publish" wire:confirm="Publier les élèves éligibles ?">Publier</button>
                </div>
                @error('approverName') <span class="text-error">{{ $message }}</span> @enderror
            </div>
        @endif
    </section>

    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div>
                <h3 class="sch-detail-toolbar__title" style="font-size:1rem;">Élèves</h3>
                <p class="sch-detail-toolbar__hint">Éligibilité selon frais / notes validées / etc.</p>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Élève</th><th>Moyenne</th><th>Mention</th><th>Éligible</th><th>Publié</th><th>Blocages</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($publication->lines as $line)
                        <tr>
                            <td><strong>{{ $line->student?->student_code }}</strong> — {{ $line->student?->full_name }}</td>
                            <td>{{ $line->average !== null ? number_format($line->average, 2, ',', ' ') : '—' }}</td>
                            <td>{{ $line->grade_label ?? '—' }}</td>
                            <td>@if($line->eligible)<span class="badge badge-success">Oui</span>@else<span class="badge badge-secondary">Non</span>@endif</td>
                            <td>{{ $line->is_published ? 'Oui' : 'Non' }}</td>
                            <td style="max-width:280px;font-size:12px;color:#64748b;">{{ $line->blocked_reasons ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucun élève. Cliquez « Évaluer les élèves ».</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide">
                <div class="sch-modal__head"><h3 class="sch-modal__title">Modifier la publication</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
                <div class="sch-modal__body"><div class="form-grid">
                    <div class="form-span-2"><label class="label">Titre</label><input class="input" wire:model="title"></div>
                    <div><label class="label">Année</label><select class="input" wire:model="academicYearId">@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach</select></div>
                    <div><label class="label">Classe</label><select class="input" wire:model="classId"><option value="">Toutes</option>@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div class="form-span-2"><label class="label">Règle</label><select class="input" wire:model="publicationRuleId"><option value="">—</option>@foreach($rules as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></div>
                    <div class="form-span-2"><label class="label">Notes</label><textarea class="input" rows="2" wire:model="notes"></textarea></div>
                </div></div>
                <div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
            </div>
        </div>
    @endif
</div>
