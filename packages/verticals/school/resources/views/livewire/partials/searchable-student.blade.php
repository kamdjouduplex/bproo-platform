{{-- Searchable student picker. Parent component must use SearchesStudents trait. --}}
<div>
    <label class="label">{{ $label ?? 'Étudiant' }}</label>

    @if ($studentId && $selectedStudentLabel !== '')
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px; padding:8px 10px; border:1px solid #dbe7f5; border-radius:8px; background:#f8fbff;">
            <span style="flex:1; font-size:14px;"><strong>{{ $selectedStudentLabel }}</strong></span>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="clearStudent">Changer</button>
        </div>
    @else
        <input
            class="input"
            type="search"
            wire:model.live.debounce.200ms="studentSearch"
            placeholder="{{ $placeholder ?? 'Rechercher ID, prénom, nom…' }}"
            autocomplete="off"
        >
        @php $results = $studentResults ?? collect(); @endphp
        @if (trim($studentSearch) !== '' && $results->isNotEmpty())
            <div style="margin-top:8px; max-height:240px; overflow-y:auto; border:1px solid #dbe7f5; border-radius:8px; background:#fff; box-shadow:0 4px 14px rgba(15,39,68,.08);">
                @foreach ($results as $s)
                    <div
                        style="padding:10px 12px; border-bottom:1px solid #eef2f7; cursor:pointer;"
                        wire:click="selectStudent({{ $s->id }})"
                        wire:key="student-opt-{{ $s->id }}"
                        onmouseover="this.style.background='#f0f7ff'"
                        onmouseout="this.style.background='#fff'"
                    >
                        <strong>{{ $s->student_code }}</strong>
                        — {{ $s->first_name }} {{ $s->last_name }}
                    </div>
                @endforeach
            </div>
        @elseif (trim($studentSearch) !== '')
            <div style="margin-top:8px; font-size:13px; color:#64748b;">Aucun résultat.</div>
        @endif
    @endif

    @error('studentId') <span class="text-error">{{ $message }}</span> @enderror
</div>
