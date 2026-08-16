<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')
    @include('school::livewire.school.grading._nav')

    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div>
                <h2 class="sch-detail-toolbar__title">Règle de calcul</h2>
                <p class="sch-detail-toolbar__hint">{{ $rule->academicYear?->name }} · {{ $rule->schoolClass?->name ?? 'Toutes classes' }}</p>
            </div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.grading.rules.index', ['tenant'=>$tenantCode]) }}">Retour</a>
                @if($isManage)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.grading.rules.show', ['tenant'=>$tenantCode,'id'=>$rule->id]) }}">Voir</a>
                    <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.grading.rules.manage', ['tenant'=>$tenantCode,'id'=>$rule->id]) }}">Gérer</a>
                @endif
            </div>
        </div>
        <div class="sch-info-grid">
            <div class="sch-info-item"><span class="sch-info-item__label">Système</span><div class="sch-info-item__value">{{ $rule->gradingSystem?->name ?? '—' }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Note de passage</span><div class="sch-info-item__value">{{ $rule->pass_mark }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Promotion</span><div class="sch-info-item__value">{{ $rule->promotion_average }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Max échecs</span><div class="sch-info-item__value">{{ $rule->max_failed_subjects ?? 'Illimité' }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Classement</span><div class="sch-info-item__value">{{ $rule->ranking_method === 'simple_average' ? 'Moyenne simple' : 'Moyenne pondérée' }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Absence = 0</span><div class="sch-info-item__value">{{ $rule->absent_counts_as_zero ? 'Oui' : 'Non' }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Notes validées only</span><div class="sch-info-item__value">{{ $rule->require_validated_marks ? 'Oui' : 'Non' }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Active</span><div class="sch-info-item__value">{{ $rule->is_active ? 'Oui' : 'Non' }}</div></div>
        </div>
        @if($isManage)
            <div class="sch-actions-panel">
                <div class="sch-actions-panel__label">Actions</div>
                <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
            </div>
        @endif
    </section>

    @if($isManage)
        <section class="card app-table-card">
            <div class="sch-detail-toolbar">
                <div>
                    <h3 class="sch-detail-toolbar__title" style="font-size:1rem;">Aperçu du calcul</h3>
                    <p class="sch-detail-toolbar__hint">Teste le moteur configurable sur un élève (notes d’examens existantes).</p>
                </div>
            </div>
            <div style="padding:0 16px 16px;">
                <div class="form-grid" style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end;">
                    <div>@include('school::livewire.partials.searchable-student')</div>
                    <button type="button" class="btn btn-primary" wire:click="runPreview">Calculer</button>
                </div>
            </div>
            @if($preview)
                <div class="sch-info-grid">
                    <div class="sch-info-item"><span class="sch-info-item__label">Moyenne</span><div class="sch-info-item__value">{{ $preview['average'] !== null ? number_format($preview['average'], 2, ',', ' ').' / '.$preview['scale_base'] : '—' }}</div></div>
                    <div class="sch-info-item"><span class="sch-info-item__label">Mention</span><div class="sch-info-item__value">{{ $preview['grade_label'] ?? '—' }}</div></div>
                    <div class="sch-info-item"><span class="sch-info-item__label">Réussi</span><div class="sch-info-item__value">{{ $preview['passed'] ? 'Oui' : 'Non' }}</div></div>
                    <div class="sch-info-item"><span class="sch-info-item__label">Promu</span><div class="sch-info-item__value">{{ $preview['promoted'] ? 'Oui' : 'Non' }}</div></div>
                    <div class="sch-info-item"><span class="sch-info-item__label">Matières échouées</span><div class="sch-info-item__value">{{ $preview['failed_subjects'] }}</div></div>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Matière</th><th>Moyenne</th><th>Coeff</th><th>Pondéré</th><th>Passé</th></tr></thead>
                        <tbody>
                            @forelse($preview['subjects'] as $subj)
                                <tr>
                                    <td>{{ $subj['subject_name'] }}</td>
                                    <td>{{ $subj['average'] !== null ? number_format($subj['average'], 2, ',', ' ') : '—' }}</td>
                                    <td>{{ $subj['coefficient'] }}</td>
                                    <td>{{ $subj['weighted'] !== null ? number_format($subj['weighted'], 2, ',', ' ') : '—' }}</td>
                                    <td>{{ $subj['passed'] === null ? '—' : ($subj['passed'] ? 'Oui' : 'Non') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">Aucune note trouvée pour cet élève.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide">
                <div class="sch-modal__head"><h3 class="sch-modal__title">Modifier la règle</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
                <div class="sch-modal__body"><div class="form-grid">
                    <div><label class="label">Année</label><select class="input" wire:model="academicYearId">@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach</select></div>
                    <div><label class="label">Classe</label><select class="input" wire:model="classId"><option value="">Toutes</option>@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div><label class="label">Système</label><select class="input" wire:model="gradingSystemId"><option value="">—</option>@foreach($systems as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                    <div><label class="label">Classement</label><select class="input" wire:model="rankingMethod"><option value="weighted_average">Moyenne pondérée</option><option value="simple_average">Moyenne simple</option></select></div>
                    <div><label class="label">Note de passage</label><input class="input" type="number" step="0.01" wire:model="passMark"></div>
                    <div><label class="label">Moyenne promotion</label><input class="input" type="number" step="0.01" wire:model="promotionAverage"></div>
                    <div><label class="label">Max matières échouées</label><input class="input" type="number" wire:model="maxFailedSubjects"></div>
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="absentCountsAsZero"> Absence = 0</label></div>
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="requireValidatedMarks"> Notes validées uniquement</label></div>
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="isActive"> Active</label></div>
                </div></div>
                <div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
            </div>
        </div>
    @endif
</div>
