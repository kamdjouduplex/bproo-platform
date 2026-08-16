<div class="page-body">
    @include('school::livewire.partials.crud-styles')
    @include('school::livewire.school.grading._nav')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Règles de calcul</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouvelle règle</button>
            </div>
        </div>
        <div class="sch-filters">
            <select class="input" wire:model.live="filterYearId" style="max-width:220px;">
                <option value="">Toutes années</option>
                @foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach
            </select>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Année</th><th>Classe</th><th>Système</th><th>Seuil</th><th>Promotion</th><th>Classement</th><th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                        <tr>
                            <td>{{ $rule->academicYear?->name }}</td>
                            <td>{{ $rule->schoolClass?->name ?? 'Toutes' }}</td>
                            <td>{{ $rule->gradingSystem?->name ?? '—' }}</td>
                            <td>{{ $rule->pass_mark }}</td>
                            <td>{{ $rule->promotion_average }}</td>
                            <td>{{ $rule->ranking_method === 'simple_average' ? 'Simple' : 'Pondéré' }}</td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.grading.rules.show', ['tenant'=>$tenantCode,'id'=>$rule->id]) }}">Voir</a>
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.grading.rules.manage', ['tenant'=>$tenantCode,'id'=>$rule->id]) }}">Gérer</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Aucune règle. Le calcul utilisera des défauts (moyenne pondérée, seuil = moitié de la base).</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin:12px 16px 16px;">{{ $rules->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide">
                <div class="sch-modal__head"><h3 class="sch-modal__title">Nouvelle règle</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
                <div class="sch-modal__body"><div class="form-grid">
                    <div><label class="label">Année</label><select class="input" wire:model="academicYearId"><option value="">—</option>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach</select>@error('academicYearId') <span class="text-error">{{ $message }}</span> @enderror</div>
                    <div><label class="label">Classe (vide = toutes)</label><select class="input" wire:model="classId"><option value="">Toutes</option>@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div><label class="label">Système / barème</label><select class="input" wire:model="gradingSystemId"><option value="">—</option>@foreach($systems as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                    <div><label class="label">Classement</label><select class="input" wire:model="rankingMethod"><option value="weighted_average">Moyenne pondérée</option><option value="simple_average">Moyenne simple</option></select></div>
                    <div><label class="label">Note de passage</label><input class="input" type="number" step="0.01" wire:model="passMark"></div>
                    <div><label class="label">Moyenne de promotion</label><input class="input" type="number" step="0.01" wire:model="promotionAverage"></div>
                    <div><label class="label">Max matières échouées</label><input class="input" type="number" wire:model="maxFailedSubjects" placeholder="Illimité"></div>
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="absentCountsAsZero"> Absence = 0</label></div>
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="requireValidatedMarks"> Uniquement notes validées</label></div>
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="isActive"> Active</label></div>
                </div></div>
                <div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
            </div>
        </div>
    @endif
</div>
