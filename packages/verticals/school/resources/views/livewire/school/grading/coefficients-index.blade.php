<div class="page-body">
    @include('school::livewire.partials.crud-styles')
    @include('school::livewire.school.grading._nav')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Coefficients matières</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouveau coefficient</button>
            </div>
        </div>
        <div class="sch-filters">
            <select class="input" wire:model.live="filterYearId" style="max-width:200px;">
                <option value="">Toutes années</option>
                @foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach
            </select>
            <select class="input" wire:model.live="filterClassId" style="max-width:180px;">
                <option value="">Toutes classes</option>
                @foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Année</th><th>Classe</th><th>Matière</th><th>Coefficient</th><th class="right">Actions</th></tr></thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->academicYear?->name }}</td>
                            <td>{{ $row->schoolClass?->name }}</td>
                            <td><strong>{{ $row->subject?->name }}</strong></td>
                            <td>{{ rtrim(rtrim(number_format((float)$row->coefficient,2,',',' '),'0'),',') }}</td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.grading.coefficients.show', ['tenant'=>$tenantCode,'id'=>$row->id]) }}">Voir</a>
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.grading.coefficients.manage', ['tenant'=>$tenantCode,'id'=>$row->id]) }}">Gérer</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Aucun coefficient. Sans coefficient, le moteur utilise 1.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin:12px 16px 16px;">{{ $rows->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal">
                <div class="sch-modal__head"><h3 class="sch-modal__title">Nouveau coefficient</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
                <div class="sch-modal__body"><div class="form-grid">
                    <div><label class="label">Année</label><select class="input" wire:model="academicYearId"><option value="">—</option>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach</select>@error('academicYearId') <span class="text-error">{{ $message }}</span> @enderror</div>
                    <div><label class="label">Classe</label><select class="input" wire:model="classId"><option value="">—</option>@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>@error('classId') <span class="text-error">{{ $message }}</span> @enderror</div>
                    <div><label class="label">Matière</label><select class="input" wire:model="subjectId"><option value="">—</option>@foreach($subjects as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>@error('subjectId') <span class="text-error">{{ $message }}</span> @enderror</div>
                    <div><label class="label">Coefficient</label><input class="input" type="number" step="0.01" wire:model="coefficient">@error('coefficient') <span class="text-error">{{ $message }}</span> @enderror</div>
                </div></div>
                <div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
            </div>
        </div>
    @endif
</div>
