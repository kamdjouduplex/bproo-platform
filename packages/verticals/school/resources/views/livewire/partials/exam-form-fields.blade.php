{{-- Shared exam create/edit fields. Parent must pass $examKinds, $examPeriods, $years, $classes, $subjects, $teachers. --}}
<div class="form-grid">
    <div>
        <label class="label">Type d’évaluation</label>
        <select class="input" wire:model.live="kind">
            <option value="">—</option>
            @foreach($examKinds as $kindOpt)
                <option value="{{ $kindOpt['value'] }}">{{ $kindOpt['label'] }}</option>
            @endforeach
        </select>
        @error('kind') <span class="text-error">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="label">Période</label>
        <select class="input" wire:model.live="period">
            <option value="">—</option>
            @foreach($examPeriods as $periodOpt)
                <option value="{{ $periodOpt['value'] }}">{{ $periodOpt['label'] }}</option>
            @endforeach
        </select>
        @error('period') <span class="text-error">{{ $message }}</span> @enderror
    </div>
    <div class="form-span-2">
        <label class="label">Titre</label>
        <input class="input" type="text" wire:model="title" placeholder="Ex. Devoir — Séquence 1">
        <p class="sch-modal__hint">Le titre peut rester le libellé automatique (type + période) ou être précisé (Devoir 2, Interro surprise…).</p>
        @error('title') <span class="text-error">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="label">Année académique</label>
        <select class="input" wire:model="academicYearId">
            <option value="">—</option>
            @foreach($years as $y)
                <option value="{{ $y->id }}">{{ $y->name }}</option>
            @endforeach
        </select>
        @error('academicYearId') <span class="text-error">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="label">Classe</label>
        <select class="input" wire:model="classId">
            <option value="">—</option>
            @foreach($classes as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
        @error('classId') <span class="text-error">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="label">Matière</label>
        <select class="input" wire:model="subjectId">
            <option value="">—</option>
            @foreach($subjects as $s)
                <option value="{{ $s->id }}">{{ $s->name }}</option>
            @endforeach
        </select>
        @error('subjectId') <span class="text-error">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="label">Enseignant</label>
        <select class="input" wire:model="teacherId">
            <option value="">—</option>
            @foreach($teachers as $t)
                <option value="{{ $t->id }}">{{ $t->full_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Date</label>
        <input class="input" type="date" wire:model="examDate">
    </div>
    <div>
        <label class="label">Note max</label>
        <input class="input" type="number" step="0.01" wire:model="maxScore">
        @error('maxScore') <span class="text-error">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="label">Coefficient</label>
        <input class="input" type="number" step="0.01" wire:model="coefficient">
        @error('coefficient') <span class="text-error">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="label">Statut</label>
        <select class="input" wire:model="status">
            <option value="draft">Brouillon</option>
            <option value="open">Ouvert</option>
            <option value="closed">Clôturé</option>
        </select>
    </div>
    <div class="form-span-2">
        <label class="label">Notes / consignes</label>
        <textarea class="input" rows="2" wire:model="notes"></textarea>
    </div>
</div>
